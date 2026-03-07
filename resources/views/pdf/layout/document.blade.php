<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $titulo ?? 'Documento' }}</title>
    <style>
        @page {
            size: A4;
            margin: 10mm 12mm 15mm 12mm;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 8pt;
            color: #000;
            line-height: 1.3;
        }

        table {
            border-collapse: collapse;
        }

        p, h1, h2, h3, h4, h5, h6 {
            margin: 0;
            padding: 0;
        }

        /* --- Colores del tema --- */
        .border-theme { border-color: #fadc06; }
        .bg-theme { background-color: #fadc06; }
        .text-bold { font-weight: bold; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }

        /* --- Utilidades --- */
        .w-full { width: 100%; }
        .mb-10 { margin-bottom: 10px; }
        .mb-15 { margin-bottom: 15px; }
        .mt-10 { margin-top: 10px; }
        .mt-15 { margin-top: 15px; }
        .p-4 { padding: 4px; }
        .p-6 { padding: 6px; }
        .p-8 { padding: 8px; }
        .p-12 { padding: 12px; }

        @yield('styles')
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
