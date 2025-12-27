<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoPrestamo extends Model
{
    protected $table = 'pagos_prestamos';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'prestamo_id',
        'numero_pago',
        'monto',
        'fecha_pago',
        'metodo_pago',
        'numero_operacion',
        'observaciones',
        'user_id',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_pago' => 'datetime',
            'user_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function prestamo(): BelongsTo
    {
        return $this->belongsTo(Prestamo::class, 'prestamo_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
