<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActualizarEstadoRequerimientoRequest;
use App\Http\Requests\CrearRequerimientoInternoRequest;
use App\Http\Resources\RequerimientoInternoResource;
use App\Services\Interfaces\RequerimientoInternoServiceInterface;
use Illuminate\Http\Request;

class RequerimientoInternoController extends Controller
{
    public function __construct(
        private RequerimientoInternoServiceInterface $service
    ) {}

    /**
     * Listar requerimientos con filtros
     */
    public function index(Request $request)
    {
        $filters = $request->only([
            'estado', 'tipo_solicitud', 'area', 'prioridad',
            'desde', 'hasta', 'search',
        ]);

        $perPage = $request->get('per_page', 20);
        $requerimientos = $this->service->listarPaginado($filters, $perPage);

        return RequerimientoInternoResource::collection($requerimientos);
    }

    /**
     * Crear requerimiento
     */
    public function store(CrearRequerimientoInternoRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        $requerimiento = $this->service->crear($data);

        return (new RequerimientoInternoResource($requerimiento))
            ->additional(['message' => 'Requerimiento creado exitosamente'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Mostrar detalle
     */
    public function show(int $id)
    {
        $requerimiento = $this->service->obtenerPorId($id);

        return new RequerimientoInternoResource($requerimiento);
    }

    /**
     * Actualizar estado (aprobar, rechazar, anular)
     */
    public function updateEstado(ActualizarEstadoRequerimientoRequest $request, int $id)
    {
        $requerimiento = $this->service->cambiarEstado($id, $request->validated()['estado']);

        return (new RequerimientoInternoResource($requerimiento))
            ->additional(['message' => 'Estado actualizado a ' . $request->estado]);
    }
}
