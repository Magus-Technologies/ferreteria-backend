<?php

namespace App\UseCases\CrearCajaPrincipal;

use App\Models\CajaPrincipal;

class CrearCajaPrincipalResponse
{
    public function __construct(
        public bool $success,
        public CajaPrincipal $cajaPrincipal,
        public string $message
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->cajaPrincipal,
        ];
    }
}
