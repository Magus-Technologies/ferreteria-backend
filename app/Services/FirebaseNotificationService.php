<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class FirebaseNotificationService
{
    private string $projectId;
    private string $serviceAccountPath;
    private string $fcmUrl;

    public function __construct()
    {
        $this->projectId = config('services.firebase.project_id', 'ferreteria-38320');
        $this->serviceAccountPath = config('services.firebase.credentials_path', '');
        $this->fcmUrl = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
    }

    /**
     * Obtiene un token de acceso OAuth2 usando el Service Account
     */
    private function getAccessToken(): ?string
    {
        // Cache del token por 55 minutos (expira en 60)
        return Cache::remember('firebase_access_token', 55 * 60, function () {
            try {
                if (!file_exists($this->serviceAccountPath)) {
                    Log::error('Firebase: Archivo de credenciales no encontrado', [
                        'path' => $this->serviceAccountPath
                    ]);
                    return null;
                }

                $credentials = json_decode(file_get_contents($this->serviceAccountPath), true);
                
                if (!$credentials) {
                    Log::error('Firebase: Error al parsear credenciales');
                    return null;
                }

                // Crear JWT para obtener access token
                $now = time();
                $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
                $payload = base64_encode(json_encode([
                    'iss' => $credentials['client_email'],
                    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                    'aud' => 'https://oauth2.googleapis.com/token',
                    'iat' => $now,
                    'exp' => $now + 3600,
                ]));

                // Firmar con la clave privada
                $privateKey = openssl_pkey_get_private($credentials['private_key']);
                if (!$privateKey) {
                    Log::error('Firebase: Error al cargar clave privada');
                    return null;
                }

                $signature = '';
                openssl_sign("$header.$payload", $signature, $privateKey, OPENSSL_ALGO_SHA256);
                $jwt = "$header.$payload." . $this->base64UrlEncode($signature);

                // Intercambiar JWT por access token
                $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    Log::info('Firebase: Access token obtenido exitosamente');
                    return $data['access_token'] ?? null;
                }

                Log::error('Firebase: Error al obtener access token', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;

            } catch (\Exception $e) {
                Log::error('Firebase: Excepción al obtener token', [
                    'message' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    /**
     * Base64 URL encode
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Envía una notificación push a un dispositivo específico
     */
    public function sendNotification(
        string $fcmToken,
        string $title,
        string $body,
        array $data = []
    ): bool {
        $accessToken = $this->getAccessToken();
        
        if (!$accessToken) {
            Log::warning('Firebase: No se pudo obtener access token');
            return false;
        }

        try {
            $payload = [
                'message' => [
                    'token' => $fcmToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'webpush' => [
                        'notification' => [
                            'icon' => '/icon-192x192.png',
                            'badge' => '/icon-72x72.png',
                            'vibrate' => [200, 100, 200],
                        ],
                        'fcm_options' => [
                            'link' => config('app.url') . '/ui/facturacion-electronica/mis-entregas',
                        ],
                    ],
                    'data' => array_map('strval', $data), // FCM requiere valores string
                ],
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($this->fcmUrl, $payload);

            if ($response->successful()) {
                Log::info('Firebase: Notificación enviada exitosamente', [
                    'token' => substr($fcmToken, 0, 20) . '...',
                    'title' => $title,
                ]);
                return true;
            }

            $error = $response->json();
            Log::error('Firebase: Error al enviar notificación', [
                'status' => $response->status(),
                'error' => $error,
            ]);

            // Si el token es inválido, limpiar cache y reintentar
            if ($response->status() === 401) {
                Cache::forget('firebase_access_token');
            }

            // Token de dispositivo inválido
            if (isset($error['error']['details'])) {
                foreach ($error['error']['details'] as $detail) {
                    if (str_contains($detail['@type'] ?? '', 'UNREGISTERED') ||
                        str_contains($detail['errorCode'] ?? '', 'UNREGISTERED')) {
                        // TODO: Limpiar token inválido de la DB
                        Log::warning('Firebase: Token de dispositivo inválido', [
                            'token' => substr($fcmToken, 0, 20) . '...',
                        ]);
                    }
                }
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Firebase: Excepción al enviar notificación', [
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Envía notificación a múltiples dispositivos
     */
    public function sendToMultiple(
        array $fcmTokens,
        string $title,
        string $body,
        array $data = []
    ): array {
        $results = [];
        
        foreach ($fcmTokens as $token) {
            $results[$token] = $this->sendNotification($token, $title, $body, $data);
        }

        return $results;
    }

    /**
     * Envía notificación a todos los usuarios con un cargo específico
     */
    public function sendToCargo(
        string $cargo,
        string $title,
        string $body,
        array $data = [],
        ?string $excludeUserId = null
    ): int {
        $users = \App\Models\User::where('cargo', $cargo)
            ->whereNotNull('fcm_token')
            ->where('estado', true)
            ->when($excludeUserId, fn($q) => $q->where('id', '!=', $excludeUserId))
            ->get();

        $successCount = 0;

        foreach ($users as $user) {
            if ($this->sendNotification($user->fcm_token, $title, $body, $data)) {
                $successCount++;
            }
        }

        return $successCount;
    }

    /**
     * Envía notificación a todos los usuarios con un rol específico
     */
    public function sendToRole(
        string $rolSistema,
        string $title,
        string $body,
        array $data = []
    ): int {
        $users = \App\Models\User::where('rol_sistema', $rolSistema)
            ->whereNotNull('fcm_token')
            ->where('estado', true)
            ->get();

        $successCount = 0;

        foreach ($users as $user) {
            if ($this->sendNotification($user->fcm_token, $title, $body, $data)) {
                $successCount++;
            }
        }

        return $successCount;
    }
}
