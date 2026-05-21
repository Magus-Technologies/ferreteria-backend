<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActualizarEstadoRequerimientoRequest;
use App\Http\Requests\CrearRequerimientoInternoRequest;
use App\Http\Resources\RequerimientoInternoResource;
use App\Services\Interfaces\RequerimientoInternoServiceInterface;
use App\Services\Pdf\RequerimientoInternoPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use App\Mail\RequerimientoInternoMail;

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
            'estado', 'tipo_solicitud', 'cargo', 'prioridad',
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

    /**
     * Actualizar cantidad ordenada de un producto
     */
    public function actualizarCantidadOrdenada(Request $request, int $productoId)
    {
        $validated = $request->validate([
            'cantidad_ordenada' => 'required|numeric|min:0',
            'orden_compra_id' => 'nullable|integer',
            'orden_compra_codigo' => 'nullable|string|max:50',
        ]);

        $this->service->actualizarCantidadOrdenada(
            $productoId,
            $validated['cantidad_ordenada'],
            $validated['orden_compra_id'] ?? null,
            $validated['orden_compra_codigo'] ?? null
        );

        return response()->json([
            'message' => 'Cantidad ordenada actualizada exitosamente',
        ]);
    }

    /**
     * Enviar requerimiento por correo
     */
    public function enviarCorreo(Request $request, int $id, RequerimientoInternoPdfService $pdfService)
    {
        $request->validate([
            'email' => 'required|email',
            'columnas' => 'nullable|array',
            'columnas.*' => 'string'
        ]);

        $requerimiento = $this->service->obtenerPorId($id);
        $email = $request->input('email');
        $columnas = $request->input('columnas', null);

        // Generar binario PDF con las columnas especificadas
        $pdfBinario = $pdfService->generarBinario($id, $columnas);
        $fileName = "SOC-{$requerimiento->codigo}.pdf";

        try {
            Mail::to($email)->send(new RequerimientoInternoMail($requerimiento, $pdfBinario, $fileName));
            
            return response()->json([
                'success' => true,
                'message' => "El Requerimiento {$requerimiento->codigo} se ha enviado correctamente a {$email}"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Error al enviar el correo: " . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Pasar aprobación a otro cargo
     */
    public function pasarAprobacion(Request $request, int $id)
    {
        $request->validate([
            'to_cargo_id' => 'required|integer',
            'reason' => 'nullable|string',
        ]);

        $requerimiento = $this->service->obtenerPorId($id);

        DB::beginTransaction();
        try {
            $fromCargo = $requerimiento->assigned_cargo_id ?? null;

            // Registrar en historial
            \App\Models\ApprovalHistory::create([
                'requerimiento_id' => $requerimiento->id,
                'from_cargo_id' => $fromCargo,
                'to_cargo_id' => $request->input('to_cargo_id'),
                'user_id' => $request->user()->id ?? null,
                'action' => 'pasar',
                'reason' => $request->input('reason'),
            ]);

            // Actualizar assigned_cargo_id y estado
            $requerimiento->assigned_cargo_id = $request->input('to_cargo_id');
            $requerimiento->approval_state = 'en_revision';
            $requerimiento->save();

            DB::commit();

            return response()->json(['data' => $requerimiento]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al pasar aprobación', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Aprobar requerimiento (solo cargo asignado)
     */
    public function aprobar(Request $request, int $id)
    {
        $requerimiento = $this->service->obtenerPorId($id);

        // Validar que el requerimiento esté en estado pendiente o en revisión
        if (!in_array($requerimiento->approval_state, ['pendiente', 'en_revision'])) {
            return response()->json(['message' => 'El requerimiento no está en estado para ser aprobado'], 400);
        }

        // Validar permiso por cargo: comparar user.cargo con requerimiento.cargo (case-insensitive)
        $userCargo = $request->user()->cargo ?? null;
        $requerimientoCargo = $requerimiento->cargo ?? null;
        $isSupervisor = $request->user()->es_supervisor ?? false;

        // Comparación case-insensitive
        $cargoMatch = $userCargo && $requerimientoCargo && 
                      strtolower($userCargo) === strtolower($requerimientoCargo);

        // Solo el usuario con el cargo requerido o supervisor puede aprobar
        if (!$isSupervisor && !$cargoMatch) {
            return response()->json([
                'message' => 'No autorizado. Solo usuarios con cargo ' . $requerimientoCargo . ' pueden aprobar',
                'required_cargo' => $requerimientoCargo,
                'user_cargo' => $userCargo
            ], 403);
        }

        DB::beginTransaction();
        try {
            $requerimiento->approval_state = 'aprobado';
            $requerimiento->approved_by = $request->user()->id ?? null;
            $requerimiento->approved_at = now();
            $requerimiento->save();

            // Si afecta calendario y existe vehiculo_id -> crear bloqueo
            if ($requerimiento->afecta_calendario && $requerimiento->vehiculo_id) {
                \App\Models\VehiculoMantenimiento::create([
                    'vehiculo_id' => $requerimiento->vehiculo_id,
                    'tipo' => 'mantenimiento',
                    'descripcion' => 'Bloque creado por aprobación de requerimiento ' . $requerimiento->codigo,
                    'fecha_inicio' => $requerimiento->fecha_requerida ?? now(),
                    'fecha_fin' => $requerimiento->fecha_requerida ? now()->addHours(2) : now()->addHours(2),
                    'estado' => 'aprobado',
                    'created_by' => $request->user()->id ?? null,
                ]);
            }

            // Registrar en approval_history
            \App\Models\ApprovalHistory::create([
                'requerimiento_id' => $requerimiento->id,
                'from_cargo_id' => $requerimiento->assigned_cargo_id,
                'to_cargo_id' => $requerimiento->assigned_cargo_id,
                'user_id' => $request->user()->id ?? null,
                'action' => 'aprobar',
                'reason' => $request->input('reason') ?? null,
            ]);

            DB::commit();

            return (new RequerimientoInternoResource($requerimiento))
                ->additional(['message' => 'Requerimiento aprobado exitosamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al aprobar', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Rechazar requerimiento (solo cargo asignado)
     */
    public function rechazar(Request $request, int $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $requerimiento = $this->service->obtenerPorId($id);

        // Validar que el requerimiento esté en estado pendiente o en revisión
        if (!in_array($requerimiento->approval_state, ['pendiente', 'en_revision'])) {
            return response()->json(['message' => 'El requerimiento no está en estado para ser rechazado'], 400);
        }

        // Validar permiso por cargo: comparar user.cargo con requerimiento.cargo (case-insensitive)
        $userCargo = $request->user()->cargo ?? null;
        $requerimientoCargo = $requerimiento->cargo ?? null;
        $isSupervisor = $request->user()->es_supervisor ?? false;

        // Comparación case-insensitive
        $cargoMatch = $userCargo && $requerimientoCargo && 
                      strtolower($userCargo) === strtolower($requerimientoCargo);

        // Solo el usuario con el cargo requerido o supervisor puede rechazar
        if (!$isSupervisor && !$cargoMatch) {
            return response()->json([
                'message' => 'No autorizado. Solo usuarios con cargo ' . $requerimientoCargo . ' pueden rechazar',
                'required_cargo' => $requerimientoCargo,
                'user_cargo' => $userCargo
            ], 403);
        }

        DB::beginTransaction();
        try {
            $requerimiento->approval_state = 'rechazado';
            $requerimiento->save();

            // Registrar en approval_history
            \App\Models\ApprovalHistory::create([
                'requerimiento_id' => $requerimiento->id,
                'from_cargo_id' => $requerimiento->assigned_cargo_id,
                'to_cargo_id' => $requerimiento->assigned_cargo_id,
                'user_id' => $request->user()->id ?? null,
                'action' => 'rechazar',
                'reason' => $request->input('reason'),
            ]);

            DB::commit();

            return (new RequerimientoInternoResource($requerimiento))
                ->additional(['message' => 'Requerimiento rechazado']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al rechazar', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Escalar a cargo superior (pasar a superior jerárquico)
     */
    public function escalarASuperior(Request $request, int $id)
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $requerimiento = $this->service->obtenerPorId($id);

        // Validar que el requerimiento esté en estado pendiente o en revisión
        if (!in_array($requerimiento->approval_state, ['pendiente', 'en_revision'])) {
            return response()->json(['message' => 'El requerimiento no está en estado para ser escalado'], 400);
        }

        // Validar permiso por cargo: comparar user.cargo con requerimiento.cargo (case-insensitive)
        $userCargo = $request->user()->cargo ?? null;
        $requerimientoCargo = $requerimiento->cargo ?? null;
        $isSupervisor = $request->user()->es_supervisor ?? false;

        // Comparación case-insensitive
        $cargoMatch = $userCargo && $requerimientoCargo && 
                      strtolower($userCargo) === strtolower($requerimientoCargo);

        // Solo el usuario con el cargo requerido o supervisor puede escalar
        if (!$isSupervisor && !$cargoMatch) {
            return response()->json([
                'message' => 'No autorizado. Solo usuarios con cargo ' . $requerimientoCargo . ' pueden escalar',
                'required_cargo' => $requerimientoCargo,
                'user_cargo' => $userCargo
            ], 403);
        }

        // Obtener cargo superior desde catalogo_cargos
        // Buscar el cargo actual por descripción (case-insensitive)
        $cargoActual = \App\Models\CatalogoCargo::whereRaw('LOWER(descripcion) = ?', [strtolower($requerimientoCargo)])
            ->first();

        if (!$cargoActual) {
            return response()->json(['message' => 'Cargo no encontrado en el catálogo'], 400);
        }

        if (!$cargoActual->parent) {
            return response()->json(['message' => 'No existe cargo superior en el organigrama'], 400);
        }

        // Buscar el cargo superior por código
        $cargoSuperior = \App\Models\CatalogoCargo::where('codigo', $cargoActual->parent)->first();

        if (!$cargoSuperior) {
            return response()->json(['message' => 'Cargo superior no encontrado'], 400);
        }

        DB::beginTransaction();
        try {
            // Actualizar assigned_cargo_id al cargo superior
            $requerimiento->assigned_cargo_id = $cargoSuperior->id;
            $requerimiento->approval_state = 'en_revision';
            $requerimiento->save();

            // Registrar en approval_history
            \App\Models\ApprovalHistory::create([
                'requerimiento_id' => $requerimiento->id,
                'from_cargo_id' => $cargoActual->id,
                'to_cargo_id' => $cargoSuperior->id,
                'user_id' => $request->user()->id ?? null,
                'action' => 'escalar',
                'reason' => $request->input('reason') ?? 'Escalado a superior jerárquico',
            ]);

            DB::commit();

            return (new RequerimientoInternoResource($requerimiento))
                ->additional([
                    'message' => 'Requerimiento escalado a ' . $cargoSuperior->descripcion,
                    'escalado_a' => $cargoSuperior->descripcion
                ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al escalar', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener historial de aprobaciones
     */
    public function approvalHistory(int $id)
    {
        $requerimiento = $this->service->obtenerPorId($id);

        $history = \App\Models\ApprovalHistory::where('requerimiento_id', $requerimiento->id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json(['data' => $history]);
    }
}

