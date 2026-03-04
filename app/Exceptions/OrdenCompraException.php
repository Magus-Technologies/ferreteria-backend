<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class OrdenCompraException extends Exception
{
    public static function noEncontrada(int $id): self
    {
        return new self("La orden de compra con ID {$id} no fue encontrada", 404);
    }

    public static function yaAnulada(): self
    {
        return new self("La orden de compra ya se encuentra anulada", 422);
    }

    public static function noAnulable(string $estado): self
    {
        return new self(
            "Solo se pueden anular órdenes en estado Pendiente o En Proceso. Estado actual: {$estado}",
            422
        );
    }

    public static function sinProductos(): self
    {
        return new self("La orden de compra debe tener al menos un producto", 422);
    }

    public static function errorAlCrear(string $detalle): self
    {
        return new self("Error al crear la orden de compra: {$detalle}", 500);
    }

    public static function errorAlAnular(string $detalle): self
    {
        return new self("Error al anular la orden de compra: {$detalle}", 500);
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
