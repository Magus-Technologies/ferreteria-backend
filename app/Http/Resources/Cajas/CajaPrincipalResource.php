<?php

namespace App\Http\Resources\Cajas;

use App\Models\TransaccionCaja;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CajaPrincipalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'estado' => $this->estado,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'numero_documento' => $this->user->numero_documento,
            ],
            'sub_cajas' => SubCajaResource::collection($this->whenLoaded('subCajas')),
            'total_sub_cajas' => $this->subCajas->count(),
            'saldo_total' => $this->saldoTotalReal(),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Saldo total de la caja principal RECALCULADO desde `transacciones_caja`,
     * no sumando la columna guardada `sub_cajas.saldo_actual`.
     *
     * Esa columna queda por encima del dinero real: un Traslado a Bóveda registra
     * su egreso en el libro pero deliberadamente NO la descuenta (ver
     * TrasladoBovedaService::registrarTraslado), y con el tiempo acumula además
     * descuadres propios. Es el MISMO criterio que usa
     * MovimientoInternoService::calcularSaldoRealSubCaja(), que alimenta las
     * columnas "Saldo Cerrado" / "Saldo No Cerrado" del modal de Sub-Cajas — así
     * el total de la tabla cuadra con el detalle en vez de mostrar un monto mayor.
     */
    private function saldoTotalReal(): string
    {
        $subCajaIds = $this->subCajas->pluck('id');

        if ($subCajaIds->isEmpty()) {
            return '0.00';
        }

        $total = (float) TransaccionCaja::whereIn('sub_caja_id', $subCajaIds)
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo_transaccion = 'ingreso' THEN monto ELSE -monto END), 0) as total")
            ->value('total');

        return number_format($total, 2, '.', '');
    }
}
