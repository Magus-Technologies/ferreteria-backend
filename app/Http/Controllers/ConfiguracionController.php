<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use Illuminate\Http\Request;

class ConfiguracionController extends Controller
{
    /**
     * Obtener el estado del envío automático a SUNAT
     */
    public function getAutoSendStatus()
    {
        $empresa = Empresa::first();

        return response()->json([
            'factura' => [
                'enabled' => (bool) ($empresa->sunat_auto_send_factura_enabled ?? false),
                'after_days' => (int) ($empresa->sunat_auto_send_factura_after_days ?? 3),
            ],
            'boleta' => [
                'enabled' => (bool) ($empresa->sunat_auto_send_boleta_enabled ?? false),
                'after_days' => (int) ($empresa->sunat_auto_send_boleta_after_days ?? 0),
            ],
            'nota_credito' => [
                'enabled' => (bool) ($empresa->sunat_auto_send_nota_credito_enabled ?? false),
                'after_days' => (int) ($empresa->sunat_auto_send_nota_credito_after_days ?? 0),
            ],
            'guia' => [
                'enabled' => (bool) ($empresa->sunat_auto_send_guia_enabled ?? false),
                'after_days' => (int) ($empresa->sunat_auto_send_guia_after_days ?? 0),
            ],
        ]);
    }

    /**
     * Actualizar el estado del envío automático a SUNAT
     */
    public function updateAutoSendStatus(Request $request)
    {
        $request->validate([
            'type' => 'required|in:factura,boleta,nota_credito,guia,all',
            'config' => 'required_unless:type,all|array',
            'config.enabled' => 'required_with:config|boolean',
            'config.after_days' => 'required_with:config|integer|min:0|max:15',
            'configs' => 'required_if:type,all|array',
        ]);

        $empresa = Empresa::firstOrFail();
        $type = $request->input('type');

        if ($type === 'all') {
            $configs = $request->input('configs');
            foreach (['factura', 'boleta', 'nota_credito', 'guia'] as $t) {
                if (isset($configs[$t])) {
                    $this->saveToEmpresa($empresa, $t, $configs[$t]);
                }
            }
        } else {
            $this->saveToEmpresa($empresa, $type, $request->input('config'));
        }

        return response()->json([
            'message' => 'Configuración actualizada correctamente',
        ]);
    }

    /**
     * Persiste en la tabla `empresa`, no en cache: el deploy corre
     * `php artisan cache:clear` (ver .github/workflows/deploy.yml), así que
     * un `Cache::forever()` acá se borraba en cada deploy — el toggle
     * volvía a su default silenciosamente sin que nadie lo desactivara.
     */
    private function saveToEmpresa(Empresa $empresa, string $type, array $config): void
    {
        $empresa->update([
            "sunat_auto_send_{$type}_enabled" => $config['enabled'],
            "sunat_auto_send_{$type}_after_days" => $config['after_days'],
        ]);
    }
}
