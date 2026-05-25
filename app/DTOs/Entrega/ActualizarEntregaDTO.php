<?php

namespace App\DTOs\Entrega;

use App\Http\Requests\Entrega\ActualizarEntregaRequest;

readonly class ActualizarEntregaDTO
{
    public function __construct(
        public ?string $choferId,
        public ?int    $vehiculoId,
        public ?string $fechaProgramada,
        public ?string $horaInicio,
        public ?string $horaFin,
        public ?string $direccionEntrega,
        public ?string $referenciaEntrega,
        public ?float  $latitud,
        public ?float  $longitud,
        public ?string $observaciones,
    ) {}

    public static function fromRequest(ActualizarEntregaRequest $request): self
    {
        return new self(
            choferId:          $request->validated('chofer_id'),
            vehiculoId:        $request->validated('vehiculo_id')
                                   ? (int) $request->validated('vehiculo_id') : null,
            fechaProgramada:   $request->validated('fecha_programada'),
            horaInicio:        $request->validated('hora_inicio'),
            horaFin:           $request->validated('hora_fin'),
            direccionEntrega:  $request->validated('direccion_entrega'),
            referenciaEntrega: $request->validated('referencia_entrega'),
            latitud:           $request->validated('latitud')
                                   ? (float) $request->validated('latitud') : null,
            longitud:          $request->validated('longitud')
                                   ? (float) $request->validated('longitud') : null,
            observaciones:     $request->validated('observaciones'),
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'chofer_id'          => $this->choferId,
            'vehiculo_id'        => $this->vehiculoId,
            'fecha_programada'   => $this->fechaProgramada,
            'hora_inicio'        => $this->horaInicio,
            'hora_fin'           => $this->horaFin,
            'direccion_entrega'  => $this->direccionEntrega,
            'referencia_entrega' => $this->referenciaEntrega,
            'latitud'            => $this->latitud,
            'longitud'           => $this->longitud,
            'observaciones'      => $this->observaciones,
        ], fn ($v) => $v !== null);
    }
}
