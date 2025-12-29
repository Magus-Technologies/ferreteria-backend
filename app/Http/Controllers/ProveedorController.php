<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use App\Models\Vendedor;
use App\Models\Carro;
use App\Models\Chofer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProveedorController extends Controller
{
    /**
     * Buscar proveedores con filtros
     * GET /api/proveedores?search=...
     */
    public function index(Request $request)
    {
        $query = Proveedor::with(['vendedores', 'carros', 'choferes']);

        // Filtro por búsqueda
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('razon_social', 'LIKE', "%{$search}%")
                    ->orWhere('ruc', 'LIKE', "%{$search}%");
            });
        }

        // Filtro por estado
        if ($request->has('estado')) {
            $query->where('estado', $request->estado == '1' || $request->estado === true);
        }

        // Ordenar
        if ($request->has('search') && !empty($request->search)) {
            $query->orderBy('razon_social', 'asc');
        } else {
            $query->latest('id');
        }

        // Paginación
        $perPage = min($request->input('per_page', 50), 100);
        $proveedores = $query->paginate($perPage);

        return response()->json($proveedores);
    }

    /**
     * Crear nuevo proveedor
     * POST /api/proveedores
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'razon_social' => 'required|string|max:191|unique:proveedor,razon_social',
            'ruc' => 'required|string|max:191|unique:proveedor,ruc',
            'direccion' => 'nullable|string|max:191',
            'telefono' => 'nullable|string|max:191',
            'email' => 'nullable|email|max:191',
            'estado' => 'required|boolean',
            'vendedores' => 'nullable|array',
            'vendedores.*.dni' => 'required|string|size:8|unique:vendedor,dni',
            'vendedores.*.nombres' => 'required|string|max:191',
            'vendedores.*.direccion' => 'nullable|string|max:191',
            'vendedores.*.telefono' => 'nullable|string|max:191',
            'vendedores.*.email' => 'nullable|email|max:191',
            'vendedores.*.estado' => 'required|boolean',
            'vendedores.*.cumple' => 'nullable|date',
            'carros' => 'nullable|array',
            'carros.*.placa' => 'required|string|max:191',
            'choferes' => 'nullable|array',
            'choferes.*.dni' => 'required|string|size:8|unique:chofer,dni',
            'choferes.*.name' => 'required|string|max:191',
            'choferes.*.licencia' => 'required|string|max:191',
        ], [
            'razon_social.required' => 'La razón social es requerida',
            'razon_social.unique' => 'Ya existe un proveedor con esta razón social',
            'ruc.required' => 'El RUC es requerido',
            'ruc.unique' => 'Ya existe un proveedor con este RUC',
            'vendedores.*.dni.unique' => 'Ya existe un vendedor con este DNI',
            'vendedores.*.dni.size' => 'El DNI debe tener 8 dígitos',
            'choferes.*.dni.unique' => 'Ya existe un chofer con este DNI',
            'choferes.*.dni.size' => 'El DNI debe tener 8 dígitos',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors()
                ]
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Crear proveedor
            $proveedor = Proveedor::create([
                'razon_social' => $request->razon_social,
                'ruc' => $request->ruc,
                'direccion' => $request->direccion,
                'telefono' => $request->telefono,
                'email' => $request->email,
                'estado' => $request->estado,
            ]);

            // Crear vendedores si existen
            if ($request->has('vendedores') && is_array($request->vendedores)) {
                foreach ($request->vendedores as $vendedorData) {
                    Vendedor::create([
                        'dni' => $vendedorData['dni'],
                        'nombres' => $vendedorData['nombres'],
                        'direccion' => $vendedorData['direccion'] ?? null,
                        'telefono' => $vendedorData['telefono'] ?? null,
                        'email' => $vendedorData['email'] ?? null,
                        'estado' => $vendedorData['estado'],
                        'cumple' => isset($vendedorData['cumple']) ? $vendedorData['cumple'] : null,
                        'proveedor_id' => $proveedor->id,
                    ]);
                }
            }

            // Crear carros si existen
            if ($request->has('carros') && is_array($request->carros)) {
                foreach ($request->carros as $carroData) {
                    Carro::create([
                        'placa' => $carroData['placa'],
                        'proveedor_id' => $proveedor->id,
                    ]);
                }
            }

            // Crear choferes si existen
            if ($request->has('choferes') && is_array($request->choferes)) {
                foreach ($request->choferes as $choferData) {
                    Chofer::create([
                        'dni' => $choferData['dni'],
                        'name' => $choferData['name'],
                        'licencia' => $choferData['licencia'],
                        'proveedor_id' => $proveedor->id,
                    ]);
                }
            }

            DB::commit();

            // Retornar proveedor con relaciones
            $proveedor->load(['vendedores', 'carros', 'choferes']);

            return response()->json([
                'data' => $proveedor,
                'message' => 'Proveedor creado exitosamente'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => [
                    'message' => 'Error al crear el proveedor: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Obtener un proveedor específico
     * GET /api/proveedores/{id}
     */
    public function show($id)
    {
        $proveedor = Proveedor::with(['vendedores', 'carros', 'choferes'])->find($id);

        if (!$proveedor) {
            return response()->json([
                'error' => ['message' => 'Proveedor no encontrado']
            ], 404);
        }

        return response()->json(['data' => $proveedor]);
    }

    /**
     * Actualizar proveedor
     * PUT /api/proveedores/{id}
     */
    public function update(Request $request, $id)
    {
        $proveedor = Proveedor::find($id);

        if (!$proveedor) {
            return response()->json([
                'error' => ['message' => 'Proveedor no encontrado']
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'razon_social' => [
                'required',
                'string',
                'max:191',
                Rule::unique('proveedor', 'razon_social')->ignore($id)
            ],
            'ruc' => [
                'required',
                'string',
                'max:191',
                Rule::unique('proveedor', 'ruc')->ignore($id)
            ],
            'direccion' => 'nullable|string|max:191',
            'telefono' => 'nullable|string|max:191',
            'email' => 'nullable|email|max:191',
            'estado' => 'required|boolean',
            'vendedores' => 'nullable|array',
            'vendedores.*.dni' => 'required|string|size:8',
            'vendedores.*.nombres' => 'required|string|max:191',
            'vendedores.*.direccion' => 'nullable|string|max:191',
            'vendedores.*.telefono' => 'nullable|string|max:191',
            'vendedores.*.email' => 'nullable|email|max:191',
            'vendedores.*.estado' => 'required|boolean',
            'vendedores.*.cumple' => 'nullable|date',
            'carros' => 'nullable|array',
            'carros.*.placa' => 'required|string|max:191',
            'choferes' => 'nullable|array',
            'choferes.*.dni' => 'required|string|size:8',
            'choferes.*.name' => 'required|string|max:191',
            'choferes.*.licencia' => 'required|string|max:191',
        ], [
            'razon_social.required' => 'La razón social es requerida',
            'razon_social.unique' => 'Ya existe un proveedor con esta razón social',
            'ruc.required' => 'El RUC es requerido',
            'ruc.unique' => 'Ya existe un proveedor con este RUC',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors()
                ]
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Actualizar proveedor
            $proveedor->update([
                'razon_social' => $request->razon_social,
                'ruc' => $request->ruc,
                'direccion' => $request->direccion,
                'telefono' => $request->telefono,
                'email' => $request->email,
                'estado' => $request->estado,
            ]);

            // Eliminar todos los vendedores existentes y crear los nuevos
            Vendedor::where('proveedor_id', $id)->delete();
            if ($request->has('vendedores') && is_array($request->vendedores)) {
                foreach ($request->vendedores as $vendedorData) {
                    Vendedor::create([
                        'dni' => $vendedorData['dni'],
                        'nombres' => $vendedorData['nombres'],
                        'direccion' => $vendedorData['direccion'] ?? null,
                        'telefono' => $vendedorData['telefono'] ?? null,
                        'email' => $vendedorData['email'] ?? null,
                        'estado' => $vendedorData['estado'],
                        'cumple' => isset($vendedorData['cumple']) ? $vendedorData['cumple'] : null,
                        'proveedor_id' => $proveedor->id,
                    ]);
                }
            }

            // Eliminar todos los carros existentes y crear los nuevos
            Carro::where('proveedor_id', $id)->delete();
            if ($request->has('carros') && is_array($request->carros)) {
                foreach ($request->carros as $carroData) {
                    Carro::create([
                        'placa' => $carroData['placa'],
                        'proveedor_id' => $proveedor->id,
                    ]);
                }
            }

            // Eliminar todos los choferes existentes y crear los nuevos
            Chofer::where('proveedor_id', $id)->delete();
            if ($request->has('choferes') && is_array($request->choferes)) {
                foreach ($request->choferes as $choferData) {
                    Chofer::create([
                        'dni' => $choferData['dni'],
                        'name' => $choferData['name'],
                        'licencia' => $choferData['licencia'],
                        'proveedor_id' => $proveedor->id,
                    ]);
                }
            }

            DB::commit();

            // Retornar proveedor con relaciones
            $proveedor->load(['vendedores', 'carros', 'choferes']);

            return response()->json([
                'data' => $proveedor,
                'message' => 'Proveedor actualizado exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => [
                    'message' => 'Error al actualizar el proveedor: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Eliminar proveedor
     * DELETE /api/proveedores/{id}
     */
    public function destroy($id)
    {
        try {
            $proveedor = Proveedor::find($id);

            if (!$proveedor) {
                return response()->json([
                    'error' => ['message' => 'Proveedor no encontrado']
                ], 404);
            }

            // Las relaciones se eliminan automáticamente por onDelete: Cascade en BD
            $proveedor->delete();

            return response()->json([
                'data' => 'ok',
                'message' => 'Proveedor eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            // Si hay relaciones que impiden el borrado
            if (str_contains($e->getMessage(), 'FOREIGN KEY') || str_contains($e->getMessage(), '1451')) {
                return response()->json([
                    'error' => [
                        'message' => 'Este proveedor tiene registros a su nombre en el sistema'
                    ]
                ], 409);
            }

            return response()->json([
                'error' => [
                    'message' => 'Error al eliminar el proveedor: ' . $e->getMessage()
                ]
            ], 500);
        }
    }
}
