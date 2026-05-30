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

            // 3. Filtrar vales por modalidad, restricciones y tipo de precio.
            // Solo MISMA_COMPRA se aplica AUTOMÁTICAMENTE. Los PROXIMA_COMPRA se aplican
            // manualmente tecleando su código (VC-...) en el canje (ver aplicarValeGenerado),
            // y también se aplican en esa misma venta — no se difieren a una compra futura.
            $valesAplicables = $valesPotenciales->filter(function($vale) use ($categorias, $productos, $tiposPrecio, $venta) {
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

            // 3c. Respetar el límite de vales distintos por venta.
            // Si algún vale tiene max_vales_por_venta, se aplica el más restrictivo.
            $limiteMax = $valesAplicables
                ->pluck('max_vales_por_venta')
                ->filter()
                ->min();
            if ($limiteMax !== null && $valesAplicables->count() > $limiteMax) {
                $valesAplicables = $valesAplicables->take($limiteMax);
            }

            // Precio unitario REAL al que se vendió cada producto en esta venta
            // (precio_total / cantidad por línea). Se usa para valorar PRODUCTO_GRATIS
            // con el precio efectivamente cobrado, en paridad con el resumen del frontend.
            $preciosLineaPorProducto = $this->mapearPreciosLinea($detallesVenta);
            $cantidadesLineaPorProducto = $this->mapearCantidadesLinea($detallesVenta);

            // 4. Aplicar cada vale
            $valesAplicados = collect();
            foreach ($valesAplicables as $vale) {
                $aplicado = $this->aplicarVale($vale, $venta, $precioTotal, $preciosLineaPorProducto, $cantidadesLineaPorProducto);
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
     * Construir un mapa producto_id => precio unitario real vendido en la venta
     * (precio_total / cantidad por línea). Se omiten líneas con cantidad 0.
     */
    private function mapearPreciosLinea(array $detallesVenta): array
    {
        $mapa = [];
        foreach ($detallesVenta as $detalle) {
            $productoId = $detalle['producto_id'] ?? null;
            $cantidad = (float) ($detalle['cantidad'] ?? 0);
            $precioTotal = (float) ($detalle['precio_total'] ?? 0);
            if ($productoId && $cantidad > 0) {
                $mapa[$productoId] = $precioTotal / $cantidad;
            }
        }
        return $mapa;
    }

    /**
     * Mapa producto_id => cantidad total (unidades) vendida en la venta.
     * Usado para escalar el beneficio del 2x1 (ej. compra 10, lleva 5 gratis).
     */
    private function mapearCantidadesLinea(array $detallesVenta): array
    {
        $mapa = [];
        foreach ($detallesVenta as $detalle) {
            $productoId = $detalle['producto_id'] ?? null;
            $cantidad = (float) ($detalle['cantidad'] ?? 0);
            if ($productoId && $cantidad > 0) {
                $mapa[$productoId] = ($mapa[$productoId] ?? 0) + $cantidad;
            }
        }
        return $mapa;
    }

    /**
     * Determina si el umbral `cantidad_minima` del vale debe interpretarse como
     * cantidad de unidades (true) o como precio en S/ (false).
     */
    private function esUmbralPorUnidades(ValeCompra $vale): bool
    {
        return self::esUmbralPorUnidadesStatic($vale);
    }

    /**
     * Helper estático equivalente: útil desde el controller (donde no hay instancia).
     *
     * Prioridad:
     * 1) PRODUCTO_GRATIS y DOS_POR_UNO siempre son por unidades (el beneficio lo exige).
     * 2) Si el vale tiene `tipo_umbral` definido por el usuario, se respeta
     *    (CANTIDAD = unidades, MONTO = soles).
     * 3) Vales antiguos sin `tipo_umbral`: se infiere por modalidad (compatibilidad).
     */
    public static function esUmbralPorUnidadesStatic(ValeCompra $vale): bool
    {
        if (in_array($vale->tipo_promocion, ['PRODUCTO_GRATIS', 'DOS_POR_UNO'], true)) {
            return true;
        }

        if (!empty($vale->tipo_umbral)) {
            return $vale->tipo_umbral === 'CANTIDAD';
        }

        // Compatibilidad para vales creados antes de existir tipo_umbral.
        return in_array($vale->modalidad, ['POR_PRODUCTOS', 'MIXTO'], true);
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
    /**
     * Calcular el beneficio (descuento) de un vale para una venta, según su tipo.
     * Devuelve ['monto' => ?float, 'tipo' => ?string].
     *
     * - PRODUCTO_GRATIS: valor del producto gratis (precio real de la línea si está en
     *   el carrito; si no, precio público del producto) × cantidad gratis.
     * - DOS_POR_UNO: precio del producto más barato del vale que esté en el carrito
     *   (o precio público del más barato si ninguno está) × extras gratis.
     * - DESCUENTO_* y demás: descuento_valor / descuento_tipo del vale.
     *
     * Se usa tanto al aplicar el vale en la misma compra como al canjear un código de
     * próxima compra, para que ambos caminos calculen exactamente igual.
     */
    private function calcularDescuentoBeneficio(
        ValeCompra $vale,
        Venta $venta,
        array $preciosLineaPorProducto = [],
        array $cantidadesLineaPorProducto = []
    ): array {
        if ($vale->tipo_promocion === 'PRODUCTO_GRATIS' && $vale->producto_gratis_id) {
            $cantidadGratis = (float) ($vale->cantidad_producto_gratis ?: 1);
            $monto = $vale->descuento_valor;

            $precioLinea = $preciosLineaPorProducto[$vale->producto_gratis_id] ?? null;
            if ($precioLinea !== null && $precioLinea > 0) {
                $monto = $precioLinea * $cantidadGratis;
            } else {
                $paudGratis = \App\Models\ProductoAlmacenUnidadDerivada::whereHas('productoAlmacen', function($q) use ($vale, $venta) {
                        $q->where('producto_id', $vale->producto_gratis_id)
                          ->where('almacen_id', $venta->almacen_id);
                    })
                    ->where('precio_publico', '>', 0)
                    ->first();
                if ($paudGratis) {
                    $monto = (float) $paudGratis->precio_publico * $cantidadGratis;
                }
            }
            // grupos = 1 (el producto gratis se entrega una vez por activación)
            return ['monto' => $monto, 'tipo' => $vale->descuento_tipo, 'grupos' => 1];
        }

        if ($vale->tipo_promocion === 'DOS_POR_UNO') {
            $gratisPorGrupo = (float) ($vale->cantidad_producto_gratis ?: 1);
            $tamGrupo = (float) ($vale->cantidad_minima ?: 1);
            $monto = (float) ($vale->descuento_valor ?? 0);
            $grupos = 1;
            $productoIds = $vale->productos->pluck('id')->toArray();

            $preciosEnCarrito = [];
            $cantidadEnCarrito = 0.0;
            foreach ($productoIds as $pid) {
                if (isset($preciosLineaPorProducto[$pid]) && $preciosLineaPorProducto[$pid] > 0) {
                    $preciosEnCarrito[] = $preciosLineaPorProducto[$pid];
                }
                $cantidadEnCarrito += (float) ($cantidadesLineaPorProducto[$pid] ?? 0);
            }

            if (!empty($preciosEnCarrito)) {
                $grupos = $tamGrupo > 0 ? (int) floor($cantidadEnCarrito / $tamGrupo) : 1;
                $unidadesGratis = $grupos * $gratisPorGrupo;
                $monto = min($preciosEnCarrito) * $unidadesGratis;
            } elseif (!empty($productoIds)) {
                $paudBarato = \App\Models\ProductoAlmacenUnidadDerivada::whereHas('productoAlmacen', function($q) use ($productoIds, $venta) {
                        $q->whereIn('producto_id', $productoIds)
                          ->where('almacen_id', $venta->almacen_id);
                    })
                    ->where('precio_publico', '>', 0)
                    ->orderBy('precio_publico', 'asc')
                    ->first();
                if ($paudBarato) {
                    $monto = (float) $paudBarato->precio_publico * $gratisPorGrupo;
                }
            }
            return ['monto' => $monto, 'tipo' => $vale->descuento_tipo, 'grupos' => $grupos];
        }

        // DESCUENTO_MISMA_COMPRA / DESCUENTO_PROXIMA_COMPRA / otros
        return ['monto' => $vale->descuento_valor, 'tipo' => $vale->descuento_tipo, 'grupos' => 1];
    }

    private function aplicarVale(
        ValeCompra $vale,
        Venta $venta,
        float $precioTotal,
        array $preciosLineaPorProducto = [],
        array $cantidadesLineaPorProducto = []
    ): ?ValeCompraAplicado {
        DB::beginTransaction();

        try {
            // SORTEO: registrar participación sin descuento, pero genera un código de sorteo.
            if ($vale->tipo_promocion === 'SORTEO') {
                $descuentoAplicado = null;
                $descuentoTipo = null;
                $codigoValeGenerado = $vale->generarCodigoValeCliente();
            }
            // Resto (PRODUCTO_GRATIS, DOS_POR_UNO, DESCUENTO_*), sea MISMA o PROXIMA:
            // aplicar el beneficio en ESTA venta. Los PROXIMA_COMPRA no se difieren: se
            // aplican al teclear su código (la diferencia con MISMA es solo que no se
            // auto-detectan, requieren código manual).
            else {
                $beneficio = $this->calcularDescuentoBeneficio($vale, $venta, $preciosLineaPorProducto, $cantidadesLineaPorProducto);
                $descuentoAplicado = $beneficio['monto'];
                $descuentoTipo = $beneficio['tipo'];
                $gruposUsados = (int) ($beneficio['grupos'] ?? 1);
                $codigoValeGenerado = null;
            }

            // `cantidad_productos` es el nombre histórico de la columna; ahora almacena
            // el MONTO TOTAL en S/ que disparó la activación del vale.
            $aplicado = ValeCompraAplicado::create([
                'vale_compra_id' => $vale->id,
                'venta_id' => $venta->id,
                'cliente_id' => $venta->cliente_id,
                'cantidad_productos' => $precioTotal,
                'descuento_aplicado' => $descuentoAplicado,
                'descuento_tipo' => $descuentoTipo,
                'genera_vale_futuro' => false,
                'codigo_vale_generado' => $codigoValeGenerado,
                'fecha_validez_generado' => null,
                'aplicado_por' => auth()->id(),
            ]);

            // Decrementar stock por la cantidad de grupos/usos reales aplicados.
            // DOS_POR_UNO: compra 10 con 2x1 → 5 grupos → descuenta 5 del stock.
            // PRODUCTO_GRATIS, DESCUENTO y SORTEO: siempre 1 por venta.
            $vale->decrementarStock($gruposUsados ?? 1);

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
                // fecha_validez_generado es DATE (sin hora): comparar por fecha para que
                // un código válido "hasta hoy" siga canjeable todo el día (evita que la
                // hora de now() lo invalide desde la medianoche).
                $q->whereNull('fecha_validez_generado')
                  ->orWhereDate('fecha_validez_generado', '>=', today());
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
            return $this->aplicarValeGeneradoExistente($valeGenerado, $venta, $codigo, $detallesVenta);
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

        // Verificar límite de vales distintos por venta.
        // Contar cuántos vales ya se aplicaron a esta venta (sin contar los de tipo futuro).
        if ($vale->max_vales_por_venta !== null) {
            $valesYaAplicados = ValeCompraAplicado::where('venta_id', $venta->id)
                ->where('genera_vale_futuro', false)
                ->count();
            if ($valesYaAplicados >= $vale->max_vales_por_venta) {
                Log::info("Vale {$codigo} rechazado: límite de {$vale->max_vales_por_venta} vales por venta alcanzado", [
                    'venta_id' => $venta->id,
                    'vales_aplicados' => $valesYaAplicados,
                ]);
                return false;
            }
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

        // Aplicar el vale (MISMA o PROXIMA): el beneficio se aplica en esta venta.
        // Se pasan los precios reales de línea para valorar PRODUCTO_GRATIS / DOS_POR_UNO
        // en paridad con el resumen del frontend.
        $aplicado = $this->aplicarVale(
            $vale,
            $venta,
            $this->calcularPrecioTotal($detallesVenta),
            $this->mapearPreciosLinea($detallesVenta),
            $this->mapearCantidadesLinea($detallesVenta)
        );
        return $aplicado !== null;
    }

    /**
     * Aplicar un vale generado existente (encontrado en ValeCompraAplicado).
     * Además de consumir el código, calcula el beneficio del vale original
     * (producto gratis / 2x1 / descuento) y lo registra como aplicación en la
     * venta de canje, para que el descuento quede persistido y visible (PDF/auditoría).
     */
    private function aplicarValeGeneradoExistente(
        ValeCompraAplicado $valeGenerado,
        Venta $venta,
        string $codigo,
        array $detallesVenta = []
    ): bool {

        DB::beginTransaction();

        try {
            // Marcar el código como usado
            $valeGenerado->marcarComoUsado();

            // Calcular y registrar el beneficio sobre la venta de canje.
            $vale = ValeCompra::with(['productos', 'productoGratis'])
                ->find($valeGenerado->vale_compra_id);

            if ($vale) {
                $preciosLineaPorProducto = $this->mapearPreciosLinea($detallesVenta);
                $precioTotal = $this->calcularPrecioTotal($detallesVenta);
                $beneficio = $this->calcularDescuentoBeneficio($vale, $venta, $preciosLineaPorProducto);

                ValeCompraAplicado::create([
                    'vale_compra_id' => $vale->id,
                    'venta_id' => $venta->id,
                    'cliente_id' => $venta->cliente_id,
                    'cantidad_productos' => $precioTotal,
                    'descuento_aplicado' => $beneficio['monto'],
                    'descuento_tipo' => $beneficio['tipo'],
                    'genera_vale_futuro' => false,
                    'codigo_vale_generado' => null,
                    'fecha_validez_generado' => null,
                    'aplicado_por' => auth()->id(),
                ]);
            }

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
