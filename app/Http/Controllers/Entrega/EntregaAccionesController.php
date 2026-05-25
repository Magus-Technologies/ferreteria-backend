<?php

namespace App\Http\Controllers\Entrega;

use App\DTOs\Entrega\AnularEntregaDTO;
use App\Exceptions\Entrega\TransicionInvalidaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Entrega\AnularEntregaRequest;
use App\Http\Requests\Entrega\ReasignarChoferRequest;
use App\Http\Resources\Entrega\EntregaResource;
use App\Repositories\Entrega\EntregaRepository;
use App\Services\Entrega\EntregaService;
use Illuminate\Http\Request;

class EntregaAccionesController extends Controller
{
    public function __construct(
        private EntregaService    $service,
        private EntregaRepository $repo,
    ) {}

    /**
     * POST /api/entregas/{id}/confirmar
     * Transiciona a estado "Entregado" y aplica stock.
     */
    public function confirmar(int $id, Request $request): EntregaResource
    {
        $entrega = $this->repo->findByIdOrFail($id);

        try {
            $resultado = $this->service->confirmar($entrega, $request->user()->id);
        } catch (TransicionInvalidaException $e) {
            abort(422, $e->getMessage());
        }

        return new EntregaResource($resultado);
    }

    /**
     * POST /api/entregas/{id}/en-camino
     * Transiciona a estado "En Camino" y aplica descuento de cantidad_pendiente.
     */
    public function enCamino(int $id, Request $request): EntregaResource
    {
        $entrega = $this->repo->findByIdOrFail($id);

        try {
            $resultado = $this->service->marcarEnCamino($entrega, $request->user()->id);
        } catch (TransicionInvalidaException $e) {
            abort(422, $e->getMessage());
        }

        return new EntregaResource($resultado);
    }

    /**
     * POST /api/entregas/{id}/anular
     * Cancela la entrega y revierte stock si fue aplicado.
     */
    public function anular(int $id, AnularEntregaRequest $request): EntregaResource
    {
        $entrega = $this->repo->findByIdOrFail($id);
        $dto     = AnularEntregaDTO::fromRequest($request);

        try {
            $resultado = $this->service->anular($entrega, $dto->userId, $dto->motivo);
        } catch (TransicionInvalidaException $e) {
            abort(422, $e->getMessage());
        }

        return new EntregaResource($resultado);
    }

    /**
     * POST /api/entregas/{id}/reasignar-chofer
     */
    public function reasignarChofer(int $id, ReasignarChoferRequest $request): EntregaResource
    {
        $entrega   = $this->repo->findByIdOrFail($id);
        $resultado = $this->service->reasignarChofer(
            $entrega,
            $request->validated('chofer_id'),
            $request->validated('vehiculo_id') ? (int) $request->validated('vehiculo_id') : null,
        );

        return new EntregaResource($resultado);
    }

    /**
     * POST /api/entregas/{id}/aceptar-pedido
     * Chofer acepta un pedido externo asignado desde la app móvil.
     */
    public function aceptarPedido(int $id, Request $request): EntregaResource
    {
        $entrega = $this->repo->findByIdOrFail($id);

        abort_if(
            $entrega->chofer_id !== $request->user()->id,
            403,
            'Solo el chofer asignado puede aceptar este pedido.'
        );

        $entrega->update(['aceptado_at' => now()]);

        return new EntregaResource($entrega->fresh(['tipoEntrega', 'estadoEntrega', 'detalles']));
    }
}
