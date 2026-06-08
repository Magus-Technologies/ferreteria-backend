<?php

namespace App\Services;

use App\Events\ModelChanged;
use App\Models\AutorizacionConfig;
use App\Models\AutorizacionOtorgada;
use App\Models\SolicitudAutorizacion;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AutorizacionService
{
    protected FirebaseNotificationService $fcm;

    public function __construct(FirebaseNotificationService $fcm)
    {
        $this->fcm = $fcm;
    }

    /**
     * Verificar si un usuario necesita autorización y si ya la tiene.
     */
    public function verificar(string $userId, string $modulo, string $accion): array
    {
        $user = User::with('roles')->findOrFail($userId);
        $roleIds = $user->roles->pluck('id')->toArray();

        if (empty($roleIds)) {
            return ['requiere' => false, 'tiene_autorizacion' => true];
        }

        // Buscar si algún rol del usuario tiene config de autorización para este módulo+acción
        $config = AutorizacionConfig::where('modulo', $modulo)
            ->where('accion', $accion)
            ->where('requiere_autorizacion', true)
            ->whereIn('role_id', $roleIds)
            ->first();

        if (!$config) {
            return ['requiere' => false, 'tiene_autorizacion' => true];
        }

        // Tiene config → verificar si ya tiene autorización otorgada vigente
        $autorizacion = AutorizacionOtorgada::where('user_id', $userId)
            ->where('modulo', $modulo)
            ->where('accion', $accion)
            ->activas()
            ->first();

        return [
            'requiere' => true,
            'tiene_autorizacion' => $autorizacion !== null,
            'config_id' => $config->id,
            'autorizador_id' => $config->autorizador_id,
        ];
    }

    /**
     * Crear una solicitud de autorización.
     */
    public function crearSolicitud(
        string $userId,
        string $modulo,
        string $accion,
        string $descripcion,
        ?array $metadata = null,
    ): SolicitudAutorizacion {
        $user = User::with('roles')->findOrFail($userId);
        $roleIds = $user->roles->pluck('id')->toArray();

        $config = AutorizacionConfig::where('modulo', $modulo)
            ->where('accion', $accion)
            ->where('requiere_autorizacion', true)
            ->whereIn('role_id', $roleIds)
            ->first();

        if (!$config) {
            throw new \Exception('No se requiere autorización para esta acción');
        }

        // Verificar que no tenga una solicitud pendiente para la misma acción
        $existente = SolicitudAutorizacion::where('solicitante_id', $userId)
            ->where('modulo', $modulo)
            ->where('accion', $accion)
            ->where('estado', 'pendiente')
            ->first();

        if ($existente) {
            throw new \Exception('Ya tienes una solicitud pendiente para esta acción');
        }

        // Resolver el destino de la solicitud según el modo configurado.
        [$autorizadorId, $cargoAutorizador, $roleAutorizadorId] = $this->resolverDestino($config, $user, $userId);

        // En jerarquía, si no hay aprobador (sin cargo padre, sin rol vinculado o sin
        // usuarios con ese rol) NO se cae a admins: se bloquea y se avisa.
        if (($config->tipo_autorizador ?? 'usuario') === 'jerarquia'
            && !$autorizadorId && !$cargoAutorizador && !$roleAutorizadorId) {
            throw new \Exception('No hay un aprobador en el organigrama para esta solicitud. Revisa que tu cargo tenga un cargo superior, que ese cargo tenga un rol vinculado y que exista al menos un usuario con ese rol.');
        }

        $solicitud = SolicitudAutorizacion::create([
            'solicitante_id' => $userId,
            'autorizador_id' => $autorizadorId,
            'cargo_autorizador' => $cargoAutorizador,
            'role_autorizador_id' => $roleAutorizadorId,
            'role_id' => $config->role_id,
            'modulo' => $modulo,
            'accion' => $accion,
            'descripcion' => $descripcion,
            'metadata' => $metadata,
            'estado' => 'pendiente',
        ]);

        // Enviar notificación FCM al autorizador
        $this->notificarAutorizador($solicitud, $user);

        return $solicitud->load(['solicitante', 'autorizador', 'role']);
    }

    /**
     * Resolver a quién se dirige la solicitud según el modo de la config.
     *
     * @return array{0: ?string, 1: ?string, 2: ?int} [autorizador_id, cargo_autorizador, role_autorizador_id]
     */
    private function resolverDestino(AutorizacionConfig $config, User $solicitante, string $userId): array
    {
        $tipo = $config->tipo_autorizador ?? 'usuario';

        // Modo cargo: cualquier usuario del cargo elegido.
        if ($tipo === 'cargo') {
            return [null, $config->cargo_autorizador ?: null, null];
        }

        // Modo jerarquía: sube por el organigrama desde el cargo del solicitante y
        // resuelve el aprobador por el ROL vinculado al primer cargo ANCESTRO que
        // tenga rol y usuarios (relación cargo↔rol). Si un cargo intermedio no tiene
        // rol, no se rompe la cadena: escala al siguiente nivel hacia arriba.
        if ($tipo === 'jerarquia') {
            if (!empty($solicitante->cargo)) {
                $cargoActual = \App\Models\CatalogoCargo::where('codigo', $solicitante->cargo)->first();
                $codigoPadre = $cargoActual?->parent;
                $visitados = [];

                while ($codigoPadre && !in_array($codigoPadre, $visitados, true)) {
                    $visitados[] = $codigoPadre; // evita ciclos en el organigrama
                    $cargoPadre = \App\Models\CatalogoCargo::where('codigo', $codigoPadre)->first();
                    if (!$cargoPadre) {
                        break;
                    }

                    $roleId = $cargoPadre->role_id;
                    if ($roleId) {
                        // ¿Existe al menos un usuario con ese rol (distinto del solicitante)?
                        $hayUsuarios = User::whereHas('roles', fn ($q) => $q->where('role.id', $roleId))
                            ->where('id', '!=', $userId)
                            ->where('estado', true)
                            ->exists();

                        if ($hayUsuarios) {
                            return [null, null, (int) $roleId];
                        }
                    }

                    $codigoPadre = $cargoPadre->parent; // sigue subiendo
                }
            }
            // Recorrió toda la cadena hacia arriba sin encontrar un cargo con rol y
            // usuarios: no hay aprobador. crearSolicitud lo bloqueará (no cae a admins).
            return [null, null, null];
        }

        // Modo usuario (por defecto): usuario fijo configurado.
        return [$config->autorizador_id ?: null, null, null];
    }

    /**
     * IDs de usuarios que pueden autorizar una solicitud (según el modo del config).
     * Reutiliza resolverDestino: usuario fijo, cargo, o admins (fallback).
     */
    private function autorizadoresValidos(AutorizacionConfig $config, User $solicitante): \Illuminate\Support\Collection
    {
        [$autorizadorId, $cargoAutorizador, $roleAutorizadorId] = $this->resolverDestino($config, $solicitante, $solicitante->id);

        if ($autorizadorId) {
            return User::where('id', $autorizadorId)->where('estado', true)->pluck('id');
        }

        if ($cargoAutorizador) {
            return User::where('cargo', $cargoAutorizador)
                ->where('id', '!=', $solicitante->id)
                ->where('estado', true)
                ->pluck('id');
        }

        if ($roleAutorizadorId) {
            return User::whereHas('roles', fn ($q) => $q->where('role.id', $roleAutorizadorId))
                ->where('id', '!=', $solicitante->id)
                ->where('estado', true)
                ->pluck('id');
        }

        // Fallback: administradores
        return User::whereRaw('UPPER(rol_sistema) = ?', ['ADMINISTRADOR'])
            ->where('id', '!=', $solicitante->id)
            ->where('estado', true)
            ->pluck('id');
    }

    /**
     * Buscar el config de autorización vigente para el solicitante.
     */
    private function configPara(User $solicitante, string $modulo, string $accion): ?AutorizacionConfig
    {
        $roleIds = $solicitante->roles->pluck('id')->toArray();
        if (empty($roleIds)) {
            return null;
        }

        return AutorizacionConfig::where('modulo', $modulo)
            ->where('accion', $accion)
            ->where('requiere_autorizacion', true)
            ->whereIn('role_id', $roleIds)
            ->first();
    }

    /**
     * Supervisores válidos (con clave de supervisor) que pueden autorizar esta
     * acción para el solicitante. Usado por el override en sitio.
     */
    public function supervisoresValidos(string $requesterId, string $modulo, string $accion): \Illuminate\Support\Collection
    {
        $solicitante = User::with('roles')->findOrFail($requesterId);
        $config = $this->configPara($solicitante, $modulo, $accion);
        if (!$config) {
            return collect();
        }

        $ids = $this->autorizadoresValidos($config, $solicitante);

        return User::whereIn('id', $ids)
            ->where('es_supervisor', true)
            ->whereNotNull('supervisor_password')
            ->where('estado', true)
            ->get(['id', 'name']);
    }

    /**
     * Override en sitio: un supervisor presente autoriza con su clave.
     * Concede una autorización de USO ÚNICO al solicitante.
     */
    public function autorizarConClaveSupervisor(
        string $requesterId,
        string $modulo,
        string $accion,
        string $supervisorId,
        string $password,
    ): AutorizacionOtorgada {
        $solicitante = User::with('roles')->findOrFail($requesterId);
        $config = $this->configPara($solicitante, $modulo, $accion);
        if (!$config) {
            throw new \Exception('No se requiere autorización para esta acción');
        }

        $supervisor = User::find($supervisorId);
        if (!$supervisor || !$supervisor->es_supervisor || !$supervisor->supervisor_password) {
            throw new \Exception('El supervisor seleccionado no tiene clave de supervisor');
        }
        if (!Hash::check($password, $supervisor->supervisor_password)) {
            throw new \Exception('Clave de supervisor incorrecta');
        }

        // El supervisor debe ser un autorizador válido para esta acción.
        if (!$this->autorizadoresValidos($config, $solicitante)->contains($supervisorId)) {
            throw new \Exception('Ese supervisor no puede autorizar esta acción');
        }

        // Si había una solicitud pendiente, marcarla como aprobada (auditoría).
        $solicitud = SolicitudAutorizacion::where('solicitante_id', $requesterId)
            ->where('modulo', $modulo)
            ->where('accion', $accion)
            ->where('estado', 'pendiente')
            ->first();
        if ($solicitud) {
            $solicitud->update([
                'estado' => 'aprobada',
                'tipo_aprobacion' => 'una_vez',
                'respondido_por' => $supervisorId,
                'respondido_at' => now(),
                'comentario_respuesta' => 'Autorizado con clave de supervisor',
            ]);
        }

        // Conceder de uso único.
        $otorgada = AutorizacionOtorgada::updateOrCreate(
            [
                'user_id' => $requesterId,
                'modulo' => $modulo,
                'accion' => $accion,
            ],
            [
                'role_id' => $config->role_id,
                'tipo' => 'una_vez',
                'fecha_expiracion' => null,
                'otorgada_por' => $supervisorId,
                'solicitud_id' => $solicitud?->id,
                'activa' => true,
            ]
        );

        // Tiempo real: refrescar la pantalla del solicitante (auth_granted).
        ModelChanged::dispatch('autorizaciones', 'aprobada', $solicitud?->id, $requesterId);

        return $otorgada;
    }

    /**
     * Aprobar una solicitud.
     */
    public function aprobar(
        string $solicitudId,
        string $aprobadorId,
        string $tipoAprobacion,
        ?int $duracionHoras = null,
        ?string $comentario = null,
    ): SolicitudAutorizacion {
        $solicitud = SolicitudAutorizacion::findOrFail($solicitudId);

        if ($solicitud->estado !== 'pendiente') {
            throw new \Exception('La solicitud ya fue procesada');
        }

        $fechaExpiracion = null;
        if ($tipoAprobacion === 'temporal' && $duracionHoras) {
            $fechaExpiracion = now()->addHours($duracionHoras);
        }

        // Actualizar solicitud
        $solicitud->update([
            'estado' => 'aprobada',
            'tipo_aprobacion' => $tipoAprobacion,
            'duracion_horas' => $duracionHoras,
            'respondido_por' => $aprobadorId,
            'respondido_at' => now(),
            'comentario_respuesta' => $comentario,
        ]);

        // Crear o actualizar autorización otorgada
        AutorizacionOtorgada::updateOrCreate(
            [
                'user_id' => $solicitud->solicitante_id,
                'modulo' => $solicitud->modulo,
                'accion' => $solicitud->accion,
            ],
            [
                'role_id' => $solicitud->role_id,
                'tipo' => $tipoAprobacion,
                'fecha_expiracion' => $fechaExpiracion,
                'otorgada_por' => $aprobadorId,
                'solicitud_id' => $solicitud->id,
                'activa' => true,
            ]
        );

        // Notificar al solicitante
        $this->notificarSolicitante($solicitud, 'aprobada');

        // Tiempo real: avisar al solicitante para que su pantalla refresque el
        // usuario (auth_granted) y el candado del elemento/vista desaparezca solo.
        ModelChanged::dispatch('autorizaciones', 'aprobada', $solicitud->id, $solicitud->solicitante_id);

        return $solicitud->load(['solicitante', 'respondidoPor']);
    }

    /**
     * Rechazar una solicitud.
     */
    public function rechazar(
        string $solicitudId,
        string $aprobadorId,
        ?string $comentario = null,
    ): SolicitudAutorizacion {
        $solicitud = SolicitudAutorizacion::findOrFail($solicitudId);

        if ($solicitud->estado !== 'pendiente') {
            throw new \Exception('La solicitud ya fue procesada');
        }

        $solicitud->update([
            'estado' => 'rechazada',
            'respondido_por' => $aprobadorId,
            'respondido_at' => now(),
            'comentario_respuesta' => $comentario,
        ]);

        // Notificar al solicitante
        $this->notificarSolicitante($solicitud, 'rechazada');

        return $solicitud->load(['solicitante', 'respondidoPor']);
    }

    /**
     * Revocar una autorización otorgada.
     */
    public function revocar(string $autorizacionId): void
    {
        $autorizacion = AutorizacionOtorgada::findOrFail($autorizacionId);
        $autorizacion->update(['activa' => false]);
    }

    /**
     * Consumir una autorización de uso único tras usarla.
     * Solo afecta a las de tipo 'una_vez'; devuelve true si consumió una.
     */
    public function consumirUnaVez(string $userId, string $modulo, string $accion): bool
    {
        $otorgada = AutorizacionOtorgada::where('user_id', $userId)
            ->where('modulo', $modulo)
            ->where('accion', $accion)
            ->where('tipo', 'una_vez')
            ->activas()
            ->first();

        if (!$otorgada) {
            return false;
        }

        $otorgada->update(['activa' => false]);
        return true;
    }

    /**
     * Limpiar autorizaciones temporales expiradas.
     */
    public function limpiarExpiradas(): int
    {
        return AutorizacionOtorgada::where('tipo', 'temporal')
            ->where('activa', true)
            ->where('fecha_expiracion', '<', now())
            ->update(['activa' => false]);
    }

    /**
     * Enviar notificación al autorizador.
     */
    private function notificarAutorizador(SolicitudAutorizacion $solicitud, User $solicitante): void
    {
        try {
            $accionLabel = ['crear' => 'crear', 'editar' => 'editar', 'eliminar' => 'eliminar'];
            $titulo = 'Nueva solicitud de autorización';
            $body = "{$solicitante->name} solicita permiso para {$accionLabel[$solicitud->accion]} en {$solicitud->modulo}";
            $data = [
                'type' => 'autorizacion',
                'solicitud_id' => $solicitud->id,
                'modulo' => $solicitud->modulo,
                'accion' => $solicitud->accion,
            ];

            if ($solicitud->autorizador_id) {
                // Enviar al autorizador específico
                $autorizador = User::find($solicitud->autorizador_id);
                if ($autorizador?->fcm_token) {
                    $this->fcm->sendNotification($autorizador->fcm_token, $titulo, $body, $data);
                }
            } elseif ($solicitud->cargo_autorizador) {
                // Enviar a todos los usuarios que ocupan el cargo destino (cualquiera puede autorizar)
                $usuarios = User::where('cargo', $solicitud->cargo_autorizador)
                    ->where('id', '!=', $solicitud->solicitante_id)
                    ->where('estado', true)
                    ->whereNotNull('fcm_token')
                    ->get();

                foreach ($usuarios as $u) {
                    $this->fcm->sendNotification($u->fcm_token, $titulo, $body, $data);
                }
            } elseif ($solicitud->role_autorizador_id) {
                // Enviar a todos los usuarios con el rol destino (jerarquía por rol del cargo padre)
                $usuarios = User::whereHas('roles', fn ($q) => $q->where('role.id', $solicitud->role_autorizador_id))
                    ->where('id', '!=', $solicitud->solicitante_id)
                    ->where('estado', true)
                    ->whereNotNull('fcm_token')
                    ->get();

                foreach ($usuarios as $u) {
                    $this->fcm->sendNotification($u->fcm_token, $titulo, $body, $data);
                }
            } else {
                // Sin destino específico => enviar a todos los admins
                $this->fcm->sendToRole('ADMINISTRADOR', $titulo, $body, $data);
            }
        } catch (\Exception $e) {
            Log::warning('Error enviando notificación de autorización: ' . $e->getMessage());
        }
    }

    /**
     * Notificar al solicitante sobre la resolución.
     */
    private function notificarSolicitante(SolicitudAutorizacion $solicitud, string $resultado): void
    {
        try {
            $solicitante = User::find($solicitud->solicitante_id);
            if (!$solicitante?->fcm_token) {
                return;
            }

            $titulo = $resultado === 'aprobada'
                ? 'Solicitud aprobada'
                : 'Solicitud rechazada';

            $body = "Tu solicitud para {$solicitud->accion} en {$solicitud->modulo} fue {$resultado}";

            if ($resultado === 'aprobada' && $solicitud->tipo_aprobacion === 'temporal') {
                $body .= " (por {$solicitud->duracion_horas}h)";
            }

            $data = [
                'type' => 'autorizacion_respuesta',
                'solicitud_id' => $solicitud->id,
                'resultado' => $resultado,
                'modulo' => $solicitud->modulo,
                'accion' => $solicitud->accion,
            ];

            $this->fcm->sendNotification($solicitante->fcm_token, $titulo, $body, $data);
        } catch (\Exception $e) {
            Log::warning('Error notificando solicitante: ' . $e->getMessage());
        }
    }
}
