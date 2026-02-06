<?php

namespace App\Services\Interfaces;

interface XmlStorageServiceInterface
{
    /**
     * Guardar archivo XML
     */
    public function guardarXml(string $contenido, string $nombreArchivo): string;

    /**
     * Guardar archivo CDR
     */
    public function guardarCdr(string $contenido, string $nombreArchivo): string;

    /**
     * Obtener contenido de archivo XML
     */
    public function obtenerXml(string $ruta): string;

    /**
     * Obtener contenido de archivo CDR
     */
    public function obtenerCdr(string $ruta): string;

    /**
     * Verificar si existe un archivo
     */
    public function existeArchivo(string $ruta): bool;

    /**
     * Eliminar archivo
     */
    public function eliminarArchivo(string $ruta): bool;

    /**
     * Generar nombre de archivo para XML
     */
    public function generarNombreXml(string $ruc, string $tipoDoc, string $serie, string $numero): string;

    /**
     * Generar nombre de archivo para CDR
     */
    public function generarNombreCdr(string $ruc, string $tipoDoc, string $serie, string $numero): string;
}
