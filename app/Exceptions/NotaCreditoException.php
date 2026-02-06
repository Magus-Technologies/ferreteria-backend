<?php

namespace App\Exceptions;

use Exception;

class NotaCreditoException extends Exception
{
    public static function ventaNoEncontrada(string $ventaId): self
    {
        return new self("La venta con ID {$ventaId} no fue encontrada", 404);
    }

    public static function ventaNoValida(string $razon): self
    {
        return new self("La venta no es válida para generar nota de crédito: {$razon}", 422);
    }

    public static function serieNoEncontrada(string $serie): self
    {
        return new self("La serie {$serie} no fue encontrada o no está activa", 404);
    }

    public static function motivoNoEncontrado(int $motivoId): self
    {
        return new self("El motivo con ID {$motivoId} no fue encontrado", 404);
    }

    public static function motivoNoValido(string $razon): self
    {
        return new self("El motivo no es válido: {$razon}", 422);
    }

    public static function notaCreditoNoEncontrada(string $notaCreditoId): self
    {
        return new self("La nota de crédito con ID {$notaCreditoId} no fue encontrada", 404);
    }

    public static function notaCreditoNoEditable(string $estado): self
    {
        return new self("La nota de crédito no puede editarse en estado: {$estado}", 422);
    }

    public static function notaCreditoNoEnviable(string $razon): self
    {
        return new self("La nota de crédito no puede enviarse: {$razon}", 422);
    }

    public static function montoInvalido(string $razon): self
    {
        return new self("El monto de la nota de crédito es inválido: {$razon}", 422);
    }

    public static function datosIncompletos(string $campo): self
    {
        return new self("Datos incompletos: falta el campo {$campo}", 422);
    }

    public static function errorAlGuardar(string $detalle): self
    {
        return new self("Error al guardar la nota de crédito: {$detalle}", 500);
    }
}
