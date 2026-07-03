<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Consulta el Registro Nacional de Transporte de Mercancías del MTC por RUC.
 *
 * El portal del MTC no expone API pública: se scrapea el formulario ASP.NET
 * (sin captcha). Flujo: GET inicial para obtener VIEWSTATE + cookies de
 * sesión, luego POST a la página *_display.aspx con hdopcion/hdvalore
 * (replicando lo que hace el JS ValidaForm de la página).
 *
 * Un RUC puede tener VARIOS registros (renovaciones); se devuelven todos,
 * el más reciente primero (el orden del portal se respeta).
 */
class MtcConsultaService
{
    private const BASE_URL = 'https://www.mtc.gob.pe/tramitesenlinea/tweb_tLinea/tw_consultadgtt/';
    private const FORM_PAGE = 'Frm_rep_intra_mercancia.aspx';
    private const DISPLAY_PAGE = 'Frm_rep_intra_mercancia_display.aspx';
    private const DETAIL_PAGE = 'Frm_rep_intra_mercancia_datos.aspx';
    private const CACHE_TTL = 60 * 60 * 24; // 24h: el registro MTC cambia poco

    /**
     * Registros ordenados con los HABILITADOS primero (un RUC puede tener
     * varios registros y solo alguno estar vigente — ej. renovaciones o
     * modalidades distintas ya vencidas).
     *
     * @return array<int, array{codigo: string, razon_social: string, ruc: string,
     *                          estado: string, habilitado: bool,
     *                          vigente_hasta: string, modalidad: string}>
     */
    public function consultarPorRuc(string $ruc): array
    {
        if (! preg_match('/^\d{11}$/', $ruc)) {
            return [];
        }

        // v2: cache key con versión — el shape cambió al agregar estado/detalle.
        $key = "mtc_registro_v2_{$ruc}";
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $registros = $this->scrape($ruc);
        } catch (\Throwable $e) {
            Log::warning("Consulta MTC falló para RUC {$ruc}: {$e->getMessage()}");
            $registros = [];
        }

        // Un resultado vacío puede ser el portal MTC caído o la red del
        // hosting bloqueando la salida — cachearlo 24h dejaría el autofill
        // muerto un día entero. Vacío se cachea solo 5 min (reintento pronto);
        // con datos, 24h.
        Cache::put($key, $registros, count($registros) > 0 ? self::CACHE_TTL : 300);

        return $registros;
    }

    private function scrape(string $ruc): array
    {
        $cookieFile = tempnam(sys_get_temp_dir(), 'mtc');

        try {
            // 1) GET inicial: VIEWSTATE + cookies de sesión
            $page = $this->request(self::BASE_URL . self::FORM_PAGE, null, $cookieFile);
            if ($page === '') {
                return [];
            }

            // 2) POST de búsqueda (el JS de la página redirige a *_display.aspx)
            $post = [
                '__VIEWSTATE'          => $this->hidden($page, '__VIEWSTATE'),
                '__VIEWSTATEGENERATOR' => $this->hidden($page, '__VIEWSTATEGENERATOR'),
                '__EVENTVALIDATION'    => $this->hidden($page, '__EVENTVALIDATION'),
                'rbOpciones'           => '2', // 2 = búsqueda por RUC
                'txtValor'             => $ruc,
                'hdopc'                => $this->hidden($page, 'hdopc'),
                'hdopcion'             => '2',
                'hdvalore'             => $ruc,
            ];

            $result = $this->request(self::BASE_URL . self::DISPLAY_PAGE, $post, $cookieFile);

            $registros = $this->parseResultados($result, $ruc);

            // 3) Detalle de cada registro (POST con hdpartida/hdruc, replica
            //    el JS toDetalle de la página): estado Habilitado/No Habilitado,
            //    vigencia y modalidad. Habilitados primero.
            foreach ($registros as &$registro) {
                $detalle = $this->consultarDetalle($result, $registro['codigo'], $ruc, $cookieFile);
                $registro = array_merge($registro, $detalle);
            }
            unset($registro);

            usort($registros, fn ($a, $b) => (int) $b['habilitado'] <=> (int) $a['habilitado']);

            return $registros;
        } finally {
            @unlink($cookieFile);
        }
    }

    /**
     * @return array{estado: string, habilitado: bool, vigente_hasta: string, modalidad: string}
     */
    private function consultarDetalle(string $displayHtml, string $codigo, string $ruc, string $cookieFile): array
    {
        $post = [
            '__VIEWSTATE'          => $this->hidden($displayHtml, '__VIEWSTATE'),
            '__VIEWSTATEGENERATOR' => $this->hidden($displayHtml, '__VIEWSTATEGENERATOR'),
            '__EVENTVALIDATION'    => $this->hidden($displayHtml, '__EVENTVALIDATION'),
            'hdpartida'            => $codigo,
            'hdruc'                => $ruc,
        ];

        $html = $this->request(self::BASE_URL . self::DETAIL_PAGE, $post, $cookieFile);

        $campo = function (string $label) use ($html): string {
            if (preg_match('/' . $label . ':.*?<td[^>]*>(.*?)<\/td>/s', $html, $m)) {
                return trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5));
            }
            return '';
        };

        $estado = $campo('Estado');

        return [
            'estado'        => $estado,
            'habilitado'    => stripos($estado, 'No Habilitado') === false && stripos($estado, 'Habilitado') !== false,
            'vigente_hasta' => $campo('Vigente\s+Hasta'),
            'modalidad'     => $campo('Modalidad de Empresa'),
        ];
    }

    private function request(string $url, ?array $post, string $cookieFile): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_COOKIEJAR      => $cookieFile,
            CURLOPT_COOKIEFILE     => $cookieFile,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        ]);

        // El portal MTC bloquea IPs de datacenters extranjeros (ej. Contabo).
        // En producción configurar MTC_PROXY en .env con un proxy de IP
        // peruana: MTC_PROXY="http://user:pass@host:puerto". Sin proxy en
        // local (la IP peruana del ISP llega directo).
        $proxy = config('services.mtc.proxy');
        if ($proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
        }
        if ($post !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Lanzar con el motivo real (timeout, DNS, bloqueo, HTTP 403...) para
        // que el log del servidor diga POR QUÉ falló y no solo que falló.
        if ($body === false || $body === '') {
            throw new \RuntimeException("MTC request a {$url} falló: curl='{$curlError}' http={$httpCode}");
        }
        if ($httpCode >= 400) {
            throw new \RuntimeException("MTC request a {$url} devolvió HTTP {$httpCode}");
        }

        return $body;
    }

    private function hidden(string $html, string $name): string
    {
        if (preg_match('/name="' . preg_quote($name, '/') . '"[^>]*value="([^"]*)"/s', $html, $m)) {
            return html_entity_decode($m[1]);
        }

        return '';
    }

    /**
     * Parsea la tabla de resultados: Item | Código | Razón Social | R.U.C. | [Consultar]
     */
    private function parseResultados(string $html, string $ruc): array
    {
        $registros = [];

        if (! preg_match_all('/<tr[^>]*>(.*?)<\/tr>/s', $html, $rows)) {
            return $registros;
        }

        foreach ($rows[1] as $row) {
            if (! preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $row, $cells)) {
                continue;
            }
            $vals = array_map(
                fn ($c) => trim(html_entity_decode(strip_tags($c), ENT_QUOTES | ENT_HTML5)),
                $cells[1]
            );

            // Fila de datos: [item numérico, código, razón social, ruc, ...]
            if (count($vals) >= 4 && ctype_digit($vals[0]) && $vals[3] === $ruc && $vals[1] !== '') {
                $registros[] = [
                    'codigo'       => $vals[1],
                    'razon_social' => $vals[2],
                    'ruc'          => $vals[3],
                ];
            }
        }

        return $registros;
    }
}
