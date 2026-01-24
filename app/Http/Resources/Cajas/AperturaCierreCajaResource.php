<?php

namespace App\Http\Resources\Cajas;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AperturaCierreCajaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'caja_principal_id' => $this->caja_principal_id,
            'sub_caja_id' => $this->sub_caja_id,
            'user_id' => $this->user_id,
            'monto_apertura' => number_format($this->monto_apertura, 2, '.', ''),
            'fecha_apertura' => $this->fecha_apertura->toIso8601String(),
            'monto_cierre' => $this->monto_cierre ? number_format($this->monto_cierre, 2, '.', '') : null,
            'fecha_cierre' => $this->fecha_cierre?->toIso8601String(),
            'estado' => $this->estado,
            'caja_principal' => $this->whenLoaded('cajaPrincipal', function () {
                return [
                    'id' => $this->cajaPrincipal->id,
                    'codigo' => $this->cajaPrincipal->codigo,
                    'nombre' => $this->cajaPrincipal->nombre,
                ];
            }),
            'sub_caja_chica' => $this->whenLoaded('subCaja', function () {
                return [
                    'id' => $this->subCaja->id,
                    'codigo' => $this->subCaja->codigo,
                    'nombre' => $this->subCaja->nombre,
                    'saldo_actual' => number_format($this->subCaja->saldo_actual, 2, '.', ''),
                ];
            }),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            'supervisor' => $this->whenLoaded('supervisor', function () {
                return $this->supervisor ? [
                    'id' => $this->supervisor->id,
                    'name' => $this->supervisor->name,
                ] : null;
            }),
            'diferencia_efectivo' => $this->diferencia_efectivo ? number_format($this->diferencia_efectivo, 2, '.', '') : null,
            'diferencia_total' => $this->diferencia_total ? number_format($this->diferencia_total, 2, '.', '') : null,
            'comentarios' => $this->comentarios,
            'distribuciones_vendedores' => $this->whenLoaded('distribucionesVendedores', function () {
                return $this->distribucionesVendedores->map(function ($distribucion) {
                    return [
                        'id' => $distribucion->id,
                        'vendedor_id' => $distribucion->vendedor_id,
                        'vendedor_nombre' => $distribucion->vendedor->name ?? 'N/A',
                        'monto_asignado' => number_format($distribucion->monto_asignado, 2, '.', ''),
                    ];
                });
            }),
        ];
    }
}
