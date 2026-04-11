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

        // Determinar la acción según el método HTTP
        $action = match ($request->method()) {
            'POST' => 'created',
            'PUT', 'PATCH' => 'updated',
            'DELETE' => 'deleted',
            default => 'updated',
        };

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

        $userId = auth()->id();

        event(new ModelChanged(
            module: $module,
            action: $action,
            recordId: $recordId,
            userId: $userId ? (string) $userId : null,
        ));

        return $response;
    }
}
