<?php

return [
    'url' => env('SUNAT_API_URL', 'http://api-sunat-laravel.test'),

    'ruc' => env('SUNAT_API_RUC', '20000000001'),
    'razon_social' => env('SUNAT_API_RAZON_SOCIAL', 'MI EMPRESA SAC'),
    'nombre_comercial' => env('SUNAT_API_NOMBRE_COMERCIAL', 'MI EMPRESA'),

    'ubigeo' => env('SUNAT_API_UBIGEO', '150101'),
    'departamento' => env('SUNAT_API_DEPARTAMENTO', 'LIMA'),
    'provincia' => env('SUNAT_API_PROVINCIA', 'LIMA'),
    'distrito' => env('SUNAT_API_DISTRITO', 'LIMA'),
    'direccion' => env('SUNAT_API_DIRECCION', 'AV. EJEMPLO 123'),

    'sol_user' => env('SUNAT_API_SOL_USER', 'MODDATOS'),
    'sol_pass' => env('SUNAT_API_SOL_PASS', 'moddatos'),

    'client_id' => env('SUNAT_API_CLIENT_ID', ''),
    'secret_client' => env('SUNAT_API_SECRET_CLIENT', ''),

    'endpoint' => env('SUNAT_API_ENDPOINT', 'beta'),

    'auto_send_factura_enabled' => env('SUNAT_API_AUTO_SEND_FACTURA_ENABLED', false),
    'auto_send_factura_after_days' => env('SUNAT_API_AUTO_SEND_FACTURA_AFTER_DAYS', 3),

    'auto_send_boleta_enabled' => env('SUNAT_API_AUTO_SEND_BOLETA_ENABLED', false),
    'auto_send_boleta_after_days' => env('SUNAT_API_AUTO_SEND_BOLETA_AFTER_DAYS', 0),
];
