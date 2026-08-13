<?php

namespace App\Services\Implementations;

use App\DTOs\PrestamoVendedor\CrearSolicitudEfectivoDTO;
use App\DTOs\PrestamoVendedor\RechazarSolicitudDTO;
use App\Exceptions\EfectivoInsuficienteException;
use App\Exceptions\PermisoPrestamoException;
use App\Exceptions\SolicitudYaProcesadaException;
use App\Models\DistribucionEfectivoVendedor;
use App\Models\SolicitudEfectivoVendedor;
use App\Models\TransferenciaEfectivoVendedor;
use App\Models\TransaccionCaja;
use App\Models\MovimientoCaja;
use App\Models\DespliegueDePago;
use App\Services\Cajas\EfectivoDisponibleService;
use App\Services\Interfaces\PrestamoVendedorServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PrestamoVendedorService implements PrestamoVendedorServiceInterface
{
    public function __construct(
        private EfectivoDisponibleService $efectivoDisponibleService
    ) {}

    public function crearSolicitud(CrearSolicitudEfectivoDTO $dto, int|string $vendedorSolicitanteId): array
    {
        // Obtener el prestamista para verificar si es admin
        $prestamista = \App\Models\User::find($dto->vendedorPrestamistaId);
        
        // Si el prestamista es admin, NO validar efectivo disponible
        $esAdmin = $prestamista && ($prestamista->hasRole('admin') || $prestamista->hasRole('administrador'));
        
        if (!$esAdmin) {
            // Solo validar efectivo disponible si el prestamista NO es admin
            $efectivoDisponible = $this->calcularEfectivoDisponible($dto->aperturaId, $dto->vendedorPrestamistaId);

            if ($efectivoDisponible < $dto->montoSolicitado) {
                throw new EfectivoInsuficienteException($efectivoDisponible, $dto->montoSolicitado);
            }
        }

        $solicitud = SolicitudEfectivoVendedor::create([
            'apertura_cierre_caja_id' => $dto->aperturaId,
            'vendedor_solicitante_id' => $vendedorSolicitanteId,
            'vendedor_prestamista_id' => $dto->vendedorPrestamistaId,
            'monto_solicitado' => $dto->montoSolicitado,
            'motivo' => $dto->motivo,
            'estado' => 'pendiente',
        ]);

        $solicitud->load(['vendedorSolicitante', 'vendedorPrestamista']);

        return [
            'id' => $solicitud->id,
            'monto' => number_format($solicitud->monto_solicitado, 2, '.', ''),
            'motivo' => $solicitud->motivo,
            'estado' => $solicitud->estado,
            'solicitante' => $solicitud->vendedorSolicitante->name,
            'prestamista' => $solicitud->vendedorPrestamista->name,
            'fecha' => $solicitud->fecha_solicitud->toIso8601String(),
        ];
    }

    public function aprobarSolicitud(string $solicitudId, int|string $vendedorPrestamistaId, int $subCajaOrigenId, ?float $montoAprobado = null): array
    {
        return DB::transaction(function () use ($solicitudId, $vendedorPrestamistaId, $subCajaOrigenId, $montoAprobado) {
            $solicitud = SolicitudEfectivoVendedor::with([
                'vendedorSolicitante',
                'vendedorPrestamista',
                'aperturaCierreCaja'
            ])->lockForUpdate()->findOrFail($solicitudId);

            // Validaciones
            if (!$solicitud->estaPendiente()) {
                throw new SolicitudYaProcesadaException();
            }

            if ($vendedorPrestamistaId !== $solicitud->vendedor_prestamista_id) {
                throw new PermisoPrestamoException('No tienes permiso para aprobar esta solicitud');
            }

            // Validar que la sub-caja pertenezca al prestamista
            $subCajaOrigen = \App\Models\SubCaja::findOrFail($subCajaOrigenId);
            $apertura = $solicitud->aperturaCierreCaja;
            
            if ($subCajaOrigen->caja_principal_id !== $apertura->caja_principal_id) {
                throw new \Exception('La sub-caja seleccionada no pertenece a tu caja principal');
            }

            // Determinar monto a transferir
            $montoATransferir = $montoAprobado ?? $solicitud->monto_solicitado;
            
            if ($montoATransferir > $solicitud->monto_solicitado) {
                throw new \Exception('El monto aprobado no puede ser mayor al solicitado');
            }

            // Calcular efectivo disponible en la sub-caja origen
            $efectivoDisponible = $this->calcularEfectivoEnSubCaja($subCajaOrigenId, $vendedorPrestamistaId);

            if ($efectivoDisponible < $montoATransferir) {
                throw new EfectivoInsuficienteException($efectivoDisponible, $montoATransferir);
            }

            // Obtener Caja Chica del solicitante (destino)
            $cajaChica = \App\Models\SubCaja::where('caja_principal_id', $apertura->caja_principal_id)
                ->where('tipo_caja', 'CC')
                ->firstOrFail();

            // Actualizar solicitud
            $solicitud->update([
                'estado' => 'aprobada',
                'fecha_respuesta' => now(),
                'sub_caja_origen_id' => $subCajaOrigenId,
                'sub_caja_destino_id' => $cajaChica->id,
            ]);

            // Crear transferencia
            $transferencia = TransferenciaEfectivoVendedor::create([
                'solicitud_id' => $solicitud->id,
                'apertura_cierre_caja_id' => $solicitud->apertura_cierre_caja_id,
                'vendedor_origen_id' => $solicitud->vendedor_prestamista_id,
                'sub_caja_origen_id' => $subCajaOrigenId,
                'vendedor_destino_id' => $solicitud->vendedor_solicitante_id,
                'sub_caja_destino_id' => $cajaChica->id,
                'monto' => $montoATransferir,
                'fecha_transferencia' => now(),
            ]);

            // Registrar transacciones y movimientos
            $this->registrarTransaccionesYMovimientos($solicitud, $transferencia, $subCajaOrigen, $cajaChica);

            return [
                'transferencia_id' => $transferencia->id,
                'monto' => number_format($transferencia->monto, 2, '.', ''),
                'origen' => $solicitud->vendedorPrestamista->name,
                'destino' => $solicitud->vendedorSolicitante->name,
                'sub_caja_origen' => $subCajaOrigen->nombre,
                'sub_caja_destino' => $cajaChica->nombre,
            ];
        });
    }

    public function rechazarSolicitud(string $solicitudId, RechazarSolicitudDTO $dto, int|string $vendedorPrestamistaId): void
    {
        $solicitud = SolicitudEfectivoVendedor::findOrFail($solicitudId);

        if (!$solicitud->estaPendiente()) {
            throw new SolicitudYaProcesadaException();
        }

        if ($vendedorPrestamistaId !== $solicitud->vendedor_prestamista_id) {
            throw new PermisoPrestamoException('No tienes permiso para rechazar esta solicitud');
        }

        $solicitud->update([
            'estado' => 'rechazada',
            'fecha_respuesta' => now(),
            'comentario_respuesta' => $dto->comentario,
        ]);
    }

    public function anularSolicitud(string $solicitudId, int|string $usuarioId): void
    {
        DB::transaction(function () use ($solicitudId, $usuarioId) {
            $solicitud = SolicitudEfectivoVendedor::with(['aperturaCierreCaja', 'transferencia'])
                ->lockForUpdate()
                ->findOrFail($solicitudId);

            // Solo se puede anular un préstamo ya aprobado — pendiente/rechazada nunca
            // movieron dinero real, no hay nada que revertir.
            if (!$solicitud->estaAprobada()) {
                throw new \Exception('Solo se puede anular un préstamo aprobado.');
            }

            if (
                $usuarioId !== $solicitud->vendedor_prestamista_id
                && $usuarioId !== $solicitud->vendedor_solicitante_id
            ) {
                throw new PermisoPrestamoException('anular');
            }

            $apertura = $solicitud->aperturaCierreCaja;
            if ($apertura && $apertura->estado !== 'abierta' && $apertura->estado !== 'activa') {
                throw new \Exception('No se puede anular un préstamo de una caja ya cerrada.');
            }

            $transferencia = $solicitud->transferencia;
            if (!$transferencia) {
                throw new \Exception('No se encontró la transferencia asociada a este préstamo.');
            }

            $subCajaOrigen = \App\Models\SubCaja::findOrFail($transferencia->sub_caja_origen_id);
            $subCajaDestino = \App\Models\SubCaja::findOrFail($transferencia->sub_caja_destino_id);
            $monto = (float) $transferencia->monto;

            // 1. Eliminar transacciones de caja asociadas a la transferencia.
            TransaccionCaja::where('referencia_id', $transferencia->id)
                ->where('referencia_tipo', 'transferencia_vendedor')
                ->delete();

            // 2. Eliminar movimientos de caja asociados. MovimientoCaja no guarda
            // referencia_id/referencia_tipo para estas filas (ver
            // registrarTransaccionesYMovimientos), así que se identifican por apertura +
            // sub_caja + monto + concepto, igual que TrasladoBovedaService::anularTraslado.
            MovimientoCaja::where('apertura_cierre_id', $solicitud->apertura_cierre_caja_id)
                ->where('sub_caja_id', $subCajaOrigen->id)
                ->where('salida', $monto)
                ->where('tipo_movimiento', 'transferencia')
                ->where('concepto', 'LIKE', 'Préstamo de S/.%')
                ->delete();

            MovimientoCaja::where('apertura_cierre_id', $solicitud->apertura_cierre_caja_id)
                ->where('sub_caja_id', $subCajaDestino->id)
                ->where('ingreso', $monto)
                ->where('tipo_movimiento', 'transferencia')
                ->where('concepto', 'LIKE', 'Préstamo de S/.%')
                ->delete();

            // 3. Restaurar saldos de ambas sub-cajas (inverso de la aprobación).
            $subCajaOrigen->increment('saldo_actual', $monto);
            $subCajaDestino->decrement('saldo_actual', $monto);

            // 4. Marcar la solicitud como anulada (no se elimina, queda como registro).
            $solicitud->update([
                'estado' => 'anulada',
                'fecha_anulacion' => now(),
            ]);
        });
    }

    public function listarSolicitudesPendientes(int|string $vendedorId): array
    {
        $solicitudes = SolicitudEfectivoVendedor::with(['vendedorSolicitante', 'aperturaCierreCaja'])
            ->where('vendedor_prestamista_id', $vendedorId)
            ->where('estado', 'pendiente')
            ->orderBy('fecha_solicitud', 'desc')
            ->get();

        return $solicitudes->map(function ($solicitud) {
            return [
                'id' => $solicitud->id,
                'vendedor_solicitante' => [
                    'id' => $solicitud->vendedor_solicitante_id,
                    'name' => $solicitud->vendedorSolicitante->name,
                ],
                'monto_solicitado' => (float) $solicitud->monto_solicitado,
                'motivo' => $solicitud->motivo,
                'estado' => $solicitud->estado,
                'created_at' => $solicitud->fecha_solicitud->toIso8601String(),
            ];
        })->toArray();
    }

    public function listarTodasLasSolicitudes(int|string $vendedorId): array
    {
        $solicitudes = SolicitudEfectivoVendedor::with(['vendedorSolicitante', 'vendedorPrestamista'])
            ->where(function ($query) use ($vendedorId) {
                $query->where('vendedor_solicitante_id', $vendedorId)
                    ->orWhere('vendedor_prestamista_id', $vendedorId);
            })
            ->orderBy('fecha_solicitud', 'desc')
            ->get();

        return $solicitudes->map(function ($solicitud) {
            return [
                'id' => $solicitud->id,
                'vendedor_solicitante' => [
                    'id' => $solicitud->vendedor_solicitante_id,
                    'name' => $solicitud->vendedorSolicitante->name,
                ],
                'vendedor_prestamista' => [
                    'id' => $solicitud->vendedor_prestamista_id,
                    'name' => $solicitud->vendedorPrestamista->name,
                ],
                'monto_solicitado' => $solicitud->monto_solicitado,
                'estado' => $solicitud->estado,
                'motivo' => $solicitud->motivo,
                'created_at' => $solicitud->fecha_solicitud->toIso8601String(),
            ];
        })->toArray();
    }

    public function obtenerVendedoresConEfectivo(string $aperturaId, int|string $vendedorActualId): array
    {

        try {
            $apertura = \App\Models\AperturaCierreCaja::find($aperturaId);

            if (!$apertura) {
                throw new \Exception('Apertura de caja no encontrada');
            }

            $cajaPrincipalId = $apertura->caja_principal_id;
            
            
            // Obtener todos los vendedores con distribución de efectivo (excepto el actual)
            $distribuciones = DistribucionEfectivoVendedor::with('vendedor')
                ->where('apertura_cierre_caja_id', $aperturaId)
                ->where('user_id', '!=', $vendedorActualId)
                ->get();


            $vendedoresConEfectivo = [];

            foreach ($distribuciones as $dist) {

                // Calcular efectivo disponible en Caja Chica del vendedor
                $efectivoDisponible = $this->calcularEfectivoEnCajaChica($cajaPrincipalId, $dist->user_id);


                if ($efectivoDisponible > 0) {
                    $vendedoresConEfectivo[] = [
                        'vendedor_id' => $dist->user_id,
                        'vendedor_nombre' => $dist->vendedor->name,
                        'efectivo_inicial' => number_format($dist->monto, 2, '.', ''),
                        'efectivo_disponible' => number_format($efectivoDisponible, 2, '.', ''),
                    ];
                }
            }


            return $vendedoresConEfectivo;
        } catch (\Exception $e) {
            Log::error('❌ Error al obtener vendedores con efectivo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Calcular efectivo disponible en una sub-caja específica del vendedor.
     *
     * Es la validación que corre al APROBAR una solicitud, y tiene que dar el
     * MISMO número que obtenerVendedoresConEfectivo() le mostró al usuario al
     * listar los prestamistas. Antes duplicaba el cálculo con una copia vieja
     * que se quedó sin las tres correcciones que sí tiene
     * calcularEfectivoEnCajaChica():
     *
     *   1. La apertura se buscaba sin filtrar por `user_id`, así que `->first()`
     *      podía devolver la de OTRO vendedor y el monto de distribución salía
     *      de una sesión ajena (normalmente 0).
     *   2. Las transacciones no se acotaban a `fecha_apertura`: sumaba el
     *      historial COMPLETO del vendedor en la sub-caja, de todas las sesiones
     *      ya cerradas. Como los egresos acumulados de meses superan a los
     *      ingresos, el "disponible" salía profundamente NEGATIVO
     *      (ej. -34,785.40) y toda aprobación se rechazaba.
     *   3. No exceptuaba los egresos de `movimiento_interno` que vuelven a la
     *      misma sesión del mismo vendedor, que se autocancelaban con su ingreso.
     *
     * Por eso ahora delega en calcularEfectivoEnCajaChica() en vez de duplicar:
     * una sola definición de "efectivo del vendedor desde que aperturó".
     */
    private function calcularEfectivoEnSubCaja(int $subCajaId, int|string $vendedorId): float
    {
        $subCaja = \App\Models\SubCaja::findOrFail($subCajaId);

        // Caja Chica: efectivo del vendedor en ESTA sesión (distribución de
        // apertura + ingresos − egresos de los despliegues de pago EFECTIVO).
        if ($subCaja->tipo_caja === 'CC') {
            return $this->calcularEfectivoEnCajaChica($subCaja->caja_principal_id, $vendedorId);
        }

        // Otras sub-cajas: mismo criterio de "desde que aperturó". La sub-caja ya
        // define el método de pago, así que acá no se filtra por despliegue, pero
        // el corte por sesión del vendedor sí aplica igual — sin él se arrastraba
        // el saldo de sesiones cerradas anteriores.
        $aperturaActiva = \App\Models\AperturaCierreCaja::where('caja_principal_id', $subCaja->caja_principal_id)
            ->where('user_id', $vendedorId)
            ->whereNull('fecha_cierre')
            ->first();

        if (!$aperturaActiva) {
            return 0;
        }

        $transacciones = \App\Models\TransaccionCaja::where('sub_caja_id', $subCajaId)
            ->where('user_id', $vendedorId)
            ->where('created_at', '>=', $aperturaActiva->fecha_apertura)
            ->get();

        $ingresos = $transacciones->where('tipo_transaccion', 'ingreso')->sum('monto');
        $egresos = $transacciones->where('tipo_transaccion', 'egreso')->sum('monto');

        return $ingresos - $egresos;
    }

    /**
     * Calcular efectivo disponible en Caja Chica del vendedor
     */
    private function calcularEfectivoEnCajaChica(int $cajaPrincipalId, int|string $vendedorId): float
    {
        // Buscar la Caja Chica
        $cajaChica = \App\Models\SubCaja::where('caja_principal_id', $cajaPrincipalId)
            ->where('tipo_caja', 'CC')
            ->first();

        if (!$cajaChica) {
            return 0;
        }

        // Obtener la apertura activa DEL VENDEDOR (no cualquiera). "Caja General" es
        // compartida: puede haber varias aperturas abiertas simultáneamente bajo la misma
        // caja principal (una por vendedor). Sin filtrar por user_id, ->first() podía
        // devolver la apertura de OTRO vendedor y calcular el efectivo mal.
        $aperturaActiva = \App\Models\AperturaCierreCaja::where('caja_principal_id', $cajaPrincipalId)
            ->where('user_id', $vendedorId)
            ->whereNull('fecha_cierre')
            ->first();

        if (!$aperturaActiva) {
            return 0;
        }

        // Monto inicial de distribución
        $montoInicial = DistribucionEfectivoVendedor::where('apertura_cierre_caja_id', $aperturaActiva->id)
            ->where('user_id', $vendedorId)
            ->sum('monto');

        // Obtener IDs de despliegues de pago tipo EFECTIVO de la Caja Chica
        $desplieguePagoIds = $cajaChica->despliegues_pago_ids ?? [];
        
        $desplieguePagoEfectivoIds = \App\Models\DespliegueDePago::whereIn('id', $desplieguePagoIds)
            ->whereHas('metodoDePago', function ($query) {
                $query->whereNull('cuenta_bancaria')
                      ->where(function ($q) {
                          $q->where('name', 'like', '%efectivo%')
                            ->orWhere('name', 'like', '%Efectivo%');
                      });
            })
            ->pluck('id')
            ->toArray();

        if (empty($desplieguePagoEfectivoIds)) {
            return $montoInicial;
        }

        // Calcular transacciones de efectivo (excluyendo aperturas), SOLO desde que se
        // aperturó esta sesión. Sin este filtro, se sumaban transacciones de sesiones
        // YA CERRADAS anteriores (ej. saldo_actual acumulado de días previos), inflando
        // el efectivo "disponible" muy por encima de lo que realmente hay en la sesión
        // abierta actual.
        $transacciones = \App\Models\TransaccionCaja::where('sub_caja_id', $cajaChica->id)
            ->where('user_id', $vendedorId)
            ->where(function ($query) use ($desplieguePagoEfectivoIds) {
                $query->whereIn('despliegue_pago_id', $desplieguePagoEfectivoIds)
                      ->orWhere(function ($q) {
                          $q->whereNull('despliegue_pago_id')
                            ->where('referencia_tipo', 'venta');
                      });
            })
            ->sinFilasBase()
            ->where('created_at', '>=', $aperturaActiva->fecha_apertura)
            ->get();

        // De los `movimiento_interno` solo cuenta el TRASLADO DE EFECTIVO (el que
        // lleva `destino_user_id`): ese dinero entra a la sesión abierta de un
        // vendedor y pasa a ser suyo.
        //
        // El MOVIMIENTO ENTRE CAJAS (sin `destino_user_id`) queda fuera de AMBOS
        // lados: es dinero ya CERRADO que solo cambia de cajón, nadie lo recibe en
        // mano. Mismo criterio que ClasificadorMovimientos y que
        // MovimientoInternoService::calcularSaldoMovible().
        //
        // Antes el egreso solo se perdonaba si el ingreso volvía a la MISMA sub-caja
        // y al MISMO usuario. Un movimiento 57 → 58 no cumplía esa condición, así que
        // le restaba al vendedor plata que nunca fue de su sesión y lo dejaba en
        // negativo (-18,748.30 con una apertura de 200), bloqueándole los préstamos.
        $idsTrasladoASesion = $this->efectivoDisponibleService->idsTrasladoASesion($transacciones);
        $esTrasladoASesion = fn ($t) => in_array($t->referencia_id, $idsTrasladoASesion, true);

        $ingresos = (float) $transacciones
            ->where('tipo_transaccion', 'ingreso')
            ->filter(fn ($t) => ($t->referencia_tipo ?? null) !== 'movimiento_interno'
                || $esTrasladoASesion($t))
            ->sum('monto');

        $egresos = (float) $transacciones
            ->where('tipo_transaccion', 'egreso')
            ->where('referencia_tipo', '!=', 'movimiento_interno')
            ->sum('monto');

        // Lo mandado a bóveda ya no lo tiene en mano, así que no lo puede prestar.
        // No está en el libro (el traslado no sale de la caja), se lee de su tabla.
        $boveda = $this->efectivoDisponibleService->trasladosBovedaActivos(
            $cajaChica->id,
            [$aperturaActiva->id],
            $vendedorId
        );

        return $montoInicial + $ingresos - $egresos - $boveda;
    }

    public function calcularEfectivoDisponible(string $aperturaId, int|string $vendedorId): float
    {
        $apertura = \App\Models\AperturaCierreCaja::find($aperturaId);

        if (!$apertura) {
            return 0;
        }

        $cajaPrincipalId = $apertura->caja_principal_id;

        // $vendedorId aquí es el PRESTAMISTA, no el solicitante dueño de $aperturaId.
        // "Caja General" es compartida: cada vendedor tiene su PROPIA apertura abierta
        // dentro de la misma caja principal. Antes se buscaba la distribución del
        // prestamista en la apertura del SOLICITANTE (nunca existe, porque cada quien
        // tiene la suya) y siempre daba 0 aunque el prestamista sí tuviera efectivo.
        // Hay que resolver la apertura PROPIA del prestamista primero.
        $aperturaPrestamista = \App\Models\AperturaCierreCaja::where('caja_principal_id', $cajaPrincipalId)
            ->where('user_id', $vendedorId)
            ->whereNull('fecha_cierre')
            ->first();

        if (!$aperturaPrestamista) {
            return 0;
        }

        // Verificar que el vendedor (prestamista) tenga distribución en SU apertura
        $distribucion = DistribucionEfectivoVendedor::where('apertura_cierre_caja_id', $aperturaPrestamista->id)
            ->where('user_id', $vendedorId)
            ->first();

        if (!$distribucion) {
            return 0;
        }

        // Calcular efectivo real en Caja Chica considerando todas las transacciones
        return $this->calcularEfectivoEnCajaChica($cajaPrincipalId, $vendedorId);
    }

    public function listarTransferencias(int|string $vendedorId): array
    {
        $transferencias = TransferenciaEfectivoVendedor::with([
            'vendedorOrigen',
            'vendedorDestino',
            'solicitud'
        ])
            ->where(function ($query) use ($vendedorId) {
                $query->where('vendedor_origen_id', $vendedorId)
                    ->orWhere('vendedor_destino_id', $vendedorId);
            })
            // Excluir préstamos cuya solicitud fue anulada.
            ->whereDoesntHave('solicitud', fn ($q) => $q->where('estado', 'anulada'))
            ->orderBy('created_at', 'desc')
            ->get();

        return $transferencias->map(function ($transferencia) {
            return [
                'id' => $transferencia->id,
                'vendedor_origen' => [
                    'id' => $transferencia->vendedor_origen_id,
                    'name' => $transferencia->vendedorOrigen->name,
                ],
                'vendedor_destino' => [
                    'id' => $transferencia->vendedor_destino_id,
                    'name' => $transferencia->vendedorDestino->name,
                ],
                'monto' => $transferencia->monto,
                'tipo' => 'prestamo', // Por ahora todas son préstamos
                'created_at' => $transferencia->created_at->toIso8601String(),
            ];
        })->toArray();
    }

    private function registrarTransaccionesYMovimientos(
        SolicitudEfectivoVendedor $solicitud,
        TransferenciaEfectivoVendedor $transferencia,
        \App\Models\SubCaja $subCajaOrigen,
        \App\Models\SubCaja $subCajaDestino
    ): void {
        // Resolver el método de pago EFECTIVO propio de cada sub-caja (no una búsqueda
        // genérica por nombre): puede haber varios despliegues "tipo efectivo" activos
        // en el sistema (ej. "efectivo" y "efectivo negro"), y `calcularEfectivoEnSubCaja`/
        // `calcularEfectivoEnCajaChica` solo reconocen como válido el que está en
        // `sub_caja.despliegues_pago_ids` de ESA sub-caja específica. Si acá se usaba
        // uno "genérico" que no está en esa lista, la transacción del préstamo quedaba
        // invisible para el cálculo de "efectivo disponible" del vendedor (bug real:
        // un préstamo aprobado no se reflejaba ni en Traslado a Bóveda ni en el
        // selector de "Solicitar Préstamo").
        $desplieguePagoOrigen = $this->resolverDesplieguePagoEfectivo($subCajaOrigen);
        $desplieguePagoDestino = $this->resolverDesplieguePagoEfectivo($subCajaDestino);

        $saldoAnteriorOrigen = $subCajaOrigen->saldo_actual;
        $saldoAnteriorDestino = $subCajaDestino->saldo_actual;

        // Transacción de salida en sub-caja origen (prestamista)
        TransaccionCaja::create([
            'id' => (string) Str::ulid(),
            'sub_caja_id' => $subCajaOrigen->id,
            'despliegue_pago_id' => $desplieguePagoOrigen?->id,
            'tipo_transaccion' => 'egreso',
            'monto' => $transferencia->monto,
            'saldo_anterior' => $saldoAnteriorOrigen,
            'saldo_nuevo' => $saldoAnteriorOrigen - $transferencia->monto,
            'descripcion' => "Préstamo de efectivo a {$solicitud->vendedorSolicitante->name}",
            'referencia_id' => $transferencia->id,
            'referencia_tipo' => 'transferencia_vendedor',
            'user_id' => $solicitud->vendedor_prestamista_id,
            'fecha' => now(),
        ]);

        // Transacción de entrada en Caja Chica (solicitante)
        TransaccionCaja::create([
            'id' => (string) Str::ulid(),
            'sub_caja_id' => $subCajaDestino->id,
            'despliegue_pago_id' => $desplieguePagoDestino?->id,
            'tipo_transaccion' => 'ingreso',
            'monto' => $transferencia->monto,
            'saldo_anterior' => $saldoAnteriorDestino,
            'saldo_nuevo' => $saldoAnteriorDestino + $transferencia->monto,
            'descripcion' => "Préstamo de efectivo recibido de {$solicitud->vendedorPrestamista->name}",
            'referencia_id' => $transferencia->id,
            'referencia_tipo' => 'transferencia_vendedor',
            'user_id' => $solicitud->vendedor_solicitante_id,
            'fecha' => now(),
        ]);

        // Actualizar saldos de las sub-cajas
        $subCajaOrigen->update(['saldo_actual' => $saldoAnteriorOrigen - $transferencia->monto]);
        $subCajaDestino->update(['saldo_actual' => $saldoAnteriorDestino + $transferencia->monto]);

        // Movimiento de salida (prestamista)
        MovimientoCaja::create([
            'id' => (string) Str::ulid(),
            'apertura_cierre_id' => $solicitud->apertura_cierre_caja_id,
            'caja_principal_id' => $subCajaOrigen->caja_principal_id,
            'sub_caja_id' => $subCajaOrigen->id,
            'cajero_id' => $solicitud->vendedor_prestamista_id,
            'fecha_hora' => now(),
            'tipo_movimiento' => 'transferencia',
            'concepto' => "Préstamo de S/. {$transferencia->monto} a {$solicitud->vendedorSolicitante->name}",
            'saldo_inicial' => $saldoAnteriorOrigen,
            'ingreso' => 0,
            'salida' => $transferencia->monto,
            'saldo_final' => $saldoAnteriorOrigen - $transferencia->monto,
            'estado_caja' => 'abierta',
        ]);

        // Movimiento de entrada (solicitante)
        MovimientoCaja::create([
            'id' => (string) Str::ulid(),
            'apertura_cierre_id' => $solicitud->apertura_cierre_caja_id,
            'caja_principal_id' => $subCajaDestino->caja_principal_id,
            'sub_caja_id' => $subCajaDestino->id,
            'cajero_id' => $solicitud->vendedor_solicitante_id,
            'fecha_hora' => now(),
            'tipo_movimiento' => 'transferencia',
            'concepto' => "Préstamo de S/. {$transferencia->monto} recibido de {$solicitud->vendedorPrestamista->name}",
            'saldo_inicial' => $saldoAnteriorDestino,
            'ingreso' => $transferencia->monto,
            'salida' => 0,
            'saldo_final' => $saldoAnteriorDestino + $transferencia->monto,
            'estado_caja' => 'abierta',
        ]);
    }

    /**
     * Resuelve el despliegue de pago EFECTIVO válido para una sub-caja específica —
     * es decir, el que está listado en `sub_caja.despliegues_pago_ids`. Mismo criterio
     * que usa `calcularEfectivoEnCajaChica()`/`SubCajaController::calcularEfectivoEnSubCaja()`
     * para decidir qué transacciones cuentan como "efectivo disponible" de esa sub-caja:
     * una transacción con un despliegue_pago_id que NO esté en esa lista queda invisible
     * para esos cálculos, aunque el método de pago también se llame "efectivo".
     */
    private function resolverDesplieguePagoEfectivo(\App\Models\SubCaja $subCaja): ?DespliegueDePago
    {
        $desplieguePagoIds = $subCaja->despliegues_pago_ids ?? [];

        $desplieguePago = DespliegueDePago::whereIn('id', $desplieguePagoIds)
            ->whereHas('metodoDePago', function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('cuenta_bancaria')
                      ->orWhere('cuenta_bancaria', 'SIN-CUENTA');
                })
                ->where(function ($q) {
                    $q->where('name', 'like', '%efectivo%')
                      ->orWhere('name', 'like', '%Efectivo%');
                });
            })
            ->first();

        if ($desplieguePago) {
            return $desplieguePago;
        }

        // Fallback: si la sub-caja no tiene ningún despliegue de efectivo configurado
        // (caso legacy/mal configurado), usar el mismo criterio genérico de antes en
        // vez de dejar la transacción sin método de pago.
        return DespliegueDePago::where('activo', true)
            ->where(function ($query) {
                $query->where('name', 'like', '%Efectivo%')
                      ->orWhere('name', 'like', '%efectivo%');
            })
            ->first();
    }
}
