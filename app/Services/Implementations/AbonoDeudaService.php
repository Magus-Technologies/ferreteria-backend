<?php

namespace App\Services\Implementations;

use App\Models\AbonoDeudaPersonal;
use App\Models\DeudaPersonal;
use App\Services\Interfaces\AbonoDeudaServiceInterface;
use Illuminate\Support\Facades\DB;
use Exception;

class AbonoDeudaService implements AbonoDeudaServiceInterface
{
    /**
     * Registrar un abono a una deuda personal
     *
     * @param array $data
     * @return AbonoDeudaPersonal
     * @throws Exception
     */
    public function registrarAbono(array $data): AbonoDeudaPersonal
    {
        DB::beginTransaction();
        try {
            $deuda = DeudaPersonal::findOrFail($data['deuda_personal_id']);
            
            // Validaciones
            if ($deuda->esta_pagada) {
                throw new Exception('Esta deuda ya está completamente pagada');
            }
            
            if ($data['monto'] > $deuda->saldo_pendiente) {
                throw new Exception('El monto a abonar excede el saldo pendiente');
            }
            
            if ($data['monto'] <= 0) {
                throw new Exception('El monto debe ser mayor a 0');
            }
            
            // Crear abono
            $abono = AbonoDeudaPersonal::create([
                'deuda_personal_id' => $deuda->id,
                'monto' => $data['monto'],
                'metodo_pago_id' => $data['metodo_pago_id'] ?? null,
                'numero_operacion' => $data['numero_operacion'] ?? null,
                'observaciones' => $data['observaciones'] ?? null,
                'saldo_anterior' => $deuda->saldo_pendiente,
                'saldo_despues' => $deuda->saldo_pendiente - $data['monto'],
                'registrado_por_user_id' => auth()->id(),
                'fecha_abono' => now(),
            ]);
            
            // Actualizar deuda
            $deuda->monto_abonado += $data['monto'];
            $deuda->saldo_pendiente -= $data['monto'];
            
            // Actualizar estado
            if ($deuda->saldo_pendiente <= 0) {
                $deuda->estado = 'pagada';
            } elseif ($deuda->monto_abonado > 0) {
                $deuda->estado = 'parcialmente_pagada';
            }
            
            $deuda->save();
            
            DB::commit();
            return $abono->load(['metodoPago', 'registradoPor']);
            
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Obtener historial de abonos de una deuda
     *
     * @param int $deudaId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function obtenerHistorialAbonos(int $deudaId)
    {
        return AbonoDeudaPersonal::where('deuda_personal_id', $deudaId)
            ->with(['metodoPago', 'registradoPor'])
            ->orderBy('fecha_abono', 'desc')
            ->get();
    }
    
    /**
     * Obtener resumen de deudas de un usuario
     *
     * @param int|string $userId
     * @return array
     */
    public function obtenerResumenDeudas(int|string $userId): array
    {
        $deudas = DeudaPersonal::where('user_id', $userId)
            ->with(['arqueoDiario.aperturaCierreCaja.cajaPrincipal', 'abonos', 'user'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        return [
            'total_deudas' => $deudas->count(),
            'deudas_pendientes' => $deudas->where('estado', 'pendiente')->count(),
            'deudas_parciales' => $deudas->where('estado', 'parcialmente_pagada')->count(),
            'deudas_pagadas' => $deudas->where('estado', 'pagada')->count(),
            'monto_total_original' => $deudas->sum('monto_original'),
            'monto_total_abonado' => $deudas->sum('monto_abonado'),
            'saldo_total_pendiente' => $deudas->sum('saldo_pendiente'),
            'deudas' => $deudas,
        ];
    }
}
