<?php

namespace App\Http\Middleware;

use App\Events\ModelChanged;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware que automáticamente despacha un evento ModelChanged
 * cuando una petición de escritura (POST/PUT/PATCH/DELETE) retorna éxito.
 *
 * Uso en rutas:
 *   Route::post('/productos', ...)->middleware('broadcast:productos');
 *   Route::apiResource('clientes', ...)->middleware('broadcast:clientes');
 */
class BroadcastModelChanges
{
    private bool $shouldBroadcast = false;

    private string $module = '';

    private string $action = 'updated';

    private ?string $recordId = null;

    private ?string $userId = null;

    public function handle(Request $request, Closure $next, string $module): Response
    {
        $response = $next($request);

        // Solo broadcast en métodos de escritura exitosos
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $response;
        }

        // Solo si la respuesta es exitosa (2xx)
        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            return $response;
        }

        // Intentar extraer el ID del registro del response body
        $recordId = null;
        try {
            $content = $response->getContent();
            if ($content) {
                $data = json_decode($content, true);
                $recordId = $data['data']['id'] ?? $data['id'] ?? null;
                if ($recordId) {
                    $recordId = (string) $recordId;
                }
            }
        } catch (\Throwable $e) {
            // No importa si no podemos extraer el ID
        }

        // Guardamos los datos para emitir el evento en terminate() en vez de
        // acá. ModelChanged es ShouldBroadcastNow (síncrono, sin necesitar un
        // queue worker), así que antes esto abría la conexión a Reverb DENTRO
        // del ciclo request-response — el usuario esperaba ese round-trip
        // para cualquier escritura (crear, editar, anular, etc.). Al moverlo
        // a terminate(), Laravel ya mandó la respuesta al navegador
        // (fastcgi_finish_request bajo PHP-FPM) antes de emitir el evento, así
        // que el broadcast deja de sumar latencia percibida.
        $this->shouldBroadcast = true;
        $this->module = $module;
        $this->action = match ($request->method()) {
            'POST' => 'created',
            'PUT', 'PATCH' => 'updated',
            'DELETE' => 'deleted',
            default => 'updated',
        };
        $this->recordId = $recordId;
        $userId = auth()->id();
        $this->userId = $userId ? (string) $userId : null;

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        if (!$this->shouldBroadcast) {
            return;
        }

        // El broadcast es "best effort": si el servidor de WebSockets (Reverb)
        // está caído, no debe romper nada — la respuesta ya se envió. Solo
        // logueamos.
        try {
            event(new ModelChanged(
                module: $this->module,
                action: $this->action,
                recordId: $this->recordId,
                userId: $this->userId,
            ));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                "Broadcast ModelChanged falló (módulo {$this->module}); ¿Reverb caído? " . $e->getMessage()
            );
        }
    }
}
