<?php

namespace App\Services;

use App\Services\Interfaces\SunatApiServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SunatApiService implements SunatApiServiceInterface
{
    private string $baseUrl;
    private string $endpoint;
    private array $empresa;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('sunat-api.url'), '/') . '/api/v1';
        $this->endpoint = config('sunat-api.endpoint', 'beta');
        $this->empresa = [
            'ruc' => config('sunat-api.ruc'),
            'usuario' => config('sunat-api.sol_user'),
            'clave' => config('sunat-api.sol_pass'),
            'razon_social' => config('sunat-api.razon_social'),
            'nombreComercial' => config('sunat-api.nombre_comercial'),
            'direccion' => config('sunat-api.direccion'),
            'ubigeo' => config('sunat-api.ubigeo'),
            'distrito' => config('sunat-api.distrito'),
            'provincia' => config('sunat-api.provincia'),
            'departamento' => config('sunat-api.departamento'),
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
                'modo' => $this->obtenerModo(),
            ];
        } catch (\Exception $e) {
            Log::error('[SunatApiService] Error generarYEnviarFactura', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'xml' => '',
                'cdr' => '',
                'hash_cpe' => '',
                'hash_cdr' => '',
                'codigo_sunat' => '98',
                'mensaje_sunat' => $e->getMessage(),
                'modo' => $this->obtenerModo(),
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
                'modo' => $this->obtenerModo(),
            ];
        } catch (\Exception $e) {
            Log::error('[SunatApiService] Error generarYEnviarNotaCredito', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'xml' => '',
                'cdr' => '',
                'hash_cpe' => '',
                'hash_cdr' => '',
                'codigo_sunat' => '98',
                'mensaje_sunat' => $e->getMessage(),
                'modo' => $this->obtenerModo(),
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
                'modo' => $this->obtenerModo(),
            ];
        } catch (\Exception $e) {
            Log::error('[SunatApiService] Error generarYEnviarNotaDebito', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'xml' => '',
                'cdr' => '',
                'hash_cpe' => '',
                'hash_cdr' => '',
                'codigo_sunat' => '98',
                'mensaje_sunat' => $e->getMessage(),
                'modo' => $this->obtenerModo(),
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

            $sendPayload = [
                'endpoint' => $this->endpoint,
                'ruc' => (int) $this->empresa['ruc'],
                'usuario' => $this->empresa['usuario'],
                'clave' => $this->empresa['clave'],
                'client_id' => config('sunat-api.client_id'),
                'secret_client' => config('sunat-api.secret_client'),
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
                'modo' => $this->obtenerModo(),
                'ticker' => $sendResult['ticker'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('[SunatApiService] Error generarYEnviarGuiaRemision', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'xml' => '',
                'cdr' => '',
                'hash_cpe' => '',
                'hash_cdr' => '',
                'codigo_sunat' => '98',
                'mensaje_sunat' => $e->getMessage(),
                'modo' => $this->obtenerModo(),
            ];
        }
    }

    public function consultarEstado(string $ruc, string $tipoDoc, string $serie, string $numero): array
    {
        return [
            'success' => false,
            'estado' => 'NO_DISPONIBLE',
            'mensaje' => 'Consulta directa a SUNAT no disponible con API externa. Verifique el estado en el CDR almacenado.',
        ];
    }

    public function esModoSimulacion(): bool
    {
        return false;
    }

    public function obtenerDatosEmpresa(): array
    {
        return $this->empresa;
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
        $payload = [
            'endpoint' => $this->endpoint,
            'ruc' => (int) $this->empresa['ruc'],
            'usuario' => $this->empresa['usuario'],
            'clave' => $this->empresa['clave'],
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
            'endpoint' => $this->endpoint,
            'documento' => ($data['tipo_doc'] ?? '01') === '01' ? 'factura' : 'boleta',
            'empresa' => $this->empresa,
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
            'endpoint' => $this->endpoint,
            'documento' => $tipo,
            'empresa' => $this->empresa,
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
            'ubigeo_salida' => $data['ubigeo_partida'] ?? $this->empresa['ubigeo'],
            'direccion_salida' => $data['direccion_partida'] ?? $this->empresa['direccion'],
            'ubigeo_llegada' => $data['ubigeo_llegada'] ?? $this->empresa['ubigeo'],
            'direccion_llegada' => $data['direccion_llegada'] ?? '',
        ];

        $payload = [
            'endpoint' => $this->endpoint,
            'documento' => $data['tipo_guia'] ?? 'remitente',
            'empresa' => $this->empresa,
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

    private function obtenerModo(): string
    {
        return 'PRODUCCION';
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
