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

        // Validar permiso por cargo: comparar user.cargo (codigo) con catalogo_cargos.codigo
        $cargoAsignado = null;
        if ($requerimiento->assigned_cargo_id) {
            $cargoAsignado = \App\Models\CatalogoCargo::find($requerimiento->assigned_cargo_id);
        }

        $userCargoCodigo = $request->user()->cargo ?? null;
        $isSupervisor = $request->user()->es_supervisor ?? false;

        if (!$isSupervisor && $cargoAsignado && $cargoAsignado->codigo !== $userCargoCodigo) {
            return response()->json(['message' => 'No autorizado para aprobar este requerimiento'], 403);
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

            return response()->json(['data' => $requerimiento]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al aprobar', 'error' => $e->getMessage()], 500);
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

