<?php

namespace App\UseCases\RegistrarTransaccion;

use App\Models\TransaccionCaja;

class RegistrarTransaccionResponse
{
    public function __construct(
        public bool $success,
        public TransaccionCaja $transaccion,
        public string $message
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->transaccion,
        ];
    }
}
