<?php

namespace App\Services;

use App\Models\Paquete;
use App\Models\PaqueteProducto;
use Illuminate\Support\Facades\DB;

/**
 * Servicio para manejar la lógica de negocio de Paquetes
 */
class PaqueteService
{
    /**
     * Crear un nuevo paquete con sus productos
     * 
     * @param array $data
     * @return Paquete
     * @throws \Exception
     */
    public function crearPaquete(array $data): Paquete
    {
        return DB::transaction(function () use ($data) {
            // 1. Crear el paquete
            $paquete = Paquete::create([
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'] ?? null,
                'activo' => $data['activo'] ?? true,
            ]);

            // 2. Agregar productos al paquete
            $this->agregarProductos($paquete->id, $data['productos']);

            // 3. Cargar relaciones para la respuesta
            return $paquete->load([
                'productos:id,paquete_id,producto_id,unidad_derivada_id,cantidad,tipo_precio,precio_publico,precio_especial,precio_minimo,precio_ultimo,descuento_publico,descuento_especial,descuento_minimo,descuento_ultimo',
                'productos.producto:id,name,cod_producto,marca_id',
                'productos.producto.marca:id,name',
                'productos.unidadDerivada:id,name',
            ]);
        });
    }

    /**
     * Actualizar un paquete existente
     * 
     * @param Paquete $paquete
     * @param array $data
     * @return Paquete
     * @throws \Exception
     */
    public function actualizarPaquete(Paquete $paquete, array $data): Paquete
    {
        return DB::transaction(function () use ($paquete, $data) {
            // 1. Actualizar datos básicos del paquete
            if (isset($data['nombre'])) {
                $paquete->nombre = $data['nombre'];
            }
            if (isset($data['descripcion'])) {
                $paquete->descripcion = $data['descripcion'];
            }
            if (isset($data['activo'])) {
                $paquete->activo = $data['activo'];
            }
            $paquete->save();

            // 2. Si se enviaron productos, reemplazar todos
            if (isset($data['productos'])) {
                // Eliminar productos existentes
                PaqueteProducto::where('paquete_id', $paquete->id)->delete();
                
                // Agregar nuevos productos
                $this->agregarProductos($paquete->id, $data['productos']);
            }

            // 3. Cargar relaciones para la respuesta
            return $paquete->load([
                'productos:id,paquete_id,producto_id,unidad_derivada_id,cantidad,tipo_precio,precio_publico,precio_especial,precio_minimo,precio_ultimo,descuento_publico,descuento_especial,descuento_minimo,descuento_ultimo',
                'productos.producto:id,name,cod_producto,marca_id',
                'productos.producto.marca:id,name',
                'productos.unidadDerivada:id,name',
            ]);
        });
    }

    /**
     * Eliminar un paquete
     * 
     * @param Paquete $paquete
     * @return bool
     * @throws \Exception
     */
    public function eliminarPaquete(Paquete $paquete): bool
    {
        return DB::transaction(function () use ($paquete) {
            // Los productos se eliminan automáticamente por CASCADE
            return $paquete->delete();
        });
    }

    /**
     * Agregar productos a un paquete
     * 
     * @param int $paqueteId
     * @param array $productos
     * @return void
     */
    private function agregarProductos(int $paqueteId, array $productos): void
    {
        foreach ($productos as $productoData) {
            PaqueteProducto::create([
                'paquete_id' => $paqueteId,
                'producto_id' => $productoData['producto_id'],
                'unidad_derivada_id' => $productoData['unidad_derivada_id'],
                'cantidad' => $productoData['cantidad'],
                'tipo_precio' => $productoData['tipo_precio'] ?? 'publico',
                'precio_publico' => $productoData['precio_publico'] ?? null,
                'precio_especial' => $productoData['precio_especial'] ?? null,
                'precio_minimo' => $productoData['precio_minimo'] ?? null,
                'precio_ultimo' => $productoData['precio_ultimo'] ?? null,
                'descuento_publico' => $productoData['descuento_publico'] ?? 0,
                'descuento_especial' => $productoData['descuento_especial'] ?? 0,
                'descuento_minimo' => $productoData['descuento_minimo'] ?? 0,
                'descuento_ultimo' => $productoData['descuento_ultimo'] ?? 0,
            ]);
        }
    }
}

