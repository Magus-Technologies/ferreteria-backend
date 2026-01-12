<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfiguracionImpresion extends Model
{
    protected $table = 'configuracion_impresion';

    protected $fillable = [
        'user_id',
        'tipo_documento',
        'campo',
        'font_family',
        'font_size',
        'font_weight',
    ];

    protected $casts = [
        'font_size' => 'integer',
    ];

    /**
     * Relación con el usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Valores por defecto para un campo
     */
    public static function getDefaults(): array
    {
        return [
            'font_family' => 'Arial',
            'font_size' => 10,
            'font_weight' => 'normal',
        ];
    }

    /**
     * Campos disponibles por tipo de documento
     */
    public static function getCamposPorTipoDocumento(string $tipo_documento): array
    {
        $camposComunes = [
            'fecha' => 'Fecha',
            'numero_documento' => 'Número de Documento',
            'empresa_nombre' => 'Nombre de Empresa',
            'empresa_ruc' => 'RUC de Empresa',
            'empresa_direccion' => 'Dirección de Empresa',
        ];

        $camposPorTipo = [
            'ingreso_salida' => [
                ...$camposComunes,
                'almacen' => 'Almacén',
                'usuario' => 'Usuario',
                'proveedor' => 'Proveedor',
                'tipo_ingreso' => 'Tipo de Ingreso/Salida',
                'observaciones' => 'Observaciones',
                'tabla_codigo' => 'Tabla: Código',
                'tabla_cantidad' => 'Tabla: Cantidad',
                'tabla_unidad' => 'Tabla: Unidad',
                'tabla_costo' => 'Tabla: Costo',
                'tabla_subtotal' => 'Tabla: Subtotal',
            ],
            'venta' => [
                ...$camposComunes,
                'cliente_nombre' => 'Nombre del Cliente',
                'cliente_documento' => 'Documento del Cliente',
                'cliente_direccion' => 'Dirección del Cliente',
                'tabla_codigo' => 'Tabla: Código',
                'tabla_descripcion' => 'Tabla: Descripción',
                'tabla_cantidad' => 'Tabla: Cantidad',
                'tabla_unidad' => 'Tabla: Unidad',
                'tabla_precio' => 'Tabla: Precio',
                'tabla_subtotal' => 'Tabla: Subtotal',
                'subtotal' => 'Subtotal',
                'igv' => 'IGV',
                'total' => 'Total',
                'metodo_pago' => 'Método de Pago',
            ],
            'cotizacion' => [
                ...$camposComunes,
                'cliente_nombre' => 'Nombre del Cliente',
                'cliente_documento' => 'Documento del Cliente',
                'fecha_vencimiento' => 'Fecha de Vencimiento',
                'vendedor' => 'Vendedor',
                'tabla_codigo' => 'Tabla: Código',
                'tabla_descripcion' => 'Tabla: Descripción',
                'tabla_cantidad' => 'Tabla: Cantidad',
                'tabla_unidad' => 'Tabla: Unidad',
                'tabla_precio' => 'Tabla: Precio',
                'tabla_descuento' => 'Tabla: Descuento',
                'tabla_subtotal' => 'Tabla: Subtotal',
                'subtotal' => 'Subtotal',
                'total_descuento' => 'Total Descuento',
                'total' => 'Total',
            ],
            'prestamo' => [
                ...$camposComunes,
                'entidad_nombre' => 'Nombre de Entidad',
                'entidad_documento' => 'Documento de Entidad',
                'tipo_operacion' => 'Tipo de Operación',
                'garantia' => 'Garantía',
                'tabla_codigo' => 'Tabla: Código',
                'tabla_descripcion' => 'Tabla: Descripción',
                'tabla_cantidad' => 'Tabla: Cantidad',
                'tabla_unidad' => 'Tabla: Unidad',
                'tabla_costo' => 'Tabla: Costo',
                'tabla_importe' => 'Tabla: Importe',
                'monto_total' => 'Monto Total',
            ],
            'recepcion_almacen' => [
                ...$camposComunes,
                'almacen' => 'Almacén',
                'proveedor' => 'Proveedor',
                'orden_compra' => 'Orden de Compra',
                'tabla_codigo' => 'Tabla: Código',
                'tabla_cantidad' => 'Tabla: Cantidad',
                'tabla_unidad' => 'Tabla: Unidad',
                'tabla_costo' => 'Tabla: Costo',
                'tabla_subtotal' => 'Tabla: Subtotal',
            ],
            'compra' => [
                ...$camposComunes,
                'proveedor' => 'Proveedor',
                'tabla_codigo' => 'Tabla: Código',
                'tabla_descripcion' => 'Tabla: Descripción',
                'tabla_cantidad' => 'Tabla: Cantidad',
                'tabla_unidad' => 'Tabla: Unidad',
                'tabla_precio' => 'Tabla: Precio',
                'tabla_subtotal' => 'Tabla: Subtotal',
                'subtotal' => 'Subtotal',
                'igv' => 'IGV',
                'total' => 'Total',
            ],
        ];

        return $camposPorTipo[$tipo_documento] ?? $camposComunes;
    }
}
