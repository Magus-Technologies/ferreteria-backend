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
    
    /**
     * Enviar orden de compra por correo
     */
    public function enviarCorreo(Request $request, int $id, \App\Services\Pdf\OrdenCompraPdfService $pdfService)
    {
        $request->validate([
            'email' => 'required|email',
            'columnas' => 'nullable|array',
            'columnas.*' => 'string',
            'formato' => 'nullable|string|in:a4,ticket',
        ]);

        $orden = $this->service->obtenerPorId($id);
        $email = $request->input('email');
        $columnas = $request->input('columnas', null);
        $formato = $request->input('formato', 'a4');

        // Generar binario PDF con las columnas y formato especificados
        $pdfBinario = $pdfService->generarBinario($id, $columnas, $formato);
        $fileName = "OC-{$orden->codigo}.pdf";

        try {
            \Illuminate\Support\Facades\Mail::to($email)->send(new \App\Mail\OrdenCompraMail($orden, $pdfBinario, $fileName));
            
            return response()->json([
                'success' => true,
                'message' => "La Orden {$orden->codigo} se ha enviado correctamente a {$email}"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Error al enviar el correo: " . $e->getMessage()
            ], 500);
        }
    }
}
