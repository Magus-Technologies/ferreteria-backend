<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ConfiguracionController extends Controller
{
    /**
     * Obtener el estado del envío automático a SUNAT
     */
    public function getAutoSendStatus()
    {
        return response()->json([
            'factura' => [
                'enabled' => Cache::get('greenter_auto_send_factura_enabled', config('services.greenter.auto_send_factura_enabled', false)),
                'after_days' => (int) Cache::get('greenter_auto_send_factura_after_days', config('services.greenter.auto_send_factura_after_days', 3)),
            ],
            'boleta' => [
                'enabled' => Cache::get('greenter_auto_send_boleta_enabled', config('services.greenter.auto_send_boleta_enabled', false)),
                'after_days' => (int) Cache::get('greenter_auto_send_boleta_after_days', config('services.greenter.auto_send_boleta_after_days', 0)),
            ],
        ]);
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

        $type = $request->input('type');

        if ($type === 'all') {
            $configs = $request->input('configs');
            foreach (['factura', 'boleta'] as $t) {
                if (isset($configs[$t])) {
                    $this->saveToCache($t, $configs[$t]);
                }
            }
        } else {
            $this->saveToCache($type, $request->input('config'));
        }

        return response()->json([
            'message' => 'Configuración actualizada correctamente en cache persistente',
        ]);
    }

    private function saveToCache($type, $config)
    {
        Cache::forever("greenter_auto_send_{$type}_enabled", $config['enabled']);
        Cache::forever("greenter_auto_send_{$type}_after_days", $config['after_days']);
    }
}
