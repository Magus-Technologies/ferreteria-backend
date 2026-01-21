<?php

namespace App\Http\Controllers\Cajas;

use App\DTOs\Prestamo\AprobarPrestamoDTO;
use App\DTOs\Prestamo\CrearPrestamoDTO;
use App\DTOs\Prestamo\RechazarPrestamoDTO;
use App\Exceptions\AperturaNoActivaException;
use App\Exceptions\PermisoPrestamoException;
use App\Exceptions\PrestamoYaProcesadoException;
use App\Exceptions\SaldoInsuficienteException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cajas\CrearPrestamoRequest;
use App\Services\Interfaces\PrestamoEntreCajasServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrestamoEntreCajasController extends Controller
{
    public function __construct(
        private PrestamoEntreCajasServiceInterface $prestamoService
    ) {}

    /**
     * Listar préstamos
     */
    public function index(): JsonResponse
    {
        $prestamos = $this->prestamoService->listarPrestamos();

        return response()->json([
            'success' => true,
            'data' => $prestamos,
        ]);
    }

    /**
     * Crear solicitud de préstamo entre cajas (requiere aprobación)
     */
    public function store(CrearPrestamoRequest $request): JsonResponse
    {
        try {
            $dto = CrearPrestamoDTO::fromRequest($request->validated());
            $prestamo = $this->prestamoService->crearSolicitud($dto);

            return response()->json([
                'success' => true,
                'message' => 'Solicitud de préstamo enviada. Esperando aprobación del dueño de la caja.',
                'data' => $prestamo,
            ], 201);
        } catch (SaldoInsuficienteException $e) {
            return response()->json([
                'success' => false,
                'message' => 'La caja origen no tiene saldo suficiente',
                'data' => [
                    'saldo_disponible' => $e->saldoDisponible,
                    'monto_solicitado' => $e->montoSolicitado,
                ],
            ], 400);
        } catch (AperturaNoActivaException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear solicitud: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Aprobar solicitud de préstamo
     */
    public function aprobar(string $id, Request $request): JsonResponse
    {
        try {
            $request->validate([
                'sub_caja_origen_id' => ['required', 'integer', 'exists:sub_cajas,id'],
            ]);

            $dto = new AprobarPrestamoDTO(
                prestamoId: $id,
                aprobadorId: auth()->id(),
                subCajaOrigenId: $request->input('sub_caja_origen_id')
            );

            $prestamo = $this->prestamoService->aprobar($dto);

            return response()->json([
                'success' => true,
                'message' => 'Préstamo aprobado y ejecutado exitosamente',
                'data' => $prestamo,
            ]);
        } catch (PermisoPrestamoException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (PrestamoYaProcesadoException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (SaldoInsuficienteException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Saldo insuficiente en la sub-caja seleccionada',
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar préstamo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rechazar solicitud de préstamo
     */
    public function rechazar(string $id, Request $request): JsonResponse
    {
        try {
            $dto = new RechazarPrestamoDTO(
                prestamoId: $id,
                rechazadorId: auth()->id(),
                motivoRechazo: $request->input('motivo_rechazo')
            );

            $prestamo = $this->prestamoService->rechazar($dto);

            return response()->json([
                'success' => true,
                'message' => 'Préstamo rechazado',
                'data' => $prestamo,
            ]);
        } catch (PermisoPrestamoException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (PrestamoYaProcesadoException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al rechazar préstamo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar préstamos pendientes de aprobación para el usuario actual
     */
    public function pendientes(): JsonResponse
    {
        $prestamos = $this->prestamoService->listarPendientes(auth()->id());

        return response()->json([
            'success' => true,
            'data' => $prestamos,
        ]);
    }

    /**
     * Devolver préstamo
     */
    public function devolver(string $id): JsonResponse
    {
        try {
            $prestamo = $this->prestamoService->devolver($id, auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'Préstamo devuelto exitosamente',
                'data' => $prestamo,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al devolver préstamo: ' . $e->getMessage(),
            ], 500);
        }
    }
}
