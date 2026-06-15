<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TipoCambioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            // Fecha opcional (YYYY-MM-DD): permite traer el tipo de cambio de un
            // día anterior (ej. al crear una compra/orden con fecha pasada).
            $fecha = $request->query('fecha');
            if ($fecha && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $fecha)) {
                $fecha = null;
            }

            $cacheKey = 'tipo_cambio_sunat' . ($fecha ? "_{$fecha}" : '');
            $data = Cache::remember($cacheKey, 3600, function () use ($fecha) {
                return $this->consultarTipoCambio($fecha);
            });

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([
                'error' => ['message' => 'Error al obtener tipo de cambio: ' . $e->getMessage()]
            ], 500);
        }
    }

    private function consultarTipoCambio(?string $fecha = null): array
    {
        // API principal: SUNAT via apis.net.pe (soporta ?date=YYYY-MM-DD)
        try {
            $request = Http::withOptions(['verify' => false])
                ->timeout(10)
                ->withHeaders([
                    'Referer' => 'https://apis.net.pe/tipo-de-cambio-sunat-api',
                    'Authorization' => 'Bearer ' . config('services.apis_peru.token', 'apis-token-12676.06vC22lNLuV4uUGX4CsxHcdKf2tT92T8'),
                ]);

            $url = 'https://api.apis.net.pe/v2/sunat/tipo-cambio';
            $response = $fecha
                ? $request->get($url, ['date' => $fecha])
                : $request->get($url);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['precioCompra']) && isset($data['precioVenta'])) {
                    return [
                        'compra' => (float) $data['precioCompra'],
                        'venta' => (float) $data['precioVenta'],
                        'fecha' => $data['fecha'] ?? now()->toDateString(),
                        'fuente' => 'SUNAT',
                    ];
                }
            }
        } catch (\Exception $e) {
            // Continuar al respaldo
        }

        // API respaldo: exchangerate-api
        try {
            $response = Http::withOptions(['verify' => false])
                ->timeout(10)
                ->get('https://api.exchangerate-api.com/v4/latest/USD');

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['rates']['PEN'])) {
                    $tasa = (float) $data['rates']['PEN'];
                    return [
                        'compra' => round($tasa - 0.02, 4),
                        'venta' => round($tasa, 4),
                        'fecha' => now()->toDateString(),
                        'fuente' => 'ExchangeRate',
                    ];
                }
            }
        } catch (\Exception $e) {
            // Ambas fallaron
        }

        // Valor por defecto
        return [
            'compra' => 3.70,
            'venta' => 3.72,
            'fecha' => now()->toDateString(),
            'fuente' => 'default',
        ];
    }
}
