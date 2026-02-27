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
        \Log::info('ConfiguracionController::getAutoSendStatus - Inicio');
        // Leer directamente del .env para obtener el valor más actualizado
        $envPath = base_path('.env');
        \Log::info('Path .env: ' . $envPath);

        if (!File::exists($envPath)) {
            \Log::info('.env no existe');
            return response()->json([
                'factura' => ['enabled' => false, 'after_days' => 3],
                'boleta' => ['enabled' => false, 'after_days' => 0],
            ]);
        }

        $envContent = File::get($envPath);
        \Log::info('.env cargado, longitud: ' . strlen($envContent));

        return response()->json([
            'factura' => $this->parseConfig($envContent, 'FACTURA', 3),
            'boleta' => $this->parseConfig($envContent, 'BOLETA', 0),
        ]);
    }

    private function parseConfig($envContent, $type, $defaultDays)
    {
        $enabled = false;
        $afterDays = $defaultDays;

        if (preg_match('/^GREENTER_AUTO_SEND_' . $type . '_ENABLED=(true|false)/m', $envContent, $matches)) {
            $enabled = $matches[1] === 'true';
        }

        if (preg_match('/^GREENTER_AUTO_SEND_' . $type . '_AFTER_DAYS=(\d+)/m', $envContent, $matches)) {
            $afterDays = (int)$matches[1];
        }

        return ['enabled' => $enabled, 'after_days' => $afterDays];
    }

    /**
     * Actualizar el estado del envío automático a SUNAT
     */
    public function updateAutoSendStatus(Request $request)
    {
        $request->validate([
            'type' => 'required|in:factura,boleta,all',
            'config' => 'required_unless:type,all|array',
            'config.enabled' => 'required_with:config|boolean',
            'config.after_days' => 'required_with:config|integer|min:0|max:15',
            'configs' => 'required_if:type,all|array',
        ]);

        $envPath = base_path('.env');
        if (!File::exists($envPath)) {
            return response()->json(['message' => 'Archivo .env no encontrado'], 404);
        }

        $envContent = File::get($envPath);
        $type = $request->input('type');

        if ($type === 'all') {
            $configs = $request->input('configs');
            foreach (['factura', 'boleta'] as $t) {
                if (isset($configs[$t])) {
                    $envContent = $this->updateEnvConfig($envContent, strtoupper($t), $configs[$t]);
                }
            }
        } else {
            $envContent = $this->updateEnvConfig($envContent, strtoupper($type), $request->input('config'));
        }

        // Guardar el archivo
        File::put($envPath, $envContent);

        return response()->json([
            'message' => 'Configuración actualizada correctamente',
        ]);
    }

    private function updateEnvConfig($envContent, $upperType, $config)
    {
        $enabled = $config['enabled'] ? 'true' : 'false';
        $afterDays = (int)($config['after_days'] ?? 0);

        // Enabled
        $keyEnabled = "GREENTER_AUTO_SEND_{$upperType}_ENABLED";
        if (preg_match("/^{$keyEnabled}=.*/m", $envContent)) {
            $envContent = preg_replace("/^{$keyEnabled}=.*/m", "{$keyEnabled}={$enabled}", $envContent);
        } else {
            $envContent .= "\n{$keyEnabled}={$enabled}";
        }

        // After Days
        $keyAfterDays = "GREENTER_AUTO_SEND_{$upperType}_AFTER_DAYS";
        if (preg_match("/^{$keyAfterDays}=.*/m", $envContent)) {
            $envContent = preg_replace("/^{$keyAfterDays}=.*/m", "{$keyAfterDays}={$afterDays}", $envContent);
        } else {
            $envContent .= "\n{$keyAfterDays}={$afterDays}";
        }

        return $envContent;
    }
}
