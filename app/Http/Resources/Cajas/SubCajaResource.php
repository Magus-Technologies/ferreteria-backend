<?php

namespace App\Http\Resources\Cajas;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubCajaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $desplieguePagos = $this->getDesplieguePagos();

        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'tipo_caja' => $this->tipo_caja,
            'tipo_caja_label' => $this->tipo_caja === 'CC' ? 'Caja Chica' : 'Sub-Caja',
            'despliegues_pago_ids' => $this->despliegues_pago_ids,
            'despliegues_pago' => $desplieguePagos->map(function ($dp) {
                return [
                    'id' => $dp->id,
                    'name' => $dp->name,
                    'adicional' => $dp->adicional,
                    'metodo_de_pago' => [
                        'id' => $dp->metodoDePago->id,
                        'name' => $dp->metodoDePago->name,
                        'cuenta_bancaria' => $dp->metodoDePago->cuenta_bancaria,
                    ],
                ];
            }),
            'acepta_todos_metodos' => in_array('*', $this->despliegues_pago_ids),
            'tipos_comprobante' => $this->tipos_comprobante,
            'tipos_comprobante_labels' => $this->getTiposComprobanteLabels(),
            'saldo_actual' => number_format($this->saldo_actual, 2, '.', ''),
            'proposito' => $this->proposito,
            'estado' => $this->estado,
            'es_caja_chica' => $this->esCajaChica(),
            'puede_eliminar' => $this->puedeEliminar(),
            'puede_modificar' => $this->puedeModificar(),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    private function getTiposComprobanteLabels(): array
    {
        $labels = [
            '01' => 'Factura',
            '03' => 'Boleta',
            'nv' => 'Nota de Venta',
        ];

        return array_map(fn($tipo) => $labels[$tipo] ?? $tipo, $this->tipos_comprobante);
    }
}
