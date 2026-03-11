<?php

namespace App\Services\Interfaces;

interface GreenterServiceInterface
{
    /**
     * Generar XML y enviar nota de débito a SUNAT
     */
    public function generarYEnviarNotaDebito(array $data): array;

    /**
     * Generar solo el XML de la nota de débito (sin enviar)
     */
    public function generarXmlNotaDebito(array $data): string;

    /**
     * Generar XML y enviar nota de crédito a SUNAT
     */
    public function generarYEnviarNotaCredito(array $data): array;

    /**
     * Generar solo el XML de la nota de crédito (sin enviar)
     */
    public function generarXmlNotaCredito(array $data): string;

    /**
     * Generar XML y enviar factura/boleta a SUNAT
     */
    public function generarYEnviarFactura(array $data): array;

    /**
     * Generar solo el XML de la factura/boleta (sin enviar a SUNAT)
     */
    public function generarXmlFactura(array $data): string;

    /**
     * Generar XML y enviar guía de remisión a SUNAT
     */
    public function generarYEnviarGuiaRemision(array $data): array;

    /**
     * Generar solo el XML de la guía de remisión (sin enviar)
     */
    public function generarXmlGuiaRemision(array $data): string;

    /**
     * Consultar estado de comprobante en SUNAT
     */
    public function consultarEstado(string $ruc, string $tipoDoc, string $serie, string $numero): array;

    /**
     * Verificar si está en modo simulación
     */
    public function esModoSimulacion(): bool;

    /**
     * Obtener información de la empresa configurada
     */
    public function obtenerDatosEmpresa(): array;
}
