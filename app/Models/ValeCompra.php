<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ValeCompra extends Model
{
    protected $table = 'vales_compra';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'tipo_promocion',
        'momento_aplicacion',
        'sorteo_incluye_producto',
        'modalidad',
        'cantidad_minima',
        'tipo_umbral',
        'max_vales_por_venta',
        'descuento_tipo',
        'descuento_valor',
        'descuento_alcance',
        'descuento_producto_ids',
        'descuento_categoria_ids',
        'producto_gratis_id',
        'cantidad_producto_gratis',
        'fecha_inicio',
        'fecha_fin',
        'fecha_validez_vale',
        'dias_validez_vale',
        'usa_limite_por_cliente',
        'limite_usos_cliente',
        'usa_limite_stock',
        'stock_disponible',
        'aplica_precio_publico',
        'aplica_precio_especial',
        'aplica_precio_minimo',
        'aplica_precio_ultimo',
        'estado',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_minima' => 'decimal:2',
            'descuento_valor' => 'decimal:2',
            'cantidad_producto_gratis' => 'decimal:3',
            'descuento_producto_ids' => 'array',
            'descuento_categoria_ids' => 'array',
            'fecha_inicio' => 'date:Y-m-d',
            'fecha_fin' => 'date:Y-m-d',
            'fecha_validez_vale' => 'date:Y-m-d',
            'dias_validez_vale' => 'integer',
            'usa_limite_por_cliente' => 'boolean',
            'limite_usos_cliente' => 'integer',
            'usa_limite_stock' => 'boolean',
            'stock_disponible' => 'integer',
            'aplica_precio_publico' => 'boolean',
            'aplica_precio_especial' => 'boolean',
            'aplica_precio_minimo' => 'boolean',
            'aplica_precio_ultimo' => 'boolean',
            'sorteo_incluye_producto' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // Relaciones

    public function productoGratis(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_gratis_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function categorias(): BelongsToMany
    {
        return $this->belongsToMany(
            Categoria::class,
            'vales_compra_categorias',
            'vale_compra_id',
            'categoria_id'
        )->withPivot('created_at');
    }

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(
            Producto::class,
            'vales_compra_productos',
            'vale_compra_id',
            'producto_id'
        )->withPivot('created_at');
    }

    public function aplicaciones(): HasMany
    {
        return $this->hasMany(ValeCompraAplicado::class, 'vale_compra_id');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(ValeCompraHistorial::class, 'vale_compra_id');
    }

    // Scopes

    public function scopeActivos($query)
    {
        return $query->where('estado', 'ACTIVO');
    }

    public function scopeVigentes($query)
    {
        // fecha_inicio/fecha_fin son DATE (sin hora). Comparamos por fecha para que
        // un vale cuyo rango incluye hoy sea vigente todo el día (evita que
        // fecha_fin = hoy quede invalidada desde la medianoche por la hora de now()).
        return $query->whereDate('fecha_inicio', '<=', today())
                    ->where(function($q) {
                        $q->whereNull('fecha_fin')
                          ->orWhereDate('fecha_fin', '>=', today());
                    });
    }

    public function scopePorModalidad($query, $modalidad)
    {
        return $query->where('modalidad', $modalidad);
    }

    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipo_promocion', $tipo);
    }

    // Métodos auxiliares

    public function esVigente(): bool
    {
        // Comparamos por fecha (no datetime): fecha_inicio/fecha_fin son DATE, y un
        // vale con fecha_fin = hoy debe seguir vigente hasta el final del día.
        $hoy = today();
        $inicioValido = $this->fecha_inicio->lte($hoy);
        $finValido = is_null($this->fecha_fin) || $this->fecha_fin->gte($hoy);

        return $inicioValido && $finValido;
    }

    public function tieneStockDisponible(): bool
    {
        if (!$this->usa_limite_stock) {
            return true;
        }
        
        return $this->stock_disponible > 0;
    }

    public function clientePuedeUsar(int $clienteId): bool
    {
        if (!$this->usa_limite_por_cliente) {
            return true;
        }

        $usosCliente = $this->aplicaciones()
            ->where('cliente_id', $clienteId)
            ->count();

        return $usosCliente < $this->limite_usos_cliente;
    }

    public function decrementarStock(int $cantidad = 1): void
    {
        if (!$this->usa_limite_stock || $this->stock_disponible <= 0) return;

        $decremento = min($cantidad, $this->stock_disponible);
        $this->decrement('stock_disponible', $decremento);

        if ($this->stock_disponible <= 0) {
            $this->update(['estado' => 'FINALIZADO']);
            $this->historial()->create([
                'accion' => 'STOCK_AGOTADO',
                'descripcion' => 'Stock de promociones agotado',
                'fecha' => now(),
            ]);
        }
    }

    public function generarCodigoValeCliente(): string
    {
        return 'VCC-' . $this->id . '-' . strtoupper(substr(md5(uniqid()), 0, 8));
    }

    public static function generarNuevoCodigo(): string
    {
        $ultimo = self::orderBy('id', 'desc')->first();
        $numero = $ultimo ? $ultimo->id + 1 : 1;
        return 'VC-' . str_pad($numero, 4, '0', STR_PAD_LEFT);
    }
}
