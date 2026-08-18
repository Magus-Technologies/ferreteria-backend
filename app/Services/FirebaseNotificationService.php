<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class FirebaseNotificationService
{
    private string $projectId;
    private string $serviceAccountPath;
    private string $fcmUrl;

    private const MAX_TOKENS_PER_REQUEST = 500;

    public function __construct()
    {
        $this->projectId = config('services.firebase.project_id', 'ferreteria-38320');
        $this->serviceAccountPath = config('services.firebase.credentials_path', '');
        $this->fcmUrl = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
    }

    private function getAccessToken(): ?string
    {
        return Cache::remember('firebase_access_token', 55 * 60, function () {
            try {
                if (!file_exists($this->serviceAccountPath)) {
                    Log::channel('firebase')->error('Firebase: Archivo de credenciales no encontrado', [
                        'path' => $this->serviceAccountPath
                    ]);
                    return null;
                }

                $credentials = json_decode(file_get_contents($this->serviceAccountPath), true);

                if (!$credentials) {
                    Log::channel('firebase')->error('Firebase: Error al parsear credenciales');
                    return null;
                }

                $now = time();
                $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
                $payload = base64_encode(json_encode([
                    'iss' => $credentials['client_email'],
                    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                    'aud' => 'https://oauth2.googleapis.com/token',
                    'iat' => $now,
                    'exp' => $now + 3600,
                ]));

                $privateKey = openssl_pkey_get_private($credentials['private_key']);
                if (!$privateKey) {
                    Log::channel('firebase')->error('Firebase: Error al cargar clave privada');
                    return null;
                }

                $signature = '';
                openssl_sign("$header.$payload", $signature, $privateKey, OPENSSL_ALGO_SHA256);
                $jwt = "$header.$payload." . $this->base64UrlEncode($signature);

                $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    // Log::channel('firebase')->info('Firebase: Access token obtenido exitosamente');
                    return $data['access_token'] ?? null;
                }

                Log::channel('firebase')->error('Firebase: Error al obtener access token', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;

            } catch (\Exception $e) {
                Log::channel('firebase')->error('Firebase: Excepción al obtener token', [
                    'message' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

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
            Log::channel('firebase')->warning('Firebase: No se pudo obtener access token');
            return false;
        }

        try {
            $payload = $this->buildPayload($fcmToken, $title, $body, $data);

            $response = Http::withToken($accessToken)
                ->timeout(15)
                ->post($this->fcmUrl, $payload);

            if ($response->successful()) {
                // Log::channel('firebase')->info('Firebase: Notificación enviada exitosamente', [
                //     'token' => substr($fcmToken, 0, 20) . '...',
                //     'title' => $title,
                // ]);
                return true;
            }

            $status = $response->status();

            // Token expirado — refrescar y reintentar una vez
            if ($status === 401) {
                Cache::forget('firebase_access_token');
                $newToken = $this->getAccessToken();
                if ($newToken) {
                    $retry = Http::withToken($newToken)
                        ->timeout(15)
                        ->post($this->fcmUrl, $payload);

                    if ($retry->successful()) {
                        // Log::channel('firebase')->info('Firebase: Notificación enviada tras refrescar token');
                        return true;
                    }
                    $response = $retry;
                    $status = $retry->status();
                }
            }

            $error = $response->json('error') ?? [];

            Log::channel('firebase')->error('Firebase: Error al enviar notificación', [
                'status' => $status,
                'error_status' => $error['status'] ?? null,
                'error_message' => $error['message'] ?? null,
            ]);

            // Token de dispositivo inválido — limpiar
            if ($this->isUnregisteredV1($error)) {
                $this->cleanupInvalidToken($fcmToken);
            }

            return false;

        } catch (\Exception $e) {
            Log::channel('firebase')->error('Firebase: Excepción al enviar notificación', [
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Envía notificación a múltiples dispositivos.
     *
     * IMPORTANTE: la API FCM HTTP v1 (/v1/projects/.../messages:send) NO
     * soporta multicast — acepta UN token por request. El campo
     * `registration_tokens` era de la API legacy (apagada por Google en
     * 2024) y v1 lo rechaza con 400. Por eso se envía un request por token,
     * en paralelo con Http::pool (lotes de 50 conexiones concurrentes).
     */
    public function sendToMultiple(
        array $fcmTokens,
        string $title,
        string $body,
        array $data = []
    ): array {
        $tokens = array_values(array_unique(array_filter($fcmTokens)));
        if (empty($tokens)) return [];

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            Log::channel('firebase')->error('Firebase: sin access token, no se envió a múltiples', [
                'token_count' => count($tokens),
            ]);
            return array_fill_keys($tokens, false);
        }

        $results = [];

        foreach (array_chunk($tokens, 50) as $chunk) {
            $responses = Http::pool(fn ($pool) => array_map(
                fn ($token) => $pool->as($token)
                    ->withToken($accessToken)
                    ->timeout(15)
                    ->post($this->fcmUrl, $this->buildPayload($token, $title, $body, $data)),
                $chunk
            ));

            foreach ($chunk as $token) {
                $response = $responses[$token] ?? null;

                // Http::pool devuelve la excepción como valor cuando falla la conexión
                if (!$response instanceof \Illuminate\Http\Client\Response) {
                    $results[$token] = false;
                    Log::channel('firebase')->error('Firebase: error de conexión en envío', [
                        'token' => substr($token, 0, 20) . '...',
                        'error' => $response instanceof \Throwable ? $response->getMessage() : 'desconocido',
                    ]);
                    continue;
                }

                if ($response->successful()) {
                    $results[$token] = true;
                    continue;
                }

                // 401: access token expirado — refrescar y reintentar este token
                if ($response->status() === 401) {
                    Cache::forget('firebase_access_token');
                    $nuevoToken = $this->getAccessToken();
                    if ($nuevoToken) {
                        $accessToken = $nuevoToken;
                        $retry = Http::withToken($accessToken)
                            ->timeout(15)
                            ->post($this->fcmUrl, $this->buildPayload($token, $title, $body, $data));
                        if ($retry->successful()) {
                            $results[$token] = true;
                            continue;
                        }
                        $response = $retry;
                    }
                }

                $results[$token] = false;
                $error = $response->json('error') ?? [];

                Log::channel('firebase')->error('Firebase: error al enviar a token', [
                    'token' => substr($token, 0, 20) . '...',
                    'status' => $response->status(),
                    'error_status' => $error['status'] ?? null,
                    'error_message' => $error['message'] ?? null,
                ]);

                if ($this->isUnregisteredV1($error)) {
                    $this->cleanupInvalidToken($token);
                }
            }
        }

        $exitosos = count(array_filter($results));
        // Log::channel('firebase')->info('Firebase: envío múltiple completado', [
        //     'total' => count($tokens),
        //     'exitosos' => $exitosos,
        //     'fallidos' => count($tokens) - $exitosos,
        // ]);

        return $results;
    }

    /**
     * Detecta token no registrado en formato de error FCM v1:
     * status NOT_FOUND o details[].errorCode UNREGISTERED.
     */
    private function isUnregisteredV1(array $error): bool
    {
        if (($error['status'] ?? '') === 'NOT_FOUND') return true;

        foreach ($error['details'] ?? [] as $detail) {
            if (($detail['errorCode'] ?? '') === 'UNREGISTERED') return true;
        }

        return false;
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
        $tokens = User::where('cargo', $cargo)
            ->whereNotNull('fcm_token')
            ->where('estado', true)
            ->when($excludeUserId, fn($q) => $q->where('id', '!=', $excludeUserId))
            ->pluck('fcm_token')
            ->toArray();

        if (empty($tokens)) return 0;

        $results = $this->sendToMultiple($tokens, $title, $body, $data);
        return count(array_filter($results));
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
        $tokens = User::where('rol_sistema', $rolSistema)
            ->whereNotNull('fcm_token')
            ->where('estado', true)
            ->pluck('fcm_token')
            ->toArray();

        if (empty($tokens)) return 0;

        $results = $this->sendToMultiple($tokens, $title, $body, $data);
        return count(array_filter($results));
    }

    /**
     * Envía notificación a todos los usuarios activos con FCM token
     * que tengan acceso al permiso de módulo especificado.
     *
     * Usa el sistema de blacklist: los usuarios sin la restricción tienen acceso.
     */
    public function sendToUsersWithModuleAccess(
        string $modulePermission,
        string $title,
        string $body,
        array $data = [],
        array $excludeTokens = []
    ): int {
        // Log::channel('firebase')->info('sendToUsersWithModuleAccess iniciado', [
        //     'modulePermission' => $modulePermission,
        //     'title' => $title,
        // ]);

        $users = User::with(['restrictions', 'roles.restrictions'])
            ->whereNotNull('fcm_token')
            ->where('estado', true)
            ->get();

        $totalConToken = $users->count();

        $conAcceso = $users->filter(fn(User $user) => $user->hasAccess($modulePermission));

        $sinAcceso = $totalConToken - $conAcceso->count();

        $tokens = array_diff($conAcceso->pluck('fcm_token')->toArray(), $excludeTokens);

        // Log::channel('firebase')->info('sendToUsersWithModuleAccess: resultado consulta', [
        //     'total_usuarios_con_token' => $totalConToken,
        //     'sin_acceso_modulo' => $sinAcceso,
        //     'excluidos_ya_notificados' => count($excludeTokens),
        //     'destinatarios_finales' => count($tokens),
        // ]);

        if (empty($tokens)) {
            Log::channel('firebase')->warning('sendToUsersWithModuleAccess: 0 destinatarios, no se envió nada');
            return 0;
        }

        $results = $this->sendToMultiple(array_values($tokens), $title, $body, $data);
        $exitosos = count(array_filter($results));
        // Log::channel('firebase')->info('sendToUsersWithModuleAccess: completado', [
        //     'enviados' => $exitosos,
        //     'total_destinatarios' => count($tokens),
        // ]);
        return $exitosos;
    }

    // ==================== PRIVADO ====================

    private function buildPayload(string $token, string $title, string $body, array $data): array
    {
        return [
            'message' => [
                'token' => $token,
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
                        'link' => config('app.url') . ($data['link'] ?? '/ui/facturacion-electronica/mis-entregas'),
                    ],
                ],
                'data' => array_map('strval', $data),
            ],
        ];
    }

    private function cleanupInvalidToken(string $token): void
    {
        try {
            User::where('fcm_token', $token)->update(['fcm_token' => null]);
            Log::channel('firebase')->warning('Firebase: Token inválido limpiado de la DB', [
                'token' => substr($token, 0, 20) . '...',
            ]);
        } catch (\Exception $e) {
            Log::channel('firebase')->error('Firebase: Error al limpiar token inválido', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
