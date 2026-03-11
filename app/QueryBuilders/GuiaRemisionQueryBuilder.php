<?php

namespace App\QueryBuilders;

use App\Models\GuiaRemision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class GuiaRemisionQueryBuilder
{
    protected Builder $query;

    public function __construct()
    {
        $this->query = GuiaRemision::query();
    }

    /**
     * Aplicar relaciones eager loading
     */
    public function withRelations(): self
    {
        $this->query->with([
            'venta:id,serie,numero,cliente_id',
            'cliente:id,tipo_cliente,numero_documento,nombres,apellidos,razon_social,telefono',
            'motivoTraslado:id,codigo,descripcion',
            'chofer:id,dni,name,licencia',
            'almacenOrigen:id,name',
            'almacenDestino:id,name',
            'user:id,name',
            'detalles.producto.marca',
            'detalles.producto.unidadMedida',
            'detalles.unidadDerivadaInmutable',
        ]);

        return $this;
    }

    /**
     * Filtrar por venta
     */
    public function filterByVenta(?string $ventaId): self
    {
        if ($ventaId) {
            $this->query->where('venta_id', $ventaId);
        }

        return $this;
    }

    /**
     * Filtrar por cliente
     */
    public function filterByCliente(?int $clienteId): self
    {
        if ($clienteId) {
            $this->query->where('cliente_id', $clienteId);
        }

        return $this;
    }

    /**
     * Filtrar por almacén de origen
     */
    public function filterByAlmacenOrigen(?int $almacenId): self
    {
        if ($almacenId) {
            $this->query->where('almacen_origen_id', $almacenId);
        }

        return $this;
    }

    /**
     * Filtrar por almacén de destino
     */
    public function filterByAlmacenDestino(?int $almacenId): self
    {
        if ($almacenId) {
            $this->query->where('almacen_destino_id', $almacenId);
        }

        return $this;
    }

    /**
     * Filtrar por tipo de guía
     */
    public function filterByTipoGuia(?string $tipo): self
    {
        if ($tipo) {
            $this->query->where('tipo_guia', $tipo);
        }

        return $this;
    }

    /**
     * Filtrar por estado
     */
    public function filterByEstado(?string $estado): self
    {
        if ($estado) {
            $this->query->where('estado', $estado);
        }

        return $this;
    }

    /**
     * Filtrar por motivo de traslado
     */
    public function filterByMotivoTraslado(?int $motivoId): self
    {
        if ($motivoId) {
            $this->query->where('motivo_traslado_id', $motivoId);
        }

        return $this;
    }

    /**
     * Filtrar por modalidad de transporte
     */
    public function filterByModalidadTransporte(?string $modalidad): self
    {
        if ($modalidad) {
            $this->query->where('modalidad_transporte', $modalidad);
        }

        return $this;
    }

    /**
     * Filtrar por rango de fechas de emisión
     */
    public function filterByFechaEmision(?string $desde, ?string $hasta): self
    {
        if ($desde) {
            $this->query->whereDate('fecha_emision', '>=', $desde);
        }

        if ($hasta) {
            $this->query->whereDate('fecha_emision', '<=', $hasta);
        }

        return $this;
    }

    /**
     * Filtrar por rango de fechas de traslado
     */
    public function filterByFechaTraslado(?string $desde, ?string $hasta): self
    {
        if ($desde) {
            $this->query->whereDate('fecha_traslado', '>=', $desde);
        }

        if ($hasta) {
            $this->query->whereDate('fecha_traslado', '<=', $hasta);
        }

        return $this;
    }

    /**
     * Búsqueda general (serie, número, cliente, referencia)
     */
    public function search(?string $search): self
    {
        if ($search && $search !== '') {
            $this->query->where(function ($q) use ($search) {
                $q->where('serie', 'like', "%{$search}%")
                    ->orWhere('numero', 'like', "%{$search}%")
                    ->orWhere('referencia', 'like', "%{$search}%")
                    ->orWhereHas('cliente', function ($q) use ($search) {
                        $q->where('numero_documento', 'like', "%{$search}%")
                            ->orWhere('nombres', 'like', "%{$search}%")
                            ->orWhere('apellidos', 'like', "%{$search}%")
                            ->orWhere('razon_social', 'like', "%{$search}%");
                    });
            });
        }

        return $this;
    }

    /**
     * Aplicar todos los filtros desde el Request
     */
    public function applyFilters(Request $request): self
    {
        return $this
            ->filterByVenta($request->input('venta_id'))
            ->filterByCliente($request->input('cliente_id'))
            ->filterByAlmacenOrigen($request->input('almacen_origen_id'))
            ->filterByAlmacenDestino($request->input('almacen_destino_id'))
            ->filterByTipoGuia($request->input('tipo_guia'))
            ->filterByEstado($request->input('estado'))
            ->filterByMotivoTraslado($request->input('motivo_traslado_id'))
            ->filterByModalidadTransporte($request->input('modalidad_transporte'))
            ->filterByFechaEmision(
                $request->input('fecha_emision_desde'),
                $request->input('fecha_emision_hasta')
            )
            ->filterByFechaTraslado(
                $request->input('fecha_traslado_desde'),
                $request->input('fecha_traslado_hasta')
            )
            ->search($request->input('search'));
    }

    /**
     * Ordenar por fecha de creación descendente
     */
    public function orderByCreatedDesc(): self
    {
        $this->query->orderBy('created_at', 'desc');

        return $this;
    }

    /**
     * Obtener el query builder
     */
    public function getQuery(): Builder
    {
        return $this->query;
    }

    /**
     * Obtener todos los resultados
     */
    public function get()
    {
        return $this->query->get();
    }

    /**
     * Obtener resultados paginados
     */
    public function paginate(int $perPage = 50)
    {
        return $this->query->paginate($perPage);
    }

    /**
     * Obtener resultados limitados (sin paginación)
     */
    public function limit(int $limit = 100)
    {
        return $this->query->limit($limit)->get();
    }
}
