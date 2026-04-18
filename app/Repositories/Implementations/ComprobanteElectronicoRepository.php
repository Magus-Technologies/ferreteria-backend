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

    public function findBySerieCorrelativo(string $serie, int $correlativo): ?ComprobanteElectronico
    {
        return ComprobanteElectronico::where('serie', $serie)
            ->where('correlativo', $correlativo)
            ->with(['detalles', 'intentosEnvio'])
            ->first();
    }

    public function findByDocumento(string $tipoDocumento, string $documentoId): ?ComprobanteElectronico
    {
        // NOTA: En producción NO hay venta_id, esta función busca por cliente
        // Se mantiene por compatibilidad pero no es funcional con la estructura real
        return null;
    }

    public function findBySerieNumero(string $serie, int $numero): ?ComprobanteElectronico
    {
        // Alias para findBySerieCorrelativo
        return $this->findBySerieCorrelativo($serie, $numero);
    }

    public function getByTipoDocumento(string $tipoDocumento): Collection
    {
        return ComprobanteElectronico::where('tipo_comprobante', $tipoDocumento)
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
        return ComprobanteElectronico::where('estado_sunat', 'PENDIENTE')
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
            $data['codigo_respuesta_sunat'] = $codigo;
        }

        if ($mensaje !== null) {
            $data['mensaje_respuesta_sunat'] = $mensaje;
        }

        if (in_array($estado, ['ACEPTADO', 'ACEPTADO_CON_OBSERVACIONES', 'RECHAZADO'])) {
            $data['fecha_respuesta_sunat'] = now();
        }

        if ($estado === 'PROCESANDO') {
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
        // Obtener el ultimo número de intento para este comprobante
        $ultimoIntento = IntentoEnvioSunat::where('comprobante_id', $comprobanteId)
            ->max('numero_intento') ?? 0;
        
        IntentoEnvioSunat::create([
            'comprobante_id' => $comprobanteId, // ✅ Fixed: was comprobante_electronico_id
            'numero_intento' => $ultimoIntento + 1,
            'fecha_intento' => now(),
            'resultado' => $exitoso ? 'exitoso' : 'fallido', // ✅ Fixed: was 'exitoso' boolean
            'codigo_respuesta' => $codigoRespuesta,
            'mensaje_respuesta' => $mensajeRespuesta ?? $detalleError,
            'ticket_numero' => null,
        ]);
    }

    public function obtenerSiguienteCorrelativo(string $serie): int
    {
        $ultimo = ComprobanteElectronico::where('serie', $serie)
            ->orderBy('correlativo', 'desc')
            ->first();

        return $ultimo ? $ultimo->correlativo + 1 : 1;
    }
}
