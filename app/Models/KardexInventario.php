<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KardexInventario extends Model
{
    protected $table = 'kardex_inventarios';

    protected $fillable = [
        'fecha',
        'codigo',
        'producto',
        'proveedor_nombre',
        'tipo',
        'movimiento',
        'documento',
        'unidad',
        'cantidad',
        'cantidad_fraccion',
        'precio',
        'costo',
        'entrada',
        'salida',
        'stock_anterior',
        'cant_ingreso',
        'cant_salida',
        'stock_actual',
        'costo_anterior',
        'costo_actual',
        'referencia_id',
        'producto_almacen_id',
        'usuario_id',
        'almacen_id',
        'producto_id',
        'proveedor_id',
        'producto_nombre',
        'producto_codigo',
        'orden',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'cantidad' => 'float',
        'cantidad_fraccion' => 'float',
        'precio' => 'float',
        'costo' => 'float',
        'entrada' => 'float',
        'salida' => 'float',
        'stock_anterior' => 'float',
        'cant_ingreso' => 'float',
        'cant_salida' => 'float',
        'stock_actual' => 'float',
        'costo_anterior' => 'float',
        'costo_actual' => 'float',
    ];

    public function productoAlmacen(): BelongsTo
    {
        return $this->belongsTo(ProductoAlmacen::class, 'producto_almacen_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
