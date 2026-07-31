<?php

namespace App\QueryFilters;

use Illuminate\Support\Facades\DB;

class GananciasQueryFilter
{
    public function __construct(private array $filtros)
    {
    }

    /**
     * Aplicar todos los filtros comunes a una query
     */
    public function apply($query, string $prefijo = 'v'): void
    {
        $this->porAlmacen($query, $prefijo);
        $this->porFechas($query, $prefijo);
        $this->porCliente($query);
        $this->porUsuario($query);
        $this->porBusqueda($query);
        $this->porProductoId($query);
        $this->porProductoServicio($query);
        $this->porMarcaId($query);
        $this->porMarca($query);
        $this->porFormaPago($query, $prefijo);
        $this->porTipoDocumento($query, $prefijo);
        $this->porSerieNumero($query);
        $this->porConfirmarCaja($query);
        $this->porIncluir($query);
    }

    /**
     * Aplicar solo filtros básicos (almacén, fechas, búsqueda)
     */
    public function applyBasic($query, string $prefijo = 'v'): void
    {
        $this->porAlmacen($query, $prefijo);
        $this->porFechas($query, $prefijo);
        $this->porBusqueda($query);
    }

    /**
     * Aplicar filtros para compras
     */
    public function applyCompras($query, string $prefijo = 'comp'): void
    {
        $this->porAlmacen($query, $prefijo);
        $this->porFechas($query, $prefijo);
    }

    /**
     * Aplicar filtros para pagos de compras
     */
    public function applyPagosCompras($query, string $prefijoCompra = 'c', string $prefijoPago = 'p'): void
    {
        if (!empty($this->filtros['almacen_id'])) {
            $query->where("{$prefijoCompra}.almacen_id", $this->filtros['almacen_id']);
        }

        if (!empty($this->filtros['desde'])) {
            $query->whereDate("{$prefijoPago}.fecha", '>=', $this->filtros['desde']);
        }

        if (!empty($this->filtros['hasta'])) {
            $query->whereDate("{$prefijoPago}.fecha", '<=', $this->filtros['hasta']);
        }

        if (!empty($this->filtros['search'])) {
            $search = $this->filtros['search'];
            $query->where(function ($q) use ($search, $prefijoCompra, $prefijoPago) {
                $q->where('prov.razon_social', 'like', "%{$search}%")
                    ->orWhere("{$prefijoCompra}.serie", 'like', "%{$search}%")
                    ->orWhere("{$prefijoCompra}.numero", 'like', "%{$search}%")
                    ->orWhere("{$prefijoPago}.numero_operacion", 'like', "%{$search}%");
            });
        }
    }

    /**
     * Aplicar filtros para gastos extras
     */
    public function applyGastosExtras($query, string $prefijo = 'ge'): void
    {
        if (!empty($this->filtros['desde'])) {
            $query->whereDate("{$prefijo}.created_at", '>=', $this->filtros['desde']);
        }

        if (!empty($this->filtros['hasta'])) {
            $query->whereDate("{$prefijo}.created_at", '<=', $this->filtros['hasta']);
        }

        if (!empty($this->filtros['search'])) {
            $search = $this->filtros['search'];
            $query->where(function ($q) use ($search, $prefijo) {
                $q->where("{$prefijo}.concepto", 'like', "%{$search}%");
            });
        }
    }

    /**
     * Aplicar filtros para gastos de compras
     */
    public function applyGastosCompras($query): void
    {
        if (!empty($this->filtros['almacen_id'])) {
            $query->where('c.almacen_id', $this->filtros['almacen_id']);
        }

        if (!empty($this->filtros['desde'])) {
            $query->whereDate('ge.created_at', '>=', $this->filtros['desde']);
        }

        if (!empty($this->filtros['hasta'])) {
            $query->whereDate('ge.created_at', '<=', $this->filtros['hasta']);
        }

        if (!empty($this->filtros['search'])) {
            $search = $this->filtros['search'];
            $query->where(function ($q) use ($search) {
                $q->where('prov.razon_social', 'like', "%{$search}%")
                    ->orWhere('c.serie', 'like', "%{$search}%")
                    ->orWhere('c.numero', 'like', "%{$search}%")
                    ->orWhere('ge.concepto', 'like', "%{$search}%");
            });
        }
    }

    /**
     * Aplicar filtros para comisiones de vendedores
     */
    public function applyComisiones($query, string $prefijo = 'cp'): void
    {
        if (!empty($this->filtros['desde'])) {
            $query->whereDate("{$prefijo}.fecha_pago", '>=', $this->filtros['desde']);
        }

        if (!empty($this->filtros['hasta'])) {
            $query->whereDate("{$prefijo}.fecha_pago", '<=', $this->filtros['hasta']);
        }

        if (!empty($this->filtros['search'])) {
            $search = $this->filtros['search'];
            $query->where(function ($q) use ($search) {
                $q->where('u.name', 'like', "%{$search}%")
                    ->orWhere('cp.observacion', 'like', "%{$search}%");
            });
        }
    }

    /**
     * Aplicar filtros para pérdidas (ventas y salidas)
     */
    public function applyPerdidas($query, string $tipo = 'venta'): void
    {
        if ($tipo === 'venta') {
            if (!empty($this->filtros['almacen_id'])) {
                $query->where('v.almacen_id', $this->filtros['almacen_id']);
            }

            if (!empty($this->filtros['desde'])) {
                $query->whereDate('v.fecha', '>=', $this->filtros['desde']);
            }

            if (!empty($this->filtros['hasta'])) {
                $query->whereDate('v.fecha', '<=', $this->filtros['hasta']);
            }

            if (!empty($this->filtros['search'])) {
                $search = $this->filtros['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('p.name', 'like', "%{$search}%")
                        ->orWhere('v.numero', 'like', "%{$search}%");
                });
            }
        } elseif ($tipo === 'salida') {
            if (!empty($this->filtros['almacen_id'])) {
                $query->where('isa.almacen_id', $this->filtros['almacen_id']);
            }

            if (!empty($this->filtros['desde'])) {
                $query->whereDate('isa.fecha', '>=', $this->filtros['desde']);
            }

            if (!empty($this->filtros['hasta'])) {
                $query->whereDate('isa.fecha', '<=', $this->filtros['hasta']);
            }

            if (!empty($this->filtros['search'])) {
                $search = $this->filtros['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('p.name', 'like', "%{$search}%")
                        ->orWhere('isa.numero', 'like', "%{$search}%")
                        ->orWhere('tis.name', 'like', "%{$search}%");
                });
            }
        } elseif ($tipo === 'nota_credito') {
            if (!empty($this->filtros['almacen_id'])) {
                $query->where('v.almacen_id', $this->filtros['almacen_id']);
            }

            if (!empty($this->filtros['desde'])) {
                $query->whereDate('nc.fecha', '>=', $this->filtros['desde']);
            }

            if (!empty($this->filtros['hasta'])) {
                $query->whereDate('nc.fecha', '<=', $this->filtros['hasta']);
            }

            if (!empty($this->filtros['search'])) {
                $search = $this->filtros['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('nc.serie', 'like', "%{$search}%")
                        ->orWhere('nc.numero', 'like', "%{$search}%")
                        ->orWhere('v.numero', 'like', "%{$search}%")
                        ->orWhere('mn.descripcion', 'like', "%{$search}%");
                });
            }
        }
    }

    private function porAlmacen($query, string $prefijo): void
    {
        if (!empty($this->filtros['almacen_id'])) {
            $query->where("{$prefijo}.almacen_id", $this->filtros['almacen_id']);
        }
    }

    private function porFechas($query, string $prefijo): void
    {
        if (!empty($this->filtros['desde'])) {
            $query->whereDate("{$prefijo}.fecha", '>=', $this->filtros['desde']);
        }

        if (!empty($this->filtros['hasta'])) {
            $query->whereDate("{$prefijo}.fecha", '<=', $this->filtros['hasta']);
        }
    }

    private function porCliente($query): void
    {
        if (!empty($this->filtros['cliente_id'])) {
            $query->where('v.cliente_id', $this->filtros['cliente_id']);
        }
    }

    private function porUsuario($query): void
    {
        if (!empty($this->filtros['user_id'])) {
            $query->where('v.user_id', $this->filtros['user_id']);
        }
    }

    private function porBusqueda($query): void
    {
        if (!empty($this->filtros['search'])) {
            $search = '%' . $this->filtros['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('c.numero_documento', 'like', $search)
                    ->orWhere('c.razon_social', 'like', $search)
                    ->orWhere(DB::raw("CONCAT(c.nombres, ' ', c.apellidos)"), 'like', $search);
            });
        }
    }

    private function porProductoId($query): void
    {
        // Filtro exacto por producto (id), usado por el SelectProductos del filtro.
        if (!empty($this->filtros['producto_id'])) {
            $query->where('pa.producto_id', $this->filtros['producto_id']);
        }
    }

    private function porProductoServicio($query): void
    {
        if (!empty($this->filtros['producto_servicio'])) {
            $query->where('p.name', 'like', '%' . $this->filtros['producto_servicio'] . '%');
        }
    }

    private function porMarcaId($query): void
    {
        // Filtro exacto por marca (id), usado por el SelectMarcas del filtro.
        if (!empty($this->filtros['marca_id'])) {
            $query->where('p.marca_id', $this->filtros['marca_id']);
        }
    }

    private function porMarca($query): void
    {
        if (!empty($this->filtros['marca'])) {
            $query->where('m.name', 'like', '%' . $this->filtros['marca'] . '%');
        }
    }

    private function porFormaPago($query, string $prefijo): void
    {
        if (!empty($this->filtros['forma_pago'])) {
            $query->where("{$prefijo}.forma_de_pago", $this->filtros['forma_pago']);
        }
    }

    private function porTipoDocumento($query, string $prefijo): void
    {
        if (!empty($this->filtros['tipo_doc'])) {
            $query->where("{$prefijo}.tipo_documento", $this->filtros['tipo_doc']);
        }
    }

    private function porSerieNumero($query): void
    {
        // Se filtra por serie y/o número de forma independiente (antes exigía ambos, así
        // que escribir solo el número no filtraba nada). El número puede ser el correlativo
        // del comprobante electrónico o el número de la venta (para notas de venta sin CE).
        if (!empty($this->filtros['serie'])) {
            $query->where('ce.serie', $this->filtros['serie']);
        }

        if (!empty($this->filtros['numero'])) {
            $numero = $this->filtros['numero'];
            $query->where(function ($q) use ($numero) {
                $q->where('ce.correlativo', $numero)
                    ->orWhere('v.numero', $numero);
            });
        }
    }

    private function porConfirmarCaja($query): void
    {
        if (!empty($this->filtros['confirmar_caja'])) {
            // Se filtra con whereExists (no join) para no multiplicar filas cuando
            // la venta tiene varios despliegues de pago. Además funciona tanto en la
            // query detallada como en la de resumen, que no incluyen el join a `dp`.
            $desplieguePagoId = $this->filtros['confirmar_caja'];
            $query->whereExists(function ($sub) use ($desplieguePagoId) {
                $sub->select(DB::raw(1))
                    ->from('desplieguedepagoventa as dpv')
                    ->whereColumn('dpv.venta_id', 'v.id')
                    ->where('dpv.despliegue_de_pago_id', $desplieguePagoId);
            });
        }
    }

    private function porIncluir($query): void
    {
        if (!empty($this->filtros['incluir'])) {
            switch ($this->filtros['incluir']) {
                case 'con_ganancia':
                    $query->whereRaw('(udiv.precio - (CASE WHEN pav.costo > 0 THEN pav.costo ELSE pa.costo END) * udiv.factor) * udiv.cantidad > 0');
                    break;
                case 'con_perdida':
                    $query->whereRaw('(udiv.precio - (CASE WHEN pav.costo > 0 THEN pav.costo ELSE pa.costo END) * udiv.factor) * udiv.cantidad < 0');
                    break;
                case 'sin_costo':
                    $query->whereRaw('(CASE WHEN pav.costo > 0 THEN pav.costo ELSE pa.costo END) = 0');
                    break;
            }
        }
    }
}
