<?php

namespace App\Http\Controllers\Cajas;

use App\DTOs\PrestamoVendedor\CrearSolicitudEfectivoDTO;
use App\DTOs\PrestamoVendedor\RechazarSolicitudDTO;
use App\Exceptions\EfectivoInsuficienteException;
use App\Exceptions\PermisoPrestamoException;
use App\Exceptions\SolicitudYaProcesadaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cajas\CrearSolicitudEfectivoRequest;
use App\Services\Interfaces\PrestamoVendedorServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrestamoVendedorController extends Controller
{
    public function __construct(
        private PrestamoVendedorServiceInterface $prestamoVendedorService
    ) {}

    /**
     * Crear solicitud de efectivo
     */
    public function crearSolicitud(CrearSolicitudEfectivoRequest $request): JsonResponse
    {
        try {
            $dto = CrearSolicitudEfectivoDTO::fromRequest($request->validated());
            $solicitud = $this->prestamoVendedorService->crearSolicitud($dto, auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'Solicitud enviada exitosamente',
                'data' => $solicitud,
            ], 201);
        } catch (EfectivoInsuficienteException $e) {
            return response()->json([
                'success' => false,
                'message' => 'El vendedor no tiene suficiente efectivo disponible',
                'data' => [
                    'efectivo_disponible' => $e->efectivoDisponible,
                    'monto_solicitado' => $e->montoSolicitado,
                ],
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear solicitud: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Aprobar solicitud de efectivo
     */
    public function aprobarSolicitud(string $id, Request $request): JsonResponse
    {
        $request->validate([
            'sub_caja_origen_id' => ['required', 'integer', 'exists:sub_cajas,id'],
            'monto_aprobado' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        try {
            $transferencia = $this->prestamoVendedorService->aprobarSolicitud(
                $id,
                auth()->id(),
                $request->sub_caja_origen_id,
                $request->monto_aprobado
            );

            return response()->json([
                'success' => true,
                'message' => 'Solicitud aprobada y efectivo transferido',
                'data' => $transferencia,
            ]);
        } catch (SolicitudYaProcesadaException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (PermisoPrestamoException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (EfectivoInsuficienteException $e) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes suficiente efectivo disponible',
                'data' => [
                    'efectivo_disponible' => $e->efectivoDisponible,
                    'monto_solicitado' => $e->montoSolicitado,
                ],
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar solicitud: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rechazar solicitud de efectivo
     */
    public function rechazarSolicitud(string $id, Request $request): JsonResponse
    {
        $request->validate([
            'comentario' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $dto = RechazarSolicitudDTO::fromRequest($request->all());
            $this->prestamoVendedorService->rechazarSolicitud($id, $dto, auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'Solicitud rechazada',
            ]);
        } catch (SolicitudYaProcesadaException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (PermisoPrestamoException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al rechazar solicitud: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar solicitudes pendientes (recibidas)
     */
    public function solicitudesPendientes(): JsonResponse
    {
        try {
            $solicitudes = $this->prestamoVendedorService->listarSolicitudesPendientes(auth()->id());

            return response()->json([
                'success' => true,
                'data' => $solicitudes,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener solicitudes: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar todas las solicitudes (propias y recibidas)
     */
    public function listarSolicitudes(): JsonResponse
    {
        try {
            $solicitudes = $this->prestamoVendedorService->listarTodasLasSolicitudes(auth()->id());

            return response()->json([
                'success' => true,
                'data' => $solicitudes,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener solicitudes: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Consultar vendedores con efectivo disponible
     */
    public function vendedoresConEfectivo(Request $request): JsonResponse
    {
        $request->validate([
            'apertura_id' => ['required', 'string'],
        ]);

        try {
            // Verificar si existe en la tabla nueva o legacy
            $aperturaExiste = \App\Models\AperturaCierreCaja::where('id', $request->apertura_id)->exists() ||
                             \App\Models\AperturaYCierreCaja::where('id', $request->apertura_id)->exists();
            
            if (!$aperturaExiste) {
                return response()->json([
                    'success' => false,
                    'message' => 'La apertura de caja no existe',
                ], 404);
            }

            $vendedores = $this->prestamoVendedorService->obtenerVendedoresConEfectivo(
                $request->apertura_id,
                auth()->id()
            );

            return response()->json([
                'success' => true,
                'data' => $vendedores,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error al obtener vendedores con efectivo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener vendedores: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar transferencias de efectivo entre vendedores
     */
    public function listarTransferencias(): JsonResponse
    {
        try {
            $transferencias = $this->prestamoVendedorService->listarTransferencias(auth()->id());

            return response()->json([
                'success' => true,
                'data' => $transferencias,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener transferencias: ' . $e->getMessage(),
            ], 500);
        }
    }
}
