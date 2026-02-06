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
    | Para pruebas en Beta usar:
    | - RUC: 20000000001
    | - Usuario: MODDATOS
    | - Clave: moddatos
    */
    'sol_user' => env('GREENTER_SOL_USER', 'MODDATOS'),
    'sol_pass' => env('GREENTER_SOL_PASS', 'moddatos'),
    
    /*
    |--------------------------------------------------------------------------
    | Certificado Digital
    |--------------------------------------------------------------------------
    | Para pruebas usar un certificado self-signed (incluido en Greenter)
    | Para producción usar certificado digital válido comprado
    */
    'certificate_path' => function_exists('storage_path') 
        ? storage_path('certificates/SFSCert.pem')
        : env('GREENTER_CERTIFICATE_PATH', storage_path('certificates/certificate.pem')),
    'certificate_password' => env('GREENTER_CERTIFICATE_PASSWORD', ''),
    
    /*
    |--------------------------------------------------------------------------
    | Ambiente (beta o producción)
    |--------------------------------------------------------------------------
    | false = Entorno Beta (Pruebas)
    | true = Entorno Producción
    */
    'production' => env('GREENTER_PRODUCTION', false),
    
    /*
    |--------------------------------------------------------------------------
    | Endpoints SUNAT
    |--------------------------------------------------------------------------
    | URLs de los servicios web de SUNAT
    | Beta: Para pruebas sin valor legal
    | Producción: Para comprobantes con valor legal
    */
    'endpoints' => [
        'beta' => [
            // Facturas, Boletas y Notas (Crédito/Débito)
            'invoice' => env('GREENTER_ENDPOINT_INVOICE', 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService'),
            // Guías de Remisión
            'despatch' => env('GREENTER_ENDPOINT_DESPATCH', 'https://e-beta.sunat.gob.pe/ol-ti-itemision-guia-gem-beta/billService'),
            // Consultas de CDR (Constancia de Recepción)
            'consult' => env('GREENTER_ENDPOINT_CONSULT', 'https://e-beta.sunat.gob.pe/ol-it-wsconscdr-beta/billService'),
        ],
        'production' => [
            'invoice' => 'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService',
            'despatch' => 'https://e-guiaremision.sunat.gob.pe/ol-ti-itemision-guia-gem/billService',
            'consult' => 'https://e-factura.sunat.gob.pe/ol-it-wsconscdr/billService',
        ],
    ],
];
