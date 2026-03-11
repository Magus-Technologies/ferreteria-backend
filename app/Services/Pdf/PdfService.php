<?php

namespace App\Services\Pdf;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PdfService
{
    /**
     * Obtener la ruta absoluta del logo optimizado para PDF.
     * Redimensiona imagenes grandes para que el motor PDF no tarde procesandolas.
     */
    public static function getLogoPath(?string $logoPath = null): string
    {
        if ($logoPath) {
            $fullPath = storage_path('app/public/' . $logoPath);
            if (file_exists($fullPath)) {
                return self::optimizarImagenParaPdf($fullPath);
            }
        }

        $defaultLogo = public_path('logo-vertical.png');
        if (file_exists($defaultLogo)) {
            return self::optimizarImagenParaPdf($defaultLogo);
        }

        return '';
    }

    /**
     * Redimensionar imagen si es muy grande (> 600px).
     * Genera una version cache en storage/app/pdf-cache/.
     */
    private static function optimizarImagenParaPdf(string $path, int $maxWidth = 400): string
    {
        $info = @getimagesize($path);
        if (!$info || $info[0] <= $maxWidth) {
            return $path; // Ya es pequena, no optimizar
        }

        $cacheDir = storage_path('app/pdf-cache');
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $cacheFile = $cacheDir . '/' . md5($path . filemtime($path)) . '.png';
        if (file_exists($cacheFile)) {
            return $cacheFile;
        }

        $type = $info[2];
        $source = match ($type) {
            IMAGETYPE_PNG => imagecreatefrompng($path),
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_GIF => imagecreatefromgif($path),
            default => null,
        };

        if (!$source) {
            return $path;
        }

        $ratio = $maxWidth / $info[0];
        $newHeight = (int) round($info[1] * $ratio);

        $resized = imagecreatetruecolor($maxWidth, $newHeight);

        // Preservar transparencia para PNG
        if ($type === IMAGETYPE_PNG) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefill($resized, 0, 0, $transparent);
        }

        imagecopyresampled($resized, $source, 0, 0, 0, 0, $maxWidth, $newHeight, $info[0], $info[1]);
        imagepng($resized, $cacheFile, 6);

        imagedestroy($source);
        imagedestroy($resized);

        return $cacheFile;
    }

    /**
     * Renderizar una vista Blade como PDF y devolver la respuesta HTTP.
     */
    public static function render(
        string $view,
        array $data,
        string $filename,
        string $orientation = 'portrait',
        ?array $paperSize = null,
    ): Response {
        $pdf = Pdf::loadView($view, $data)
            ->setPaper($paperSize ?? 'a4', $orientation);

        return $pdf->stream($filename);
    }

    /**
     * Convertir un numero a texto en espanol.
     * Ej: 1234.56 => "MIL DOSCIENTOS TREINTA Y CUATRO CON 56/100"
     */
    public static function numeroALetras(float $numero): string
    {
        $entero = (int) floor(abs($numero));
        $decimal = (int) round((abs($numero) - $entero) * 100);

        $letras = self::enteroALetras($entero);

        return strtoupper($letras) . " CON {$decimal}/100";
    }

    /**
     * Formatear una fecha.
     */
    public static function formatFecha(
        mixed $fecha,
        string $formato = 'd/m/Y',
    ): string {
        if (!$fecha) {
            return '-';
        }

        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format($formato);
        }

        return date($formato, strtotime($fecha));
    }

    /**
     * Formatear moneda.
     */
    public static function formatMoneda(
        mixed $valor,
        int $decimales = 2,
        string $prefijo = 'S/. ',
    ): string {
        if ($valor === null) {
            return '-';
        }

        return $prefijo . number_format((float) $valor, $decimales, '.', ',');
    }

    // ----- Helpers internos -----

    private static function enteroALetras(int $numero): string
    {
        if ($numero === 0) {
            return 'cero';
        }

        $unidades = [
            '', 'uno', 'dos', 'tres', 'cuatro', 'cinco',
            'seis', 'siete', 'ocho', 'nueve',
        ];

        $decenas = [
            '', 'diez', 'veinte', 'treinta', 'cuarenta', 'cincuenta',
            'sesenta', 'setenta', 'ochenta', 'noventa',
        ];

        $especiales = [
            11 => 'once', 12 => 'doce', 13 => 'trece',
            14 => 'catorce', 15 => 'quince',
        ];

        $centenas = [
            '', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos',
            'quinientos', 'seiscientos', 'setecientos', 'ochocientos',
            'novecientos',
        ];

        if ($numero < 0) {
            return 'menos ' . self::enteroALetras(abs($numero));
        }

        if ($numero < 10) {
            return $unidades[$numero];
        }

        if ($numero >= 11 && $numero <= 15) {
            return $especiales[$numero];
        }

        if ($numero >= 16 && $numero <= 19) {
            return 'dieci' . $unidades[$numero - 10];
        }

        if ($numero == 10) {
            return 'diez';
        }

        if ($numero >= 21 && $numero <= 29) {
            return 'veinti' . $unidades[$numero - 20];
        }

        if ($numero == 20) {
            return 'veinte';
        }

        if ($numero < 100) {
            $d = (int) ($numero / 10);
            $u = $numero % 10;
            return $decenas[$d] . ($u > 0 ? ' y ' . $unidades[$u] : '');
        }

        if ($numero == 100) {
            return 'cien';
        }

        if ($numero < 1000) {
            $c = (int) ($numero / 100);
            $resto = $numero % 100;
            return $centenas[$c] . ($resto > 0 ? ' ' . self::enteroALetras($resto) : '');
        }

        if ($numero < 2000) {
            $resto = $numero % 1000;
            return 'mil' . ($resto > 0 ? ' ' . self::enteroALetras($resto) : '');
        }

        if ($numero < 1000000) {
            $miles = (int) ($numero / 1000);
            $resto = $numero % 1000;
            return self::enteroALetras($miles) . ' mil'
                . ($resto > 0 ? ' ' . self::enteroALetras($resto) : '');
        }

        if ($numero < 2000000) {
            $resto = $numero % 1000000;
            return 'un millon'
                . ($resto > 0 ? ' ' . self::enteroALetras($resto) : '');
        }

        $millones = (int) ($numero / 1000000);
        $resto = $numero % 1000000;
        return self::enteroALetras($millones) . ' millones'
            . ($resto > 0 ? ' ' . self::enteroALetras($resto) : '');
    }
}
