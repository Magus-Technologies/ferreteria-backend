<?php

namespace App\Services;

use App\Models\ValeCompra;
use App\Models\ValeCompraAplicado;
use App\Models\ValeCompraHistorial;
use App\Models\Venta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ValeCompraService
{
    /**
     * Aplicar vales automáticamente a una venta
     * 
     * @param Venta $venta
     * @param array $detallesVenta Productos con sus cantidades, categorías, etc.
     * @return Collection Vales aplicados
     */
    public function aplicarValesAutomaticos(Venta $venta, array $detallesVenta): Collection
    {
        try {
            // 1. Calcular datos de la venta
            $cantidadTotal = $this->calcularCantidadTotal($detallesVenta);
            $categorias = $this->extraerCategorias($detallesVenta);
            $productos = $this->extraerProductos($detallesVenta);

            Log::info('Buscando vales aplicables', [
                'venta_id' => $venta->id,
                'cantidad_total' => $cantidadTotal,
                'categorias' => $categorias,
                'productos' => $productos,
            ]);

            // 2. Buscar vales potencialmente aplicables
            $valesPotenciales = $this->buscarValesPotenciales($cantidadTotal);

            // 3. Filtrar vales por modalidad y restricciones
            $valesAplicables = $valesPotenciales->filter(function($vale) use ($categorias, $productos, $venta) {
                return $this->validarVale($vale, $categorias, $productos, $venta->cliente_id);
            });

            Log::info('Vales aplicables encontrados', [
                'count' => $valesAplicables->count(),
                'vales' => $valesAplicables->pluck('id', 'codigo'),
            ]);

            // 4. Aplicar cada vale
            $valesAplicados = collect();
            foreach ($valesAplicables as $vale) {
                $aplicado = $this->aplicarVale($vale, $venta, $cantidadTotal);
                if ($aplicado) {
                    $valesAplicados->push($aplicado);
                }
            }

            return $valesAplicados;

        } catch (\Exception $e) {
            Log::error('Error al aplicar vales automáticos', [
                'venta_id' => $venta->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // No lanzamos excepción para no interrumpir la venta
            return collect();
        }
    }

    /**
     * Calcular cantidad total de productos en la venta
     */
    private function calcularCantidadTotal(array $detallesVenta): float
    {
        return collect($detallesVenta)->sum(function($detalle) {
            return $detalle['cantidad'] ?? 0;
        });
    }

    /**
     * Extraer IDs de categorías de los productos en venta
     */
    private function extraerCategorias(array $detallesVenta): array
    {
        return collect($detallesVenta)
            ->pluck('categoria_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Extraer IDs de productos en venta
     */
    private function extraerProductos(array $detallesVenta): array
    {
        return collect($detallesVenta)
            ->pluck('producto_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Buscar vales potencialmente aplicables
     */
    private function buscarValesPotenciales(float $cantidadTotal): Collection
    {
        return ValeCompra::with(['categorias', 'productos', 'productoGratis'])
            ->activos()
            ->vigentes()
            ->where('cantidad_minima', '<=', $cantidadTotal)
            ->get();
    }

    /**
     * Validar si un vale es aplicable según modalidad y restricciones (público)
     */
    public function validarValePublic(
        ValeCompra $vale,
        array $categoriasVenta,
        array $productosVenta,
        ?int $clienteId
    ): bool {
        return $this->validarVale($vale, $categoriasVenta, $productosVenta, $clienteId);
    }

    /**
     * Aplicar un vale único (el que el vendedor eligió manualmente)
     */
    public function aplicarValeUnico(
        ValeCompra $vale,
        Venta $venta,
        float $cantidadProductos
    ): ?ValeCompraAplicado {
        return $this->aplicarVale($vale, $venta, $cantidadProductos);
    }

    /**
     * Validar si un vale es aplicable según modalidad y restricciones
     */
    private function validarVale(
        ValeCompra $vale,
        array $categoriasVenta,
        array $productosVenta,
        ?int $clienteId
    ): bool {
        // Verificar stock
        if (!$vale->tieneStockDisponible()) {
            Log::debug("Vale {$vale->codigo}: Sin stock disponible");
            return false;
        }

        // Verificar límite por cliente
        if ($clienteId && !$vale->clientePuedeUsar($clienteId)) {
            Log::debug("Vale {$vale->codigo}: Cliente {$clienteId} excedió límite de usos");
            return false;
        }

        // Validar por modalidad
        switch ($vale->modalidad) {
            case 'CANTIDAD_MINIMA':
                return true; // Ya validado en la query principal

            case 'POR_CATEGORIA':
                $categoriasVale = $vale->categorias->pluck('id')->toArray();
                $interseccion = array_intersect($categoriasVenta, $categoriasVale);
                $valido = count($interseccion) > 0;
                
                if (!$valido) {
                    Log::debug("Vale {$vale->codigo}: No cumple POR_CATEGORIA", [
                        'categorias_vale' => $categoriasVale,
                        'categorias_venta' => $categoriasVenta,
                    ]);
                }
                
                return $valido;

            case 'POR_PRODUCTOS':
                $productosVale = $vale->productos->pluck('id')->toArray();
                $interseccion = array_intersect($productosVenta, $productosVale);
                $valido = count($interseccion) > 0;
                
                if (!$valido) {
                    Log::debug("Vale {$vale->codigo}: No cumple POR_PRODUCTOS", [
                        'productos_vale' => $productosVale,
                        'productos_venta' => $productosVenta,
                    ]);
                }
                
                return $valido;

            case 'MIXTO':
                $categoriasVale = $vale->categorias->pluck('id')->toArray();
                $productosVale = $vale->productos->pluck('id')->toArray();
                
                $tieneCategoria = count(array_intersect($categoriasVenta, $categoriasVale)) > 0;
                $tieneProducto = count(array_intersect($productosVenta, $productosVale)) > 0;
                $valido = $tieneCategoria && $tieneProducto;
                
                if (!$valido) {
                    Log::debug("Vale {$vale->codigo}: No cumple MIXTO", [
                        'tiene_categoria' => $tieneCategoria,
                        'tiene_producto' => $tieneProducto,
                    ]);
                }
                
                return $valido;

            default:
                return false;
        }
    }

    /**
     * Aplicar un vale específico a una venta
     */
    private function aplicarVale(
        ValeCompra $vale,
        Venta $venta,
        float $cantidadProductos
    ): ?ValeCompraAplicado {
        DB::beginTransaction();

        try {
            $aplicado = ValeCompraAplicado::create([
                'vale_compra_id' => $vale->id,
                'venta_id' => $venta->id,
                'cliente_id' => $venta->cliente_id,
                'cantidad_productos' => $cantidadProductos,
                'descuento_aplicado' => $vale->descuento_valor,
                'descuento_tipo' => $vale->descuento_tipo,
                'genera_vale_futuro' => $vale->tipo_promocion === 'DESCUENTO_PROXIMA_COMPRA',
                'codigo_vale_generado' => $vale->tipo_promocion === 'DESCUENTO_PROXIMA_COMPRA' 
                    ? $vale->generarCodigoValeCliente() 
                    : null,
                'fecha_validez_generado' => $vale->fecha_validez_vale,
                'aplicado_por' => auth()->id(),
            ]);

            // Decrementar stock si aplica
            $vale->decrementarStock();

            // Registrar en historial
            ValeCompraHistorial::registrar(
                $vale->id,
                'APLICADO',
                "Vale aplicado a venta {$venta->numero}",
                null,
                [
                    'venta_id' => $venta->id,
                    'cantidad_productos' => $cantidadProductos,
                    'descuento_aplicado' => $vale->descuento_valor,
                ],
                auth()->id()
            );

            DB::commit();

            Log::info("Vale {$vale->codigo} aplicado exitosamente", [
                'venta_id' => $venta->id,
                'descuento' => $vale->descuento_valor,
                'tipo_descuento' => $vale->descuento_tipo,
            ]);

            return $aplicado;

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error("Error al aplicar vale {$vale->codigo}", [
                'venta_id' => $venta->id,
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    /**
     * Verificar si un código de vale generado es válido
     */
    public function verificarValeGenerado(string $codigo): ?ValeCompraAplicado
    {
        return ValeCompraAplicado::where('codigo_vale_generado', $codigo)
            ->where('genera_vale_futuro', true)
            ->where('usado', false)
            ->where('fecha_validez_generado', '>=', now())
            ->first();
    }

    /**
     * Aplicar un vale generado (de próxima compra)
     */
    public function aplicarValeGenerado(
        string $codigo,
        Venta $venta
    ): bool {
        $valeGenerado = $this->verificarValeGenerado($codigo);

        if (!$valeGenerado) {
            return false;
        }

        DB::beginTransaction();

        try {
            // Marcar como usado
            $valeGenerado->marcarComoUsado();

            // Registrar en historial del vale original
            ValeCompraHistorial::registrar(
                $valeGenerado->vale_compra_id,
                'APLICADO',
                "Vale generado {$codigo} usado en venta {$venta->numero}",
                null,
                [
                    'codigo_vale_generado' => $codigo,
                    'venta_id' => $venta->id,
                ]
            );

            DB::commit();

            Log::info("Vale generado {$codigo} aplicado exitosamente", [
                'venta_id' => $venta->id,
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error("Error al aplicar vale generado {$codigo}", [
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Obtener vales disponibles para un cliente
     */
    public function valesDisponiblesCliente(int $clienteId): Collection
    {
        return ValeCompraAplicado::where('cliente_id', $clienteId)
            ->valesPendientes()
            ->with(['valeCompra'])
            ->get();
    }
}
