<?php

namespace App\Http\Controllers;

use App\Models\FuentePersonalizada;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FuentePersonalizadaController extends Controller
{
    protected function resolverEmpresaId(Request $request): ?int
    {
        $header = $request->header('X-Empresa-Activa');
        if ($header) return (int) $header;

        $user = $request->user() ?? Auth::user();
        if ($user && isset($user->empresa_id)) return (int) $user->empresa_id;

        return null;
    }

    public function index(Request $request)
    {
        $empresaId = $this->resolverEmpresaId($request);
        if (!$empresaId) {
            return response()->json(['success' => false, 'message' => 'Sin empresa activa'], 400);
        }

        $fuentes = FuentePersonalizada::where('empresa_id', $empresaId)->get()->map(function ($f) {
            return [
                'id' => $f->id,
                'nombre' => $f->nombre,
                'archivo_original' => $f->archivo_original,
                'archivo_url' => Storage::disk('public')->url($f->archivo_path),
                'tipo_mime' => $f->tipo_mime,
                'created_at' => $f->created_at,
            ];
        });

        return response()->json(['success' => true, 'data' => $fuentes]);
    }

    public function upload(Request $request)
    {
        $empresaId = $this->resolverEmpresaId($request);
        if (!$empresaId) {
            return response()->json(['success' => false, 'message' => 'Sin empresa activa'], 400);
        }

        $validated = $request->validate([
            'nombre' => [
                'required', 'string', 'max:80',
                Rule::unique('fuentes_personalizadas')->where('empresa_id', $empresaId),
            ],
            // Solo TTF y OTF: son los únicos formatos soportados por Dompdf.
            // Validamos por extensión del archivo original (no por MIME), porque
            // muchos navegadores/SO reportan los TTF como application/octet-stream.
            'archivo' => [
                'required',
                'file',
                'max:5120',
                function ($attribute, $value, $fail) {
                    if (!$value) return;
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (!in_array($ext, ['ttf', 'otf'], true)) {
                        $fail('Solo se permiten archivos .ttf u .otf (formatos soportados por el PDF).');
                    }
                },
            ],
        ]);

        $file = $request->file('archivo');
        $originalName = $file->getClientOriginalName();
        $mime = $file->getMimeType();
        $safeName = Str::slug($validated['nombre']) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs("fonts/{$empresaId}", $safeName, 'public');

        if (!$path) {
            return response()->json(['success' => false, 'message' => 'Error al guardar el archivo'], 500);
        }

        $fuente = FuentePersonalizada::create([
            'empresa_id' => $empresaId,
            'nombre' => $validated['nombre'],
            'archivo_original' => $originalName,
            'archivo_path' => $path,
            'tipo_mime' => $mime,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Fuente subida correctamente',
            'data' => [
                'id' => $fuente->id,
                'nombre' => $fuente->nombre,
                'archivo_original' => $fuente->archivo_original,
                'archivo_url' => Storage::disk('public')->url($fuente->archivo_path),
                'tipo_mime' => $fuente->tipo_mime,
            ],
        ]);
    }

    public function download(Request $request)
    {
        $empresaId = $this->resolverEmpresaId($request);
        if (!$empresaId) {
            return response()->json(['success' => false, 'message' => 'Sin empresa activa'], 400);
        }

        $validated = $request->validate([
            'nombre' => [
                'required', 'string', 'max:80',
                Rule::unique('fuentes_personalizadas')->where('empresa_id', $empresaId),
            ],
            'url' => 'required|url|max:500',
        ]);

        $nombre = $validated['nombre'];
        $url = $validated['url'];

        try {
            $response = Http::timeout(30)->get($url);
            if (!$response->successful()) {
                return response()->json(['success' => false, 'message' => 'No se pudo descargar la fuente desde la URL'], 400);
            }

            $content = $response->body();
            $mime = $response->header('Content-Type', 'font/ttf');

            // Solo aceptar TTF/OTF: Dompdf no soporta WOFF/WOFF2.
            // Detectamos por mime o por extensión de la URL.
            $urlExt = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
            $ext = match (true) {
                str_contains($mime, 'opentype') || $urlExt === 'otf' => 'otf',
                str_contains($mime, 'ttf') || str_contains($mime, 'truetype') || $urlExt === 'ttf' => 'ttf',
                default => null,
            };

            if ($ext === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'La URL no apunta a un archivo .ttf u .otf válido (formatos soportados por el PDF).',
                ], 422);
            }

            $safeName = Str::slug($nombre) . '.' . $ext;
            $path = "fonts/{$empresaId}/{$safeName}";

            if (!Storage::disk('public')->put($path, $content)) {
                return response()->json(['success' => false, 'message' => 'Error al guardar la fuente'], 500);
            }

            $fuente = FuentePersonalizada::create([
                'empresa_id' => $empresaId,
                'nombre' => $nombre,
                'archivo_original' => basename($url),
                'archivo_path' => $path,
                'tipo_mime' => $mime,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Fuente descargada correctamente',
                'data' => [
                    'id' => $fuente->id,
                    'nombre' => $fuente->nombre,
                    'archivo_original' => $fuente->archivo_original,
                    'archivo_url' => Storage::disk('public')->url($fuente->archivo_path),
                    'tipo_mime' => $fuente->tipo_mime,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al descargar: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $empresaId = $this->resolverEmpresaId($request);
        if (!$empresaId) {
            return response()->json(['success' => false, 'message' => 'Sin empresa activa'], 400);
        }

        $fuente = FuentePersonalizada::where('id', $id)->where('empresa_id', $empresaId)->first();
        if (!$fuente) {
            return response()->json(['success' => false, 'message' => 'Fuente no encontrada'], 404);
        }

        Storage::disk('public')->delete($fuente->archivo_path);
        $fuente->delete();

        return response()->json(['success' => true, 'message' => 'Fuente eliminada correctamente']);
    }
}
