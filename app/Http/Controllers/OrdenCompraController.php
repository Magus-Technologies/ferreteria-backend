<?php

namespace App\Http\Controllers;

use App\Http\Requests\CrearOrdenCompraRequest;
use App\Http\Resources\OrdenCompraResource;
use App\Services\Interfaces\OrdenCompraServiceInterface;
use Illuminate\Http\Request;

class OrdenCompraController extends Controller
{
    public function __construct(
        private OrdenCompraServiceInterface $service
    ) {}

    /**
     * Listar órdenes de compra con filtros
     */
    public function index(Request $request)
    {
        $filters = $request->only([
            'estado', 'proveedor_id', 'requerimiento_id', 'almacen_id',
            'desde', 'hasta', 'search', 'tipo_documento', 'forma_de_pago',
        ]);

        $perPage = $request->get('per_page', 20);
        $ordenes = $this->service->listarPaginado($filters, $perPage);

        return OrdenCompraResource::collection($ordenes);
    }

    /**
     * Crear orden de compra
     */
    public function store(CrearOrdenCompraRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        $orden = $this->service->crear($data);

        return (new OrdenCompraResource($orden))
            ->additional(['message' => "Orden de compra {$orden->codigo} creada exitosamente"])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Actualizar orden de compra
     */
    public function update(CrearOrdenCompraRequest $request, int $id)
    {
        $data = $request->validated();
        
        $orden = $this->service->actualizar($id, $data);

        return (new OrdenCompraResource($orden))
            ->additional(['message' => "Orden de compra {$orden->codigo} actualizada exitosamente"]);
    }

    /**
     * Mostrar detalle
     */
    public function show(int $id)
    {
        $orden = $this->service->obtenerPorId($id);

        return new OrdenCompraResource($orden);
    }

    /**
     * Aprobar orden de compra
     */
    public function aprobar(int $id)
    {
        $orden = $this->service->aprobar($id);

        return (new OrdenCompraResource($orden))
            ->additional(['message' => "Orden {$orden->codigo} aprobada correctamente"]);
    }

    /**
     * Anular orden de compra
     */
    public function anular(int $id)
    {
        $orden = $this->service->anular($id);

        return (new OrdenCompraResource($orden))
            ->additional(['message' => "Orden {$orden->codigo} anulada correctamente"]);
    }
}
