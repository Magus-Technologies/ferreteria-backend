<?php

return [

    'show_warnings' => false,

    'public_path' => null,

    'convert_entities' => true,

    'options' => [
        // Directorio donde Dompdf almacena las métricas y caché de fuentes personalizadas.
        // Debe existir y ser escribible por el proceso del servidor web.
        'font_dir' => storage_path('fonts'),
        'font_cache' => storage_path('fonts'),

        'temp_dir' => sys_get_temp_dir(),

        // chroot: limita el acceso del PDF a archivos locales. base_path() incluye
        // storage/app/public donde residen las fuentes subidas, tanto en Windows
        // como en Linux.
        'chroot' => realpath(base_path()),

        'allowed_protocols' => [
            'data://' => ['rules' => []],
            'file://' => ['rules' => []],
            'http://' => ['rules' => []],
            'https://' => ['rules' => []],
        ],

        'artifactPathValidation' => null,
        'log_output_file' => null,

        // Habilitado: reduce el tamaño del PDF al incluir solo los glifos usados.
        'enable_font_subsetting' => true,

        'pdf_backend' => 'CPDF',
        'default_media_type' => 'screen',
        'default_paper_size' => 'a4',
        'default_paper_orientation' => 'portrait',
        'default_font' => 'serif',
        'dpi' => 96,

        'enable_php' => false,
        'enable_javascript' => true,

        // Habilitado: permite que @font-face cargue archivos vía file:// (y URLs
        // remotas si fuera necesario en el futuro). Sin esto, Dompdf no carga
        // las fuentes personalizadas.
        'enable_remote' => true,
        'allowed_remote_hosts' => null,

        'font_height_ratio' => 1.1,
        'enable_html5_parser' => true,
    ],

];
