<?php

namespace App\Repositories\Entrega;

use App\Models\Entrega;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class EntregaRepository implements EntregaRepositoryInterface
{
    private const EAGER_DEFAULT = [
        'tipoEntrega',
        'tipoDespacho',
        'estadoEntrega',
        'quienEntrega',
        'venta:id,serie,numero,cliente_id,tipo_documento',
        'venta.cliente:id,nombres,apellidos,razon_social,telefono,numero_documento,email',
        // Direcciones con GPS — el modal Mapa de Entrega resuelve las coords desde
        // aquí cuando la entrega no tiene latitud/longitud propias (evita geocodificar).
        'venta.cliente.direcciones:id,cliente_id,tipo,direccion,latitud,longitud',
        'almacenSalida:id,name',
        'chofer:id,name',
        'vehiculo:id,name,tipo,placa',
        'entregaLegacy:id,chofer_id,vehiculo_id,fecha_programada,hora_inicio,hora_fin,direccion_entrega,referencia_entrega,latitud,longitud,observaciones',
        'entregaLegacy.despachador:id,name',
        'entregaLegacy.vehiculo:id,name,tipo,placa',
        'detalles.unidadDerivadaVenta.unidadDerivadaInmutable',
        'detalles.unidadDerivadaVenta.productoAlmacenVenta.productoAlmacen.producto',
    ];

    public function findById(int $id): ?Entrega
    {
        return Entrega::with(self::EAGER_DEFAULT)->find($id);
    }

    public function findByIdOrFail(int $id): Entrega
    {
        return Entrega::with(self::EAGER_DEFAULT)->findOrFail($id);
    }

    public function porVenta(string $ventaId): Collection
    {
        return Entrega::with(self::EAGER_DEFAULT)
            ->where('venta_id', $ventaId)
            ->orderBy('venta_entrega_secuencia')
            ->get();
    }

    public function listadoFiltrado(array $filtros): LengthAwarePaginator
    {
        $query = Entrega::with([
            'tipoEntrega',
            'estadoEntrega',
            'quienEntrega',
            'venta:id,serie,numero,cliente_id,tipo_documento',
            'venta.cliente:id,nombres,apellidos,razon_social',
            'chofer:id,name',
            'vehiculo:id,name,placa',
            'entregaLegacy:id,chofer_id,vehiculo_id,fecha_programada,hora_inicio,hora_fin,direccion_entrega,referencia_entrega,latitud,longitud,observaciones',
            'entregaLegacy.despachador:id,name',
            'entregaLegacy.vehiculo:id,name,tipo,placa',
        ]);

        // Filtro de venta
        if (! empty($filtros['venta_id'])) {
            $query->where('venta_id', $filtros['venta_id']);
        }

        // Filtro de estado
        if (! empty($filtros['estado'])) {
            $estados = is_array($filtros['estado']) ? $filtros['estado'] : [$filtros['estado']];
            $query->whereHas('estadoEntrega', fn ($q) => $q->whereIn('codigo', $estados));
        }

        // Filtro de tipo de entrega
        if (! empty($filtros['tipo_entrega'])) {
            $query->whereHas('tipoEntrega', fn ($q) => $q->where('codigo', $filtros['tipo_entrega']));
        }

        // Filtro de chofer
        if (! empty($filtros['chofer_id'])) {
            $query->where('chofer_id', $filtros['chofer_id']);
        }

        // Filtro de fechas
        if (! empty($filtros['fecha_desde'])) {
            $query->where('fecha_creacion', '>=', $filtros['fecha_desde']);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $query->where('fecha_creacion', '<=', $filtros['fecha_hasta']);
        }

        // Solo programadas
        if (! empty($filtros['solo_programadas'])) {
            $query->whereNotNull('fecha_programada');
        }

        // Búsqueda de texto (número de venta o cliente)
        if (! empty($filtros['search'])) {
            $q = $filtros['search'];
            $query->where(function ($sub) use ($q) {
                $sub->whereHas('venta', function ($v) use ($q) {
                    $v->where('serie', 'like', "%{$q}%")
                      ->orWhere('numero', 'like', "%{$q}%");
                })->orWhereHas('venta.cliente', function ($c) use ($q) {
                    $c->where('razon_social', 'like', "%{$q}%")
                      ->orWhere('nombres', 'like', "%{$q}%")
                      ->orWhere('numero_documento', 'like', "%{$q}%");
                });
            });
        }

        $perPage = $filtros['per_page'] ?? 30;

        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function listarFlat(array $filtros): Collection
    {
        $query = Entrega::with([
            'tipoEntrega',
            'tipoDespacho',
            'estadoEntrega',
            'quienEntrega',
            'venta:id,serie,numero,cliente_id,tipo_documento',
            'venta.cliente:id,nombres,apellidos,razon_social,telefono,numero_documento',
            // Direcciones con GPS para el modal Mapa de Entrega (fallback de coords).
            'venta.cliente.direcciones:id,cliente_id,tipo,direccion,latitud,longitud',
            'chofer:id,name',
            'vehiculo:id,name,placa',
            'entregaLegacy:id,chofer_id,vehiculo_id,fecha_programada,hora_inicio,hora_fin,direccion_entrega,referencia_entrega,latitud,longitud,observaciones',
            'entregaLegacy.despachador:id,name',
            'entregaLegacy.vehiculo:id,name,tipo,placa',
            'detalles.unidadDerivadaVenta.unidadDerivadaInmutable',
            'detalles.unidadDerivadaVenta.productoAlmacenVenta.productoAlmacen.producto',
        ]);

        if (! empty($filtros['estado'])) {
            $estados = is_array($filtros['estado']) ? $filtros['estado'] : [$filtros['estado']];
            $query->whereHas('estadoEntrega', fn ($q) => $q->whereIn('codigo', $estados));
        }

        if (! empty($filtros['tipo_entrega'])) {
            $query->whereHas('tipoEntrega', fn ($q) => $q->where('codigo', $filtros['tipo_entrega']));
        }

        if (! empty($filtros['chofer_id'])) {
            $choferId = $filtros['chofer_id'];
            $query->where(function ($sub) use ($choferId) {
                $sub->where('chofer_id', $choferId)
                    ->orWhereHas('entregaLegacy', fn ($legacy) => $legacy->where('chofer_id', $choferId));
            });
        }

        if (! empty($filtros['vehiculo_id'])) {
            $vehiculoId = $filtros['vehiculo_id'];
            $query->where(function ($sub) use ($vehiculoId) {
                $sub->where('vehiculo_id', $vehiculoId)
                    ->orWhereHas('entregaLegacy', fn ($legacy) => $legacy->where('vehiculo_id', $vehiculoId));
            });
        }

        $filtrarPorFechaProgramada = array_key_exists('solo_programadas', $filtros);
        $soloProgramadasActivas = ! empty($filtros['solo_programadas']);

        if ($filtrarPorFechaProgramada) {
            $query->where(function ($sub) {
                $sub->whereNotNull('fecha_programada')
                    ->orWhereHas('entregaLegacy', fn ($legacy) => $legacy->whereNotNull('fecha_programada'));
            });

            $query->whereHas('tipoDespacho', fn ($q) => $q->where('codigo', '!=', 'in'));

            if ($soloProgramadasActivas) {
                $query->whereHas('estadoEntrega', fn ($q) => $q->whereNotIn('codigo', ['en', 'ca']));
            }
        }

        if (! empty($filtros['fecha_desde']) && $filtrarPorFechaProgramada) {
            $fechaDesde = $filtros['fecha_desde'];
            $query->where(function ($sub) use ($fechaDesde) {
                $sub->whereDate('fecha_programada', '>=', $fechaDesde)
                    ->orWhereHas('entregaLegacy', fn ($legacy) => $legacy->whereDate('fecha_programada', '>=', $fechaDesde));
            });
        } elseif (! empty($filtros['fecha_desde'])) {
            $query->where('fecha_creacion', '>=', $filtros['fecha_desde']);
        }

        if (! empty($filtros['fecha_hasta']) && $filtrarPorFechaProgramada) {
            $fechaHasta = $filtros['fecha_hasta'];
            $query->where(function ($sub) use ($fechaHasta) {
                $sub->whereDate('fecha_programada', '<=', $fechaHasta)
                    ->orWhereHas('entregaLegacy', fn ($legacy) => $legacy->whereDate('fecha_programada', '<=', $fechaHasta));
            });
        } elseif (! empty($filtros['fecha_hasta'])) {
            $query->where('fecha_creacion', '<=', $filtros['fecha_hasta']);
        }

        if (! empty($filtros['search'])) {
            $q = $filtros['search'];
            $query->where(function ($sub) use ($q) {
                $sub->whereHas('venta', fn ($v) => $v->where('serie', 'like', "%{$q}%")->orWhere('numero', 'like', "%{$q}%"))
                    ->orWhereHas('venta.cliente', fn ($c) => $c->where('razon_social', 'like', "%{$q}%")->orWhere('nombres', 'like', "%{$q}%")->orWhere('numero_documento', 'like', "%{$q}%"));
            });
        }

        return $query->orderByDesc('created_at')->get();
    }
}
