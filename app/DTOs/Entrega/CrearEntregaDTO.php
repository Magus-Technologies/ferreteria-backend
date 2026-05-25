<?php

namespace App\DTOs\Entrega;

use App\Http\Requests\Entrega\CrearEntregaRequest;

readonly class CrearEntregaDTO
{
    /**
     * @param  ProductoEntregaDTO[]  $productos
     */
    public function __construct(
        public string  $ventaId,
        public string  $tipoEntrega,
        public ?string $tipoDespacho,
        public string  $quienEntrega,
        public int     $almacenSalidaId,
        public ?string $choferId,
        public ?int    $vehiculoId,
        public string  $tipoPedido,
        public ?string $cargoDestino,
        public ?string $fechaProgramada,
        public ?string $horaInicio,
        public ?string $horaFin,
        public ?string $direccionEntrega,
        public ?string $referenciaEntrega,
        public ?float  $latitud,
        public ?float  $longitud,
        public ?string $observaciones,
        public array   $productos,
        public string  $userCreadorId,
    ) {}

    public static function fromRequest(CrearEntregaRequest $request): self
    {
        return new self(
            ventaId:           $request->validated('venta_id'),
            tipoEntrega:       $request->validated('tipo_entrega'),
            tipoDespacho:      $request->validated('tipo_despacho'),
            quienEntrega:      $request->validated('quien_entrega'),
            almacenSalidaId:   (int) $request->validated('almacen_salida_id'),
            choferId:          $request->validated('chofer_id'),
            vehiculoId:        $request->validated('vehiculo_id')
                                   ? (int) $request->validated('vehiculo_id') : null,
            tipoPedido:        $request->validated('tipo_pedido', 'interno'),
            cargoDestino:      $request->validated('cargo_destino'),
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
            productos:         array_map(
                fn ($p) => ProductoEntregaDTO::fromArray($p),
                $request->validated('productos')
            ),
            userCreadorId:     $request->user()->id,
        );
    }

    public function toArray(): array
    {
        return [
            'venta_id'          => $this->ventaId,
            'tipo_entrega'      => $this->tipoEntrega,
            'tipo_despacho'     => $this->tipoDespacho,
            'quien_entrega'     => $this->quienEntrega,
            'almacen_salida_id' => $this->almacenSalidaId,
            'chofer_id'         => $this->choferId,
            'vehiculo_id'       => $this->vehiculoId,
            'tipo_pedido'       => $this->tipoPedido,
            'cargo_destino'     => $this->cargoDestino,
            'fecha_programada'  => $this->fechaProgramada,
            'hora_inicio'       => $this->horaInicio,
            'hora_fin'          => $this->horaFin,
            'direccion_entrega' => $this->direccionEntrega,
            'referencia_entrega'=> $this->referenciaEntrega,
            'latitud'           => $this->latitud,
            'longitud'          => $this->longitud,
            'observaciones'     => $this->observaciones,
            'productos'         => array_map(fn ($p) => $p->toArray(), $this->productos),
            'user_creador_id'   => $this->userCreadorId,
        ];
    }
}
