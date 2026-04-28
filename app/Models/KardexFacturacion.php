<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KardexFacturacion extends Model
{
    protected $table = 'kardex_facturacions';

    protected $fillable = [
        'fecha',
        'codigo',
        'producto',
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
        'referencia_id',
        'venta_id',
        'producto_almacen_id',
        'usuario_id',
        'almacen_id',
        'producto_id',
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
    ];

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function productoAlmacen(): BelongsTo
    {
        return $this->belongsTo(ProductoAlmacen::class, 'producto_almacen_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
