<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubCaja extends Model
{
    protected $table = 'sub_cajas';

    protected $fillable = [
        'codigo',
        'nombre',
        'caja_principal_id',
        'tipo_caja',
        'despliegues_pago_ids',
        'tipos_comprobante',
        'saldo_actual',
        'proposito',
        'estado',
    ];

    protected $casts = [
        'despliegues_pago_ids' => 'array',
        'tipos_comprobante' => 'array',
        'saldo_actual' => 'decimal:2',
        'estado' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relaciones
    public function cajaPrincipal()
    {
        return $this->belongsTo(CajaPrincipal::class, 'caja_principal_id');
    }

    public function transacciones()
    {
        return $this->hasMany(TransaccionCaja::class, 'sub_caja_id');
    }

    public function prestamosEnviados()
    {
        return $this->hasMany(PrestamoEntreCajas::class, 'sub_caja_origen_id');
    }

    public function prestamosRecibidos()
    {
        return $this->hasMany(PrestamoEntreCajas::class, 'sub_caja_destino_id');
    }

    public function movimientosOrigen()
    {
        return $this->hasMany(MovimientoInterno::class, 'sub_caja_origen_id');
    }

    public function movimientosDestino()
    {
        return $this->hasMany(MovimientoInterno::class, 'sub_caja_destino_id');
    }

    public function cierres()
    {
        return $this->hasMany(CierreCaja::class, 'sub_caja_id');
    }

    // Métodos helper
    
    /**
     * Obtener los DespliegueDePago asociados a esta sub-caja
     */
    public function getDesplieguePagos()
    {
        if (empty($this->despliegues_pago_ids)) {
            return collect([]);
        }

        // Si es ["*"], retornar todos los activos
        if (in_array('*', $this->despliegues_pago_ids)) {
            return DespliegueDePago::with('metodoDePago')
                ->where('mostrar', 1)
                ->get();
        }

        // Retornar los específicos
        return DespliegueDePago::with('metodoDePago')
            ->whereIn('id', $this->despliegues_pago_ids)
            ->get();
    }

    /**
     * Verificar si esta sub-caja acepta un método de pago específico
     */
    public function aceptaMetodoPago(string $desplieguePagoId): bool
    {
        if (empty($this->despliegues_pago_ids)) {
            return false;
        }

        // Si tiene "*", acepta todos
        if (in_array('*', $this->despliegues_pago_ids)) {
            return true;
        }

        // Verificar si el ID está en el array
        return in_array($desplieguePagoId, $this->despliegues_pago_ids);
    }

    /**
     * Verificar si esta sub-caja acepta un tipo de comprobante
     */
    public function aceptaComprobante(string $tipoComprobante): bool
    {
        if (empty($this->tipos_comprobante)) {
            return false;
        }

        return in_array($tipoComprobante, $this->tipos_comprobante);
    }

    /**
     * Calcular especificidad de la sub-caja (para priorización)
     * Mayor número = más específica
     */
    public function calcularEspecificidad(): int
    {
        $especificidadMetodo = 0;
        $especificidadComprobante = 0;

        // Especificidad de métodos de pago
        if (in_array('*', $this->despliegues_pago_ids)) {
            $especificidadMetodo = 0; // Menos específica
        } else {
            $especificidadMetodo = 100 - count($this->despliegues_pago_ids);
        }

        // Especificidad de comprobantes
        $especificidadComprobante = 100 - count($this->tipos_comprobante);

        return $especificidadMetodo + $especificidadComprobante;
    }

    /**
     * Verificar si es Caja Chica
     */
    public function esCajaChica(): bool
    {
        return $this->tipo_caja === 'CC';
    }

    /**
     * Verificar si puede ser eliminada
     */
    public function puedeEliminar(): bool
    {
        return !$this->esCajaChica() && $this->saldo_actual == 0;
    }

    /**
     * Verificar si puede ser modificada
     */
    public function puedeModificar(): bool
    {
        return !$this->esCajaChica();
    }
}
