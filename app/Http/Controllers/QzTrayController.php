<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QzTrayController extends Controller
{
    /**
     * Obtener certificado público para QZ Tray
     */
    public function getCertificate()
    {
        try {
            $certPath = storage_path('certificates/qz-certificate.pem');
            
            if (!file_exists($certPath)) {
                Log::error('QZ Tray: Certificado no encontrado', ['path' => $certPath]);
                return response()->json([
                    'error' => 'Certificado no encontrado. Ejecuta el script de generación.'
                ], 404);
            }
            
            $certificate = file_get_contents($certPath);
            
            return response($certificate)
                ->header('Content-Type', 'text/plain')
                ->header('Cache-Control', 'public, max-age=86400'); // Cache 24 horas
        } catch (\Exception $e) {
            Log::error('QZ Tray: Error al obtener certificado', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Error al obtener el certificado'
            ], 500);
        }
    }
    
    /**
     * Firmar petición de QZ Tray con llave privada
     */
    public function signRequest(Request $request)
    {
        try {
            $request->validate([
                'data' => 'required|string'
            ]);
            
            $dataToSign = $request->input('data');
            $privateKeyPath = storage_path('certificates/qz-private-key.pem');
            
            if (!file_exists($privateKeyPath)) {
                Log::error('QZ Tray: Llave privada no encontrada', ['path' => $privateKeyPath]);
                return response()->json([
                    'error' => 'Llave privada no encontrada. Ejecuta el script de generación.'
                ], 500);
            }
            
            // Leer llave privada
            $privateKeyContent = file_get_contents($privateKeyPath);

            // Cargar llave privada con OpenSSL nativo
            $privateKey = openssl_pkey_get_private($privateKeyContent);
            if (!$privateKey) {
                throw new \RuntimeException('No se pudo cargar la llave privada: ' . openssl_error_string());
            }

            // Firmar con SHA512 + PKCS1
            $signature = '';
            if (!openssl_sign($dataToSign, $signature, $privateKey, OPENSSL_ALGO_SHA512)) {
                throw new \RuntimeException('Error al firmar: ' . openssl_error_string());
            }

            // Convertir a Base64
            $signatureBase64 = base64_encode($signature);
            
            return response($signatureBase64)
                ->header('Content-Type', 'text/plain');
                
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Datos inválidos',
                'details' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('QZ Tray: Error al firmar petición', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Error al firmar la petición'
            ], 500);
        }
    }
    
    /**
     * Verificar estado del sistema de certificados
     */
    public function status()
    {
        $certPath = storage_path('certificates/qz-certificate.pem');
        $privateKeyPath = storage_path('certificates/qz-private-key.pem');
        $publicKeyPath = storage_path('certificates/qz-public-key.pem');
        
        $status = [
            'certificate_exists' => file_exists($certPath),
            'private_key_exists' => file_exists($privateKeyPath),
            'public_key_exists' => file_exists($publicKeyPath),
            'storage_path' => storage_path('certificates'),
            'certificate_readable' => file_exists($certPath) && is_readable($certPath),
            'private_key_readable' => file_exists($privateKeyPath) && is_readable($privateKeyPath),
        ];
        
        $status['all_ready'] = $status['certificate_exists'] 
            && $status['private_key_exists'] 
            && $status['public_key_exists']
            && $status['certificate_readable']
            && $status['private_key_readable'];
        
        if ($status['certificate_exists']) {
            $certContent = file_get_contents($certPath);
            $status['certificate_preview'] = substr($certContent, 0, 100) . '...';
        }
        
        return response()->json($status);
    }
}
