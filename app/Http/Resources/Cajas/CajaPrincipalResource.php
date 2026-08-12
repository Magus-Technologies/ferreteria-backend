<?php

namespace App\Http\Resources\Cajas;

use App\Services\Interfaces\MovimientoInternoServiceInterface;
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
     * no sumando la columna guardada `sub_cajas.saldo_actual` (esa columna queda
     * por encima del dinero real y acumula descuadres propios).
     *
     * Es el "Total General" del modal de Sub-Cajas: Saldo Cerrado + Saldo No
     * Cerrado, o sea todo el dinero que hay en la caja.
     *
     * Se delega en MovimientoInternoService, el MISMO calculador que alimenta ese
     * modal. Antes este resource repetía la suma por su cuenta y se desincronizó:
     * cuando el Traslado a Bóveda dejó de restar del saldo real, la copia de acá
     * siguió descontándolo y la tabla mostraba un monto menor que el detalle,
     * separándose más con cada traslado.
     */
    private function saldoTotalReal(): string
    {
        $total = app(MovimientoInternoServiceInterface::class)
            ->saldoRealCajaPrincipal($this->id);

        return number_format($total, 2, '.', '');
    }
}
