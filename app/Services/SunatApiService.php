<?php

namespace App\Services;

use App\Models\Empresa;
use App\Services\Interfaces\SunatApiServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SunatApiService implements SunatApiServiceInterface
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('sunat-api.url'), '/') . '/api/v1';
    }

    private function getEmpresa(): array
    {
        $empresa = Empresa::first();

        if (!$empresa) {
            $modo = 'beta';
            return [
                'ruc' => '20000000001',
                'usuario' => 'MODDATOS',
                'clave' => 'moddatos',
                'razon_social' => 'MI EMPRESA SAC',
                'nombreComercial' => 'MI EMPRESA',
                'direccion' => 'AV. EJEMPLO 123',
                'ubigeo' => '150101',
                'distrito' => 'LIMA',
                'provincia' => 'LIMA',
                'departamento' => 'LIMA',
                'modo' => $modo,
                'client_id' => '',
                'secret_client' => '',
            ];
        }

        $modo = $empresa->sunat_modo ?? 'beta';
        $ruc = $modo === 'beta' ? '20000000001' : $empresa->ruc;

        return [
            'ruc' => $ruc,
            'usuario' => $modo === 'beta' ? 'MODDATOS' : ($empresa->sol_user ?? ''),
            'clave' => $modo === 'beta' ? 'moddatos' : ($empresa->sol_pass ?? ''),
            'razon_social' => $empresa->razon_social,
            'nombreComercial' => $empresa->nombre_comercial,
            'direccion' => $empresa->direccion,
            'ubigeo' => $empresa->ubigeo ?? '150101',
            'distrito' => $empresa->distrito ?? 'LIMA',
            'provincia' => $empresa->provincia ?? 'LIMA',
            'departamento' => $empresa->departamento ?? 'LIMA',
            'modo' => $modo,
            'client_id' => $empresa->sunat_client_id ?? '',
            'secret_client' => $empresa->sunat_secret_client ?? '',
        ];
    }

    public function generarXmlFactura(array $data): string
    {
        $payload = $this->buildComprobantePayload($data);
        $response = Http::timeout(60)->post("{$this->baseUrl}/generar/comprobante", $payload);

        if ($response->failed()) {
            throw new \Exception("Error al generar XML: " . $response->body());
        }

        $result = $response->json();
        if (!($result['estado'] ?? false)) {
            throw new \Exception($result['mensaje'] ?? 'Error desconocido al generar XML');
        }

        return $result['data']['contenido_xml'] ?? '';
    }

    public function generarYEnviarFactura(array $data): array
    {
        try {
            $xmlResult = $this->callGenerarComprobante($data);
            $sendResult = $this->callEnviarDocumento(
                $xmlResult['nombre_archivo'],
                $xmlResult['contenido_xml']
            );

            $cdrContent = $sendResult['cdr'] ?? '';

            return [
                'success' => true,
                'xml' => $xmlResult['contenido_xml'],
                'cdr' => $cdrContent,
                'hash_cpe' => $xmlResult['hash'],
                'hash_cdr' => hash('sha256', base64_decode($cdrContent) ?: $cdrContent),
                'codigo_sunat' => '0',
                'mensaje_sunat' => $sendResult['mensaje'] ?: 'La Factura ha sido aceptada',
                'modo' => strtoupper($this->getEmpresa()['modo']),
            ];
        } catch (\Exception $e) {
            Log::error('[SunatApiService] Error generarYEnviarFactura', ['error' => $e->getMessage()]);
            return [
                'success' => false, 'xml' => '', 'cdr' => '',
                'hash_cpe' => '', 'hash_cdr' => '',
                'codigo_sunat' => '98', 'mensaje_sunat' => $e->getMessage(),
                'modo' => strtoupper($this->getEmpresa()['modo']),
            ];
        }
    }

    public function generarXmlNotaCredito(array $data): string
    {
        $payload = $this->buildNotaPayload($data, 'credito');
        $response = Http::timeout(60)->post("{$this->baseUrl}/generar/nota", $payload);

        if ($response->failed()) {
            throw new \Exception("Error al generar XML Nota Crédito: " . $response->body());
        }

        $result = $response->json();
        if (!($result['estado'] ?? false)) {
            throw new \Exception($result['mensaje'] ?? 'Error desconocido al generar XML');
        }

        return $result['data']['contenido_xml'] ?? '';
    }

    public function generarYEnviarNotaCredito(array $data): array
    {
        try {
            $payload = $this->buildNotaPayload($data, 'credito');
            $response = Http::timeout(60)->post("{$this->baseUrl}/generar/nota", $payload);

            if ($response->failed()) {
                throw new \Exception("Error al generar Nota Crédito: " . $response->body());
            }

            $xmlResult = $response->json();
            if (!($xmlResult['estado'] ?? false)) {
                throw new \Exception($xmlResult['mensaje'] ?? 'Error al generar XML');
            }

            $sendResult = $this->callEnviarDocumento(
                $xmlResult['data']['nombre_archivo'],
                $xmlResult['data']['contenido_xml']
            );

            $cdrContent = $sendResult['cdr'] ?? '';

            return [
                'success' => true,
                'xml' => $xmlResult['data']['contenido_xml'],
                'cdr' => $cdrContent,
                'hash_cpe' => $xmlResult['data']['hash'],
                'hash_cdr' => hash('sha256', base64_decode($cdrContent) ?: $cdrContent),
                'codigo_sunat' => '0',
                'mensaje_sunat' => $sendResult['mensaje'] ?: 'Nota de Crédito aceptada',
                'modo' => strtoupper($this->getEmpresa()['modo']),
            ];
        } catch (\Exception $e) {
            Log::error('[SunatApiService] Error generarYEnviarNotaCredito', ['error' => $e->getMessage()]);
            return [
                'success' => false, 'xml' => '', 'cdr' => '',
                'hash_cpe' => '', 'hash_cdr' => '',
                'codigo_sunat' => '98', 'mensaje_sunat' => $e->getMessage(),
                'modo' => strtoupper($this->getEmpresa()['modo']),
            ];
        }
    }

    public function generarXmlNotaDebito(array $data): string
    {
        $payload = $this->buildNotaPayload($data, 'debito');
        $response = Http::timeout(60)->post("{$this->baseUrl}/generar/nota", $payload);

        if ($response->failed()) {
            throw new \Exception("Error al generar XML Nota Débito: " . $response->body());
        }

        $result = $response->json();
        if (!($result['estado'] ?? false)) {
            throw new \Exception($result['mensaje'] ?? 'Error desconocido al generar XML');
        }

        return $result['data']['contenido_xml'] ?? '';
    }

    public function generarYEnviarNotaDebito(array $data): array
    {
        try {
            $payload = $this->buildNotaPayload($data, 'debito');
            $response = Http::timeout(60)->post("{$this->baseUrl}/generar/nota", $payload);

            if ($response->failed()) {
                throw new \Exception("Error al generar Nota Débito: " . $response->body());
            }

            $xmlResult = $response->json();
            if (!($xmlResult['estado'] ?? false)) {
                throw new \Exception($xmlResult['mensaje'] ?? 'Error al generar XML');
            }

            $sendResult = $this->callEnviarDocumento(
                $xmlResult['data']['nombre_archivo'],
                $xmlResult['data']['contenido_xml']
            );

            $cdrContent = $sendResult['cdr'] ?? '';

            return [
                'success' => true,
                'xml' => $xmlResult['data']['contenido_xml'],
                'cdr' => $cdrContent,
                'hash_cpe' => $xmlResult['data']['hash'],
                'hash_cdr' => hash('sha256', base64_decode($cdrContent) ?: $cdrContent),
                'codigo_sunat' => '0',
                'mensaje_sunat' => $sendResult['mensaje'] ?: 'Nota de Débito aceptada',
                'modo' => strtoupper($this->getEmpresa()['modo']),
            ];
        } catch (\Exception $e) {
            Log::error('[SunatApiService] Error generarYEnviarNotaDebito', ['error' => $e->getMessage()]);
            return [
                'success' => false, 'xml' => '', 'cdr' => '',
                'hash_cpe' => '', 'hash_cdr' => '',
                'codigo_sunat' => '98', 'mensaje_sunat' => $e->getMessage(),
                'modo' => strtoupper($this->getEmpresa()['modo']),
            ];
        }
    }

    public function generarXmlGuiaRemision(array $data): string
    {
        $payload = $this->buildGuiaPayload($data);
        $response = Http::timeout(60)->post("{$this->baseUrl}/generar/guia/remision", $payload);

        if ($response->failed()) {
            throw new \Exception("Error al generar XML Guía: " . $response->body());
        }

        $result = $response->json();
        if (!($result['estado'] ?? false)) {
            throw new \Exception($result['mensaje'] ?? 'Error desconocido al generar XML');
        }

        return $result['data']['contenido_xml'] ?? '';
    }

    public function generarYEnviarGuiaRemision(array $data): array
    {
        try {
            $payload = $this->buildGuiaPayload($data);
            $response = Http::timeout(60)->post("{$this->baseUrl}/generar/guia/remision", $payload);

            if ($response->failed()) {
                throw new \Exception("Error al generar Guía: " . $response->body());
            }

            $xmlResult = $response->json();
            if (!($xmlResult['estado'] ?? false)) {
                throw new \Exception($xmlResult['mensaje'] ?? 'Error al generar XML');
            }

            $empresa = $this->getEmpresa();

            $sendPayload = [
                'endpoint' => $empresa['modo'],
                'ruc' => (int) $empresa['ruc'],
                'usuario' => $empresa['usuario'],
                'clave' => $empresa['clave'],
                'client_id' => $empresa['client_id'],
                'secret_client' => $empresa['secret_client'],
                'nombre_documento' => $xmlResult['data']['nombre_archivo'],
                'contenido_documento' => $xmlResult['data']['contenido_xml'],
            ];

            $sendResponse = Http::timeout(60)->post("{$this->baseUrl}/enviar/guia/remision", $sendPayload);

            if ($sendResponse->failed()) {
                throw new \Exception("Error al enviar Guía: " . $sendResponse->body());
            }

            $sendResult = $sendResponse->json();

            if (!($sendResult['estado'] ?? false)) {
                throw new \Exception($sendResult['mensaje'] ?? 'Error al enviar Guía a SUNAT');
            }

            $cdrContent = $sendResult['cdr'] ?? '';

            return [
                'success' => true,
                'xml' => $xmlResult['data']['contenido_xml'],
                'cdr' => $cdrContent,
                'hash_cpe' => $xmlResult['data']['hash'],
                'hash_cdr' => $cdrContent ? hash('sha256', base64_decode($cdrContent) ?: $cdrContent) : '',
                'codigo_sunat' => '0',
                'mensaje_sunat' => $sendResult['mensaje'] ?: 'Guía aceptada',
                'modo' => strtoupper($empresa['modo']),
                'ticker' => $sendResult['ticker'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('[SunatApiService] Error generarYEnviarGuiaRemision', ['error' => $e->getMessage()]);
            return [
                'success' => false, 'xml' => '', 'cdr' => '',
                'hash_cpe' => '', 'hash_cdr' => '',
                'codigo_sunat' => '98', 'mensaje_sunat' => $e->getMessage(),
                'modo' => strtoupper($this->getEmpresa()['modo']),
            ];
        }
    }

    public function consultarEstado(string $ruc, string $tipoDoc, string $serie, string $numero): array
    {
        return [
            'success' => false,
            'estado' => 'NO_DISPONIBLE',
            'mensaje' => 'Consulta directa a SUNAT no disponible con API externa.',
        ];
    }

    public function esModoSimulacion(): bool
    {
        return false;
    }

    public function obtenerDatosEmpresa(): array
    {
        return $this->getEmpresa();
    }

    private function callGenerarComprobante(array $data): array
    {
        $payload = $this->buildComprobantePayload($data);
        $response = Http::timeout(60)->post("{$this->baseUrl}/generar/comprobante", $payload);

        if ($response->failed()) {
            throw new \Exception("Error al generar comprobante: " . $response->body());
        }

        $result = $response->json();
        if (!($result['estado'] ?? false)) {
            throw new \Exception($result['mensaje'] ?? 'Error desconocido');
        }

        return $result['data'];
    }

    private function callEnviarDocumento(string $nombreDocumento, string $contenidoDocumento): array
    {
        $empresa = $this->getEmpresa();

        $payload = [
            'endpoint' => $empresa['modo'],
            'ruc' => (int) $empresa['ruc'],
            'usuario' => $empresa['usuario'],
            'clave' => $empresa['clave'],
            'nombre_documento' => $nombreDocumento,
            'contenido_documento' => $contenidoDocumento,
        ];

        $response = Http::timeout(60)->post("{$this->baseUrl}/enviar/documento/electronico", $payload);

        if ($response->failed()) {
            $body = $response->json();
            $mensaje = $body['mensaje'] ?? $response->body();
            throw new \Exception($mensaje);
        }

        $result = $response->json();

        if (!($result['estado'] ?? false)) {
            throw new \Exception($result['mensaje'] ?? 'Error al enviar a SUNAT');
        }

        return $result;
    }

    private function buildComprobantePayload(array $data): array
    {
        $empresa = $this->getEmpresa();
        $cliente = $data['cliente'] ?? [];
        $items = $data['items'] ?? [];

        $detalles = [];
        foreach ($items as $item) {
            $detalles[] = [
                'cod_producto' => $item['codigo'] ?? '',
                'unidad' => $item['unidad'] ?? 'NIU',
                'descripcion' => $item['descripcion'] ?? '',
                'cantidad' => (float) ($item['cantidad'] ?? 1),
                'precio' => (float) ($item['precio_unitario'] ?? $item['valor_unitario'] ?? 0),
            ];
        }

        return [
            'endpoint' => $empresa['modo'],
            'documento' => ($data['tipo_doc'] ?? '01') === '01' ? 'factura' : 'boleta',
            'empresa' => [
                'ruc' => (int) $empresa['ruc'],
                'usuario' => $empresa['usuario'],
                'clave' => $empresa['clave'],
                'razon_social' => $empresa['razon_social'],
                'nombreComercial' => $empresa['nombreComercial'],
                'direccion' => $empresa['direccion'],
                'ubigeo' => $empresa['ubigeo'],
                'distrito' => $empresa['distrito'],
                'provincia' => $empresa['provincia'],
                'departamento' => $empresa['departamento'],
            ],
            'cliente' => [
                'num_doc' => $cliente['num_doc'] ?? '',
                'rzn_social' => $cliente['razon_social'] ?? '',
                'tipo_doc' => $cliente['tipo_doc'] ?? '1',
                'direccion' => $cliente['direccion'] ?? '-',
            ],
            'serie' => $data['serie'] ?? '',
            'correlativo' => $data['numero'] ?? '1',
            'fecha_emision' => $data['fecha'] ?? now()->format('Y-m-d'),
            'moneda' => $data['tipo_moneda'] ?? 'PEN',
            'detalles' => $detalles,
        ];
    }

    private function buildNotaPayload(array $data, string $tipo): array
    {
        $empresa = $this->getEmpresa();
        $cliente = $data['cliente'] ?? [];
        $items = $data['items'] ?? [];

        $detalles = [];
        foreach ($items as $item) {
            $detalles[] = [
                'cod_producto' => $item['codigo'] ?? '',
                'unidad' => $item['unidad'] ?? 'NIU',
                'descripcion' => $item['descripcion'] ?? '',
                'cantidad' => (float) ($item['cantidad'] ?? 1),
                'precio' => (float) ($item['precio_unitario'] ?? $item['valor_unitario'] ?? 0),
            ];
        }

        return [
            'endpoint' => $empresa['modo'],
            'documento' => $tipo,
            'empresa' => [
                'ruc' => (int) $empresa['ruc'],
                'usuario' => $empresa['usuario'],
                'clave' => $empresa['clave'],
                'razon_social' => $empresa['razon_social'],
                'nombreComercial' => $empresa['nombreComercial'],
                'direccion' => $empresa['direccion'],
                'ubigeo' => $empresa['ubigeo'],
                'distrito' => $empresa['distrito'],
                'provincia' => $empresa['provincia'],
                'departamento' => $empresa['departamento'],
            ],
            'cliente' => [
                'num_doc' => $cliente['num_doc'] ?? '',
                'rzn_social' => $cliente['razon_social'] ?? '',
                'tipo_doc' => $cliente['tipo_doc'] ?? '1',
                'direccion' => $cliente['direccion'] ?? '-',
            ],
            'serie' => $data['serie'] ?? '',
            'correlativo' => $data['numero'] ?? '1',
            'fecha_emision' => $data['fecha'] ?? now()->format('Y-m-d'),
            'moneda' => $data['tipo_moneda'] ?? 'PEN',
            'serie_numero_afectado' => $data['num_doc_afectado'] ?? '',
            'cod_motivo' => $data['cod_motivo'] ?? '',
            'des_motivo' => $data['des_motivo'] ?? '',
            'tipo_doc_afectado' => $data['tipo_doc_afectado'] ?? '01',
            'detalles' => $detalles,
        ];
    }

    private function buildGuiaPayload(array $data): array
    {
        $empresa = $this->getEmpresa();
        $cliente = $data['destinatario'] ?? $data['cliente'] ?? [];
        $items = $data['items'] ?? [];
        $transportista = $data['transportista'] ?? null;

        $detalles = [];
        foreach ($items as $item) {
            $detalles[] = [
                'cod_producto' => $item['codigo'] ?? '',
                'unidad' => $item['unidad'] ?? 'NIU',
                'descripcion' => $item['descripcion'] ?? '',
                'cantidad' => (float) ($item['cantidad'] ?? 1),
            ];
        }

        $datosEnvio = [
            'cod_traslado' => $data['cod_traslado'] ?? '01',
            'mod_traslado' => $data['mod_traslado'] ?? '01',
            'fecha_traslado' => $data['fecha_traslado'] ?? $data['fecha'] ?? now()->format('Y-m-d'),
            'peso_total' => (float) ($data['peso_total'] ?? 0.1),
            'unidad_medida' => 'KGM',
            'ubigeo_salida' => $data['ubigeo_partida'] ?? $empresa['ubigeo'],
            'direccion_salida' => $data['direccion_partida'] ?? $empresa['direccion'],
            'ubigeo_llegada' => $data['ubigeo_llegada'] ?? $empresa['ubigeo'],
            'direccion_llegada' => $data['direccion_llegada'] ?? '',
        ];

        $payload = [
            'endpoint' => $empresa['modo'],
            'documento' => $data['tipo_guia'] ?? 'remitente',
            'empresa' => [
                'ruc' => (int) $empresa['ruc'],
                'usuario' => $empresa['usuario'],
                'clave' => $empresa['clave'],
                'razon_social' => $empresa['razon_social'],
                'nombreComercial' => $empresa['nombreComercial'],
                'direccion' => $empresa['direccion'],
                'ubigeo' => $empresa['ubigeo'],
                'distrito' => $empresa['distrito'],
                'provincia' => $empresa['provincia'],
                'departamento' => $empresa['departamento'],
            ],
            'cliente' => [
                'num_doc' => $cliente['num_doc'] ?? '',
                'rzn_social' => $cliente['razon_social'] ?? '',
                'tipo_doc' => $cliente['tipo_doc'] ?? '1',
                'direccion' => $cliente['direccion'] ?? '-',
            ],
            'serie' => $data['serie'] ?? 'T001',
            'correlativo' => $data['correlativo'] ?? '1',
            'fecha_emision' => $data['fecha_emision'] ?? now()->format('Y-m-d'),
            'datos_envio' => $datosEnvio,
            'detalles' => $detalles,
        ];

        if ($transportista) {
            $payload['transportista'] = [
                'num_doc' => $transportista['num_doc'] ?? '',
                'rzn_social' => $transportista['razon_social'] ?? $cliente['razon_social'] ?? '',
                'nro_mtc' => $transportista['nro_mtc'] ?? '',
                'placa' => $data['vehiculo_placa'] ?? '',
            ];
        }

        $choferes = $data['choferes'] ?? [];
        if (!empty($choferes)) {
            $chofer = $choferes[0];
            $payload['transportista']['placa_chofer'] = $data['vehiculo_placa'] ?? ($chofer['placa'] ?? '');
            $payload['transportista']['dni_chofer'] = $chofer['nro_doc'] ?? '';
            $payload['transportista']['nombre_chofer'] = $chofer['nombres'] ?? '';
            $payload['transportista']['apellido_chofer'] = $chofer['apellidos'] ?? '';
            $payload['transportista']['licencia_chofer'] = $chofer['licencia'] ?? '';
        }

        return $payload;
    }

    private function extraerCodigoSunat(string $cdrBase64): string
    {
        if (empty($cdrBase64)) return '98';
        try {
            $zipContent = base64_decode($cdrBase64, true);
            if ($zipContent === false) return '98';

            $zip = new \ZipArchive();
            $tempFile = tempnam(sys_get_temp_dir(), 'cdr_');
            file_put_contents($tempFile, $zipContent);
            $zip->open($tempFile);

            $cdrXml = $zip->getFromIndex(0);
            $zip->close();
            unlink($tempFile);

            if ($cdrXml && preg_match('/<cbc:ResponseCode>(.*?)<\/cbc:ResponseCode>/', $cdrXml, $m)) {
                return $m[1];
            }
        } catch (\Exception $e) {
        }
        return '0';
    }
}
