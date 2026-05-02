<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\ContactoEmpresa;
use App\Models\TerminoEmpresa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EmpresaController extends Controller
{
    public function index(): JsonResponse
    {
        $empresa = Empresa::with([
            'almacenPredeterminado',
            'marcaPredeterminada',
            'contactos',
            'terminos',
            'direcciones',
        ])->first();

        if (!$empresa) {
            return response()->json([
                'message' => 'No se encontró información de la empresa',
            ], 404);
        }

        return response()->json(['data' => $empresa]);
    }

    public function show($id): JsonResponse
    {
        $empresa = Empresa::with([
            'almacenPredeterminado',
            'marcaPredeterminada',
            'contactos',
            'terminos',
            'direcciones',
        ])->findOrFail($id);

        if ($empresa->logo) {
            $empresa->logo_url = asset('storage/' . $empresa->logo);
        }

        return response()->json(['data' => $empresa]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'almacen_id' => 'required|exists:almacenes,id',
            'marca_id' => 'required|exists:marca,id',
            'serie_ingreso' => 'nullable|integer',
            'serie_salida' => 'nullable|integer',
            'serie_recepcion_almacen' => 'nullable|integer',
            'tipo_identificacion' => 'nullable|string|max:20',
            'ruc' => 'required|string|max:191',
            'razon_social' => 'required|string|max:191',
            'nombre_comercial' => 'nullable|string|max:191',
            'regimen' => 'nullable|string|max:100',
            'actividad_economica' => 'nullable|string|max:191',
            'telefono' => 'required|string|max:191',
            'celular' => 'nullable|string|max:50',
            'email' => 'required|email|max:191',
            'logo' => 'nullable|string|max:500',
            'imprimir_impuestos_boleta' => 'nullable|boolean',
            'sol_user' => 'nullable|string|max:50',
            'sol_pass' => 'nullable|string|max:100',
            'sunat_client_id' => 'nullable|string|max:100',
            'sunat_secret_client' => 'nullable|string|max:255',
            'sunat_modo' => 'nullable|in:beta,produccion',
        ]);

        $validated['serie_ingreso'] = $validated['serie_ingreso'] ?? 1;
        $validated['serie_salida'] = $validated['serie_salida'] ?? 1;
        $validated['serie_recepcion_almacen'] = $validated['serie_recepcion_almacen'] ?? 1;
        $validated['tipo_identificacion'] = $validated['tipo_identificacion'] ?? 'RUC';

        $empresa = Empresa::create($validated);

        if ($request->has('contactos')) {
            foreach ($request->input('contactos', []) as $contacto) {
                $empresa->contactos()->create($contacto);
            }
        }

        if ($request->has('terminos')) {
            foreach ($request->input('terminos', []) as $termino) {
                $empresa->terminos()->create($termino);
            }
        }

        if ($request->has('direcciones')) {
            foreach ($request->input('direcciones', []) as $direccion) {
                $empresa->direcciones()->create(['es_principal' => false] + $direccion);
            }
        }

        $this->sincronizarDireccionPrincipal($empresa, $request);

        $empresa = $empresa->fresh(['almacenPredeterminado', 'marcaPredeterminada', 'contactos', 'terminos', 'direcciones']);

        return response()->json([
            'data' => $empresa,
            'message' => 'Empresa creada exitosamente',
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $empresa = Empresa::findOrFail($id);

        $validated = $request->validate([
            'almacen_id' => 'sometimes|required|exists:almacenes,id',
            'marca_id' => 'sometimes|required|exists:marca,id',
            'serie_ingreso' => 'nullable|integer',
            'serie_salida' => 'nullable|integer',
            'serie_recepcion_almacen' => 'nullable|integer',
            'tipo_identificacion' => 'nullable|string|max:20',
            'ruc' => 'sometimes|required|string|max:191',
            'razon_social' => 'sometimes|required|string|max:191',
            'nombre_comercial' => 'nullable|string|max:191',
            'regimen' => 'nullable|string|max:100',
            'actividad_economica' => 'nullable|string|max:191',
            'telefono' => 'sometimes|required|string|max:191',
            'celular' => 'nullable|string|max:50',
            'email' => 'sometimes|required|email|max:191',
            'logo' => 'nullable|file|image|max:2048',
            'imprimir_impuestos_boleta' => 'nullable|boolean',
            'sol_user' => 'nullable|string|max:50',
            'sol_pass' => 'nullable|string|max:100',
            'sunat_client_id' => 'nullable|string|max:100',
            'sunat_secret_client' => 'nullable|string|max:255',
            'sunat_modo' => 'nullable|in:beta,produccion',
            'contactos' => 'nullable|array',
            'contactos.*.cargo' => 'required|in:gerente,facturacion,contabilidad',
            'contactos.*.nombre' => 'nullable|string|max:191',
            'contactos.*.email' => 'nullable|email|max:191',
            'contactos.*.celular' => 'nullable|string|max:50',
            'terminos' => 'nullable|array',
            'terminos.*.tipo' => 'required|in:comprobantes_ventas,letras_cambio,guias_remision,cotizaciones,ordenes_compras',
            'terminos.*.contenido' => 'nullable|string',
            'direcciones' => 'nullable|array',
            'direcciones.*.alias' => 'nullable|string|max:100',
            'direcciones.*.direccion' => 'required|string|max:191',
            'direcciones.*.ubigeo_id' => 'nullable|exists:ubigeo_inei,id_ubigeo',
            'direcciones.*.departamento' => 'nullable|string|max:100',
            'direcciones.*.provincia' => 'nullable|string|max:100',
            'direcciones.*.distrito' => 'nullable|string|max:100',
        ]);

        if ($request->hasFile('logo')) {
            if ($empresa->logo && Storage::disk('public')->exists($empresa->logo)) {
                Storage::disk('public')->delete($empresa->logo);
            }
            $logoPath = $request->file('logo')->store('logos', 'public');
            $validated['logo'] = $logoPath;
        }

        $empresa->update($validated);

        if ($request->has('contactos')) {
            $empresa->contactos()->delete();
            foreach ($request->input('contactos', []) as $contacto) {
                $empresa->contactos()->create($contacto);
            }
        }

        if ($request->has('terminos')) {
            $empresa->terminos()->delete();
            foreach ($request->input('terminos', []) as $termino) {
                $empresa->terminos()->create($termino);
            }
        }

        if ($request->has('direcciones')) {
            $empresa->direcciones()->where('es_principal', false)->delete();
            foreach ($request->input('direcciones', []) as $direccion) {
                $empresa->direcciones()->create(['es_principal' => false] + $direccion);
            }
        }

        $this->sincronizarDireccionPrincipal($empresa, $request);

        if ($empresa->logo) {
            $empresa->logo_url = asset('storage/' . $empresa->logo);
        }

        return response()->json([
            'data' => $empresa->load(['almacenPredeterminado', 'marcaPredeterminada', 'contactos', 'terminos', 'direcciones']),
            'message' => 'Empresa actualizada exitosamente',
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $empresa = Empresa::findOrFail($id);
        $empresa->delete();

        return response()->json([
            'message' => 'Empresa eliminada exitosamente',
        ]);
    }

    public function getDatosPublicos(): JsonResponse
    {
        // Cargar direcciones — el frontend las usa para selectores como
        // "Punto de Partida" en guías de remisión donde se necesita
        // elegir entre direcciones (D1..DN) de la empresa.
        $empresa = Empresa::with('direcciones')->first();

        if (!$empresa) {
            return response()->json([
                'error' => 'No se encontró información de la empresa'
            ], 404);
        }

        return response()->json([
            'data' => [
                'id' => $empresa->id,
                'ruc' => $empresa->ruc,
                'razon_social' => $empresa->razon_social,
                'nombre_comercial' => $empresa->nombre_comercial,
                'direccion' => $empresa->direccion,
                'departamento' => $empresa->departamento,
                'provincia' => $empresa->provincia,
                'distrito' => $empresa->distrito,
                'telefono' => $empresa->telefono,
                'celular' => $empresa->celular,
                'email' => $empresa->email,
                'logo' => $empresa->logo,
                'direcciones' => $empresa->direcciones,
            ]
        ]);
    }

    private function sincronizarDireccionPrincipal(Empresa $empresa, Request $request): void
    {
        if (!$request->has('direccion')) {
            return;
        }

        $data = [
            'alias' => 'Principal',
            'direccion' => $request->input('direccion', ''),
            'ubigeo_id' => $request->input('ubigeo_id'),
            'departamento' => $request->input('departamento'),
            'provincia' => $request->input('provincia'),
            'distrito' => $request->input('distrito'),
        ];

        $principal = $empresa->direcciones()->where('es_principal', true)->first();

        if ($principal) {
            $principal->update($data);
        } else {
            $empresa->direcciones()->create(array_merge($data, ['es_principal' => true]));
        }
    }
}
