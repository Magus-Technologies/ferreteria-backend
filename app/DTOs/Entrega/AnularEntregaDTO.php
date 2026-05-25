<?php

namespace App\DTOs\Entrega;

use App\Http\Requests\Entrega\AnularEntregaRequest;

readonly class AnularEntregaDTO
{
    public function __construct(
        public string $motivo,
        public string $userId,
    ) {}

    public static function fromRequest(AnularEntregaRequest $request): self
    {
        return new self(
            motivo: $request->validated('motivo'),
            userId: $request->user()->id,
        );
    }
}
