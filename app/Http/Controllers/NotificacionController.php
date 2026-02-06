<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FirebaseNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificacionController extends Controller
{
    protected FirebaseNotificationService $firebaseService;

    public function __construct(FirebaseNotificationService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Actualiza el token FCM del usuario autenticado
     */
    public function updateFcmToken(Request $request)
    {
        $validated = $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'Usuario no autenticado'], 401);
        }

        User::where('id', $user->id)->update([
            'fcm_token' => $validated['fcm_token'],
        ]);

        return response()->json([
            'message' => 'Token FCM actualizado exitosamente',
        ]);
    }

    /**
     * Envía una notificación push a un usuario específico
     */
    public function sendNotification(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|string',
            'title' => 'required|string|max:100',
            'body' => 'required|string|max:500',
            'data' => 'nullable|array',
        ]);

        $user = User::find($validated['user_id']);

        if (!$user || !$user->fcm_token) {
            return response()->json([
                'message' => 'Usuario no tiene token FCM registrado',
            ], 404);
        }

        $result = $this->firebaseService->sendNotification(
            $user->fcm_token,
            $validated['title'],
            $validated['body'],
            $validated['data'] ?? []
        );

        if ($result) {
            return response()->json(['message' => 'Notificación enviada exitosamente']);
        }

        return response()->json(['message' => 'Error al enviar notificación'], 500);
    }

    /**
     * Envía notificación de entrega programada al despachador
     */
    public function notifyEntregaProgramada(Request $request)
    {
        $validated = $request->validate([
            'despachador_id' => 'required|string',
            'venta_serie' => 'required|string',
            'venta_numero' => 'required', // Puede ser string o número
            'direccion' => 'required|string',
            'fecha_programada' => 'required|string',
            'cliente_nombre' => 'nullable|string',
        ]);
        
        // Convertir venta_numero a string si es necesario
        $validated['venta_numero'] = (string) $validated['venta_numero'];

        $despachador = User::find($validated['despachador_id']);

        if (!$despachador) {
            return response()->json([
                'message' => 'Despachador no encontrado',
            ], 404);
        }

        if (!$despachador->fcm_token) {
            return response()->json([
                'message' => 'El despachador no tiene notificaciones habilitadas',
                'warning' => true,
            ], 200);
        }

        $clienteNombre = $validated['cliente_nombre'] ?? 'Cliente';
        
        $title = '🚚 Nueva Entrega Programada';
        $body = "Venta {$validated['venta_serie']}-{$validated['venta_numero']}\n"
              . "📍 {$validated['direccion']}\n"
              . "📅 {$validated['fecha_programada']}\n"
              . "👤 {$clienteNombre}";

        $data = [
            'type' => 'entrega',
            'venta_serie' => $validated['venta_serie'],
            'venta_numero' => $validated['venta_numero'],
            'direccion' => $validated['direccion'],
            'fecha_programada' => $validated['fecha_programada'],
        ];

        $result = $this->firebaseService->sendNotification(
            $despachador->fcm_token,
            $title,
            $body,
            $data
        );

        if ($result) {
            return response()->json([
                'message' => 'Notificación enviada al despachador exitosamente',
            ]);
        }

        return response()->json([
            'message' => 'Error al enviar notificación',
        ], 500);
    }
    
    /**
     * ENDPOINT DE PRUEBA: Envía notificación de prueba a un despachador
     * POST /api/notificaciones/test
     * Body: { "despachador_id": "xxx" }
     */
    public function testNotification(Request $request)
    {
        $validated = $request->validate([
            'despachador_id' => 'required|string',
        ]);

        $despachador = User::find($validated['despachador_id']);

        if (!$despachador) {
            return response()->json([
                'success' => false,
                'message' => 'Despachador no encontrado',
                'despachador_id' => $validated['despachador_id'],
            ], 404);
        }

        // Información detallada del despachador
        $info = [
            'despachador_id' => $despachador->id,
            'despachador_nombre' => $despachador->name,
            'despachador_rol' => $despachador->rol_sistema,
            'fcm_token_existe' => !empty($despachador->fcm_token),
            'fcm_token_preview' => $despachador->fcm_token ? substr($despachador->fcm_token, 0, 30) . '...' : null,
        ];

        if (!$despachador->fcm_token) {
            return response()->json([
                'success' => false,
                'message' => 'El despachador no tiene notificaciones habilitadas (fcm_token es NULL)',
                'info' => $info,
                'solucion' => 'El usuario debe iniciar sesión con HTTPS y aceptar los permisos de notificaciones',
            ], 200);
        }

        $title = '🧪 Notificación de Prueba';
        $body = "Hola {$despachador->name}! Esta es una notificación de prueba.\n"
              . "📱 Si ves esto, las notificaciones funcionan correctamente.\n"
              . "🎉 ¡Todo está configurado!";

        $data = [
            'type' => 'test',
            'timestamp' => now()->toDateTimeString(),
        ];

        $result = $this->firebaseService->sendNotification(
            $despachador->fcm_token,
            $title,
            $body,
            $data
        );

        if ($result) {
            return response()->json([
                'success' => true,
                'message' => 'Notificación de prueba enviada exitosamente',
                'info' => $info,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Error al enviar notificación de prueba',
            'info' => $info,
        ], 500);
    }
}
