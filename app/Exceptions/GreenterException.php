<?php

namespace App\Exceptions;

use Exception;

class GreenterException extends Exception
{
    public static function errorGenerandoXml(string $detalle): self
    {
        return new self("Error al generar XML: {$detalle}", 500);
    }

    public static function errorEnviandoSunat(string $detalle): self
    {
        return new self("Error al enviar a SUNAT: {$detalle}", 500);
    }

    public static function certificadoNoEncontrado(): self
    {
        return new self("Certificado digital no encontrado. El sistema está en modo simulación", 503);
    }

    public static function credencialesInvalidas(): self
    {
        return new self("Credenciales SOL inválidas", 401);
    }

    public static function sunatNoDisponible(): self
    {
        return new self("El servicio de SUNAT no está disponible en este momento", 503);
    }

    public static function comprobanteRechazado(string $codigo, string $mensaje): self
    {
        return new self("Comprobante rechazado por SUNAT - Código: {$codigo}, Mensaje: {$mensaje}", 422);
    }

    public static function errorAlmacenandoArchivo(string $tipo, string $detalle): self
    {
        return new self("Error al almacenar archivo {$tipo}: {$detalle}", 500);
    }

    public static function archivoNoEncontrado(string $tipo): self
    {
        return new self("Archivo {$tipo} no encontrado", 404);
    }
}
