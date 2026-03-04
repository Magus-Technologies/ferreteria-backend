<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class RequerimientoInternoException extends Exception
{
    public static function noEncontrado(int $id): self
    {
        return new self("El requerimiento interno con ID {$id} no fue encontrado", 404);
    }

    public static function estadoInvalido(string $estadoActual, string $estadoDeseado): self
    {
        return new self(
            "No se puede cambiar el estado de '{$estadoActual}' a '{$estadoDeseado}'",
            422
        );
    }

    public static function sinProductos(): self
    {
        return new self("El requerimiento de tipo OC debe tener al menos un producto", 422);
    }

    public static function sinServicio(): self
    {
        return new self("El requerimiento de tipo OS debe incluir datos del servicio", 422);
    }

    public static function errorAlCrear(string $detalle): self
    {
        return new self("Error al crear el requerimiento interno: {$detalle}", 500);
    }

    public static function errorAlActualizar(string $detalle): self
    {
        return new self("Error al actualizar el requerimiento interno: {$detalle}", 500);
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], $this->getCode() ?: 500);
    }
}
