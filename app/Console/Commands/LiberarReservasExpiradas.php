<?php

namespace App\Console\Commands;

use App\Models\Cotizacion;
use App\Models\ProductoAlmacen;
use App\Models\ProductoAlmacenCotizacion;
use App\Services\Producto\ComplementarioStockService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LiberarReservasExpiradas extends Command
{
    protected $signature = 'reservas:liberar-expiradas';

    protected $description = 'Libera el stock reservado de cotizaciones cuya fecha de vencimiento ha pasado';

    public function handle(): int
    {
        $this->info('Buscando cotizaciones con reservas expiradas...');

        $cotizaciones = Cotizacion::where('reservar_stock', true)
            ->where('fecha_vencimiento_reserva', '<', Carbon::now())
            ->whereNotIn('estado_cotizacion', ['co', 've', 'ca'])
            ->with(['productosPorAlmacen.unidadesDerivadas'])
            ->get();

        if ($cotizaciones->isEmpty()) {
            $this->info('No se encontraron cotizaciones con reservas expiradas.');
            Log::info('[LiberarReservasExpiradas] No se encontraron reservas expiradas.');
            return self::SUCCESS;
        }

        $this->info("Se encontraron {$cotizaciones->count()} cotización(es) con reservas expiradas.");

        $liberadas = 0;

        foreach ($cotizaciones as $cotizacion) {
            try {
                DB::beginTransaction();

                $kardexFacturacionService = app(\App\Services\Kardex\KardexFacturacionService::class);

                $loteService = app(\App\Services\Producto\ProductoLoteService::class);

                foreach ($cotizacion->productosPorAlmacen as $productoAlmacenCotizacion) {
                    $productoAlmacen = ProductoAlmacen::find($productoAlmacenCotizacion->producto_almacen_id);

                    if ($productoAlmacen) {
                        $totalFraccion = 0.0;
                        foreach ($productoAlmacenCotizacion->unidadesDerivadas as $unidad) {
                            // Registrar en kardex ANTES de revertir el consumo de lotes, para
                            // capturar el stock_anterior correcto.
                            $kardexFacturacionService->registrarLiberacionReservaCotizacion(
                                $cotizacion,
                                $productoAlmacen->load('producto'),
                                [
                                    'unidad_derivada_inmutable_name' => $unidad->unidadDerivadaInmutable->name ?? 'UNIDAD',
                                    'cantidad' => $unidad->cantidad,
                                    'factor' => $unidad->factor,
                                    'precio' => $unidad->precio,
                                ],
                                $productoAlmacen->costo,
                                'reserva expirada'
                            );

                            $totalFraccion += $unidad->cantidad * $unidad->factor;

                            ComplementarioStockService::procesarComplementarioPorFactor(
                                $productoAlmacen->id,
                                $unidad->factor,
                                $unidad->cantidad,
                                $productoAlmacen->almacen_id,
                                true
                            );
                        }

                        // Revertir el consumo de lotes PEPS de la reserva UNA sola vez por
                        // producto (con el total de todas sus unidades derivadas) — revertir
                        // por unidad duplicaría la reversión, ver liberarStockProducto() en
                        // CotizacionController para el mismo patrón.
                        $loteService->revertirConsumoOReingresar($productoAlmacen, 'cotizacion', $cotizacion->id, $totalFraccion);
                    }
                }

                $cotizacion->update(['reservar_stock' => false]);

                DB::commit();

                $liberadas++;

                Log::info('[LiberarReservasExpiradas] Reserva liberada', [
                    'cotizacion_id' => $cotizacion->id,
                    'numero' => $cotizacion->numero,
                    'fecha_vencimiento_reserva' => $cotizacion->fecha_vencimiento_reserva,
                ]);

                $this->line("   Cotización {$cotizacion->numero} - Stock liberado (venció: {$cotizacion->fecha_vencimiento_reserva})");
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('[LiberarReservasExpiradas] Error al liberar reserva', [
                    'cotizacion_id' => $cotizacion->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("   Error al liberar cotización {$cotizacion->id}: {$e->getMessage()}");
            }
        }

        $this->info("Proceso completado: {$liberadas}/{$cotizaciones->count()} reservas liberadas.");
        Log::info("[LiberarReservasExpiradas] Proceso completado: {$liberadas} reservas liberadas.");

        return self::SUCCESS;
    }
}