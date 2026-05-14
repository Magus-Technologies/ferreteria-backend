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
        $query = Proveedor::with(['vendedores', 'carros', 'choferes'])
            ->leftJoin('proveedor_calificaciones', function ($join) {
                $join->on('proveedor.id', '=', 'proveedor_calificaciones.proveedor_id')
                    ->whereRaw('proveedor_calificaciones.id = (
                        SELECT id FROM proveedor_calificaciones pc2 
                        WHERE pc2.proveedor_id = proveedor.id 
                        ORDER BY pc2.id DESC LIMIT 1
                    )');
            })
            ->select('proveedor.*', 'proveedor_calificaciones.estado as calificacion_estado', 'proveedor_calificaciones.observacion as calificacion_observacion', 'proveedor_calificaciones.id as calificacion_id');

        // Filtro por búsqueda
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('proveedor.razon_social', 'LIKE', "%{$search}%")
                    ->orWhere('proveedor.ruc', 'LIKE', "%{$search}%");
            });
        }

        // Filtro por estado
        if ($request->has('estado') && $request->estado !== '' && $request->estado !== null) {
            $estado = filter_var($request->estado, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($estado !== null) {
                $query->where('proveedor.estado', $estado);
            }
        }

        // Filtro por calificación
        if ($request->has('calificacion') && !empty($request->calificacion)) {
            $calificacion = $request->calificacion;
            $query->where('proveedor_calificaciones.estado', $calificacion);
        }

        // Filtro por tipo de proveedor
        if ($request->has('tipo_proveedor') && !empty($request->tipo_proveedor)) {
            $query->where('proveedor.tipo_proveedor', $request->tipo_proveedor);
        }

        // Ordenar
        if ($request->has('search') && !empty($request->search)) {
            $query->orderBy('proveedor.razon_social', 'asc');
        } else {
            $query->latest('proveedor.id');
        }

        // Paginación
        $perPage = min($request->input('per_page', 50), 100);
        $proveedores = $query->paginate($perPage);

        // Transformar los resultados para incluir la calificación en la estructura esperada
        $proveedores->getCollection()->transform(function ($proveedor) {
            if ($proveedor->calificacion_id) {
                $proveedor->ultimaCalificacion = (object) [
                    'id' => $proveedor->calificacion_id,
                    'proveedor_id' => $proveedor->id,
                    'estado' => $proveedor->calificacion_estado,
                    'observacion' => $proveedor->calificacion_observacion,
                ];
            } else {
                $proveedor->ultimaCalificacion = null;
            }
            unset($proveedor->calificacion_id);
            unset($proveedor->calificacion_estado);
            unset($proveedor->calificacion_observacion);
            return $proveedor;
        });

        return response()->json($proveedores);
    }

    /**
     * Crear nuevo proveedor
     * POST /api/proveedores
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tipo_proveedor' => 'required|in:empresa,persona',
            'razon_social' => 'required|string|max:191|unique:proveedor,razon_social',
            'ruc' => [
                $request->tipo_proveedor === 'empresa' ? 'required' : 'nullable',
                'string',
                'max:191',
                Rule::unique('proveedor', 'ruc')->whereNotNull('ruc'),
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
            'tipo_proveedor.required' => 'El tipo de proveedor es requerido',
            'tipo_proveedor.in' => 'El tipo de proveedor debe ser empresa o persona',
            'razon_social.required' => 'La razón social es requerida',
            'razon_social.unique' => 'Ya existe un proveedor con esta razón social',
            'ruc.required' => 'El RUC es requerido para proveedores empresa',
            'ruc.unique' => 'Ya existe un proveedor con este RUC/DNI',
            'vendedores.*.dni.size' => 'El DNI debe tener 8 dígitos',
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
                'tipo_proveedor' => $request->tipo_proveedor,
                'razon_social' => $request->razon_social,
                'ruc' => $request->ruc ?: null,
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
            'tipo_proveedor' => 'nullable|in:empresa,persona',
            'razon_social' => [
                'nullable',
                'string',
                'max:191',
                Rule::unique('proveedor', 'razon_social')->ignore($id)
            ],
            'ruc' => [
                'nullable',
                'string',
                'max:191',
                Rule::unique('proveedor', 'ruc')->ignore($id)->whereNotNull('ruc'),
            ],
            'direccion' => 'nullable|string|max:191',
            'telefono' => 'nullable|string|max:191',
            'email' => 'nullable|email|max:191',
            'estado' => 'nullable|boolean',
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
            'razon_social.unique' => 'Ya existe un proveedor con esta razón social',
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

            // Actualizar proveedor solo si se envían los datos
            $updateData = [];
            if ($request->has('tipo_proveedor') && !empty($request->tipo_proveedor)) {
                $updateData['tipo_proveedor'] = $request->tipo_proveedor;
            }
            if ($request->has('razon_social') && !empty($request->razon_social)) {
                $updateData['razon_social'] = $request->razon_social;
            }
            if ($request->has('ruc')) {
                $updateData['ruc'] = $request->ruc ?: null;
            }
            if ($request->has('direccion')) {
                $updateData['direccion'] = $request->direccion;
            }
            if ($request->has('telefono')) {
                $updateData['telefono'] = $request->telefono;
            }
            if ($request->has('email')) {
                $updateData['email'] = $request->email;
            }
            if ($request->has('estado') && $request->estado !== null) {
                $updateData['estado'] = $request->estado;
            }

            if (!empty($updateData)) {
                $proveedor->update($updateData);
            }

            // Eliminar todos los vendedores existentes y crear los nuevos
            if ($request->has('vendedores')) {
                Vendedor::where('proveedor_id', $id)->delete();
                if (is_array($request->vendedores)) {
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
            }

            // Eliminar todos los carros existentes y crear los nuevos
            if ($request->has('carros')) {
                Carro::where('proveedor_id', $id)->delete();
                if (is_array($request->carros)) {
                    foreach ($request->carros as $carroData) {
                        Carro::create([
                            'placa' => $carroData['placa'],
                            'proveedor_id' => $proveedor->id,
                        ]);
                    }
                }
            }

            // Eliminar todos los choferes existentes y crear los nuevos
            if ($request->has('choferes')) {
                Chofer::where('proveedor_id', $id)->delete();
                if (is_array($request->choferes)) {
                    foreach ($request->choferes as $choferData) {
                        Chofer::create([
                            'dni' => $choferData['dni'],
                            'name' => $choferData['name'],
                            'licencia' => $choferData['licencia'],
                            'proveedor_id' => $proveedor->id,
                        ]);
                    }
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

    /**
     * Obtener IDs de proveedores que tienen compras
     * GET /api/proveedores/con-compras/ids
     */
    public function getProveedoresConCompras()
    {
        $proveedoresConCompras = DB::table('compra')
            ->distinct()
            ->pluck('proveedor_id')
            ->toArray();

        return response()->json([
            'data' => $proveedoresConCompras,
        ]);
    }

    /**
     * Obtener proveedores ordenados por cantidad de compras
     * GET /api/proveedores/ordenar-por-compras
     */
    public function getProveedoresOrdenadosPorCompras(Request $request)
    {
        $query = Proveedor::with(['vendedores', 'carros', 'choferes'])
            ->leftJoin('compra', 'proveedor.id', '=', 'compra.proveedor_id')
            ->leftJoin('proveedor_calificaciones', function ($join) {
                $join->on('proveedor.id', '=', 'proveedor_calificaciones.proveedor_id')
                    ->whereRaw('proveedor_calificaciones.id = (
                        SELECT id FROM proveedor_calificaciones pc2 
                        WHERE pc2.proveedor_id = proveedor.id 
                        ORDER BY pc2.id DESC LIMIT 1
                    )');
            })
            ->select('proveedor.*', DB::raw('COUNT(compra.id) as cantidad_compras'), 'proveedor_calificaciones.estado as calificacion_estado', 'proveedor_calificaciones.observacion as calificacion_observacion', 'proveedor_calificaciones.id as calificacion_id')
            ->groupBy('proveedor.id', 'proveedor_calificaciones.id', 'proveedor_calificaciones.estado', 'proveedor_calificaciones.observacion')
            ->orderByDesc('cantidad_compras');

        // Filtro por búsqueda
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('proveedor.razon_social', 'LIKE', "%{$search}%")
                    ->orWhere('proveedor.ruc', 'LIKE', "%{$search}%");
            });
        }

        // Filtro por estado
        if ($request->has('estado') && $request->estado !== '' && $request->estado !== null) {
            $estado = filter_var($request->estado, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($estado !== null) {
                $query->where('proveedor.estado', $estado);
            }
        }

        // Filtro por calificación
        if ($request->has('calificacion') && !empty($request->calificacion)) {
            $calificacion = $request->calificacion;
            $query->where('proveedor_calificaciones.estado', $calificacion);
        }

        // Filtro por tipo de proveedor
        if ($request->has('tipo_proveedor') && !empty($request->tipo_proveedor)) {
            $query->where('proveedor.tipo_proveedor', $request->tipo_proveedor);
        }

        // Paginación
        $perPage = min($request->input('per_page', 50), 100);
        $proveedores = $query->paginate($perPage);

        // Transformar los resultados para incluir la calificación en la estructura esperada
        $proveedores->getCollection()->transform(function ($proveedor) {
            if ($proveedor->calificacion_id) {
                $proveedor->ultimaCalificacion = (object) [
                    'id' => $proveedor->calificacion_id,
                    'proveedor_id' => $proveedor->id,
                    'estado' => $proveedor->calificacion_estado,
                    'observacion' => $proveedor->calificacion_observacion,
                ];
            } else {
                $proveedor->ultimaCalificacion = null;
            }
            unset($proveedor->calificacion_id);
            unset($proveedor->calificacion_estado);
            unset($proveedor->calificacion_observacion);
            return $proveedor;
        });

        return response()->json($proveedores);
    }

    /**
     * Verificar si un RUC ya existe
     * GET /api/proveedores/check-documento?ruc=...&exclude_id=...
     */
    public function checkDocumento(Request $request)
    {
        $request->validate([
            'ruc' => 'required|string',
            'exclude_id' => 'nullable|integer',
        ]);

        $query = Proveedor::where('ruc', $request->ruc);

        // Excluir el ID actual si estamos editando
        if ($request->has('exclude_id')) {
            $query->where('id', '!=', $request->exclude_id);
        }

        $exists = $query->exists();

        return response()->json([
            'exists' => $exists,
        ]);
    }
}
