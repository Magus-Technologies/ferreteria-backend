<?php

namespace App\Services\Implementations;

use App\Exceptions\GreenterException;
use App\Services\Interfaces\XmlStorageServiceInterface;
use Illuminate\Support\Facades\Storage;

class XmlStorageService implements XmlStorageServiceInterface
{
    private string $xmlDirectory = 'facturacion_electronica/xml';
    private string $cdrDirectory = 'facturacion_electronica/cdr';

    public function guardarXml(string $contenido, string $nombreArchivo): string
    {
        try {
            $ruta = "{$this->xmlDirectory}/{$nombreArchivo}";
            Storage::put($ruta, $contenido);
            return $ruta;
        } catch (\Exception $e) {
            throw GreenterException::errorAlmacenandoArchivo('XML', $e->getMessage());
        }
    }

    public function guardarCdr(string $contenido, string $nombreArchivo): string
    {
        try {
            $ruta = "{$this->cdrDirectory}/{$nombreArchivo}";
            Storage::put($ruta, $contenido);
            return $ruta;
        } catch (\Exception $e) {
            throw GreenterException::errorAlmacenandoArchivo('CDR', $e->getMessage());
        }
    }

    public function obtenerXml(string $ruta): string
    {
        if (!$this->existeArchivo($ruta)) {
            throw GreenterException::archivoNoEncontrado('XML');
        }

        return Storage::get($ruta);
    }

    public function obtenerCdr(string $ruta): string
    {
        if (!$this->existeArchivo($ruta)) {
            throw GreenterException::archivoNoEncontrado('CDR');
        }

        return Storage::get($ruta);
    }

    public function existeArchivo(string $ruta): bool
    {
        return Storage::exists($ruta);
    }

    public function eliminarArchivo(string $ruta): bool
    {
        if (!$this->existeArchivo($ruta)) {
            return false;
        }

        return Storage::delete($ruta);
    }

    public function generarNombreXml(string $ruc, string $tipoDoc, string $serie, string $numero): string
    {
        return "{$ruc}-{$tipoDoc}-{$serie}-{$numero}.xml";
    }

    public function generarNombreCdr(string $ruc, string $tipoDoc, string $serie, string $numero): string
    {
        return "R-{$ruc}-{$tipoDoc}-{$serie}-{$numero}.xml";
    }
}
