<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Datos de la Empresa
    |--------------------------------------------------------------------------
    */
    'ruc' => env('GREENTER_RUC', '20000000001'),
    'razon_social' => env('GREENTER_RAZON_SOCIAL', 'MI EMPRESA SAC'),
    'nombre_comercial' => env('GREENTER_NOMBRE_COMERCIAL', 'MI EMPRESA'),
    
    /*
    |--------------------------------------------------------------------------
    | Dirección de la Empresa
    |--------------------------------------------------------------------------
    */
    'ubigeo' => env('GREENTER_UBIGEO', '150101'),
    'departamento' => env('GREENTER_DEPARTAMENTO', 'LIMA'),
    'provincia' => env('GREENTER_PROVINCIA', 'LIMA'),
    'distrito' => env('GREENTER_DISTRITO', 'LIMA'),
    'direccion' => env('GREENTER_DIRECCION', 'AV. EJEMPLO 123'),
    
    /*
    |--------------------------------------------------------------------------
    | Credenciales SOL (SUNAT)
    |--------------------------------------------------------------------------
    */
    'sol_user' => env('GREENTER_SOL_USER', 'MODDATOS'),
    'sol_pass' => env('GREENTER_SOL_PASS', 'MODDATOS'),
    
    /*
    |--------------------------------------------------------------------------
    | Certificado Digital
    |--------------------------------------------------------------------------
    */
    'certificate_path' => env('GREENTER_CERTIFICATE_PATH', storage_path('certificates/certificate.pem')),
    
    /*
    |--------------------------------------------------------------------------
    | Ambiente (beta o producción)
    |--------------------------------------------------------------------------
    */
    'production' => env('GREENTER_PRODUCTION', false),
];
