<?php

namespace App\Services;

use App\Models\Empresa;
use App\Services\Interfaces\SunatApiServiceInterface;
use Illuminate\Support\Facades\DB;
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

            // La GRE-API de SUNAT es asíncrona: `enviar/guia/remision` SOLO
            // entrega un ticket (nunca un CDR) — SUNAT procesa aparte y hay
            // que consultar ese ticket después (ver consultarTicketGuia) para
            // recién ahí obtener el CDR real. Antes acá se leía
            // `$sendResult['cdr']`, que el microservicio nunca devuelve para
            // guías, así que quedaba vacío y el hash se calculaba sobre string
            // vacío — parecía "aceptado" sin que SUNAT hubiera confirmado nada.
            return [
                'success' => true,
                'xml' => $xmlResult['data']['contenido_xml'],
                'hash_cpe' => $xmlResult['data']['hash'],
                'codigo_sunat' => '0',
                'mensaje_sunat' => $sendResult['mensaje'] ?: 'Ticket generado, pendiente de confirmación SUNAT',
                'modo' => strtoupper($empresa['modo']),
                'ticket' => $sendResult['ticker'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('[SunatApiService] Error generarYEnviarGuiaRemision', ['error' => $e->getMessage()]);
            return [
                'success' => false, 'xml' => '',
                'hash_cpe' => '',
                'codigo_sunat' => '98', 'mensaje_sunat' => $e->getMessage(),
                'modo' => strtoupper($this->getEmpresa()['modo']),
            ];
        }
    }

    /**
     * Consulta en SUNAT el resultado de un ticket de guía de remisión
     * (GRE-API), obtenido previamente en `generarYEnviarGuiaRemision()`.
     * SUNAT puede tardar en procesar el ticket — mientras no esté listo,
     * `success` viene en `false` sin que eso signifique un rechazo real.
     */
    public function consultarTicketGuia(string $ticket): array
    {
        try {
            $empresa = $this->getEmpresa();

            $payload = [
                'endpoint' => $empresa['modo'],
                'ruc' => (int) $empresa['ruc'],
                'usuario' => $empresa['usuario'],
                'clave' => $empresa['clave'],
                'client_id' => $empresa['client_id'],
                'secret_client' => $empresa['secret_client'],
            ];

            $response = Http::timeout(60)->post("{$this->baseUrl}/consulta/documento/ticker/{$ticket}", $payload);

            if ($response->failed()) {
                throw new \Exception('Error al consultar ticket: ' . $response->body());
            }

            $result = $response->json();

            if (!($result['estado'] ?? false)) {
                return [
                    'success' => false,
                    'mensaje_sunat' => $result['mensaje'] ?? 'SUNAT todavía está procesando el ticket',
                ];
            }

            return [
                'success' => true,
                'cdr' => $result['cdr'] ?? '',
                'mensaje_sunat' => $result['mensaje'] ?? 'Aceptado',
            ];
        } catch (\Exception $e) {
            Log::error('[SunatApiService] Error consultarTicketGuia', ['ticket' => $ticket, 'error' => $e->getMessage()]);
            return [
                'success' => false,
                'mensaje_sunat' => $e->getMessage(),
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

    public function generarXmlComunicacionBaja(array $data): string
    {
        $payload = $this->buildComunicacionBajaPayload($data);
        $response = Http::timeout(60)->post("{$this->baseUrl}/generar/comunicacion/baja", $payload);

        if ($response->failed()) {
            throw new \Exception("Error al generar XML de comunicación de baja: " . $response->body());
        }

        $result = $response->json();
        if (!($result['estado'] ?? false)) {
            throw new \Exception($result['mensaje'] ?? 'Error desconocido al generar XML de baja');
        }

        return $result['data']['contenido_xml'] ?? '';
    }

    /**
     * Comunicación de Baja (VoidedDocuments) es un flujo ASÍNCRONO en SUNAT:
     * se manda, se recibe un ticket, y hay que consultar getStatus() con
     * reintentos hasta que el CDR esté listo. El microservicio ya tiene un
     * endpoint dedicado (`/enviar/comunicacion/baja`) que hace exactamente
     * eso (ticket + polling con backoff). Antes esto pasaba por el flujo de
     * facturas/boletas (`/generar/comunicacion/baja` + `/enviar/documento/
     * electronico`, ese último pensado para el envío SÍNCRONO de un CPE) —
     * alimentarlo con un XML de baja (RA) no es lo que espera, y SUNAT lo
     * rechazaba con un error genérico sin detalle.
     */
    public function generarYEnviarComunicacionBaja(array $data): array
    {
        try {
            $payload = $this->buildComunicacionBajaPayload($data);
            // Timeout generoso: el microservicio ya hace polling interno con
            // hasta 5 reintentos (3+5+8+10+15 = 41s) esperando el CDR de SUNAT.
            $response = Http::timeout(120)->post("{$this->baseUrl}/enviar/comunicacion/baja", $payload);

            if ($response->failed()) {
                throw new \Exception('Error al enviar comunicación de baja: ' . $response->body());
            }

            $result = $response->json();
            if (!($result['estado'] ?? false)) {
                $mensaje = $result['mensaje'] ?? 'Error desconocido al enviar comunicación de baja';
                if ($result['pendiente'] ?? false) {
                    $mensaje .= ' (SUNAT todavía está procesando el ticket; reintentar en unos minutos)';
                }
                throw new \Exception($mensaje);
            }

            $xml = $result['contenido_xml'] ?? '';
            $cdrContent = $result['cdr'] ?? '';

            return [
                'success' => true,
                'xml' => $xml,
                'cdr' => $cdrContent,
                'hash_cpe' => $xml ? hash('sha256', $xml) : '',
                'hash_cdr' => hash('sha256', base64_decode($cdrContent) ?: $cdrContent),
                'codigo_sunat' => '0',
                'mensaje_sunat' => $result['mensaje'] ?: 'Comunicación de Baja aceptada',
                'modo' => strtoupper($this->getEmpresa()['modo']),
            ];
        } catch (\Exception $e) {
            Log::error('[SunatApiService] Error generarYEnviarComunicacionBaja', ['error' => $e->getMessage()]);
            return [
                'success' => false, 'xml' => '', 'cdr' => '',
                'hash_cpe' => '', 'hash_cdr' => '',
                'codigo_sunat' => '98', 'mensaje_sunat' => $e->getMessage(),
                'modo' => strtoupper($this->getEmpresa()['modo']),
            ];
        }
    }

    /**
     * Dar de baja una BOLETA vía Resumen Diario.
     *
     * SUNAT no acepta boletas (03) en Comunicación de Baja (VoidedDocuments)
     * — solo facturas/notas. Para boletas la baja se comunica con un Resumen
     * Diario que incluye esa boleta con `estado=3`.
     *
     * IMPORTANTE — `estado=3` es una MODIFICACIÓN: SUNAT solo puede dar de
     * baja algo que YA tiene registrado. Si la boleta nunca fue informada
     * (nunca llegó a ACEPTADO, ni por CPE individual ni por un resumen
     * previo), responde [2663] "El documento indicado no existe no puede ser
     * modificado". Por eso en ese caso este método hace DOS envíos:
     *   1) un resumen con `estado=1`, que declara la boleta ante SUNAT, y
     *   2) un resumen con `estado=3`, que la da de baja.
     *
     * El efecto tributario neto de los dos pasos es cero, pero entre uno y
     * otro la boleta queda declarada como válida. Si el paso 2 fallara, se
     * devuelve `declarada_previamente = true` para que el caller deje el
     * estado real en la base (ACEPTADO) y el reintento tome el camino simple
     * de un solo envío en vez de volver a declararla.
     */
    public function generarYEnviarResumenBaja(\App\Models\ComprobanteElectronico $comprobante): array
    {
        $declaradaEnEstaCorrida = false;

        try {
            $empresa = $this->getEmpresa();
            $hoy = now()->format('Y-m-d');
            $fechaEmision = \Illuminate\Support\Carbon::parse($comprobante->fecha_emision)->format('Y-m-d');

            $fueAceptadaAlgunaVez = in_array($comprobante->estado_sunat, ['ACEPTADO', 'ACEPTADO_CON_OBSERVACIONES']);

            // Fecha que va al `cbc:ReferenceDate` (ver nota en enviarResumen):
            //   - Boleta YA aceptada por CPE individual: la baja corrige un
            //     registro que para SUNAT está vigente hoy → hoy.
            //   - Boleta nunca informada: los dos envíos (declarar + dar de
            //     baja) corresponden al día de emisión real de la boleta.
            $fechaReferencia = $fueAceptadaAlgunaVez ? $hoy : $fechaEmision;

            if (! $fueAceptadaAlgunaVez) {
                $alta = $this->enviarResumen($comprobante, $empresa, 1, $fechaReferencia, $hoy);

                if (! $alta['success']) {
                    throw new \Exception(
                        'No se pudo declarar la boleta antes de darla de baja (SUNAT exige que exista para poder anularla): '
                        . $alta['mensaje']
                    );
                }

                $declaradaEnEstaCorrida = true;
            }

            $baja = $this->enviarResumen($comprobante, $empresa, 3, $fechaReferencia, $hoy);

            if (! $baja['success']) {
                throw new \Exception($baja['mensaje']);
            }

            $xml = $baja['xml'];
            $cdrContent = $baja['cdr'];

            return [
                'success' => true,
                'xml' => $xml,
                'cdr' => $cdrContent,
                'hash_cpe' => $xml ? hash('sha256', $xml) : '',
                'hash_cdr' => hash('sha256', base64_decode($cdrContent) ?: $cdrContent),
                'codigo_sunat' => '0',
                'mensaje_sunat' => $baja['mensaje'] ?: 'Boleta dada de baja vía Resumen Diario',
                'modo' => strtoupper($empresa['modo']),
            ];
        } catch (\Exception $e) {
            Log::error('[SunatApiService] Error generarYEnviarResumenBaja', [
                'comprobante_id' => $comprobante->id,
                'declarada_previamente' => $declaradaEnEstaCorrida,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false, 'xml' => '', 'cdr' => '',
                'hash_cpe' => '', 'hash_cdr' => '',
                'codigo_sunat' => '98', 'mensaje_sunat' => $e->getMessage(),
                'modo' => strtoupper($this->getEmpresa()['modo']),
                'declarada_previamente' => $declaradaEnEstaCorrida,
            ];
        }
    }

    /**
     * Envía UN Resumen Diario con un solo detalle para `$comprobante`, en el
     * `$estado` pedido (1 = declarar, 2 = modificar, 3 = dar de baja).
     *
     * OJO con los nombres de las fechas en el payload: van al REVÉS de lo que
     * sugieren. Confirmado leyendo el template real de Greenter
     * (vendor/greenter/xml/src/Xml/Templates/summary.xml.twig):
     *     <cbc:ReferenceDate>{{ doc.fecGeneracion }}</cbc:ReferenceDate>
     *     <cbc:IssueDate>{{ doc.fecResumen }}</cbc:IssueDate>
     * Es decir, 'fecha_generacion' termina siendo el ReferenceDate (la fecha
     * de los documentos que el resumen informa) y 'fecha_resumen' el IssueDate
     * (la fecha en que se emite ESTE resumen). SUNAT exige que el IssueDate
     * sea hoy: una fecha pasada devuelve [2671].
     */
    private function enviarResumen(
        \App\Models\ComprobanteElectronico $comprobante,
        array $empresa,
        int $estado,
        string $fechaReferencia,
        string $fechaIssue
    ): array {
        // Reintentos por si el contador de correlativos quedó desfasado
        // respecto de SUNAT: cada [0402] avanza al siguiente número.
        $maxIntentos = 5;

        for ($intento = 1; $intento <= $maxIntentos; $intento++) {
            $correlativo = $this->siguienteCorrelativoResumen($fechaIssue);

            $payload = [
                'endpoint' => $empresa['modo'],
                'correlativo' => (string) $correlativo,
                'fecha_generacion' => $fechaReferencia,
                'fecha_resumen' => $fechaIssue,
                'empresa' => [
                    'ruc' => (int) $empresa['ruc'],
                    'usuario' => $empresa['usuario'],
                    'clave' => $empresa['clave'],
                    'razon_social' => $empresa['razon_social'],
                    'direccion' => $empresa['direccion'],
                    'ubigeo' => $empresa['ubigeo'],
                    'distrito' => $empresa['distrito'],
                    'provincia' => $empresa['provincia'],
                    'departamento' => $empresa['departamento'],
                ],
                'detalles' => [[
                    'tipo_doc' => $comprobante->tipo_comprobante,
                    'serie_numero' => "{$comprobante->serie}-{$comprobante->correlativo}",
                    'estado' => $estado, // 1=declarar, 2=modificar, 3=baja
                    'tipo_doc_cliente' => (int) $comprobante->cliente_tipo_documento,
                    'num_doc_cliente' => (string) $comprobante->cliente_numero_documento,
                    'total' => (float) $comprobante->importe_total,
                    'mto_oper_gravadas' => (float) $comprobante->operacion_gravada,
                    'mto_igv' => (float) $comprobante->total_igv,
                    'mto_oper_exoneradas' => (float) $comprobante->operacion_exonerada,
                    'mto_oper_inafectas' => (float) $comprobante->operacion_inafecta,
                    'mto_otros_cargos' => (float) $comprobante->total_cargos,
                ]],
            ];

            // Timeout generoso: SUNAT procesa resúmenes de forma asíncrona
            // (ticket + consulta de estado) igual que la Comunicación de Baja.
            $response = Http::timeout(90)->post("{$this->baseUrl}/enviar/resumen", $payload);

            if ($response->failed()) {
                return [
                    'success' => false,
                    'mensaje' => 'Error HTTP al enviar el resumen: ' . $response->body(),
                    'xml' => '', 'cdr' => '',
                ];
            }

            $result = $response->json();

            if ($result['estado'] ?? false) {
                $this->confirmarCorrelativoResumen($fechaIssue, $correlativo);

                return [
                    'success' => true,
                    'mensaje' => $result['mensaje'] ?? '',
                    'xml' => $result['contenido_xml'] ?? '',
                    'cdr' => $result['cdr'] ?? '',
                ];
            }

            $mensaje = $result['mensaje'] ?? 'Error desconocido al enviar el resumen';
            $yaUsado = str_contains($mensaje, '0402');

            // ¿SUNAT llegó a RECIBIR el documento? Entonces el correlativo
            // quedó gastado aunque el resultado final no sea "aceptado", y hay
            // que registrarlo o el próximo envío lo reusa y choca con [0402].
            // Dos señales de que lo recibió:
            //   - viene 'ticket': el microservicio pasó del send() y falló
            //     recién al consultar el estado (ej. SUNAT todavía procesando,
            //     estado 98) — el envío YA entró.
            //   - [0402]: SUNAT dice explícitamente que ese número ya se envió.
            // Este era el agujero que hacía derivar el contador: un resumen
            // recibido pero no confirmado dejaba el número "libre" para el
            // sistema y ocupado para SUNAT.
            if ($yaUsado || ! empty($result['ticket'])) {
                $this->confirmarCorrelativoResumen($fechaIssue, $correlativo);
            }

            if ($yaUsado && $intento < $maxIntentos) {
                Log::warning('[SunatApiService] Correlativo de resumen ya usado en SUNAT, probando el siguiente', [
                    'comprobante_id' => $comprobante->id,
                    'correlativo_rechazado' => $correlativo,
                    'intento' => $intento,
                    'mensaje' => $mensaje,
                ]);

                continue;
            }

            // Loguear el payload y la respuesta CRUDA completa, no solo el
            // mensaje resumido: para diagnosticar rechazos de SUNAT hace falta
            // ver exactamente qué se mandó y qué contestaron.
            Log::error('[SunatApiService] SUNAT rechazó el resumen', [
                'comprobante_id' => $comprobante->id,
                'estado_detalle' => $estado,
                'payload_enviado' => $payload,
                'respuesta_cruda' => $result,
            ]);

            return [
                'success' => false,
                'mensaje' => $mensaje,
                'xml' => '', 'cdr' => '',
            ];
        }

        return [
            'success' => false,
            'mensaje' => "No se encontró un correlativo de resumen libre después de {$maxIntentos} intentos.",
            'xml' => '', 'cdr' => '',
        ];
    }

    /**
     * Próximo correlativo de Resumen Diario para la fecha de emisión del
     * resumen. SUNAT lo exige único por (RUC, fecha): forma parte del nombre
     * del ZIP (RC-{fecha}-{correlativo}) y repetirlo devuelve "[99] nombre del
     * archivo ZIP incorrecto".
     *
     * No se persiste acá — se confirma con confirmarCorrelativoResumen() solo
     * si SUNAT aceptó, para que un rechazo no queme números.
     */
    private function siguienteCorrelativoResumen(string $fecha): int
    {
        $ultimo = DB::table('sunat_resumen_correlativo')->where('fecha', $fecha)->value('ultimo');

        if ($ultimo === null) {
            // Primera vez para esta fecha: arrancar después de los correlativos
            // que ya se gastaron con el mecanismo viejo (una baja = un envío),
            // contando las bajas aceptadas de ese día.
            $ultimo = \App\Models\ComprobanteElectronico::where('tipo_comprobante', '03')
                ->where('estado_sunat', 'BAJA_ACEPTADA')
                ->whereDate('fecha_respuesta_sunat', $fecha)
                ->count();
        }

        return ((int) $ultimo) + 1;
    }

    private function confirmarCorrelativoResumen(string $fecha, int $correlativo): void
    {
        DB::table('sunat_resumen_correlativo')->updateOrInsert(
            ['fecha' => $fecha],
            ['ultimo' => $correlativo]
        );
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
        // Remitente — solo aplica a GRE-Transportista (lo arma el caller).
        $remitente = $data['remitente'] ?? null;

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

        // Remitente — solo se incluye si es GRE-Transportista. La API SUNAT
        // externa lo mapea a Greenter `setTercero` (tipoDoc='31').
        if ($remitente) {
            $payload['remitente'] = [
                'num_doc' => (string) ($remitente['num_doc'] ?? ''),
                'rzn_social' => $remitente['razon_social'] ?? '',
                'tipo_doc' => $remitente['tipo_doc'] ?? '6',
                'direccion' => $remitente['direccion'] ?? '-',
            ];
        }

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

    private function buildComunicacionBajaPayload(array $data): array
    {
        $empresa = $this->getEmpresa();
        $detalles = $data['detalles'] ?? [];

        $detallesFormateados = [];
        foreach ($detalles as $item) {
            $detallesFormateados[] = [
                'tipo_doc' => $item['tipo_doc'] ?? '03',
                'serie' => $item['serie'] ?? '',
                'correlativo' => $item['correlativo'] ?? '',
                'motivo' => $item['motivo'] ?? '',
            ];
        }

        return [
            'endpoint' => $empresa['modo'],
            'correlativo' => $data['correlativo'] ?? '001',
            'fecha_generacion' => $data['fecha_generacion'] ?? now()->format('Y-m-d'),
            'fecha_comunicacion' => $data['fecha_comunicacion'] ?? now()->format('Y-m-d'),
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
            'detalles' => $detallesFormateados,
        ];
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
