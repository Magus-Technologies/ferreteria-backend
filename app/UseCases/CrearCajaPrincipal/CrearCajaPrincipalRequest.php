<?php

namespace App\UseCases\CrearCajaPrincipal;

class CrearCajaPrincipalRequest
{
    public function __construct(
        public string $userId,
        public string $nombre
    ) {}
}
