<?php

namespace App\Http\Resources\Cajas;

use App\DTOs\CierreCaja\CierreCajaResultadoDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CierreCajaResource extends JsonResource
{
    public function __construct(private CierreCajaResultadoDTO $resultado)
    {
        parent::__construct($resultado->apertura);
    }

    public function toArray(Request $request): array
    {
        $base = (new AperturaCierreCajaResource($this->resource))->toArray($request);
        $base['diferencias'] = $this->resultado->diferencias->toArray();

        return $base;
    }
}
