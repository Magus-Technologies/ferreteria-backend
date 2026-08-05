<?php

namespace App\Http\Controllers\Cajas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cajas\CrearSubCajaRequest as HttpCrearSubCajaRequest;
use App\Http\Requests\Cajas\ActualizarSubCajaRequest;
use App\Http\Resources\Cajas\SubCajaResource;
use App\UseCases\CrearSubCaja\CrearSubCajaRequest;
use App\UseCases\CrearSubCaja\CrearSubCajaUseCase;
use App\Repositories\Interfaces\SubCajaRepositoryInterface;
use App\Services\Interfaces\CajaServiceInterface;
use Illuminate\Http\JsonResponse;

class SubCajaController extends Controller
{
    public function __construct(
        private CrearSubCajaUseCase $crearSubCajaUseCase,
        private SubCajaRepositoryInterface $subCajaRepository,
        private CajaServiceInterface $cajaService
    ) {}

    /**
     * Listar sub-cajas de una caja principal
     */
    public function index(int $cajaPrincipalId): JsonResponse
    {
        try {
            $subCajas = $this->subCajaRepository->findByCajaPrincipalId($cajaPrincipalId);

            return response()->json([
                'success' => true,
                'data' => SubCajaResource::collection($subCajas),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Crear una nueva sub-caja
     */
    public function store(HttpCrearSubCajaRequest $request): JsonResponse
    {
        try {
            $useCaseRequest = new CrearSubCajaRequest(
                cajaPrincipalId: $request->validated('caja_principal_id'),
                nombre: $request->validated('nombre'),
                desplieguePagoIds: $request->validated('despliegues_pago_ids'),
                tiposComprobante: $request->validated('tipos_comprobante'),
                proposito: $request->validated('proposito')
            );

            $response = $this->crearSubCajaUseCase->execute($useCaseRequest);

            return response()->json([
                'success' => $response->success,
                'message' => $response->message,
                'data' => new SubCajaResource($response->subCaja),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Obtener una sub-caja por ID
     */
    public function show(string $id): JsonResponse
    {
        try {
            $subCaja = $this->subCajaRepository->findById($id);

            if (!$subCaja) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sub-caja no encontrada',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new SubCajaResource($subCaja),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar una sub-caja
     */
    public function update(ActualizarSubCajaRequest $request, int $id): JsonResponse
    {
        try {
            $subCaja = $this->cajaService->actualizarSubCaja($id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Sub-caja actualizada exitosamente',
                'data' => new SubCajaResource($subCaja),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Eliminar una sub-caja
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $deleted = $this->cajaService->eliminarSubCaja($id);

            return response()->json([
                'success' => $deleted,
                'message' => $deleted ? 'Sub-caja eliminada exitosamente' : 'No se pudo eliminar la sub-caja',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Obtener métodos de pago disponibles para ventas
     * Retorna las sub-cajas con sus métodos de pago en formato: SubCaja/Banco/Método/Titular
     */
    public function metodosParaVentas(): JsonResponse
    {
        try {
            // Obtener TODAS las sub-cajas activas (cualquier usuario puede usarlas)
            $subCajas = \App\Models\SubCaja::where('estado', 1)->get();

            $metodos = [];

            foreach ($subCajas as $subCaja) {
                // Obtener los despliegues de pago de esta sub-caja
                $despliegues = \App\Models\DespliegueDePago::whereIn('id', $subCaja->despliegues_pago_ids)
                    ->with('metodoDePago')
                    ->where('activo', true)
                    ->where('mostrar', true)
                    ->get();

                foreach ($despliegues as $despliegue) {
                    $banco = $despliegue->metodoDePago?->name ?? 'Sin Banco';
                    $metodo = $despliegue->name;
                    $titular = $despliegue->metodoDePago?->nombre_titular ?? '';
                    $cuentaBancaria = $despliegue->metodoDePago?->cuenta_bancaria ?? null;
                    
                    // Identificar tipo basándose en el nombre y cuenta bancaria
                    $tipo = 'efectivo'; // Por defecto
                    $bancoLower = strtolower($banco);
                    $metodoLower = strtolower($metodo);
                    
                    // Si el nombre contiene "efectivo", es efectivo (verificar primero)
                    if (str_contains($bancoLower, 'efectivo') || str_contains($metodoLower, 'efectivo')) {
                        $tipo = 'efectivo';
                    }
                    // Si contiene Izipay u otros payment gateways (tarjetas) - verificar ANTES de banco
                    elseif (str_contains($bancoLower, 'izipay') || 
                              str_contains($metodoLower, 'izipay') ||
                              str_contains($bancoLower, 'culqui') ||
                              str_contains($metodoLower, 'culqui') ||
                              str_contains($bancoLower, 'visa') ||
                              str_contains($metodoLower, 'visa') ||
                              str_contains($bancoLower, 'mastercard') ||
                              str_contains($metodoLower, 'mastercard')) {
                        $tipo = 'tarjeta';
                    }
                    // Si tiene cuenta bancaria REAL (no "SIN-CUENTA"), es banco
                    elseif ($cuentaBancaria && $cuentaBancaria !== 'SIN-CUENTA') {
                        $tipo = 'banco';
                    }
                    // Si contiene nombres de bancos, es banco
                    elseif (str_contains($bancoLower, 'bcp') || 
                              str_contains($bancoLower, 'bbva') || 
                              str_contains($bancoLower, 'interbank') ||
                              str_contains($bancoLower, 'scotiabank') ||
                              str_contains($bancoLower, 'banco') ||
                              str_contains($metodoLower, 'bcp') ||
                              str_contains($metodoLower, 'bbva') ||
                              str_contains($metodoLower, 'interbank') ||
                              str_contains($metodoLower, 'scotiabank')) {
                        $tipo = 'banco';
                    }
                    // Si contiene billeteras digitales
                    elseif (str_contains($bancoLower, 'yape') || 
                              str_contains($bancoLower, 'plin') ||
                              str_contains($bancoLower, 'tunki') ||
                              str_contains($metodoLower, 'yape') ||
                              str_contains($metodoLower, 'plin') ||
                              str_contains($metodoLower, 'tunki')) {
                        $tipo = 'billetera';
                    }
                    
                    // Formato: SubCaja/Banco/Método/Titular
                    $label = $titular 
                        ? "{$subCaja->nombre}/{$banco}/{$metodo}/{$titular}"
                        : "{$subCaja->nombre}/{$banco}/{$metodo}";

                    // Crear un identificador único combinando sub_caja_id y despliegue_id
                    // Esto evita duplicados cuando múltiples sub-cajas usan el mismo despliegue
                    $uniqueValue = "{$subCaja->id}-{$despliegue->id}";
                    
                    $metodos[] = [
                        'value' => $uniqueValue, // Usar identificador único
                        'label' => $label,
                        'sub_caja_id' => $subCaja->id,
                        'despliegue_pago_id' => $despliegue->id, // Mantener el ID original
                        'sub_caja_nombre' => $subCaja->nombre,
                        'tipos_comprobante' => $subCaja->tipos_comprobante,
                        'banco' => $banco,
                        'metodo' => $metodo,
                        'titular' => $titular,
                        'tipo' => $tipo, // Agregar tipo para filtrado
                        'tipo_sobrecargo' => $despliegue->tipo_sobrecargo ?? 'ninguno',
                        'sobrecargo_porcentaje' => $despliegue->sobrecargo_porcentaje ?? 0,
                        'adicional' => $despliegue->adicional ?? 0,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $metodos,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener sub-cajas con saldo del vendedor actual
     */
    public function getConSaldoVendedor(int $cajaPrincipalId): JsonResponse
    {
        try {
            $userId = auth()->id();
            $subCajas = $this->subCajaRepository->findByCajaPrincipalId($cajaPrincipalId);
            
            $subCajasConSaldo = $subCajas->map(function ($subCaja) use ($userId) {
                // Calcular saldo del vendedor en esta sub-caja
                $saldoVendedor = $this->calcularSaldoVendedorEnSubCaja($subCaja->id, $userId);
                
                return [
                    'id' => $subCaja->id,
                    'codigo' => $subCaja->codigo,
                    'nombre' => $subCaja->nombre,
                    'tipo_caja' => $subCaja->tipo_caja,
                    'saldo_actual' => $subCaja->saldo_actual, // Saldo total
                    'saldo_vendedor' => $saldoVendedor, // Saldo del vendedor actual
                    'despliegues_pago' => $subCaja->getDesplieguePagos(),
                    'es_caja_chica' => $subCaja->esCajaChica(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $subCajasConSaldo,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener sub-cajas: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener TODAS las sub-cajas con saldo del vendedor actual (sin filtrar por caja principal)
     * Útil para vendedores que no tienen caja asignada pero interactúan con todas las sub-cajas
     */
    public function getTodasConSaldoVendedor(): JsonResponse
    {
        try {
            $userId = auth()->id();
            
            
            // Obtener TODAS las sub-cajas activas
            $subCajas = \App\Models\SubCaja::where('estado', true)->get();
            
            $subCajasConSaldo = $subCajas->map(function ($subCaja) use ($userId) {
                // Calcular saldo del vendedor en esta sub-caja
                $saldoVendedor = $this->calcularSaldoVendedorEnSubCaja($subCaja->id, $userId);
                
                // Obtener métodos de pago con sus saldos individuales
                $metodosPago = $this->obtenerMetodosPagoConSaldo($subCaja, $userId);
                
                
                return [
                    'id' => $subCaja->id,
                    'codigo' => $subCaja->codigo,
                    'nombre' => $subCaja->nombre,
                    'tipo_caja' => $subCaja->tipo_caja,
                    'caja_principal_id' => $subCaja->caja_principal_id,
                    'saldo_actual' => $subCaja->saldo_actual, // Saldo total
                    'saldo_vendedor' => $saldoVendedor, // Saldo del vendedor actual
                    'metodos_pago' => $metodosPago, // Métodos de pago con saldos individuales
                    'es_caja_chica' => $subCaja->esCajaChica(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $subCajasConSaldo,
            ]);
        } catch (\Exception $e) {
            \Log::error('❌ Error en getTodasConSaldoVendedor', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener sub-cajas: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar el efectivo disponible SEPARADO por vendedor + caja + método de efectivo.
     *
     * Cada fila representa el efectivo (apertura + ventas en efectivo − egresos) de un
     * vendedor en una Caja Chica para un método de pago de efectivo. Sirve para el
     * traslado a bóveda, donde debe figurar el efectivo de cada usuario por separado.
     *
     * Acepta ?apertura_cierre_caja_id= para limitar a la caja principal de esa apertura.
     */
    public function getEfectivoPorVendedor(): JsonResponse
    {
        try {
            // El efectivo que figura es el del USUARIO ACTUAL (no el de otros vendedores).
            $userId = auth()->id();
            $vendedor = \App\Models\User::find($userId);

            $aperturaCierreId = request()->query('apertura_cierre_caja_id');

            // Si se indica la apertura, limitar a su caja principal y acotar el efectivo
            // a lo ocurrido DESDE esa apertura.
            $apertura = $aperturaCierreId
                ? \App\Models\AperturaCierreCaja::find($aperturaCierreId)
                : null;
            $cajaPrincipalId = $apertura?->caja_principal_id;

            // TODAS las sub-cajas de efectivo del usuario (Caja Chica + las demás), no solo CC.
            $subCajas = \App\Models\SubCaja::where('estado', true)
                ->when($cajaPrincipalId, fn ($q) => $q->where('caja_principal_id', $cajaPrincipalId))
                ->get();

            $filas = [];

            foreach ($subCajas as $subCaja) {
                // Despliegues de pago de EFECTIVO de esta caja
                $desplieguesEfectivo = collect($subCaja->getDesplieguePagos())->filter(function ($despliegue) {
                    $metodo = $despliegue->metodoDePago;
                    if (!$metodo) {
                        return false;
                    }
                    $cuenta = $metodo->cuenta_bancaria;
                    $sinCuenta = empty($cuenta) || $cuenta === 'SIN-CUENTA' || $cuenta === '-';
                    // Es efectivo si el método o el despliegue se llaman "efectivo".
                    $esEfectivo = stripos($metodo->name, 'efectivo') !== false
                        || stripos($despliegue->name, 'efectivo') !== false;
                    return $sinCuenta && $esEfectivo;
                });

                if ($desplieguesEfectivo->isEmpty()) {
                    continue;
                }

                foreach ($desplieguesEfectivo as $despliegue) {
                    // Efectivo del usuario actual DESDE la apertura (distribución + ventas − egresos)
                    $saldo = $this->calcularEfectivoDesdeApertura(
                        $subCaja,
                        $userId,
                        $despliegue->id,
                        $apertura
                    );

                    // Solo mostrar lo que tenga efectivo disponible
                    if ($saldo <= 0) {
                        continue;
                    }

                    $filas[] = [
                        'vendedor_id' => $userId,
                        'vendedor_nombre' => $vendedor?->name ?? '',
                        'sub_caja_id' => $subCaja->id,
                        'sub_caja_nombre' => $subCaja->nombre,
                        'despliegue_pago_id' => $despliegue->id,
                        'metodo_nombre' => $despliegue->name,
                        'efectivo_disponible' => number_format($saldo, 2, '.', ''),
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $filas,
            ]);
        } catch (\Exception $e) {
            \Log::error('❌ Error en getEfectivoPorVendedor', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener efectivo por vendedor: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Efectivo por usuario de TODAS las aperturas abiertas: para cada usuario de
     * la apertura (encargado + vendedores distribuidos), sus despliegues de
     * EFECTIVO con el saldo acumulado desde que se aperturó. Usado como DESTINO
     * en "Traslado de Efectivo" (apuntar el dinero al efectivo de un usuario).
     */
    public function getEfectivoTodosLosUsuarios(): JsonResponse
    {
        try {
            $aperturas = \App\Models\AperturaCierreCaja::with(['user:id,name', 'distribucionesVendedores.vendedor:id,name'])
                ->where('estado', 'abierta')
                ->get();

            $filas = [];

            foreach ($aperturas as $apertura) {
                // Usuarios de la apertura: el encargado + los vendedores distribuidos
                $usuarios = collect([$apertura->user])
                    ->concat($apertura->distribucionesVendedores->map(fn ($d) => $d->vendedor))
                    ->filter()
                    ->unique('id');

                $subCajas = \App\Models\SubCaja::where('estado', true)
                    ->where('caja_principal_id', $apertura->caja_principal_id)
                    ->get();

                foreach ($usuarios as $usuario) {
                    foreach ($subCajas as $subCaja) {
                        $desplieguesEfectivo = collect($subCaja->getDesplieguePagos())->filter(function ($despliegue) {
                            $metodo = $despliegue->metodoDePago;
                            if (!$metodo) {
                                return false;
                            }
                            $cuenta = $metodo->cuenta_bancaria;
                            $sinCuenta = empty($cuenta) || $cuenta === 'SIN-CUENTA' || $cuenta === '-';
                            $esEfectivo = stripos($metodo->name, 'efectivo') !== false
                                || stripos($despliegue->name, 'efectivo') !== false;
                            return $sinCuenta && $esEfectivo;
                        });

                        foreach ($desplieguesEfectivo as $despliegue) {
                            $saldo = $this->calcularEfectivoDesdeApertura(
                                $subCaja,
                                $usuario->id,
                                $despliegue->id,
                                $apertura
                            );

                            // Incluir aunque el saldo sea 0: como DESTINO se puede
                            // depositar en un despliegue vacío.
                            $filas[] = [
                                'vendedor_id' => $usuario->id,
                                'vendedor_nombre' => $usuario->name,
                                'apertura_id' => $apertura->id,
                                'sub_caja_id' => $subCaja->id,
                                'sub_caja_nombre' => $subCaja->nombre,
                                'despliegue_pago_id' => $despliegue->id,
                                'metodo_nombre' => $despliegue->name,
                                'efectivo_disponible' => number_format(max($saldo, 0), 2, '.', ''),
                            ];
                        }
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => $filas,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener efectivo de usuarios: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Efectivo de un usuario en una sub-caja/método ACOTADO a una apertura:
     * distribución de esa apertura (si es Caja Chica) + ingresos − egresos de efectivo
     * registrados DESDE la fecha de apertura. Así solo cuenta "lo que tengo desde que aperturé".
     */
    private function calcularEfectivoDesdeApertura($subCaja, string|int $userId, string $desplieguePagoId, $apertura): float
    {
        // Monto distribuido en ESTA apertura (solo Caja Chica)
        $montoInicial = 0.0;
        if ($apertura && $subCaja->esCajaChica()) {
            $montoInicial = (float) \App\Models\DistribucionEfectivoVendedor::where('apertura_cierre_caja_id', $apertura->id)
                ->where('user_id', $userId)
                ->sum('monto');
        }

        // Transacciones del método, excluyendo las de tipo "apertura" (ya contadas arriba)
        $query = \App\Models\TransaccionCaja::where('sub_caja_id', $subCaja->id)
            ->where('user_id', $userId)
            ->where('despliegue_pago_id', $desplieguePagoId)
            ->where(function ($q) {
                $q->whereNull('referencia_tipo')
                  ->orWhere('referencia_tipo', '!=', 'apertura');
            });

        // Solo desde que se aperturó
        if ($apertura && $apertura->fecha_apertura) {
            $query->where('created_at', '>=', $apertura->fecha_apertura);
        }

        $transacciones = $query->get();
        // Ingresos: los de movimiento_interno (traslados de efectivo recibidos) SÍ cuentan
        // — es efectivo nuevo que entró a la sesión abierta (mismo criterio que
        // ClasificadorMovimientos::obtenerOtrosIngresosVendedor).
        $ingresos = (float) $transacciones->where('tipo_transaccion', 'ingreso')->sum('monto');
        // Egresos: restan todos, incluidos los de 'movimiento_interno' cuando el
        // traslado SALIÓ del control del vendedor (a otro usuario u otra sub-caja).
        // Excepción: el traslado "cerrado → sesión" (mismo usuario lo recibió de
        // vuelta en la misma sub-caja) NO debe restar, porque el ingreso ya lo suma
        // y restarlo autocancelaría ese efectivo nuevo entrando a la sesión.
        $egresos = (float) $transacciones
            ->where('tipo_transaccion', 'egreso')
            ->filter(function ($t) use ($transacciones) {
                if (($t->referencia_tipo ?? null) !== 'movimiento_interno') {
                    return true;
                }

                return !$transacciones->contains(function ($i) use ($t) {
                    return ($i->referencia_tipo ?? null) === 'movimiento_interno'
                        && $i->tipo_transaccion === 'ingreso'
                        && $i->referencia_id === $t->referencia_id
                        && $i->user_id === $t->user_id
                        && (int) $i->sub_caja_id === (int) $t->sub_caja_id;
                });
            })
            ->sum('monto');

        return $montoInicial + $ingresos - $egresos;
    }

    /**
     * Obtener todos los vendedores con efectivo disponible
     * El efectivo mostrado = el mismo que calcula el cierre de caja:
     * desde la apertura, sumando todos los despliegues de efectivo
     * (ventas, otros ingresos, préstamos recibidos) y restando los
     * egresos (gastos, compras, préstamos dados).
     */
    public function getVendedoresConEfectivo(): JsonResponse
    {
        try {
            $userId = auth()->id();

            // Solo vendedores con caja ABIERTA en este momento. Si un vendedor ya
            // cerró su caja, ese efectivo quedó reconciliado/cerrado — no es dinero
            // "en caja" disponible para prestar desde una sesión activa. Antes se
            // agregaba además cualquier usuario con transacciones históricas en
            // cajas chicas (aunque su caja ya estuviera cerrada) y se le calculaba
            // un "efectivo histórico" que seguía apareciendo en este selector mucho
            // después de haber cerrado — bug real reportado por el usuario.
            $vendedoresIds = \App\Models\AperturaCierreCaja::whereNull('fecha_cierre')
                ->where('user_id', '!=', $userId)
                ->pluck('user_id')
                ->unique()
                ->values();

            $calculadorResumen = app(\App\Services\CierreCaja\CalculadorResumenCaja::class);

            $vendedoresConEfectivo = [];

            foreach ($vendedoresIds as $vendedorId) {
                $vendedor = \App\Models\User::find($vendedorId);

                if (!$vendedor) {
                    continue;
                }

                // Apertura activa del vendedor
                $apertura = \App\Models\AperturaCierreCaja::where('user_id', $vendedorId)
                    ->whereNull('fecha_cierre')
                    ->first();

                if (!$apertura) {
                    // Por seguridad: ya venimos filtrados por apertura abierta, pero si
                    // se cerró entre el pluck() y este punto, no mostrarlo.
                    continue;
                }

                // MISMA lógica que el cierre de caja: efectivo en caja desde su apertura
                $resumen = $calculadorResumen->calcular($apertura);
                $efectivoTotal = $resumen->montoEsperado;

                // Solo incluir si tiene efectivo > 0
                if ($efectivoTotal > 0) {
                    $vendedoresConEfectivo[] = [
                        'vendedor_id' => $vendedorId,
                        'vendedor_nombre' => $vendedor->name,
                        'efectivo_disponible' => number_format($efectivoTotal, 2, '.', ''),
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $vendedoresConEfectivo,
            ]);
        } catch (\Exception $e) {
            \Log::error('❌ Error al obtener vendedores con efectivo', [
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
     * Calcular efectivo disponible en una sub-caja específica del vendedor
     * Solo considera transacciones de EFECTIVO
     */
    private function calcularEfectivoEnSubCaja(int $subCajaId, string|int $vendedorId): float
    {
        
        $subCaja = \App\Models\SubCaja::find($subCajaId);
        if (!$subCaja) {
            return 0;
        }
        
        $montoInicial = 0;

        // Obtener la apertura activa de ESTE VENDEDOR en la caja principal
        // (acota el saldo DESDE su apertura; cada vendedor tiene su propia apertura)
        $aperturaActiva = \App\Models\AperturaCierreCaja::where('caja_principal_id', $subCaja->caja_principal_id)
            ->where('user_id', $vendedorId)
            ->whereNull('fecha_cierre')
            ->first();

        // Solo si es Caja Chica, considerar la distribución inicial de efectivo
        if ($subCaja->tipo_caja === 'CC' && $aperturaActiva) {
            // Sumar solo las distribuciones de efectivo del vendedor
            $distribuciones = \App\Models\DistribucionEfectivoVendedor::where('apertura_cierre_caja_id', $aperturaActiva->id)
                ->where('user_id', $vendedorId)
                ->get();

            $montoInicial = $distribuciones->sum('monto');
        }
        
        // Obtener IDs de despliegues de pago tipo EFECTIVO de esta sub-caja
        $desplieguePagoIds = $subCaja->despliegues_pago_ids ?? [];
        
        
        // Filtrar solo los que son efectivo
        // Efectivo = sin cuenta bancaria (NULL o "SIN-CUENTA") Y nombre contiene "efectivo"
        $desplieguePagoEfectivoIds = \App\Models\DespliegueDePago::whereIn('id', $desplieguePagoIds)
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
            ->pluck('id')
            ->toArray();
        
        
        // Si no hay métodos de efectivo en esta sub-caja, retornar solo el monto inicial
        if (empty($desplieguePagoEfectivoIds)) {
            return $montoInicial;
        }
        
        // Calcular transacciones de efectivo
        // IMPORTANTE: EXCLUIR transacciones de tipo "apertura" para evitar duplicar las distribuciones
        // Solo considera transacciones DESDE la apertura activa de la caja
        $transacciones = \App\Models\TransaccionCaja::where('sub_caja_id', $subCajaId)
            ->where('user_id', $vendedorId)
            ->whereIn('despliegue_pago_id', $desplieguePagoEfectivoIds)
            ->where(function ($query) {
                $query->whereNull('referencia_tipo')
                      ->orWhere('referencia_tipo', '!=', 'apertura');
            })
            ->when($aperturaActiva, fn ($q) => $q->where('created_at', '>=', $aperturaActiva->fecha_apertura))
            ->get();
        
        
        $ingresos = $transacciones->where('tipo_transaccion', 'ingreso')->sum('monto');
        // Egresos: restan todos, incluidos los de 'movimiento_interno' cuando el
        // traslado SALIÓ del control del vendedor (a otro usuario u otra sub-caja).
        // Excepción: el traslado "cerrado → sesión" (mismo usuario lo recibió de
        // vuelta en la misma sub-caja) NO debe restar, porque el ingreso ya lo suma.
        $egresos = $transacciones
            ->where('tipo_transaccion', 'egreso')
            ->filter(function ($t) use ($transacciones) {
                if (($t->referencia_tipo ?? null) !== 'movimiento_interno') {
                    return true;
                }

                return !$transacciones->contains(function ($i) use ($t) {
                    return ($i->referencia_tipo ?? null) === 'movimiento_interno'
                        && $i->tipo_transaccion === 'ingreso'
                        && $i->referencia_id === $t->referencia_id
                        && $i->user_id === $t->user_id
                        && (int) $i->sub_caja_id === (int) $t->sub_caja_id;
                });
            })
            ->sum('monto');

        $saldoFinal = $montoInicial + $ingresos - $egresos;
        
        
        return $saldoFinal;
    }

    /**
     * Calcular saldo del vendedor en una sub-caja
     * Considera TODAS las transacciones del vendedor en esta sub-caja
     */
    private function calcularSaldoVendedorEnSubCaja(int $subCajaId, string|int $userId): string
    {
        // Obtener la sub-caja
        $subCaja = \App\Models\SubCaja::find($subCajaId);
        if (!$subCaja) {
            return '0.00';
        }
        
        // Apertura activa de ESTE VENDEDOR en la caja principal:
        // acota el saldo DESDE su apertura HASTA el cierre.
        $aperturaActiva = \App\Models\AperturaCierreCaja::where('caja_principal_id', $subCaja->caja_principal_id)
            ->where('user_id', $userId)
            ->whereNull('fecha_cierre')
            ->first();

        $montoInicial = 0;

        // Solo si es Caja Chica, considerar la distribución inicial de efectivo de esta apertura
        if ($subCaja->esCajaChica() && $aperturaActiva) {
            $distribuciones = \App\Models\DistribucionEfectivoVendedor::where('apertura_cierre_caja_id', $aperturaActiva->id)
                ->where('user_id', $userId)
                ->get();

            $montoInicial = $distribuciones->sum('monto');
        }

        // Obtener IDs de despliegues de pago de esta sub-caja
        $desplieguePagoIds = $subCaja->despliegues_pago_ids ?? [];

        // Si no hay métodos de pago en esta sub-caja, retornar solo el monto inicial
        if (empty($desplieguePagoIds)) {
            return number_format($montoInicial, 2, '.', '');
        }

        // Calcular las transacciones del vendedor en esta sub-caja, DESDE la apertura.
        // EXCLUIR transacciones de tipo "apertura" para evitar duplicar las distribuciones
        $transacciones = \App\Models\TransaccionCaja::where('sub_caja_id', $subCajaId)
            ->where('user_id', $userId)
            ->whereIn('despliegue_pago_id', $desplieguePagoIds)
            ->where(function ($query) {
                $query->whereNull('referencia_tipo')
                      ->orWhere('referencia_tipo', '!=', 'apertura');
            })
            ->when($aperturaActiva, fn ($q) => $q->where('created_at', '>=', $aperturaActiva->fecha_apertura))
            ->get();
        
        $ingresos = $transacciones->where('tipo_transaccion', 'ingreso')->sum('monto');
        $egresos = $transacciones->where('tipo_transaccion', 'egreso')->sum('monto');
        
        // Saldo = Monto inicial (solo en Caja Chica) + Ingresos - Egresos
        $saldo = $montoInicial + $ingresos - $egresos;
        
        return number_format($saldo, 2, '.', '');
    }

    /**
     * Obtener TODAS las sub-cajas con saldo EN EFECTIVO del vendedor actual
     * Similar a getTodasConSaldoVendedor pero solo considera efectivo
     */
    public function getTodasConSaldoEfectivo(): JsonResponse
    {
        try {
            $userId = auth()->id();
            
            
            // Obtener TODAS las sub-cajas activas
            $subCajas = \App\Models\SubCaja::where('estado', true)->get();
            
            
            $subCajasConSaldo = $subCajas->map(function ($subCaja) use ($userId) {
                // Calcular saldo EN EFECTIVO del vendedor en esta sub-caja
                $saldoEfectivo = $this->calcularEfectivoEnSubCaja($subCaja->id, $userId);
                
                
                return [
                    'id' => $subCaja->id,
                    'codigo' => $subCaja->codigo,
                    'nombre' => $subCaja->nombre,
                    'tipo_caja' => $subCaja->tipo_caja,
                    'caja_principal_id' => $subCaja->caja_principal_id,
                    'saldo_efectivo' => number_format($saldoEfectivo, 2, '.', ''), // Solo efectivo
                    'es_caja_chica' => $subCaja->esCajaChica(),
                ];
            })->filter(function ($subCaja) {
                // Filtrar solo las que tienen efectivo > 0
                $tiene = floatval($subCaja['saldo_efectivo']) > 0;
                return $tiene;
            })->values();


            return response()->json([
                'success' => true,
                'data' => $subCajasConSaldo,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error en getTodasConSaldoEfectivo', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener sub-cajas: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener métodos de pago de una sub-caja con sus saldos individuales para el vendedor
     */
    private function obtenerMetodosPagoConSaldo($subCaja, string|int $userId): array
    {
        $desplieguePagos = $subCaja->getDesplieguePagos();
        $metodosPago = [];
        
        foreach ($desplieguePagos as $despliegue) {
            // Calcular saldo del vendedor para este método de pago específico
            $saldo = $this->calcularSaldoVendedorPorMetodoPago($subCaja->id, $userId, $despliegue->id);
            
            $metodosPago[] = [
                'despliegue_pago_id' => $despliegue->id,
                'nombre' => $despliegue->name,
                'metodo_de_pago_id' => $despliegue->metodo_de_pago_id,
                'metodo_de_pago_nombre' => $despliegue->metodoDePago?->name ?? 'Sin nombre',
                'cuenta_bancaria' => $despliegue->metodoDePago?->cuenta_bancaria ?? null,
                'nombre_titular' => $despliegue->metodoDePago?->nombre_titular ?? null,
                'saldo_vendedor' => $saldo,
            ];
        }
        
        return $metodosPago;
    }
    
    /**
     * Calcular saldo del vendedor para un método de pago específico en una sub-caja
     */
    private function calcularSaldoVendedorPorMetodoPago(int $subCajaId, string|int $userId, string $desplieguePagoId): string
    {
        $subCaja = \App\Models\SubCaja::find($subCajaId);
        if (!$subCaja) {
            return '0.00';
        }
        
        // Apertura activa de ESTE VENDEDOR en la caja principal:
        // acota el saldo DESDE su apertura HASTA el cierre.
        $aperturaActiva = \App\Models\AperturaCierreCaja::where('caja_principal_id', $subCaja->caja_principal_id)
            ->where('user_id', $userId)
            ->whereNull('fecha_cierre')
            ->first();

        $montoInicial = 0;

        // Solo si es Caja Chica Y el método es efectivo, considerar la distribución inicial
        if ($subCaja->esCajaChica() && $aperturaActiva) {
            $despliegue = \App\Models\DespliegueDePago::find($desplieguePagoId);
            if ($despliegue && $despliegue->metodoDePago) {
                $esEfectivo = (empty($despliegue->metodoDePago->cuenta_bancaria) ||
                              $despliegue->metodoDePago->cuenta_bancaria === 'SIN-CUENTA') &&
                             (stripos($despliegue->metodoDePago->name, 'efectivo') !== false);

                if ($esEfectivo) {
                    $distribuciones = \App\Models\DistribucionEfectivoVendedor::where('apertura_cierre_caja_id', $aperturaActiva->id)
                        ->where('user_id', $userId)
                        ->get();

                    $montoInicial = $distribuciones->sum('monto');
                }
            }
        }

        // Transacciones de este método, DESDE la apertura. EXCLUIR las de tipo "apertura".
        $transacciones = \App\Models\TransaccionCaja::where('sub_caja_id', $subCajaId)
            ->where('user_id', $userId)
            ->where('despliegue_pago_id', $desplieguePagoId)
            ->where(function ($query) {
                $query->whereNull('referencia_tipo')
                      ->orWhere('referencia_tipo', '!=', 'apertura');
            })
            ->when($aperturaActiva, fn ($q) => $q->where('created_at', '>=', $aperturaActiva->fecha_apertura))
            ->get();
        
        $ingresos = $transacciones->where('tipo_transaccion', 'ingreso')->sum('monto');
        $egresos = $transacciones->where('tipo_transaccion', 'egreso')->sum('monto');
        
        // Saldo = Monto inicial (solo efectivo en Caja Chica) + Ingresos - Egresos
        $saldo = $montoInicial + $ingresos - $egresos;
        
        return number_format($saldo, 2, '.', '');
    }

    /**
     * Buscar o crear sub-caja por despliegue de pago
     * Este método permite obtener la sub-caja correcta cuando solo se tiene el ID del despliegue de pago
     */
    public function buscarPorDesplieguePago(string $desplieguePagoId): JsonResponse
    {
        try {
            // Buscar el despliegue de pago
            $desplieguePago = \App\Models\DespliegueDePago::with('metodoDePago')->find($desplieguePagoId);
            
            if (!$desplieguePago) {
                return response()->json([
                    'success' => false,
                    'message' => 'Método de pago no encontrado',
                ], 404);
            }

            // Buscar sub-cajas que contengan este despliegue de pago
            $subCajas = \App\Models\SubCaja::whereJsonContains('despliegues_pago_ids', $desplieguePagoId)
                ->where('estado', 1)
                ->with(['cajaPrincipal.user'])
                ->get();

            if ($subCajas->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay sub-cajas configuradas para este método de pago',
                    'data' => null,
                ], 404);
            }

            // Si hay múltiples sub-cajas, retornar la primera activa
            $subCaja = $subCajas->first();

            return response()->json([
                'success' => true,
                'data' => new SubCajaResource($subCaja),
                'despliegue_pago' => [
                    'id' => $desplieguePago->id,
                    'name' => $desplieguePago->name,
                    'metodo_de_pago' => $desplieguePago->metodoDePago ? [
                        'id' => $desplieguePago->metodoDePago->id,
                        'name' => $desplieguePago->metodoDePago->name,
                        'cuenta_bancaria' => $desplieguePago->metodoDePago->cuenta_bancaria,
                        'nombre_titular' => $desplieguePago->metodoDePago->nombre_titular,
                    ] : null,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
