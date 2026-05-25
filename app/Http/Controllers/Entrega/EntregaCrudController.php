<?php

namespace App\Http\Controllers\Entrega;

use App\DTOs\Entrega\ActualizarEntregaDTO;
use App\DTOs\Entrega\CrearEntregaDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Entrega\ActualizarEntregaRequest;
use App\Http\Requests\Entrega\CrearEntregaRequest;
use App\Http\Resources\Entrega\EntregaResource;
use App\Models\Entrega;
use App\Repositories\Entrega\EntregaRepository;
use App\Services\Entrega\EntregaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class EntregaCrudController extends Controller
{
    public function __construct(
        private EntregaService    $service,
        private EntregaRepository $repo,
    ) {}

    /**
     * POST /api/entregas
     */
    public function store(CrearEntregaRequest $request): EntregaResource
    {
        $entrega = $this->service->crear(CrearEntregaDTO::fromRequest($request)->toArray());

        return new EntregaResource($entrega);
    }

    /**
     * GET /api/entregas/{entrega}
     */
    public function show(int $id): EntregaResource
    {
        $entrega = $this->repo->findByIdOrFail($id);

        return new EntregaResource($entrega);
    }

    /**
     * PUT /api/entregas/{entrega}
     */
    public function update(int $id, ActualizarEntregaRequest $request): EntregaResource
    {
        $entrega    = $this->repo->findByIdOrFail($id);
        $actualizada = $this->service->actualizar($entrega, ActualizarEntregaDTO::fromRequest($request)->toArray());

        return new EntregaResource($actualizada);
    }

    /**
     * DELETE /api/entregas/{entrega}
     * Solo permite borrar entregas en estado pendiente (no afectan stock).
     */
    public function destroy(int $id): Response
    {
        $entrega = $this->repo->findByIdOrFail($id);

        abort_if($entrega->stock_aplicado, 422, 'No se puede eliminar una entrega con stock aplicado. Use anular.');

        $entrega->detalles()->delete();
        $entrega->delete();

        return response()->noContent();
    }
}
