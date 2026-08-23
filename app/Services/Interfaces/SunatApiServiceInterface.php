<?php

namespace App\Services\Interfaces;

interface SunatApiServiceInterface
{
    public function generarYEnviarNotaDebito(array $data): array;
    public function generarXmlNotaDebito(array $data): string;
    public function generarYEnviarNotaCredito(array $data): array;
    public function generarXmlNotaCredito(array $data): string;
    public function generarYEnviarFactura(array $data): array;
    public function generarXmlFactura(array $data): string;
    public function generarYEnviarGuiaRemision(array $data): array;
    public function generarXmlGuiaRemision(array $data): string;
    public function generarXmlComunicacionBaja(array $data): string;
    public function generarYEnviarComunicacionBaja(array $data): array;
    public function generarYEnviarResumenBaja(\App\Models\ComprobanteElectronico $comprobante): array;
    public function consultarEstado(string $ruc, string $tipoDoc, string $serie, string $numero): array;
    public function esModoSimulacion(): bool;
    public function obtenerDatosEmpresa(): array;
}
