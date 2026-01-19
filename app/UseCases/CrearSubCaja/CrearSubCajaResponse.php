<?php

namespace App\UseCases\CrearSubCaja;

use App\Models\SubCaja;

class CrearSubCajaResponse
{
    public function __construct(
        public bool $success,
        public SubCaja $subCaja,
        public string $message
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->subCaja,
        ];
    }
}
