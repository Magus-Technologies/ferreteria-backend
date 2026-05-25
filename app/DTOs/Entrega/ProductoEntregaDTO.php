<?php

namespace App\DTOs\Entrega;

readonly class ProductoEntregaDTO
{
    public function __construct(
        public int     $unidadDerivadaVentaId,
        public float   $cantidad,
        public ?string $ubicacion,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            unidadDerivadaVentaId: (int) $data['unidad_derivada_venta_id'],
            cantidad:              (float) $data['cantidad'],
            ubicacion:             $data['ubicacion'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'unidad_derivada_venta_id' => $this->unidadDerivadaVentaId,
            'cantidad'                 => $this->cantidad,
            'ubicacion'                => $this->ubicacion,
        ];
    }
}
