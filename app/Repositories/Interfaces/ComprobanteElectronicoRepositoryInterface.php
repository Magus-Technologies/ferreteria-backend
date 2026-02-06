<?php

namespace App\Repositories\Interfaces;

use App\Models\ComprobanteElectronico;
use Illuminate\Database\Eloquent\Collection;

interface ComprobanteElectronicoRepositoryInterface
{
    /**
     * Buscar comprobante por ID
     */
    public function findById(string $id): ?ComprobanteElectronico;

    /**
     * Buscar comprobante por documento
     */
    public function findByDocumento(string $tipoDocumento, string $documentoId): ?ComprobanteElectronico;

    /**
     * Buscar comprobante por serie y número
     */
    public function findBySerieNumero(string $serie, int $numero): ?ComprobanteElectronico;

    /**
     * Obtener comprobantes por tipo de documento
     */
    public function getByTipoDocumento(string $tipoDocumento): Collection;

    /**
     * Obtener comprobantes por estado SUNAT
     */
    public function getByEstadoSunat(string $estado): Collection;

    /**
     * Obtener comprobantes pendientes de envío
     */
    public function getPendientesEnvio(): Collection;

    /**
     * Crear comprobante electrónico
     */
    public function create(array $data): ComprobanteElectronico;

    /**
     * Actualizar comprobante electrónico
     */
    public function update(string $id, array $data): ComprobanteElectronico;

    /**
     * Actualizar estado SUNAT
     */
    public function actualizarEstadoSunat(
        string $id,
        string $estado,
        ?string $codigo = null,
        ?string $mensaje = null
    ): bool;

    /**
     * Guardar rutas de archivos XML y CDR
     */
    public function guardarArchivos(
        string $id,
        ?string $xmlPath = null,
        ?string $cdrPath = null,
        ?string $hashCpe = null,
        ?string $hashCdr = null
    ): bool;

    /**
     * Registrar intento de envío
     */
    public function registrarIntentoEnvio(
        string $comprobanteId,
        bool $exitoso,
        ?string $codigoRespuesta = null,
        ?string $mensajeRespuesta = null,
        ?string $detalleError = null,
        string $modoEnvio = 'manual'
    ): void;
}
