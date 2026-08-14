<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class FuentePersonalizada extends Model
{
    protected $table = 'fuentes_personalizadas';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'archivo_original',
        'archivo_path',
        'tipo_mime',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public static function obtenerPorEmpresa(int $empresaId)
    {
        return self::where('empresa_id', $empresaId)->get()->keyBy('nombre');
    }

    /**
     * Genera el CSS @font-face para las fuentes personalizadas de una empresa.
     * Usa URL file:/// con slashes normalizados para que funcione en Windows y Linux.
     *
     * Si se pasa $nombresUsados, sólo declara las fuentes cuyos nombres estén
     * en esa lista (case-insensitive). Esto evita que Dompdf intente parsear
     * fuentes no usadas y reviente con un 500.
     *
     * @param int $empresaId
     * @param array<string>|null $nombresUsados Lista de nombres de fuentes a declarar. null = todas.
     */
    public static function generarFontFaceCss(int $empresaId, ?array $nombresUsados = null): string
    {
        // Dompdf ignora el @font-face con URL file:/// (resolve_url devuelve
        // "none" y la descarta en silencio), asi que la fuente se registra
        // ademas por API, que es lo unico que funciona de verdad.
        self::registrarEnDompdf($empresaId, $nombresUsados);

        $query = self::where('empresa_id', $empresaId);

        if (is_array($nombresUsados)) {
            // Si la lista está vacía, no hay nada que generar
            if (empty($nombresUsados)) return '';
            $lower = array_map('strtolower', $nombresUsados);
            // Filtrar por nombre case-insensitive
            $query->whereRaw('LOWER(nombre) IN (' . implode(',', array_fill(0, count($lower), '?')) . ')', $lower);
        }

        $fuentes = $query->get();
        if ($fuentes->isEmpty()) return '';

        $css = '';
        foreach ($fuentes as $fuente) {
            // Saltar cualquier fuente que no sea TTF u OTF: Dompdf solo soporta
            // esos dos formatos y, si se le pasa un WOFF/WOFF2 disfrazado como
            // truetype o un archivo corrupto, lanza excepción y devuelve 500.
            $ext = strtolower(pathinfo($fuente->archivo_path, PATHINFO_EXTENSION));
            if (!in_array($ext, ['ttf', 'otf'], true)) {
                continue;
            }

            $path = Storage::disk('public')->path($fuente->archivo_path);

            // Verificar existencia antes de declarar el @font-face: si el archivo no
            // existe, Dompdf falla silenciosamente y puede dejar el PDF sin texto.
            if (!is_file($path)) {
                continue;
            }

            $url = self::pathToFileUrl($path);
            $format = $ext === 'otf' ? 'opentype' : 'truetype';

            $css .= "@font-face { font-family: '{$fuente->nombre}'; src: url('{$url}') format('{$format}'); font-weight: normal; font-style: normal; }\n";
            $css .= "@font-face { font-family: '{$fuente->nombre}'; src: url('{$url}') format('{$format}'); font-weight: bold; font-style: normal; }\n";
            $css .= "@font-face { font-family: '{$fuente->nombre}'; src: url('{$url}') format('{$format}'); font-weight: normal; font-style: italic; }\n";
        }
        return $css;
    }

    /**
     * Registra las fuentes de la empresa en el directorio de fuentes de Dompdf.
     *
     * Dompdf guarda ahi un installed-fonts.json global: una vez registrada, la
     * familia queda disponible para cualquier instancia posterior, sin tocar
     * los servicios de PDF uno por uno.
     *
     * Es idempotente y barato: si la familia ya figura en el cache, no hace nada.
     */
    public static function registrarEnDompdf(int $empresaId, ?array $nombresUsados = null): void
    {
        $query = self::where('empresa_id', $empresaId);

        if (is_array($nombresUsados)) {
            if (empty($nombresUsados)) return;
            $lower = array_map('strtolower', $nombresUsados);
            $query->whereRaw('LOWER(nombre) IN (' . implode(',', array_fill(0, count($lower), '?')) . ')', $lower);
        }

        $fuentes = $query->get();
        if ($fuentes->isEmpty()) return;

        $fontDir = config('dompdf.options.font_dir') ?: storage_path('fonts');
        $instaladas = self::familiasInstaladas($fontDir);

        $pendientes = $fuentes->filter(function ($f) use ($instaladas) {
            return !isset($instaladas[strtolower($f->nombre)]);
        });

        if ($pendientes->isEmpty()) return;

        if (!is_dir($fontDir) && !@mkdir($fontDir, 0775, true) && !is_dir($fontDir)) {
            // Sin directorio escribible no se puede registrar; el PDF saldra con
            // la fuente de respaldo, pero no se rompe.
            return;
        }

        try {
            $dompdf = new \Dompdf\Dompdf(new \Dompdf\Options([
                'fontDir'   => $fontDir,
                'fontCache' => $fontDir,
                'chroot'    => realpath(base_path()),
            ]));
            $metrics = $dompdf->getFontMetrics();

            foreach ($pendientes as $fuente) {
                $ext = strtolower(pathinfo($fuente->archivo_path, PATHINFO_EXTENSION));
                if (!in_array($ext, ['ttf', 'otf'], true)) continue;

                $path = Storage::disk('public')->path($fuente->archivo_path);
                if (!is_file($path)) continue;

                foreach ([['normal','normal'], ['bold','normal'], ['normal','italic']] as [$peso, $estilo]) {
                    $metrics->registerFont(
                        ['family' => $fuente->nombre, 'weight' => $peso, 'style' => $estilo],
                        $path
                    );
                }
            }
        } catch (\Throwable $e) {
            // Un problema con una fuente nunca debe tumbar la emision de un PDF
            report($e);
        }
    }

    /**
     * Lee las familias ya registradas en el cache de Dompdf.
     */
    private static function familiasInstaladas(string $fontDir): array
    {
        $archivo = rtrim($fontDir, "/\\") . DIRECTORY_SEPARATOR . 'installed-fonts.json';
        if (!is_file($archivo)) return [];
        $json = json_decode((string) file_get_contents($archivo), true);
        if (!is_array($json)) return [];
        return array_change_key_case($json, CASE_LOWER);
    }

    /**
     * Extrae los nombres únicos de fuentes referenciadas por una plantilla:
     * - la fuente principal en estilos.fuente
     * - cualquier override en estilos_secciones.{seccion}.fuente
     */
    public static function extraerFuentesUsadas(?array $estilos, ?array $estilosSecciones): array
    {
        $nombres = [];

        if (!empty($estilos['fuente'])) {
            $nombres[] = (string) $estilos['fuente'];
        }

        if (is_array($estilosSecciones)) {
            foreach ($estilosSecciones as $seccion) {
                if (is_array($seccion) && !empty($seccion['fuente'])) {
                    $nombres[] = (string) $seccion['fuente'];
                }
            }
        }

        return array_values(array_unique(array_filter($nombres, fn ($n) => $n !== '')));
    }

    /**
     * Convierte una ruta absoluta del sistema a una URL file:/// válida en
     * Windows ("C:\foo\bar.ttf" → "file:///C:/foo/bar.ttf") y en Linux
     * ("/var/www/bar.ttf" → "file:///var/www/bar.ttf").
     */
    private static function pathToFileUrl(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        return 'file:///' . ltrim($normalized, '/');
    }

    /**
     * Resuelve el formato para @font-face. Solo TTF y OTF están soportados por Dompdf.
     * Si el mime no es confiable, cae a la extensión del archivo.
     */
    private static function formatoMime(string $mime, ?string $archivoPath = null): string
    {
        $byMime = match ($mime) {
            'font/ttf', 'application/x-font-ttf' => 'truetype',
            'font/otf', 'application/x-font-opentype' => 'opentype',
            default => null,
        };

        if ($byMime !== null) {
            return $byMime;
        }

        // Fallback por extensión (mime puede venir como octet-stream).
        $ext = $archivoPath ? strtolower(pathinfo($archivoPath, PATHINFO_EXTENSION)) : '';
        return $ext === 'otf' ? 'opentype' : 'truetype';
    }
}
