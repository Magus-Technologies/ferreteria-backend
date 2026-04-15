<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfiguracionNotificacion extends Model
{
    protected $table = 'configuracion_notificaciones';

    protected $fillable = [
        'id',
        'user_id',
        'tipo',
        'habilitado',
        'dias_anticipacion',
        'extra',
    ];

    protected $casts = [
        'habilitado'        => 'boolean',
        'dias_anticipacion' => 'integer',
        'extra'             => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Tipos de notificación válidos
     */
    public static function tipos(): array
    {
        return ['cumpleanos', 'entrega', 'pago', 'vale', 'caja', 'vencimiento'];
    }
}
