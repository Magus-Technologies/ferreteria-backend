<?php

namespace App\Repositories\Implementations;

use App\Models\ComprobanteElectronico;
use App\Models\IntentoEnvioSunat;
use App\Repositories\Interfaces\ComprobanteElectronicoRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ComprobanteElectronicoRepository implements ComprobanteElectronicoRepositoryInterface
{
    public function findById(string $id): ?ComprobanteElectronico
    {
        return ComprobanteElectronico::with(['detalles', 'intentosEnvio'])->find($id);
    }

    public function findByDocumento(string $tipoDocumento, string $documentoId): ?ComprobanteElectronico
    {
        return ComprobanteElectronico::where('tipo_documento', $tipoDocumento)
            ->where('documento_id', $documentoId)
            ->with(['detalles', 'intentosEnvio'])
            ->first();
    }

    public function findBySerieNumero(string $serie, int $numero): ?ComprobanteElectronico
    {
        return ComprobanteElectronico::where('serie', $serie)
            ->where('numero', $numero)
            ->with(['detalles', 'intentosEnvio'])
            ->first();
    }

    public function getByTipoDocumento(string $tipoDocumento): Collection
    {
        return ComprobanteElectronico::where('tipo_documento', $tipoDocumento)
            ->with(['detalles'])
            ->orderBy('fecha_emision', 'desc')
            ->get();
    }

    public function getByEstadoSunat(string $estado): Collection
    {
        return ComprobanteElectronico::where('estado_sunat', $estado)
            ->with(['detalles'])
            ->orderBy('fecha_emision', 'desc')
            ->get();
    }

    public function getPendientesEnvio(): Collection
    {
        return ComprobanteElectronico::where('estado_sunat', 'pendiente')
            ->whereNull('fecha_envio_sunat')
            ->with(['detalles'])
            ->orderBy('fecha_emision', 'asc')
            ->get();
    }

    public function create(array $data): ComprobanteElectronico
    {
        return ComprobanteElectronico::create($data);
    }

    public function update(string $id, array $data): ComprobanteElectronico
    {
        $comprobante = ComprobanteElectronico::findOrFail($id);
        $comprobante->update($data);
        return $comprobante->fresh(['detalles', 'intentosEnvio']);
    }

    public function actualizarEstadoSunat(
        string $id,
        string $estado,
        ?string $codigo = null,
        ?string $mensaje = null
    ): bool {
        $data = ['estado_sunat' => $estado];

        if ($codigo !== null) {
            $data['codigo_sunat'] = $codigo;
        }

        if ($mensaje !== null) {
            $data['mensaje_sunat'] = $mensaje;
        }

        if ($estado === 'enviado' || $estado === 'aceptado') {
            $data['fecha_envio_sunat'] = now();
        }

        return ComprobanteElectronico::where('id', $id)->update($data);
    }

    public function guardarArchivos(
        string $id,
        ?string $xmlPath = null,
        ?string $cdrPath = null,
        ?string $hashCpe = null,
        ?string $hashCdr = null
    ): bool {
        $data = [];

        if ($xmlPath !== null) {
            $data['xml_path'] = $xmlPath;
        }

        if ($cdrPath !== null) {
            $data['cdr_path'] = $cdrPath;
        }

        if ($hashCpe !== null) {
            $data['hash_cpe'] = $hashCpe;
        }

        if ($hashCdr !== null) {
            $data['hash_cdr'] = $hashCdr;
        }

        return ComprobanteElectronico::where('id', $id)->update($data);
    }

    public function registrarIntentoEnvio(
        string $comprobanteId,
        bool $exitoso,
        ?string $codigoRespuesta = null,
        ?string $mensajeRespuesta = null,
        ?string $detalleError = null,
        string $modoEnvio = 'manual'
    ): void {
        IntentoEnvioSunat::create([
            'comprobante_id' => $comprobanteId,
            'fecha_intento' => now(),
            'exitoso' => $exitoso,
            'codigo_respuesta' => $codigoRespuesta,
            'mensaje_respuesta' => $mensajeRespuesta,
            'detalle_error' => $detalleError,
            'modo_envio' => $modoEnvio,
        ]);
    }
}
