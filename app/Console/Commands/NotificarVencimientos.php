<?php

namespace App\Console\Commands;

use App\Models\ConfiguracionNotificacion;
use App\Models\Cotizacion;
use App\Models\Prestamo;
use App\Models\RequerimientoInterno;
use App\Models\User;
use App\Models\ValeCompra;
use App\Services\FirebaseNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class NotificarVencimientos extends Command
{
    protected $signature = 'notificaciones:vencimientos';
    protected $description = 'Notifica a los usuarios sobre cotizaciones y préstamos próximos a vencer';

    protected FirebaseNotificationService $firebaseService;

    public function __construct(FirebaseNotificationService $firebaseService)
    {
        parent::__construct();
        $this->firebaseService = $firebaseService;
    }

    public function handle()
    {
        $this->info('Iniciando verificación de vencimientos para notificaciones...');

        // Agrupar configuraciones por tipo
        $configuraciones = ConfiguracionNotificacion::where('habilitado', true)
            ->whereIn('tipo', ['cotizacion_vence', 'prestamo_vence', 'promocion_termina', 'requerimiento_vence'])
            ->get();

        foreach ($configuraciones as $config) {
            if (!$config->user_id) continue;

            $user = User::find($config->user_id);
            if (!$user || !$user->fcm_token) continue;

            $diasAnticipacion = $config->dias_anticipacion ?? 1;
            $fechaObjetivo = Carbon::today()->addDays($diasAnticipacion)->toDateString();

            if ($config->tipo === 'cotizacion_vence') {
                $this->procesarCotizaciones($user, $fechaObjetivo, $diasAnticipacion);
            } elseif ($config->tipo === 'prestamo_vence') {
                $this->procesarPrestamos($user, $fechaObjetivo, $diasAnticipacion);
            } elseif ($config->tipo === 'promocion_termina') {
                $this->procesarPromociones($user, $fechaObjetivo, $diasAnticipacion);
            } elseif ($config->tipo === 'requerimiento_vence') {
                $this->procesarRequerimientos($user, $fechaObjetivo, $diasAnticipacion);
            }
        }

        $this->info('Verificación de vencimientos finalizada.');
    }

    private function procesarCotizaciones(User $user, string $fechaObjetivo, int $diasAnticipacion)
    {
        // Cotizaciones pendientes asignadas al usuario y con la fecha de vencimiento objetivo
        $cotizaciones = Cotizacion::whereDate('fecha_vencimiento', $fechaObjetivo)
            ->where('estado_cotizacion', 'pe') // Pendiente
            ->where('user_id', $user->id)
            ->get();

        foreach ($cotizaciones as $cotizacion) {
            $clienteNombre = $cotizacion->cliente ? $cotizacion->cliente->name : 'Cliente';
            $title = '⚠️ Cotización próxima a vencer';
            $body = "La cotización {$cotizacion->numero} de {$clienteNombre} vencerá en {$diasAnticipacion} día(s).";
            
            $data = [
                'type' => 'cotizacion_vence',
                'cotizacion_id' => $cotizacion->id,
            ];

            $this->firebaseService->sendNotification($user->fcm_token, $title, $body, $data);
            Log::channel('firebase')->info("Notificación enviada: Cotización {$cotizacion->numero} vence pronto a usuario {$user->id}");
        }
    }

    private function procesarPrestamos(User $user, string $fechaObjetivo, int $diasAnticipacion)
    {
        // Préstamos que vencen, activos y asignados al usuario (o si es el administrador de préstamos)
        $prestamos = Prestamo::whereDate('fecha_vencimiento', $fechaObjetivo)
            ->whereIn('estado_prestamo', ['pendiente', 'pagado_parcial'])
            ->where('user_id', $user->id) // Asumiendo que se notifica al vendedor/usuario que lo creó
            ->get();

        foreach ($prestamos as $prestamo) {
            $clienteNombre = $prestamo->cliente ? $prestamo->cliente->name : 'Cliente/Proveedor';
            $title = '⚠️ Préstamo próximo a vencer';
            $body = "El préstamo {$prestamo->numero} de {$clienteNombre} vencerá en {$diasAnticipacion} día(s). Saldo: {$prestamo->monto_pendiente}";
            
            $data = [
                'type' => 'prestamo_vence',
                'prestamo_id' => $prestamo->id,
            ];

            $this->firebaseService->sendNotification($user->fcm_token, $title, $body, $data);
            Log::channel('firebase')->info("Notificación enviada: Préstamo {$prestamo->numero} vence pronto a usuario {$user->id}");
        }
    }

    private function procesarPromociones(User $user, string $fechaObjetivo, int $diasAnticipacion)
    {
        $vales = ValeCompra::whereDate('fecha_fin', $fechaObjetivo)
            ->where('estado', 'ACTIVO')
            ->get();

        foreach ($vales as $vale) {
            $title = '⚠️ Promoción por terminar';
            $body = "El vale/promoción '{$vale->nombre}' terminará en {$diasAnticipacion} día(s).";
            
            $data = [
                'type' => 'promocion_termina',
                'vale_id' => $vale->id,
            ];

            $this->firebaseService->sendNotification($user->fcm_token, $title, $body, $data);
            Log::channel('firebase')->info("Notificación enviada: Promoción {$vale->nombre} termina pronto a usuario {$user->id}");
        }
    }

    private function procesarRequerimientos(User $user, string $fechaObjetivo, int $diasAnticipacion)
    {
        // Notificar al usuario que creó el requerimiento o al asignado
        $requerimientos = RequerimientoInterno::whereDate('fecha_requerida', $fechaObjetivo)
            ->whereNotIn('estado', ['ATENDIDO', 'ANULADO', 'RECHAZADO'])
            ->where(function($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('approved_by', $user->id);
            })
            ->get();

        foreach ($requerimientos as $req) {
            $title = '⚠️ Requerimiento próximo a vencer';
            $body = "El requerimiento {$req->codigo} ({$req->titulo}) tiene fecha límite en {$diasAnticipacion} día(s).";
            
            $data = [
                'type' => 'requerimiento_vence',
                'requerimiento_id' => $req->id,
            ];

            $this->firebaseService->sendNotification($user->fcm_token, $title, $body, $data);
            Log::channel('firebase')->info("Notificación enviada: Requerimiento {$req->codigo} vence pronto a usuario {$user->id}");
        }
    }
}
