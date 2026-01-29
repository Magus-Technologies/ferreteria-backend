<?php

namespace App\Http\Resources\Cajas;

use App\DTOs\CierreCaja\ResumenCajaDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CierreCajaResource extends JsonResource
{
    private ResumenCajaDTO $resumen;

    public function __construct($apertura, ResumenCajaDTO $resumen)
    {
        parent::__construct($apertura);
        $this->resumen = $resumen;
    }

    public function toArray(Request $request): array
    {
        $base = (new AperturaCierreCajaResource($this->resource))->toArray($request);
        $base['resumen'] = $this->resumen->toArray();

        return $base;
    }
}
