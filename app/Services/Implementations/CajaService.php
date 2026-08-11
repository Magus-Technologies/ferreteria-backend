<?php

namespace App\Services\Implementations;

use App\Exceptions\CajaNoEncontradaException;
use App\Exceptions\SubCajaDuplicadaException;
use App\Models\AperturaCierreCaja;
use App\Models\CajaPrincipal;
use App\Models\SubCaja;
use App\Models\DespliegueDePago;
use App\Repositories\Interfaces\CajaPrincipalRepositoryInterface;
use App\Repositories\Interfaces\SubCajaRepositoryInterface;
use App\Services\Interfaces\CajaServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CajaService implements CajaServiceInterface
{
    public function __construct(
        private CajaPrincipalRepositoryInterface $cajaPrincipalRepository,
        private SubCajaRepositoryInterface $subCajaRepository
    ) {}

    public function crearCajaPrincipal(string $userId, string $nombre, ?string $metodoPagoId = null, ?string $nombreMetodoPago = null): CajaPrincipal
    {
        return DB::transaction(function () use ($userId, $nombre, $metodoPagoId, $nombreMetodoPago) {
            // Generar código
            $codigo = $this->cajaPrincipalRepository->generarSiguienteCodigo();

            // Crear caja principal
            $cajaPrincipal = $this->cajaPrincipalRepository->create([
                'codigo' => $codigo,
                'nombre' => $nombre,
                'user_id' => $userId,
                'estado' => 1,
            ]);

            // Crear automáticamente la Caja Chica
            $this->crearCajaChicaAutomatica($cajaPrincipal->id, $codigo, $metodoPagoId, $nombreMetodoPago);

            return $cajaPrincipal->fresh(['user', 'subCajas']);
        });
    }

    private function crearCajaChicaAutomatica(int $cajaPrincipalId, string $codigoCajaPrincipal, ?string $metodoPagoId = null, ?string $nombreMetodoPago = null): SubCaja
    {

        // Si se proporciona un nombre personalizado, siempre crear uno nuevo con ese nombre
        if ($nombreMetodoPago) {
            $desplieguePagoEfectivo = $this->crearNuevoDespliegueEfectivo($nombreMetodoPago);
        } elseif ($metodoPagoId) {
            // Si se proporciona un desplieguePagoId existente, usarlo directamente
            $desplieguePagoEfectivo = DespliegueDePago::where('id', $metodoPagoId)
                ->where('activo', true)
                ->first();

        } else {
            $desplieguePagoEfectivo = $this->encontrarDespliegueEfectivoDisponible();
        }

        // Solo crear uno nuevo autogenerado si no se obtuvo ninguno aún
        if (!$desplieguePagoEfectivo) {
            $desplieguePagoEfectivo = $this->crearNuevoDespliegueEfectivo();
        }

        if (!$desplieguePagoEfectivo) {
            Log::error('No se encontró un método de pago válido para la Caja Chica');
            throw new \Exception('No se encontró un método de pago válido para la Caja Chica');
        }

        $codigo = $codigoCajaPrincipal . '-001';

        return $this->subCajaRepository->create([
            'codigo' => $codigo,
            'nombre' => 'Caja Chica',
            'caja_principal_id' => $cajaPrincipalId,
            'tipo_caja' => 'CC',
            'despliegues_pago_ids' => [$desplieguePagoEfectivo->id],
            'tipos_comprobante' => ['01', '03'], // Facturas (01) y Boletas (03)
            'saldo_actual' => 0.00,
            'proposito' => 'Efectivo de ventas con comprobantes oficiales (Facturas y Boletas)',
            'estado' => 1,
        ]);
    }

    /**
     * Busca un despliegue de tipo efectivo que no esté ya asignado a ninguna caja chica existente.
     */
    private function encontrarDespliegueEfectivoDisponible(): ?DespliegueDePago
    {
        // IDs de despliegues ya consumidos por cajas chicas existentes
        $usados = SubCaja::where('tipo_caja', 'CC')
            ->get()
            ->flatMap(fn($sc) => $sc->despliegues_pago_ids ?? [])
            ->unique()
            ->values()
            ->toArray();

        return DespliegueDePago::where('activo', true)
            ->where('mostrar', true)
            ->where(function ($q) {
                $q->where('name', 'like', '%Efectivo%')
                  ->orWhereHas('metodoDePago', fn($mq) => $mq->where('name', 'like', '%Efectivo%'));
            })
            ->whereNotIn('id', $usados)
            ->first();
    }

    /**
     * Crea un nuevo MetodoDePago y DespliegueDePago de tipo efectivo.
     * Si se proporciona $nombre, lo usa; de lo contrario genera uno incremental.
     */
    private function crearNuevoDespliegueEfectivo(?string $nombre = null): DespliegueDePago
    {
        if ($nombre) {
            $nuevoNombre = $nombre;
        } else {
            $count = \App\Models\MetodoDePago::where('name', 'like', '%Efectivo%')->count();
            $nuevoNombre = $count > 0 ? 'Efectivo' . ($count + 1) : 'Efectivo';
        }


        $metodoPago = \App\Models\MetodoDePago::create([
            'id'           => (string) Str::ulid(),
            'name'         => $nuevoNombre,
            'cuenta_bancaria' => null,
            'monto'        => 0.00,
            'monto_inicial' => 0.00,
            'activo'       => true,
        ]);

        return DespliegueDePago::create([
            'id'               => (string) Str::ulid(),
            'name'             => $nuevoNombre,
            'metodo_de_pago_id' => $metodoPago->id,
            'adicional'        => 0.00,
            'activo'           => true,
            'mostrar'          => true,
        ]);
    }

    public function crearSubCaja(int $cajaPrincipalId, array $data): SubCaja
    {
        return DB::transaction(function () use ($cajaPrincipalId, $data) {
            $cajaPrincipal = $this->cajaPrincipalRepository->findById($cajaPrincipalId);

            if (!$cajaPrincipal) {
                throw new CajaNoEncontradaException('Caja principal no encontrada');
            }

            // NUEVO: Validar exclusividad de métodos de pago
            // Validar contra otras cajas principales y sub-cajas
            $this->subCajaRepository->validarExclusividadMetodosPago(
                $cajaPrincipalId,
                $data['despliegues_pago_ids']
            );

            // Caso específico para "*": no permitir si ya hay otras sub-cajas manuales en la MISMA caja
            if (in_array('*', $data['despliegues_pago_ids'])) {
                $subCajasExistentes = $this->subCajaRepository->findByCajaPrincipalId($cajaPrincipalId);
                
                // Filtrar solo sub-cajas manuales (tipo SC), excluir Caja Chica (tipo CC)
                $subCajasManuales = $subCajasExistentes->filter(function($subCaja) {
                    return $subCaja->tipo_caja === 'SC';
                });
                
                if ($subCajasManuales->isNotEmpty()) {
                    throw new \Exception('No se puede crear una sub-caja con TODOS los métodos de pago porque ya existen otras sub-cajas con métodos específicos en esta Caja Principal.');
                }
            }

            // Validar configuración duplicada
            if ($this->subCajaRepository->existeConfiguracionDuplicada(
                $cajaPrincipalId,
                $data['despliegues_pago_ids'],
                $data['tipos_comprobante']
            )) {
                throw new SubCajaDuplicadaException();
            }

            // Generar código
            $codigo = $this->subCajaRepository->generarSiguienteCodigo($cajaPrincipal->codigo);

            $subCaja = $this->subCajaRepository->create([
                'codigo' => $codigo,
                'nombre' => $data['nombre'],
                'caja_principal_id' => $cajaPrincipalId,
                'tipo_caja' => 'SC',
                'despliegues_pago_ids' => $data['despliegues_pago_ids'],
                'tipos_comprobante' => $data['tipos_comprobante'],
                'saldo_actual' => 0.00,
                'proposito' => $data['proposito'] ?? null,
                'estado' => 1,
            ]);

            // Registrar monto_inicial automáticamente si aplica
            $this->registrarMontoInicialSiAplica($subCaja);

            return $subCaja;
        });
    }

    /**
     * Registrar monto_inicial automáticamente cuando se crea una sub-caja digital
     * con métodos de pago que tienen monto_inicial configurado
     */
    private function registrarMontoInicialSiAplica(SubCaja $subCaja): void
    {
        // Solo para sub-cajas digitales (no Caja Chica)
        if ($subCaja->tipo_caja === 'CC') {
            return;
        }

        // Obtener los despliegues de pago de esta sub-caja
        $desplieguePagoIds = $subCaja->despliegues_pago_ids ?? [];
        
        if (empty($desplieguePagoIds)) {
            return;
        }

        // Obtener los despliegues con sus métodos de pago
        $despliegues = DespliegueDePago::with('metodoDePago')
            ->whereIn('id', $desplieguePagoIds)
            ->get();

        // Agrupar por método de pago (banco) para registrar solo una vez por banco
        $bancosConMontoInicial = [];

        foreach ($despliegues as $despliegue) {
            $metodoPago = $despliegue->metodoDePago;
            
            if (!$metodoPago || $metodoPago->monto_inicial <= 0) {
                continue;
            }

            // Verificar que sea un método digital (no efectivo)
            $esEfectivo = $this->esMetodoEfectivo($metodoPago);
            
            if ($esEfectivo) {
                continue;
            }

            // Verificar si ya se registró este banco
            if (isset($bancosConMontoInicial[$metodoPago->id])) {
                continue;
            }

            // Verificar si ya se registró el monto_inicial para este banco en CUALQUIER sub-caja de esta caja principal
            $yaRegistrado = \App\Models\TransaccionCaja::whereHas('subCaja', function($query) use ($subCaja) {
                    $query->where('caja_principal_id', $subCaja->caja_principal_id);
                })
                ->where('referencia_tipo', 'monto_inicial')
                ->where('referencia_id', $metodoPago->id)
                ->exists();

            if ($yaRegistrado) {
                continue;
            }

            // IMPORTANTE: Usar el despliegue_id del método de pago actual (del banco)
            // No usar cualquier despliegue, sino el que corresponde al banco del monto_inicial
            $bancosConMontoInicial[$metodoPago->id] = [
                'despliegue_id' => $despliegue->id, // Este es el despliegue del banco (ej: BCP/Yape, BCP/Izipay, etc)
                'monto' => $metodoPago->monto_inicial,
                'nombre_banco' => $metodoPago->name,
            ];
        }

        // Registrar las transacciones de monto_inicial
        foreach ($bancosConMontoInicial as $metodoPagoId => $info) {
            try {
                $saldoAnterior = $subCaja->saldo_actual;
                $saldoNuevo = $saldoAnterior + $info['monto'];

                // Crear transacción de ingreso
                $transaccion = \App\Models\TransaccionCaja::create([
                    'id' => (string) Str::ulid(),
                    'sub_caja_id' => $subCaja->id,
                    'user_id' => Auth::id() ?? $subCaja->cajaPrincipal->user_id,
                    'tipo_transaccion' => 'ingreso',
                    'monto' => $info['monto'],
                    'saldo_anterior' => $saldoAnterior,
                    'saldo_nuevo' => $saldoNuevo,
                    'despliegue_pago_id' => $info['despliegue_id'],
                    'descripcion' => "Monto inicial de {$info['nombre_banco']}",
                    'referencia_tipo' => 'monto_inicial',
                    'referencia_id' => $metodoPagoId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Actualizar saldo de la sub-caja
                $subCaja->saldo_actual = $saldoNuevo;
                $subCaja->save();

            } catch (\Exception $e) {
                Log::error('Error al registrar monto inicial', [
                    'sub_caja_id' => $subCaja->id,
                    'metodo_pago_id' => $metodoPagoId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Verificar si un método de pago es efectivo
     */
    /**
     * Deja el `monto_inicial` de un banco reflejado en el LIBRO (`transacciones_caja`),
     * que es de donde salen todos los saldos que muestra el sistema.
     *
     * Antes esto solo pasaba al CREAR una sub-caja que incluyera un despliegue del
     * banco (registrarMontoInicialSiAplica). Crear el banco o editarle el monto
     * guardaba las columnas `monto_inicial`/`monto` y nada más, así que el número
     * aparecía en la ficha del banco pero la sub-caja seguía en 0.00: dos
     * contabilidades paralelas que nadie conciliaba.
     *
     * Registra la DIFERENCIA contra lo ya asentado, no el monto completo, para que
     * editarlo varias veces no lo duplique:
     *   - sube  → ingreso por la diferencia
     *   - baja  → egreso por la diferencia
     *   - igual → no hace nada
     *
     * Devuelve el monto ajustado (con signo), o 0 si no había nada que hacer.
     */
    public function sincronizarMontoInicialBanco(\App\Models\MetodoDePago $metodoPago): float
    {
        // El efectivo no lleva monto inicial de banco: ese dinero entra por la
        // apertura de caja, no por la configuración del método de pago.
        if ($this->esMetodoEfectivo($metodoPago)) {
            return 0.0;
        }

        $desplieguesDelBanco = DespliegueDePago::where('metodo_de_pago_id', $metodoPago->id)
            ->pluck('id')
            ->all();

        if (empty($desplieguesDelBanco)) {
            return 0.0;
        }

        // Sub-caja donde vive este banco. Sin una sub-caja que lo acepte no hay
        // dónde asentar el dinero: se queda configurado y se registrará cuando se
        // cree una (ver registrarMontoInicialSiAplica).
        $subCaja = null;
        $desplieguePagoId = null;

        foreach (SubCaja::where('estado', true)->get() as $candidata) {
            foreach ($candidata->getDesplieguePagos() as $despliegue) {
                if (in_array($despliegue->id, $desplieguesDelBanco, true)) {
                    $subCaja = $candidata;
                    $desplieguePagoId = $despliegue->id;
                    break 2;
                }
            }
        }

        if (!$subCaja) {
            return 0.0;
        }

        $yaRegistrado = (float) \App\Models\TransaccionCaja::where('referencia_tipo', 'monto_inicial')
            ->where('referencia_id', $metodoPago->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo_transaccion = 'ingreso' THEN monto ELSE -monto END), 0) as total")
            ->value('total');

        $diferencia = round((float) $metodoPago->monto_inicial - $yaRegistrado, 2);

        if (abs($diferencia) < 0.005) {
            return 0.0;
        }

        $esIngreso = $diferencia > 0;
        $saldoAnterior = (float) $subCaja->saldo_actual;
        $saldoNuevo = $saldoAnterior + $diferencia;

        \App\Models\TransaccionCaja::create([
            'id' => (string) Str::ulid(),
            'sub_caja_id' => $subCaja->id,
            'user_id' => Auth::id() ?? $subCaja->cajaPrincipal->user_id,
            'tipo_transaccion' => $esIngreso ? 'ingreso' : 'egreso',
            'monto' => abs($diferencia),
            'saldo_anterior' => $saldoAnterior,
            'saldo_nuevo' => $saldoNuevo,
            'despliegue_pago_id' => $desplieguePagoId,
            'descripcion' => ($esIngreso ? 'Monto inicial de ' : 'Ajuste de monto inicial de ') . $metodoPago->name,
            'referencia_tipo' => 'monto_inicial',
            'referencia_id' => $metodoPago->id,
            'fecha' => now(),
        ]);

        $subCaja->saldo_actual = $saldoNuevo;
        $subCaja->save();

        return $diferencia;
    }

    /**
     * ¿Este método de pago es EFECTIVO?
     *
     * Solo cuenta el nombre: si dice "efectivo", lo es. El efectivo se excluye del
     * monto inicial porque ese dinero entra por la apertura de caja, no por la
     * configuración del método.
     *
     * Antes, cuando el método no tenía cuenta bancaria, se adivinaba por palabras
     * sueltas del nombre — "caja", "cash", "sin banco". Eso marcaba como efectivo a
     * cualquier método que llevara "caja" en el nombre: un banco llamado "caja
     * prueba" quedaba excluido y su monto inicial nunca se registraba, mientras que
     * otro igual de "sin cuenta" pero llamado distinto sí entraba. La ausencia de
     * cuenta bancaria no lo vuelve efectivo — hay métodos digitales sin cuenta.
     */
    private function esMetodoEfectivo(\App\Models\MetodoDePago $metodoPago): bool
    {
        return str_contains(strtolower($metodoPago->name ?? ''), 'efectivo');
    }

    public function actualizarSubCaja(int $subCajaId, array $data): SubCaja
    {
        return DB::transaction(function () use ($subCajaId, $data) {
            $subCaja = $this->subCajaRepository->findById($subCajaId);

            if (!$subCaja) {
                throw new CajaNoEncontradaException('Sub-caja no encontrada');
            }

            // No permitir modificar Caja Chica
            if ($subCaja->tipo_caja === 'CC') {
                throw new \Exception('No se puede modificar la Caja Chica');
            }

            // NUEVO: Validar exclusividad de métodos de pago en actualización
            if (isset($data['despliegues_pago_ids'])) {
                // Caso 1: Si se intenta actualizar a "*" (todos)
                if (in_array('*', $data['despliegues_pago_ids'])) {
                    $subCajasExistentes = $this->subCajaRepository->findByCajaPrincipalId($subCaja->caja_principal_id);
                    $otrasSubCajasManuales = $subCajasExistentes->filter(function($sc) use ($subCajaId) {
                        return $sc->tipo_caja === 'SC' && $sc->id != $subCajaId;
                    });
                    
                    if ($otrasSubCajasManuales->isNotEmpty()) {
                        throw new \Exception('No se puede configurar esta sub-caja con TODOS los métodos de pago porque ya existen otras sub-cajas con métodos específicos en esta Caja Principal.');
                    }
                }
                
                // Validar contra otras cajas principales y otras sub-cajas de la misma caja
                $this->subCajaRepository->validarExclusividadMetodosPago(
                    $subCaja->caja_principal_id,
                    $data['despliegues_pago_ids'],
                    $subCajaId
                );
            }

            // Validar configuración duplicada (excluyendo la actual)
            if (isset($data['despliegues_pago_ids']) && isset($data['tipos_comprobante'])) {
                if ($this->subCajaRepository->existeConfiguracionDuplicada(
                    $subCaja->caja_principal_id,
                    $data['despliegues_pago_ids'],
                    $data['tipos_comprobante'],
                    $subCajaId
                )) {
                    throw new SubCajaDuplicadaException();
                }
            }

            return $this->subCajaRepository->update($subCajaId, $data);
        });
    }

    public function eliminarSubCaja(int $subCajaId): bool
    {
        return DB::transaction(function () use ($subCajaId) {
            $subCaja = $this->subCajaRepository->findById($subCajaId);

            if (!$subCaja) {
                throw new CajaNoEncontradaException('Sub-caja no encontrada');
            }

            // No permitir eliminar Caja Chica
            if ($subCaja->tipo_caja === 'CC') {
                throw new \Exception('No se puede eliminar la Caja Chica');
            }

            // Validar que no tenga saldo
            if ($subCaja->saldo_actual > 0) {
                throw new \Exception('No se puede eliminar una sub-caja con saldo');
            }

            return $this->subCajaRepository->delete($subCajaId);
        });
    }

    public function obtenerCajaPorUsuario(string $userId): Collection
    {
        return $this->cajaPrincipalRepository->findByUserId($userId);
    }

    public function obtenerSubCajas(int $cajaPrincipalId): array
    {
        return $this->subCajaRepository->findByCajaPrincipalId($cajaPrincipalId)->toArray();
    }
}
