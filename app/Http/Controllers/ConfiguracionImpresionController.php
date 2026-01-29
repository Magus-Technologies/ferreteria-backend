<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionImpresion;
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
}
