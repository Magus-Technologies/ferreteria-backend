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
     * @param array $valesExcluidos IDs de vales que el vendedor excluyó manualmente
     * @return Collection Vales aplicados
     */
    public function aplicarValesAutomaticos(Venta $venta, array $detallesVenta, array $valesExcluidos = []): Collection
    {
        try {
            // 1. Calcular datos de la venta: precio total (S/), cantidad total (unidades),
            // IDs de categorías/productos y tipos de precio usados.
            $precioTotal = $this->calcularPrecioTotal($detallesVenta);
            $cantidadTotal = $this->calcularCantidadTotal($detallesVenta);
            $categorias = $this->extraerCategorias($detallesVenta);
            $productos = $this->extraerProductos($detallesVenta);
            $tiposPrecio = $this->extraerTiposPrecio($detallesVenta);


            // 2. Buscar vales potencialmente aplicables (filtro por umbral según tipo/modalidad)
            $valesPotenciales = $this->buscarValesPotenciales($precioTotal, $cantidadTotal);

            // 3. Filtrar vales por modalidad, restricciones y tipo de precio
            $valesAplicables = $valesPotenciales->filter(function($vale) use ($categorias, $productos, $tiposPrecio, $venta) {
                // Solo MISMA_COMPRA se aplica automáticamente. PROXIMA_COMPRA se canjea manualmente.
                if ($vale->momento_aplicacion === 'PROXIMA_COMPRA') {
                    return false;
                }
                return $this->validarVale($vale, $categorias, $productos, $tiposPrecio, $venta->cliente_id);
            });

            // 3b. Excluir vales que el vendedor quitó manualmente en la UI
            if (!empty($valesExcluidos)) {
                $valesAplicables = $valesAplicables->reject(function($vale) use ($valesExcluidos) {
                    return in_array($vale->id, $valesExcluidos);
                });
            }

            // 4. Aplicar cada vale
            $valesAplicados = collect();
            foreach ($valesAplicables as $vale) {
                $aplicado = $this->aplicarVale($vale, $venta, $precioTotal);
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
     * Calcular monto total de la venta en S/ (suma de precio_total por línea).
     * El controller `prepararDetallesVentaParaVales` calcula `precio_total`
     * como `precio × cantidad` por unidad derivada. Si falta el campo
     * (legacy callers), se asume 0 — el vale no se activará para esa línea.
     */
    private function calcularPrecioTotal(array $detallesVenta): float
    {
        return collect($detallesVenta)->sum(function($detalle) {
            return $detalle['precio_total'] ?? 0;
        });
    }

    /**
     * Calcular cantidad total de unidades en la venta (suma de `cantidad` por línea).
     * Usado para vales cuyo umbral está expresado en unidades (PRODUCTO_GRATIS,
     * DOS_POR_UNO, o modalidad POR_PRODUCTOS / MIXTO).
     */
    private function calcularCantidadTotal(array $detallesVenta): float
    {
        return collect($detallesVenta)->sum(function($detalle) {
            return $detalle['cantidad'] ?? 0;
        });
    }

    /**
     * Determina si el umbral `cantidad_minima` del vale debe interpretarse como
     * cantidad de unidades (true) o como precio en S/ (false).
     */
    private function esUmbralPorUnidades(ValeCompra $vale): bool
    {
        return in_array($vale->tipo_promocion, ['PRODUCTO_GRATIS', 'DOS_POR_UNO'], true)
            || in_array($vale->modalidad, ['POR_PRODUCTOS', 'MIXTO'], true);
    }

    /**
     * Helper estático equivalente: útil desde el controller (donde no hay instancia).
     */
    public static function esUmbralPorUnidadesStatic(ValeCompra $vale): bool
    {
        return in_array($vale->tipo_promocion, ['PRODUCTO_GRATIS', 'DOS_POR_UNO'], true)
            || in_array($vale->modalidad, ['POR_PRODUCTOS', 'MIXTO'], true);
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
     * Extraer tipos de precio usados en la venta.
     * Cada detalle puede tener un array `tipo_precio` con los tipos detectados
     * (publico, especial, minimo, ultimo). Se unifican todos.
     */
    private function extraerTiposPrecio(array $detallesVenta): array
    {
        return collect($detallesVenta)
            ->pluck('tipo_precio')
            ->filter()
            ->flatten()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Buscar vales potencialmente aplicables. El umbral `cantidad_minima` se
     * compara contra precio o cantidad según el tipo/modalidad del vale.
     */
    private function buscarValesPotenciales(float $precioTotal, float $cantidadTotal = 0): Collection
    {
        $todos = ValeCompra::with(['categorias', 'productos', 'productoGratis'])
            ->activos()
            ->vigentes()
            ->get();

        return $todos->filter(function (ValeCompra $vale) use ($precioTotal, $cantidadTotal) {
            $umbral = $this->esUmbralPorUnidades($vale) ? $cantidadTotal : $precioTotal;
            return (float) $vale->cantidad_minima <= (float) $umbral;
        })->values();
    }

    /**
     * Validar si un vale es aplicable según modalidad y restricciones (público)
     */
    public function validarValePublic(
        ValeCompra $vale,
        array $categoriasVenta,
        array $productosVenta,
        array $tiposPrecio,
        ?int $clienteId
    ): bool {
        return $this->validarVale($vale, $categoriasVenta, $productosVenta, $tiposPrecio, $clienteId);
    }

    /**
     * Validar condiciones de un vale contra datos de una venta (precio, cantidad, etc.)
     * Útil para verificar códigos manualmente ingresados en la UI.
     */
    public function validarValeCondiciones(
        ValeCompra $vale,
        float $precioTotal,
        float $cantidadTotal = 0,
        array $categoriasVenta = [],
        array $productosVenta = [],
        array $tiposPrecio = [],
        ?int $clienteId = null
    ): array {
        $umbral = $this->esUmbralPorUnidades($vale) ? $cantidadTotal : $precioTotal;
        $cumpleUmbral = (float) $vale->cantidad_minima <= (float) $umbral;

        $tieneStock = $vale->tieneStockDisponible();
        $esVigente = $vale->esVigente();
        $clientePuedeUsar = $clienteId ? $vale->clientePuedeUsar($clienteId) : true;

        // SORTEO no valida modalidad ni tipos de precio si es solo registro
        if ($vale->tipo_promocion === 'SORTEO' && !$vale->sorteo_incluye_producto) {
            return [
                'cumple' => $cumpleUmbral && $tieneStock && $esVigente && $clientePuedeUsar,
                'umbral' => $cumpleUmbral,
                'stock' => $tieneStock,
                'vigente' => $esVigente,
                'cliente' => $clientePuedeUsar,
            ];
        }

        $cumpleValidacion = $this->validarVale($vale, $categoriasVenta, $productosVenta, $tiposPrecio, $clienteId);

        return [
            'cumple' => $cumpleUmbral && $tieneStock && $esVigente && $clientePuedeUsar && $cumpleValidacion,
            'umbral' => $cumpleUmbral,
            'stock' => $tieneStock,
            'vigente' => $esVigente,
            'cliente' => $clientePuedeUsar,
            'modalidad' => $cumpleValidacion,
        ];
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
     * Validar si un vale es aplicable según modalidad, restricciones y tipo de precio
     */
    private function validarVale(
        ValeCompra $vale,
        array $categoriasVenta,
        array $productosVenta,
        array $tiposPrecio,
        ?int $clienteId
    ): bool {
        // Verificar stock
        if (!$vale->tieneStockDisponible()) {
            return false;
        }

        // Verificar límite por cliente
        if ($clienteId && !$vale->clientePuedeUsar($clienteId)) {
            return false;
        }

        // Verificar que al menos un tipo de precio de la venta esté permitido por el vale.
        // Si no se pudo detectar el tipo de precio (array vacío), se permite por compatibilidad
        // pero se loggea para detectar PAUDs mal configurados o precios fuera de los 4 estándar.
        if (empty($tiposPrecio)) {
            Log::warning('Vale aplicado sin tipo de precio detectado', [
                'vale_id' => $vale->id,
                'vale_codigo' => $vale->codigo,
                'cliente_id' => $clienteId,
            ]);
        } else {
            $permitePublico  = (bool) $vale->aplica_precio_publico;
            $permiteEspecial = (bool) $vale->aplica_precio_especial;
            $permiteMinimo   = (bool) $vale->aplica_precio_minimo;
            $permiteUltimo   = (bool) $vale->aplica_precio_ultimo;

            $ningunPermitido = !$permitePublico && !$permiteEspecial && !$permiteMinimo && !$permiteUltimo;
            if ($ningunPermitido) {
                return false;
            }

            $tiposPermitidos = [];
            if ($permitePublico)  $tiposPermitidos[] = 'publico';
            if ($permiteEspecial) $tiposPermitidos[] = 'especial';
            if ($permiteMinimo)   $tiposPermitidos[] = 'minimo';
            if ($permiteUltimo)   $tiposPermitidos[] = 'ultimo';

            $tieneTipoPermitido = count(array_intersect($tiposPrecio, $tiposPermitidos)) > 0;
            if (!$tieneTipoPermitido) {
                return false;
            }
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
                }
                
                return $valido;

            case 'POR_PRODUCTOS':
                $productosVale = $vale->productos->pluck('id')->toArray();
                $interseccion = array_intersect($productosVenta, $productosVale);
                $valido = count($interseccion) > 0;
                
                if (!$valido) {
                }
                
                return $valido;

            case 'MIXTO':
                $categoriasVale = $vale->categorias->pluck('id')->toArray();
                $productosVale = $vale->productos->pluck('id')->toArray();
                
                $tieneCategoria = count(array_intersect($categoriasVenta, $categoriasVale)) > 0;
                $tieneProducto = count(array_intersect($productosVenta, $productosVale)) > 0;
                $valido = $tieneCategoria && $tieneProducto;
                
                if (!$valido) {
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
        float $precioTotal
    ): ?ValeCompraAplicado {
        DB::beginTransaction();

        try {
            // Determinar si el vale se entrega como código futuro (PROXIMA_COMPRA).
            // Incluye el caso histórico DESCUENTO_PROXIMA_COMPRA por compatibilidad
            // con vales creados antes de existir la columna momento_aplicacion.
            $esFuturo = $vale->momento_aplicacion === 'PROXIMA_COMPRA'
                || $vale->tipo_promocion === 'DESCUENTO_PROXIMA_COMPRA';

            if ($esFuturo) {
                // PROXIMA_COMPRA: solo se entrega el código, ningún beneficio se aplica ahora.
                // El vendedor canjeará el código manualmente en la venta donde el cliente
                // lo presente (ver modal-canjear-vale en frontend).
                $descuentoAplicado = null;
                $descuentoTipo = $vale->descuento_tipo;
                $codigoValeGenerado = $vale->generarCodigoValeCliente();
            }
            // MISMA_COMPRA: aplicar el beneficio en la venta actual.
            // Para SORTEO: registrar participación sin descuento, pero igual se genera código.
            else if ($vale->tipo_promocion === 'SORTEO') {
                $descuentoAplicado = null;
                $descuentoTipo = null;
                $codigoValeGenerado = $vale->generarCodigoValeCliente();
            }
            // Para PRODUCTO_GRATIS: obtener el valor real del producto como descuento
            else if ($vale->tipo_promocion === 'PRODUCTO_GRATIS' && $vale->producto_gratis_id) {
                $paudGratis = \App\Models\ProductoAlmacenUnidadDerivada::whereHas('productoAlmacen', function($q) use ($vale, $venta) {
                        $q->where('producto_id', $vale->producto_gratis_id)
                          ->where('almacen_id', $venta->almacen_id);
                    })
                    ->where('precio_publico', '>', 0)
                    ->first();
                $descuentoAplicado = $vale->descuento_valor;
                if ($paudGratis) {
                    $cantidadGratis = (float) ($vale->cantidad_producto_gratis ?: 1);
                    $descuentoAplicado = (float) $paudGratis->precio_publico * $cantidadGratis;
                }
                $descuentoTipo = $vale->descuento_tipo;
                $codigoValeGenerado = null;
            }
            // Para DOS_POR_UNO: descuento = precio del producto más barato del vale × extras gratis
            else if ($vale->tipo_promocion === 'DOS_POR_UNO') {
                $descuentoAplicado = (float) ($vale->descuento_valor ?? 0);
                $descuentoTipo = $vale->descuento_tipo;
                $codigoValeGenerado = null;

                $productoIds = $vale->productos->pluck('id')->toArray();
                if (!empty($productoIds)) {
                    $paudBarato = \App\Models\ProductoAlmacenUnidadDerivada::whereHas('productoAlmacen', function($q) use ($productoIds, $venta) {
                            $q->whereIn('producto_id', $productoIds)
                              ->where('almacen_id', $venta->almacen_id);
                        })
                        ->where('precio_publico', '>', 0)
                        ->orderBy('precio_publico', 'asc')
                        ->first();
                    if ($paudBarato) {
                        $cantidadExtra = (float) ($vale->cantidad_producto_gratis ?: 1);
                        $descuentoAplicado = (float) $paudBarato->precio_publico * $cantidadExtra;
                    }
                }
            }
            // Para DESCUENTO_MISMA_COMPRA: usar descuento del vale
            else {
                $descuentoAplicado = $vale->descuento_valor;
                $descuentoTipo = $vale->descuento_tipo;
                $codigoValeGenerado = null;
            }

            // `cantidad_productos` es el nombre histórico de la columna;
            // ahora almacena el MONTO TOTAL en S/ que disparó la activación
            // del vale (paridad con `cantidad_minima` que ya es precio).
            // Validez del código futuro = fecha_fin del vale (si tiene), o el campo
            // legado `fecha_validez_vale` como fallback para vales antiguos.
            // Si ambos son null, el código no expira.
            $fechaValidezCodigo = $esFuturo
                ? ($vale->fecha_fin ?? $vale->fecha_validez_vale)
                : null;

            $aplicado = ValeCompraAplicado::create([
                'vale_compra_id' => $vale->id,
                'venta_id' => $venta->id,
                'cliente_id' => $venta->cliente_id,
                'cantidad_productos' => $precioTotal,
                'descuento_aplicado' => $descuentoAplicado,
                'descuento_tipo' => $descuentoTipo,
                'genera_vale_futuro' => $esFuturo,
                'codigo_vale_generado' => $codigoValeGenerado,
                'fecha_validez_generado' => $fechaValidezCodigo,
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
                    'cantidad_productos' => $precioTotal,
                    'descuento_aplicado' => $descuentoAplicado,
                    'tipo_promocion' => $vale->tipo_promocion,
                ],
                auth()->id()
            );

            DB::commit();

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
     * Verificar si un código de vale generado es válido.
     * Acepta tanto DESCUENTO_PROXIMA_COMPRA (genera_vale_futuro=true, con vencimiento)
     * como SORTEO (genera_vale_futuro=false, sin vencimiento).
     * El criterio es: existe el código, no ha sido usado, y si tiene fecha de validez
     * aún no ha expirado.
     */
    public function verificarValeGenerado(string $codigo): ?ValeCompraAplicado
    {
        return ValeCompraAplicado::where('codigo_vale_generado', $codigo)
            ->where('usado', false)
            ->where(function ($q) {
                $q->whereNull('fecha_validez_generado')
                  ->orWhere('fecha_validez_generado', '>=', now());
            })
            ->first();
    }

    /**
     * Aplicar un vale generado (de próxima compra) o un vale regular por código manual.
     * Para vales regulares (VC-...), valida condiciones contra los datos de la venta.
     */
    public function aplicarValeGenerado(
        string $codigo,
        Venta $venta,
        array $detallesVenta = []
    ): bool {
        // 1. Buscar como código generado
        $valeGenerado = $this->verificarValeGenerado($codigo);

        if ($valeGenerado) {
            return $this->aplicarValeGeneradoExistente($valeGenerado, $venta, $codigo);
        }

        // 2. Buscar como vale regular (VC-...)
        $vale = ValeCompra::where('codigo', $codigo)
            ->where('estado', 'ACTIVO')
            ->first();

        if (!$vale) {
            return false;
        }

        if (!$vale->esVigente() || !$vale->tieneStockDisponible()) {
            return false;
        }

        // Validar condiciones si hay detalles de venta
        if (!empty($detallesVenta)) {
            $precioTotal = $this->calcularPrecioTotal($detallesVenta);
            $cantidadTotal = $this->calcularCantidadTotal($detallesVenta);
            $categorias = $this->extraerCategorias($detallesVenta);
            $productos = $this->extraerProductos($detallesVenta);
            $tiposPrecio = $this->extraerTiposPrecio($detallesVenta);

            $condiciones = $this->validarValeCondiciones(
                $vale, $precioTotal, $cantidadTotal,
                $categorias, $productos, $tiposPrecio, $venta->cliente_id
            );

            if (!$condiciones['cumple']) {
                Log::warning("Vale {$codigo} no cumple condiciones para venta {$venta->id}", $condiciones);
                return false;
            }
        }

        // Aplicar el vale regular
        $aplicado = $this->aplicarVale($vale, $venta, $this->calcularPrecioTotal($detallesVenta));
        return $aplicado !== null;
    }

    /**
     * Aplicar un vale generado existente (encontrado en ValeCompraAplicado)
     */
    private function aplicarValeGeneradoExistente(
        ValeCompraAplicado $valeGenerado,
        Venta $venta,
        string $codigo
    ): bool {

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
