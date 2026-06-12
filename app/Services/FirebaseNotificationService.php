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
                    Log::error('Firebase: Error al cargar clave privada');
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
            $payload = $this->buildPayload([$fcmToken], $title, $body, $data);

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
            $status = $response->status();

            // Token expirado — refrescar y reintentar una vez
            if ($status === 401) {
                Cache::forget('firebase_access_token');
                $newToken = $this->getAccessToken();
                if ($newToken) {
                    $retry = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $newToken,
                        'Content-Type' => 'application/json',
                    ])->post($this->fcmUrl, $payload);

                    if ($retry->successful()) {
                        Log::info('Firebase: Notificación enviada tras refrescar token');
                        return true;
                    }
                    $error = $retry->json();
                    $status = $retry->status();
                }
            }

            Log::error('Firebase: Error al enviar notificación', [
                'status' => $status,
                'error' => $error,
            ]);

            // Token de dispositivo inválido — limpiar
            if ($this->isUnregisteredError($error)) {
                $this->cleanupInvalidToken($fcmToken);
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
     * Envía notificación a múltiples dispositivos usando multicast (FCM v1)
     * Máximo 500 tokens por request. Los lotes se procesan secuencialmente.
     */
    public function sendToMultiple(
        array $fcmTokens,
        string $title,
        string $body,
        array $data = []
    ): array {
        $results = [];
        $chunks = array_chunk(array_values($fcmTokens), self::MAX_TOKENS_PER_REQUEST);

        foreach ($chunks as $chunk) {
            $batchResults = $this->sendMulticastBatch($chunk, $title, $body, $data);

            foreach ($batchResults as $i => $result) {
                $token = $chunk[$i] ?? null;
                if ($token === null) continue;

                if ($result['success'] ?? false) {
                    $results[$token] = true;
                } else {
                    $results[$token] = false;
                    if (!empty($result['unregistered'])) {
                        $this->cleanupInvalidToken($token);
                    }
                }
            }
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
        $users = User::with(['restrictions', 'roles.restrictions'])
            ->whereNotNull('fcm_token')
            ->where('estado', true)
            ->get()
            ->filter(fn(User $user) => $user->hasAccess($modulePermission))
            ->pluck('fcm_token')
            ->toArray();

        $tokens = array_diff($users, $excludeTokens);

        if (empty($tokens)) return 0;

        $results = $this->sendToMultiple(array_values($tokens), $title, $body, $data);
        return count(array_filter($results));
    }

    // ==================== PRIVADO ====================

    private function buildPayload(array $tokens, string $title, string $body, array $data): array
    {
        $message = [
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
            'data' => array_map('strval', $data),
        ];

        if (count($tokens) === 1) {
            $message['token'] = $tokens[0];
        } else {
            $message['registration_tokens'] = $tokens;
        }

        return ['message' => $message];
    }

    private function sendMulticastBatch(array $tokens, string $title, string $body, array $data): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return array_fill(0, count($tokens), ['success' => false]);
        }

        $payload = $this->buildPayload($tokens, $title, $body, $data);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->post($this->fcmUrl, $payload);

        if ($response->successful()) {
            return $this->parseMulticastResults($response->json(), $tokens);
        }

        $status = $response->status();
        $error = $response->json();

        // 401 — token expirado, reintentar una vez
        if ($status === 401) {
            Cache::forget('firebase_access_token');
            $newToken = $this->getAccessToken();
            if ($newToken) {
                $retry = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $newToken,
                    'Content-Type' => 'application/json',
                ])->post($this->fcmUrl, $payload);

                if ($retry->successful()) {
                    return $this->parseMulticastResults($retry->json(), $tokens);
                }
                $error = $retry->json();
            }
        }

        Log::error('Firebase: Error en multicast', [
            'status' => $status,
            'token_count' => count($tokens),
            'error' => $error,
        ]);

        return array_fill(0, count($tokens), ['success' => false]);
    }

    private function parseMulticastResults(array $response, array $tokens): array
    {
        $results = $response['results'] ?? [];

        return array_map(function ($result) {
            $isUnregistered = $this->isUnregisteredError($result);
            return [
                'success' => !isset($result['error']),
                'error' => $result['error'] ?? null,
                'unregistered' => $isUnregistered,
                'message_id' => $result['message_id'] ?? null,
            ];
        }, $results);
    }

    private function isUnregisteredError(?array $errorDetail): bool
    {
        if (!$errorDetail) return false;

        $errorCode = $errorDetail['errorCode'] ?? $errorDetail['error'] ?? '';
        return str_contains($errorCode, 'UNREGISTERED')
            || str_contains($errorDetail['@type'] ?? '', 'UNREGISTERED');
    }

    private function cleanupInvalidToken(string $token): void
    {
        try {
            User::where('fcm_token', $token)->update(['fcm_token' => null]);
            Log::warning('Firebase: Token inválido limpiado de la DB', [
                'token' => substr($token, 0, 20) . '...',
            ]);
        } catch (\Exception $e) {
            Log::error('Firebase: Error al limpiar token inválido', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
