<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\PlantillaImpresionDetalle;

class PlantillaImpresion extends Model
{
    protected $table = 'plantilla_impresion';

    protected $fillable = [
        'empresa_id',
        'mensaje_despedida',
        'despedida_activo',
        'logos_nota_venta',
        'estilos',
        'mensajes_extra',
        'estilos_secciones',
    ];

    protected $casts = [
        'despedida_activo' => 'boolean',
        'logos_nota_venta' => 'array',
        'estilos' => 'array',
        'mensajes_extra' => 'array',
        'estilos_secciones' => 'array',
    ];

    public const DEFAULT_DESPEDIDA = '';

    public const DEFAULT_ESTILOS = [
        'color_tema' => '#fadc06',
        'color_borde' => '#fadc06',
        'color_texto' => '#000000',
        'fuente' => 'Helvetica',
        'tamano_base' => 8,
        'grosor_borde' => 2,
        'densidad' => 'normal',
    ];

    public const DEFAULT_MENSAJES_EXTRA = [
        'label_observaciones' => 'OBSERVACIONES',
        'observaciones_default' => '- NINGUNA',
        'leyenda_consulta' => 'Consulte su documento en:',
        'leyenda_representacion' => 'Representacion impresa del comprobante electronico',
        // Flags específicos de cotización
        'ocultar_canjear' => false,
        'ocultar_despedida' => false,
        'ocultar_cuentas_bancarias' => false,
        // Oculta el logo de la empresa en el ticket (configurable por comprobante).
        'ocultar_logo' => false,
        // Bloque de firma del PDF A4 (orden de compra). El nombre no sale de la
        // tabla `empresa` porque ahí no hay campo de representante legal; se deja
        // acá para poder cambiarlo desde la configuración de plantillas sin tocar
        // código.
        'firma_cargo' => 'GERENTE GENERAL',
        'firma_nombre' => 'ELIAS CASTILLO CHIGNE',
    ];

    /**
     * Catalogo de bloques visuales del PDF. Cada bloque puede sobrescribir
     * los estilos globales con: color, tamano (pt), peso, alineacion.
     */
    public const BLOQUES = [
        'empresa_razon'      => 'Razon social de la empresa',
        'empresa_direccion'  => 'Direccion y email de la empresa',
        'caja_ruc'           => 'Caja documento - linea de RUC',
        'caja_tipo'          => 'Caja documento - banner del tipo (BOLETA/FACTURA)',
        'caja_numero'        => 'Caja documento - numero (B001-...)',
        'info_label'         => 'Info-grid: etiquetas (Cliente, RUC/DNI, etc.)',
        'info_valor'         => 'Info-grid: valores',
        'tabla_header'       => 'Tabla de productos: header amarillo',
        'tabla_fila'         => 'Tabla de productos: celdas de datos',
        'son'                => 'Linea "SON: VEINTE..."',
        'obs_label'          => 'OBSERVACIONES: etiqueta',
        'obs_valor'          => 'OBSERVACIONES: contenido',
        'total_label'        => 'Totales: etiquetas (SUBTOTAL, IGV, TOTAL)',
        'total_valor'        => 'Totales: valores',
        'despedida_footer'   => 'Mensaje al pie (GRACIAS POR SU PREFERENCIA!)',
        'consulta_leyenda'   => 'Leyenda "Consulte su documento en:"',
        'consulta_url'       => 'URL del enlace de consulta',

        // Bloques específicos del comprobante Entrega
        'entrega_info_label' => 'Entrega: etiquetas datos de entrega (FECHA, TIPO, DESPACHADOR, etc.)',
        'entrega_info_valor' => 'Entrega: valores datos de entrega',
    ];

    /**
     * Forma de un estilo por bloque. Cualquier campo en null = usar global.
     */
    public const ESTILO_BLOQUE_VACIO = [
        'color' => null,
        'tamano' => null,
        'peso' => null,
        'alineacion' => null,
        'cursiva' => null,
        'subrayado' => null,
        'fuente' => null,
    ];

    public static function defaultEstilosSecciones(): array
    {
        $defaults = [];
        foreach (array_keys(self::BLOQUES) as $key) {
            $defaults[$key] = self::ESTILO_BLOQUE_VACIO;
        }
        return $defaults;
    }

    /**
     * Obtener plantilla considerando un comprobante y formato específicos.
     * Si existe un detalle para (empresa, comprobante, formato) devuelve los
     * estilos/mensajes/estilos_secciones aplicando defaults; en caso contrario
     * devuelve la plantilla global (misma forma que obtenerPara).
     */
    public static function obtenerParaConFormato(int $empresaId, ?string $comprobante = null, ?string $formato = null): self
    {
        if ($comprobante || $formato) {
            $q = PlantillaImpresionDetalle::where('empresa_id', $empresaId);
            if ($comprobante) $q->where('comprobante', $comprobante);
            if ($formato) $q->where('formato', $formato);
            $detalle = $q->first();

            if ($detalle) {
                // Construimos un objeto similar a PlantillaImpresion con campos resueltos
                $base = self::obtenerPara($empresaId);
                $plantilla = new self();
                $plantilla->empresa_id = $empresaId;
                $plantilla->mensaje_despedida = $base->mensaje_despedida;
                $plantilla->despedida_activo = $base->despedida_activo;
                $plantilla->logos_nota_venta = $base->logos_nota_venta;
                $plantilla->estilos = array_merge(self::DEFAULT_ESTILOS, $detalle->estilos ?? []);
                $plantilla->mensajes_extra = array_merge(self::DEFAULT_MENSAJES_EXTRA, $detalle->mensajes_extra ?? []);
                $plantilla->estilos_secciones = array_merge(self::defaultEstilosSecciones(), $detalle->estilos_secciones ?? []);
                return $plantilla;
            }
        }

        return self::obtenerPara($empresaId);
    }

    public static function obtenerPara(int $empresaId): self
    {
        $plantilla = self::where('empresa_id', $empresaId)->first();

        if ($plantilla) {
            $plantilla->estilos = array_merge(self::DEFAULT_ESTILOS, $plantilla->estilos ?? []);
            $plantilla->mensajes_extra = array_merge(self::DEFAULT_MENSAJES_EXTRA, $plantilla->mensajes_extra ?? []);
            $plantilla->estilos_secciones = array_merge(
                self::defaultEstilosSecciones(),
                $plantilla->estilos_secciones ?? []
            );
            return $plantilla;
        }

        $plantilla = new self();
        $plantilla->empresa_id = $empresaId;
        $plantilla->mensaje_despedida = self::DEFAULT_DESPEDIDA;
        $plantilla->despedida_activo = false;
        $plantilla->logos_nota_venta = [];
        $plantilla->estilos = self::DEFAULT_ESTILOS;
        $plantilla->mensajes_extra = self::DEFAULT_MENSAJES_EXTRA;
        $plantilla->estilos_secciones = self::defaultEstilosSecciones();

        return $plantilla;
    }
}
