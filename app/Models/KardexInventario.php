<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KardexInventario extends Model
{
    use HasFactory;

    protected $table = 'kardex_inventarios';

    protected $fillable = [
        'fecha',
        'codigo',
        'producto',
        'tipo',
        'movimiento',
        'documento',
        'unidad',
        'cantidad',
        'precio',
        'stock_anterior',
        'cant_ingreso',
        'cant_salida',
        'stock_actual',
        'producto_almacen_id',
        'usuario_id',
    ];

    public function productoAlmacen()
    {
        return $this->belongsTo(ProductoAlmacen::class, 'producto_almacen_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
