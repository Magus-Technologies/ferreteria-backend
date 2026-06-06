<?php

namespace App\Http\Controllers;

use App\Models\AutorizacionConfig;
use App\Models\AutorizacionOtorgada;
use App\Models\SolicitudAutorizacion;
use App\Models\User;
use App\Services\AutorizacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutorizacionController extends Controller
{
    public function __construct(
        private AutorizacionService $service,
    ) {}

    // ===== CONFIGURACIÓN =====

    /**
     * Listar todas las configs de autorización.
     */
    public function configIndex(Request $request): JsonResponse
    {
        $query = AutorizacionConfig::with(['role', 'autorizador']);

        if ($request->has('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        return response()->json($query->get());
    }

    /**
     * Configs de un rol específico.
     */
    public function configPorRol(int $roleId): JsonResponse
    {
        $configs = AutorizacionConfig::with(['autorizador'])
            ->where('role_id', $roleId)
            ->get();

        return response()->json($configs);
    }

    /**
     * Crear o actualizar config de autorización.
     */
    public function configStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role_id' => 'required|integer|exists:role,id',
            'modulo' => 'required|string|max:100',
            'accion' => 'required|in:crear,editar,eliminar,acceso',
            'requiere_autorizacion' => 'required|boolean',
            'tipo_autorizador' => 'nullable|in:usuario,cargo,jerarquia',
            'autorizador_id' => 'nullable|string|exists:user,id',
            'cargo_autorizador' => 'nullable|string|max:100',
        ]);

        $tipo = $validated['tipo_autorizador'] ?? 'usuario';

        // Normalizar: solo se guarda el dato relevante al modo elegido.
        $autorizadorId = $tipo === 'usuario' ? ($validated['autorizador_id'] ?? null) : null;
        $cargoAutorizador = $tipo === 'cargo' ? ($validated['cargo_autorizador'] ?? null) : null;

        $config = AutorizacionConfig::updateOrCreate(
            [
                'role_id' => $validated['role_id'],
                'modulo' => $validated['modulo'],
                'accion' => $validated['accion'],
            ],
            [
                'requiere_autorizacion' => $validated['requiere_autorizacion'],
                'tipo_autorizador' => $tipo,
                'autorizador_id' => $autorizadorId,
                'cargo_autorizador' => $cargoAutorizador,
                'created_by' => $request->user()->id,
            ]
        );

        return response()->json($config->load(['role', 'autorizador']), 201);
    }

    /**
     * Eliminar config.
     */
    public function configDestroy(int $id): JsonResponse
    {
        AutorizacionConfig::findOrFail($id)->delete();
        return response()->json(['message' => 'Configuración eliminada']);
    }

    // ===== VERIFICACIÓN =====

    /**
     * Verificar si el usuario actual necesita autorización.
     */
    public function verificar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'modulo' => 'required|string|max:100',
            'accion' => 'required|in:crear,editar,eliminar,acceso',
        ]);

        $resultado = $this->service->verificar(
            $request->user()->id,
            $validated['modulo'],
            $validated['accion'],
        );

        return response()->json($resultado);
    }

    // ===== SOLICITUDES =====

    /**
     * Crear solicitud de autorización.
     */
    public function solicitar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'modulo' => 'required|string|max:100',
            'accion' => 'required|in:crear,editar,eliminar,acceso',
            'descripcion' => 'required|string|max:500',
            'metadata' => 'nullable|array',
        ]);

        try {
            $solicitud = $this->service->crearSolicitud(
                $request->user()->id,
                $validated['modulo'],
                $validated['accion'],
                $validated['descripcion'],
                $validated['metadata'] ?? null,
            );

            return response()->json($solicitud, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Mis solicitudes (como solicitante).
     */
    public function misSolicitudes(Request $request): JsonResponse
    {
        $solicitudes = SolicitudAutorizacion::with(['autorizador', 'respondidoPor', 'role'])
            ->where('solicitante_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json($solicitudes);
    }

    /**
     * Solicitudes pendientes (como aprobador).
     */
    public function pendientes(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $solicitudes = SolicitudAutorizacion::with(['solicitante', 'role'])
            ->pendientes()
            ->delAutorizador($userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($solicitudes);
    }

    /**
     * Conteo de pendientes (para badge).
     */
    public function pendientesCount(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $count = SolicitudAutorizacion::pendientes()
            ->delAutorizador($userId)
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Aprobar solicitud.
     */
    public function aprobar(string $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo_aprobacion' => 'required|in:temporal,permanente,una_vez',
            'duracion_horas' => 'nullable|integer|min:1',
            'comentario' => 'nullable|string|max:500',
        ]);

        if ($validated['tipo_aprobacion'] === 'temporal' && empty($validated['duracion_horas'])) {
            return response()->json(['message' => 'Debe especificar duración para autorización temporal'], 422);
        }

        try {
            $solicitud = $this->service->aprobar(
                $id,
                $request->user()->id,
                $validated['tipo_aprobacion'],
                $validated['duracion_horas'] ?? null,
                $validated['comentario'] ?? null,
            );

            return response()->json($solicitud);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Rechazar solicitud.
     */
    public function rechazar(string $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'comentario' => 'nullable|string|max:500',
        ]);

        try {
            $solicitud = $this->service->rechazar(
                $id,
                $request->user()->id,
                $validated['comentario'] ?? null,
            );

            return response()->json($solicitud);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ===== AUTORIZACIONES OTORGADAS =====

    /**
     * Ver autorizaciones activas de un usuario.
     */
    public function otorgadas(string $userId): JsonResponse
    {
        $autorizaciones = AutorizacionOtorgada::with(['otorgadaPor', 'role'])
            ->where('user_id', $userId)
            ->activas()
            ->get();

        return response()->json($autorizaciones);
    }

    /**
     * Revocar una autorización.
     */
    public function revocar(string $id): JsonResponse
    {
        try {
            $this->service->revocar($id);
            return response()->json(['message' => 'Autorización revocada']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Consumir una autorización de USO ÚNICO tras usarla.
     * Solo afecta a las de tipo 'una_vez'; para temporal/permanente es no-op.
     */
    public function consumir(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'modulo' => 'required|string|max:100',
            'accion' => 'required|in:crear,editar,eliminar,acceso',
        ]);

        $consumido = $this->service->consumirUnaVez(
            $request->user()->id,
            $validated['modulo'],
            $validated['accion'],
        );

        return response()->json(['consumido' => $consumido]);
    }

    /**
     * Supervisores válidos para autorizar (override en sitio) esta acción.
     */
    public function supervisores(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'modulo' => 'required|string|max:100',
            'accion' => 'required|in:crear,editar,eliminar,acceso',
        ]);

        $supervisores = $this->service->supervisoresValidos(
            $request->user()->id,
            $validated['modulo'],
            $validated['accion'],
        );

        return response()->json(['data' => $supervisores]);
    }

    /**
     * Override en sitio: un supervisor autoriza con su clave (concede uso único).
     */
    public function override(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'modulo' => 'required|string|max:100',
            'accion' => 'required|in:crear,editar,eliminar,acceso',
            'supervisor_id' => 'required|string|exists:user,id',
            'supervisor_password' => 'required|string',
        ]);

        try {
            $otorgada = $this->service->autorizarConClaveSupervisor(
                $request->user()->id,
                $validated['modulo'],
                $validated['accion'],
                $validated['supervisor_id'],
                $validated['supervisor_password'],
            );

            return response()->json(['data' => $otorgada, 'message' => 'Autorización concedida']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Listar usuarios disponibles como autorizadores (id + name).
     */
    public function usuarios(): JsonResponse
    {
        $users = User::select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json($users);
    }
}
