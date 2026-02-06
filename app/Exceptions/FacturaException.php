<?php

namespace App\Exceptions;

use Exception;

class FacturaException extends Exception
{
    public static function ventaNoEncontrada(string $ventaId): self
    {
        return new self("La venta con ID {$ventaId} no fue encontrada", 404);
    }

    public static function ventaNoValida(string $razon): self
    {
        return new self("La venta no es válida para facturación electrónica: {$razon}", 422);
    }

    public static function facturaNoEncontrada(string $facturaId): self
    {
        return new self("La factura con ID {$facturaId} no fue encontrada", 404);
    }

    public static function facturaNoEnviable(string $razon): self
    {
        return new self("La factura no puede enviarse: {$razon}", 422);
    }

    public static function datosIncompletos(string $campo): self
    {
        return new self("Datos incompletos: falta el campo {$campo}", 422);
    }

    public static function errorAlGuardar(string $detalle): self
    {
        return new self("Error al guardar la factura: {$detalle}", 500);
    }

    public static function comprobanteYaEnviado(): self
    {
        return new self("El comprobante ya fue enviado a SUNAT", 422);
    }
}
