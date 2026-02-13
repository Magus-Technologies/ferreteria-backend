<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class ConfiguracionController extends Controller
{
    /**
     * Obtener el estado del envío automático a SUNAT
     */
    public function getAutoSendStatus()
    {
        // Leer directamente del .env para obtener el valor más actualizado
        $envPath = base_path('.env');

        if (!File::exists($envPath)) {
            return response()->json([
                'enabled' => false
            ]);
        }

        $envContent = File::get($envPath);
        $enabled = false;

        // Buscar la línea GREENTER_AUTO_SEND_ENABLED en el .env
        if (preg_match('/^GREENTER_AUTO_SEND_ENABLED=(true|false)/m', $envContent, $matches)) {
            $enabled = $matches[1] === 'true';
        }

        return response()->json([
            'enabled' => $enabled
        ]);
    }

    /**
     * Actualizar el estado del envío automático a SUNAT
     */
    public function updateAutoSendStatus(Request $request)
    {
        $request->validate([
            'enabled' => 'required|boolean'
        ]);

        $enabled = $request->input('enabled');
        $envPath = base_path('.env');

        if (!File::exists($envPath)) {
            return response()->json([
                'message' => 'Archivo .env no encontrado'
            ], 404);
        }

        $envContent = File::get($envPath);

        // Buscar y reemplazar la línea GREENTER_AUTO_SEND_ENABLED
        $newValue = $enabled ? 'true' : 'false';

        if (preg_match('/^GREENTER_AUTO_SEND_ENABLED=.*/m', $envContent)) {
            // Si existe, reemplazarla
            $envContent = preg_replace(
                '/^GREENTER_AUTO_SEND_ENABLED=.*/m',
                "GREENTER_AUTO_SEND_ENABLED={$newValue}",
                $envContent
            );
        } else {
            // Si no existe, agregarla al final
            $envContent .= "\nGREENTER_AUTO_SEND_ENABLED={$newValue}\n";
        }

        // Guardar el archivo
        File::put($envPath, $envContent);

        // Leer el valor guardado directamente del .env para confirmar
        $envContentUpdated = File::get($envPath);
        $savedValue = false;
        if (preg_match('/^GREENTER_AUTO_SEND_ENABLED=(true|false)/m', $envContentUpdated, $matches)) {
            $savedValue = $matches[1] === 'true';
        }



        // Retornar respuesta inmediatamente sin limpiar cache
        // El cache no es necesario porque siempre leemos directamente del .env
        return response()->json([
            'message' => $enabled
                ? 'Envío automático activado correctamente'
                : 'Envío automático desactivado correctamente',
            'enabled' => $savedValue
        ]);
    }
}
