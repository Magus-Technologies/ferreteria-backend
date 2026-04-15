<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActualizarEstadoRequerimientoRequest;
use App\Http\Requests\CrearRequerimientoInternoRequest;
use App\Http\Resources\RequerimientoInternoResource;
use App\Services\Interfaces\RequerimientoInternoServiceInterface;
use App\Services\Pdf\RequerimientoInternoPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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
}
