<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaqueteRequest;
use App\Http\Requests\UpdatePaqueteRequest;
use App\Models\Paquete;
use App\Services\PaqueteService;
use Illuminate\Http\Request;

/**
 * Controller para gestionar Paquetes de productos
 * 
 * Un paquete es un conjunto predefinido de productos que se pueden
 * agregar rápidamente a una venta.
 */
class PaqueteController extends Controller
{
    protected $paqueteService;

    public function __construct(PaqueteService $paqueteService)
    {
        $this->paqueteService = $paqueteService;
    }

    /**
     * Listar paquetes con búsqueda y paginación
     */
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'sometimes|string|max:255',
            'activo' => 'sometimes|string|in:true,false,1,0',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        $query = Paquete::query()
            ->with([
                'productos.producto:id,name,cod_producto,marca_id',
                'productos.producto.marca:id,name',
                'productos.producto.unidadesDerivadasConPrecios.unidadDerivada:id,name',
                'productos.unidadDerivada:id,name',
            ])
            ->withCount('productos as productos_count');

        // Filtros
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->has('activo')) {
            // Convertir string a booleano
            $activo = filter_var($request->activo, FILTER_VALIDATE_BOOLEAN);
            $query->where('activo', $activo);
        }

        // Ordenar y paginar
        $query->orderBy('nombre', 'asc');
        $perPage = $request->get('per_page', 15);
        $paquetes = $query->paginate($perPage);

        return response()->json([
            'data' => $paquetes->items(),
            'total' => $paquetes->total(),
            'current_page' => $paquetes->currentPage(),
            'per_page' => $paquetes->perPage(),
            'last_page' => $paquetes->lastPage(),
        ]);
    }

    /**
     * Obtener un paquete por ID con todos sus productos
     */
    public function show($id)
    {
        $paquete = Paquete::with([
            'productos.producto:id,name,cod_producto,marca_id',
            'productos.producto.marca:id,name',
            'productos.producto.unidadesDerivadasConPrecios.unidadDerivada:id,name',
            'productos.unidadDerivada:id,name',
        ])->find($id);

        if (!$paquete) {
            return response()->json([
                'error' => ['message' => 'Paquete no encontrado'],
            ], 404);
        }

        return response()->json(['data' => $paquete]);
    }

    /**
     * Crear un nuevo paquete
     */
    public function store(StorePaqueteRequest $request)
    {
        try {
            $paquete = $this->paqueteService->crearPaquete($request->validated());

            return response()->json([
                'data' => $paquete,
                'message' => 'Paquete creado exitosamente',
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => ['message' => 'Error al crear paquete: ' . $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Actualizar un paquete existente
     */
    public function update(UpdatePaqueteRequest $request, $id)
    {
        try {
            $paquete = Paquete::find($id);

            if (!$paquete) {
                return response()->json([
                    'error' => ['message' => 'Paquete no encontrado'],
                ], 404);
            }

            $paquete = $this->paqueteService->actualizarPaquete($paquete, $request->validated());

            return response()->json([
                'data' => $paquete,
                'message' => 'Paquete actualizado exitosamente',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => ['message' => 'Error al actualizar paquete: ' . $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Eliminar un paquete
     */
    public function destroy($id)
    {
        try {
            $paquete = Paquete::find($id);

            if (!$paquete) {
                return response()->json([
                    'error' => ['message' => 'Paquete no encontrado'],
                ], 404);
            }

            $this->paqueteService->eliminarPaquete($paquete);

            return response()->json([
                'data' => 'Paquete eliminado exitosamente',
                'message' => 'Paquete eliminado exitosamente',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => ['message' => 'Error al eliminar paquete: ' . $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Buscar paquetes que contengan un producto específico
     */
    public function byProducto($productoId)
    {
        try {
            $paquetes = Paquete::whereHas('productos', function ($query) use ($productoId) {
                $query->where('producto_id', $productoId);
            })
            ->with([
                'productos.producto:id,name,cod_producto,marca_id',
                'productos.producto.marca:id,name',
                'productos.producto.unidadesDerivadasConPrecios.unidadDerivada:id,name',
                'productos.unidadDerivada:id,name',
            ])
            ->where('activo', true)
            ->withCount('productos as productos_count')
            ->get();

            return response()->json([
                'data' => $paquetes,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => ['message' => 'Error al buscar paquetes: ' . $e->getMessage()],
            ], 500);
        }
    }
}

