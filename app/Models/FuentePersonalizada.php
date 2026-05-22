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
     * Genera el CSS @font-face para todas las fuentes personalizadas de una empresa.
     * Usa URL file:/// con slashes normalizados para que funcione en Windows y Linux.
     */
    public static function generarFontFaceCss(int $empresaId): string
    {
        $fuentes = self::where('empresa_id', $empresaId)->get();
        if ($fuentes->isEmpty()) return '';

        $css = '';
        foreach ($fuentes as $fuente) {
            $path = Storage::disk('public')->path($fuente->archivo_path);

            // Verificar existencia antes de declarar el @font-face: si el archivo no
            // existe, Dompdf falla silenciosamente y puede dejar el PDF sin texto.
            if (!is_file($path)) {
                continue;
            }

            $url = self::pathToFileUrl($path);
            $format = self::formatoMime($fuente->tipo_mime, $fuente->archivo_path);

            $css .= "@font-face { font-family: '{$fuente->nombre}'; src: url('{$url}') format('{$format}'); font-weight: normal; font-style: normal; }\n";
            $css .= "@font-face { font-family: '{$fuente->nombre}'; src: url('{$url}') format('{$format}'); font-weight: bold; font-style: normal; }\n";
            $css .= "@font-face { font-family: '{$fuente->nombre}'; src: url('{$url}') format('{$format}'); font-weight: normal; font-style: italic; }\n";
        }
        return $css;
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
