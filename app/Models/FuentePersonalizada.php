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
     */
    public static function generarFontFaceCss(int $empresaId): string
    {
        $fuentes = self::where('empresa_id', $empresaId)->get();
        if ($fuentes->isEmpty()) return '';

        $css = '';
        foreach ($fuentes as $fuente) {
            $path = Storage::disk('public')->path($fuente->archivo_path);
            $format = self::formatoMime($fuente->tipo_mime);
            $css .= "@font-face { font-family: '{$fuente->nombre}'; src: url('{$path}') format('{$format}'); font-weight: normal; font-style: normal; }\n";
            $css .= "@font-face { font-family: '{$fuente->nombre}'; src: url('{$path}') format('{$format}'); font-weight: bold; font-style: normal; }\n";
            $css .= "@font-face { font-family: '{$fuente->nombre}'; src: url('{$path}') format('{$format}'); font-weight: normal; font-style: italic; }\n";
        }
        return $css;
    }

    /**
     * Resuelve el formato para @font-face según el mime type.
     */
    private static function formatoMime(string $mime): string
    {
        return match ($mime) {
            'font/ttf', 'application/x-font-ttf' => 'truetype',
            'font/otf', 'application/x-font-opentype' => 'opentype',
            'font/woff' => 'woff',
            'font/woff2' => 'woff2',
            default => 'truetype',
        };
    }
}
