<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionImpresion;
use App\Models\PlantillaImpresion;
use App\Models\PlantillaImpresionDetalle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ConfiguracionImpresionController extends Controller
{
    /**
     * Obtener todas las configuraciones de impresión para un tipo de documento
     */
    public function index($tipo_documento)
    {
        $user = Auth::user();

        // Validar tipo de documento
        $tiposValidos = ['ingreso_salida', 'venta', 'cotizacion', 'prestamo', 'recepcion_almacen', 'compra'];
        if (!in_array($tipo_documento, $tiposValidos)) {
            return response()->json(['error' => 'Tipo de documento inválido'], 400);
        }

        // Obtener todas las configuraciones del usuario para este tipo de documento
        $configuraciones = ConfiguracionImpresion::where('user_id', $user->id)
            ->where('tipo_documento', $tipo_documento)
            ->get()
            ->keyBy('campo');

        // Obtener campos disponibles para este tipo de documento
        $camposDisponibles = ConfiguracionImpresion::getCamposPorTipoDocumento($tipo_documento);

        return response()->json([
            'tipo_documento' => $tipo_documento,
            'campos_disponibles' => $camposDisponibles,
            'configuraciones' => $configuraciones,
        ]);
    }

    /**
     * Obtener la configuración de un campo específico
     */
    public function show($tipo_documento, $campo)
    {
        $user = Auth::user();

        // Validar tipo de documento
        $tiposValidos = ['ingreso_salida', 'venta', 'cotizacion', 'prestamo', 'recepcion_almacen', 'compra'];
        if (!in_array($tipo_documento, $tiposValidos)) {
            return response()->json(['error' => 'Tipo de documento inválido'], 400);
        }

        // Buscar configuración existente
        $configuracion = ConfiguracionImpresion::where('user_id', $user->id)
            ->where('tipo_documento', $tipo_documento)
            ->where('campo', $campo)
            ->first();

        // Si no existe, retornar valores por defecto
        if (!$configuracion) {
            return response()->json([
                'tipo_documento' => $tipo_documento,
                'campo' => $campo,
                ...ConfiguracionImpresion::getDefaults()
            ]);
        }

        return response()->json($configuracion);
    }

    /**
     * Actualizar la configuración de un campo específico
     */
    public function update(Request $request, $tipo_documento, $campo)
    {
        $user = Auth::user();

        // Validar datos
        $validated = $request->validate([
            'font_family' => 'required|string|max:50',
            'font_size' => 'required|integer|min:5|max:16',
            'font_weight' => ['required', Rule::in(['normal', 'bold'])],
        ]);

        // Validar tipo de documento
        $tiposValidos = ['ingreso_salida', 'venta', 'cotizacion', 'prestamo', 'recepcion_almacen', 'compra'];
        if (!in_array($tipo_documento, $tiposValidos)) {
            return response()->json(['error' => 'Tipo de documento inválido'], 400);
        }

        // Validar que el campo existe para este tipo de documento
        $camposDisponibles = ConfiguracionImpresion::getCamposPorTipoDocumento($tipo_documento);
        if (!array_key_exists($campo, $camposDisponibles)) {
            return response()->json(['error' => 'Campo inválido para este tipo de documento'], 400);
        }

        // Actualizar o crear configuración
        $configuracion = ConfiguracionImpresion::updateOrCreate(
            [
                'user_id' => $user->id,
                'tipo_documento' => $tipo_documento,
                'campo' => $campo,
            ],
            $validated
        );

        return response()->json([
            'message' => 'Configuración actualizada correctamente',
            'data' => $configuracion
        ]);
    }

    /**
     * Actualizar múltiples campos a la vez
     */
    public function updateMultiple(Request $request, $tipo_documento)
    {
        $user = Auth::user();

        // Validar tipo de documento
        $tiposValidos = ['ingreso_salida', 'venta', 'cotizacion', 'prestamo', 'recepcion_almacen', 'compra'];
        if (!in_array($tipo_documento, $tiposValidos)) {
            return response()->json(['error' => 'Tipo de documento inválido'], 400);
        }

        // Validar datos
        $validated = $request->validate([
            'configuraciones' => 'required|array',
            'configuraciones.*.campo' => 'required|string',
            'configuraciones.*.font_family' => 'required|string|max:50',
            'configuraciones.*.font_size' => 'required|integer|min:5|max:16',
            'configuraciones.*.font_weight' => ['required', Rule::in(['normal', 'bold'])],
        ]);

        $camposDisponibles = ConfiguracionImpresion::getCamposPorTipoDocumento($tipo_documento);
        $configuracionesActualizadas = [];

        foreach ($validated['configuraciones'] as $config) {
            // Validar que el campo existe
            if (!array_key_exists($config['campo'], $camposDisponibles)) {
                continue;
            }

            $configuracion = ConfiguracionImpresion::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'tipo_documento' => $tipo_documento,
                    'campo' => $config['campo'],
                ],
                [
                    'font_family' => $config['font_family'],
                    'font_size' => $config['font_size'],
                    'font_weight' => $config['font_weight'],
                ]
            );

            $configuracionesActualizadas[] = $configuracion;
        }

        return response()->json([
            'message' => 'Configuraciones actualizadas correctamente',
            'data' => $configuracionesActualizadas
        ]);
    }

    /**
     * Resetear la configuración de un campo específico
     */
    public function resetCampo($tipo_documento, $campo)
    {
        $user = Auth::user();

        // Validar tipo de documento
        $tiposValidos = ['ingreso_salida', 'venta', 'cotizacion', 'prestamo', 'recepcion_almacen', 'compra'];
        if (!in_array($tipo_documento, $tiposValidos)) {
            return response()->json(['error' => 'Tipo de documento inválido'], 400);
        }

        // Buscar y eliminar configuración existente
        ConfiguracionImpresion::where('user_id', $user->id)
            ->where('tipo_documento', $tipo_documento)
            ->where('campo', $campo)
            ->delete();

        return response()->json([
            'message' => 'Configuración reseteada a valores por defecto',
            'data' => [
                'tipo_documento' => $tipo_documento,
                'campo' => $campo,
                ...ConfiguracionImpresion::getDefaults()
            ]
        ]);
    }

    /**
     * Resetear todas las configuraciones de un tipo de documento
     */
    public function resetAll($tipo_documento)
    {
        $user = Auth::user();

        // Validar tipo de documento
        $tiposValidos = ['ingreso_salida', 'venta', 'cotizacion', 'prestamo', 'recepcion_almacen', 'compra'];
        if (!in_array($tipo_documento, $tiposValidos)) {
            return response()->json(['error' => 'Tipo de documento inválido'], 400);
        }

        // Eliminar todas las configuraciones del usuario para este tipo de documento
        ConfiguracionImpresion::where('user_id', $user->id)
            ->where('tipo_documento', $tipo_documento)
            ->delete();

        return response()->json([
            'message' => 'Todas las configuraciones reseteadas a valores por defecto',
            'data' => [
                'tipo_documento' => $tipo_documento,
            ]
        ]);
    }

    /**
     * Obtener la plantilla de impresión (mensajes HTML) para la empresa activa.
     */
    public function showPlantilla(Request $request)
    {
        $empresaId = $this->resolverEmpresaId($request);
        if (!$empresaId) {
            return response()->json(['success' => false, 'message' => 'Sin empresa activa'], 400);
        }
        // Soportar plantillas específicas por comprobante + formato
        $comprobante = $request->query('comprobante');
        $formato = $request->query('formato');

        if ($comprobante || $formato) {
            $q = PlantillaImpresionDetalle::where('empresa_id', $empresaId);
            if ($comprobante) $q->where('comprobante', $comprobante);
            if ($formato) $q->where('formato', $formato);
            $detalle = $q->first();

            if ($detalle) {
                // Usamos los defaults del modelo principal y aplicamos los valores del detalle
                $base = PlantillaImpresion::obtenerPara($empresaId);
                $estilos = array_merge(PlantillaImpresion::DEFAULT_ESTILOS, $detalle->estilos ?? []);
                $mensajes_extra = array_merge(PlantillaImpresion::DEFAULT_MENSAJES_EXTRA, $detalle->mensajes_extra ?? []);
                $estilos_secciones = array_merge(PlantillaImpresion::defaultEstilosSecciones(), $detalle->estilos_secciones ?? []);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'empresa_id' => (int) $empresaId,
                        'mensaje_despedida' => $base->mensaje_despedida,
                        'despedida_activo' => (bool) $base->despedida_activo,
                        'logos_nota_venta' => $base->logos_nota_venta ?? [],
                        'estilos' => $estilos,
                        'mensajes_extra' => $mensajes_extra,
                        'estilos_secciones' => $estilos_secciones,
                        'bloques_catalogo' => PlantillaImpresion::BLOQUES,
                        'comprobante' => $comprobante,
                        'formato' => $formato,
                    ],
                ]);
            }
        }

        $plantilla = PlantillaImpresion::obtenerPara($empresaId);

        return response()->json([
            'success' => true,
            'data' => [
                'empresa_id' => (int) $empresaId,
                'mensaje_despedida' => $plantilla->mensaje_despedida,
                'despedida_activo' => (bool) $plantilla->despedida_activo,
                'logos_nota_venta' => $plantilla->logos_nota_venta ?? [],
                'estilos' => $plantilla->estilos,
                'mensajes_extra' => $plantilla->mensajes_extra,
                'estilos_secciones' => $plantilla->estilos_secciones,
                'bloques_catalogo' => PlantillaImpresion::BLOQUES,
            ],
        ]);
    }

    /**
     * Crear o actualizar la plantilla de impresión para la empresa activa.
     */
    public function updatePlantilla(Request $request)
    {
        $empresaId = $this->resolverEmpresaId($request);
        if (!$empresaId) {
            return response()->json(['success' => false, 'message' => 'Sin empresa activa'], 400);
        }

        // Permitimos enviar opcionalmente comprobante+formato para guardar una plantilla específica
        $validated = $request->validate([
            'mensaje_despedida' => 'nullable|string',
            'despedida_activo' => 'boolean',
            'logos_nota_venta' => 'nullable|array',
            'logos_nota_venta.*' => 'integer|exists:empresa,id',
            'estilos' => 'nullable|array',
            'estilos.color_tema' => 'nullable|string|max:30',
            'estilos.color_borde' => 'nullable|string|max:30',
            'estilos.color_texto' => 'nullable|string|max:30',
            'estilos.fuente' => 'nullable|string|max:50',
            'estilos.tamano_base' => 'nullable|integer|min:6|max:14',
            'estilos.grosor_borde' => 'nullable|integer|min:0|max:5',
            'estilos.densidad' => 'nullable|string|in:compacta,normal,espaciada',
            'mensajes_extra' => 'nullable|array',
            'mensajes_extra.label_observaciones' => 'nullable|string|max:80',
            'mensajes_extra.observaciones_default' => 'nullable|string|max:255',
            'mensajes_extra.leyenda_consulta' => 'nullable|string|max:120',
            'mensajes_extra.leyenda_representacion' => 'nullable|string|max:200',
            'estilos_secciones' => 'nullable|array',
            'estilos_secciones.*' => 'array',
            'estilos_secciones.*.color' => 'nullable|string|max:30',
            'estilos_secciones.*.tamano' => 'nullable|integer|min:5|max:24',
            'estilos_secciones.*.peso' => 'nullable|string|in:normal,bold',
            'estilos_secciones.*.alineacion' => 'nullable|string|in:left,center,right',
            'estilos_secciones.*.cursiva' => 'nullable|boolean',
            'estilos_secciones.*.subrayado' => 'nullable|boolean',
            'estilos_secciones.*.fuente' => 'nullable|string|max:50',
            'comprobante' => 'nullable|string|max:80',
            'formato' => 'nullable|string|max:30',
        ]);
        $comprobante = $validated['comprobante'] ?? null;
        $formato = $validated['formato'] ?? null;

        // Si se especifica comprobante/formato, guardamos en la tabla de detalles
        if ($comprobante || $formato) {
            $detalle = PlantillaImpresionDetalle::updateOrCreate(
                [
                    'empresa_id' => $empresaId,
                    'comprobante' => $comprobante,
                    'formato' => $formato,
                ],
                [
                    'estilos' => $validated['estilos'] ?? null,
                    'mensajes_extra' => $validated['mensajes_extra'] ?? null,
                    'estilos_secciones' => $validated['estilos_secciones'] ?? null,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Plantilla de impresión (detalle) actualizada correctamente',
                'data' => $detalle,
            ]);
        }

        // Guardar plantilla global (comportamiento previo)
        $plantilla = PlantillaImpresion::updateOrCreate(
            ['empresa_id' => $empresaId],
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'Plantilla de impresión actualizada correctamente',
            'data' => $plantilla,
        ]);
    }

    /**
     * Resolver el ID de la empresa activa desde header o usuario autenticado.
     */
    protected function resolverEmpresaId(Request $request): ?int
    {
        $headerEmpresa = $request->header('X-Empresa-Activa');
        if ($headerEmpresa) {
            return (int) $headerEmpresa;
        }

        $user = $request->user() ?? Auth::user();
        if ($user && isset($user->empresa_id)) {
            return (int) $user->empresa_id;
        }

        return null;
    }
}
