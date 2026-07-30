<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $titulo ?? 'Ticket' }}</title>
    @php
        $est = $est ?? [];
        $bloques = $bloques ?? [];
        $colorBorde = $est['color_borde'] ?? '#000';
        $borderThin = $est['border_thin_px'] ?? 1;
        $padPx = $est['pad_px'] ?? 4;
        $fuente = $est['fuente'] ?? 'Helvetica';
        $fontPt = $est['font_pt'] ?? 7;

        $cssEmpresaRazon = $bloques['empresa_razon']['css'] ?? '';
        $cssEmpresaDir = $bloques['empresa_direccion']['css'] ?? '';
        $cssCajaRuc = $bloques['caja_ruc']['css'] ?? '';
        $cssCajaTipo = $bloques['caja_tipo']['css'] ?? '';
        $cssCajaNumero = $bloques['caja_numero']['css'] ?? '';
        $cssInfoLabel = $bloques['info_label']['css'] ?? '';
        $cssInfoValor = $bloques['info_valor']['css'] ?? '';
        $cssTablaHeader = $bloques['tabla_header']['css'] ?? '';
        $cssTablaFila = $bloques['tabla_fila']['css'] ?? '';
        $cssObsLabel = $bloques['obs_label']['css'] ?? '';
        $cssObsValor = $bloques['obs_valor']['css'] ?? '';
    @endphp
    <style>
        @page {
            size: 80mm auto;
            margin: 3mm;
        }
        {!! $font_face_css ?? '' !!}
        body {
            font-family: "{{ $fuente }}", Arial, sans-serif;
            font-size: {{ $fontPt }}pt;
            color: #000;
            line-height: 1.3;
            width: 74mm;
            margin: 0;
            padding: 0;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .separator { border-top: {{ $borderThin }}px dashed {{ $colorBorde }}; margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; }
    </style>
</head>
<body>
    {{-- Header: Logo + Empresa --}}
    <div class="text-center" style="margin-bottom: 4px;">
        @if($logoPath && !($msg['ocultar_logo'] ?? false))
            <img src="{{ $logoPath }}" style="max-height: 120px; max-width: 180px;" alt="Logo">
        @endif
        <div style="margin-top: 2px;">
            <div style="{{ $cssEmpresaRazon }}">{{ $empresa->razon_social }}</div>
            <div style="{{ $cssCajaRuc }}">R.U.C. {{ $empresa->ruc }}</div>
            <div style="{{ $cssEmpresaDir }}">{{ $empresa->direccion }}</div>
            <div style="{{ $cssEmpresaDir }}"><strong>Cel:</strong> {{ $empresa->telefono }}</div>
            <div style="{{ $cssEmpresaDir }}"><strong>Email:</strong> {{ $empresa->email }}</div>
        </div>
    </div>

    <div class="separator"></div>

    {{-- Tipo documento y numero --}}
    <div class="text-center" style="padding: 4px 0;">
        <div style="{{ $cssCajaTipo }}">{{ $tipoOperacion }}</div>
        <div style="{{ $cssCajaNumero }}">{{ $numeroDocumento }}</div>
    </div>

    <div class="separator"></div>

    {{-- Info de la devolucion --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td style="{{ $cssInfoLabel }}">F. Devoluci&oacute;n:</td>
                <td style="{{ $cssInfoValor }}">{{ $fechaDevolucion }}</td>
            </tr>
            <tr>
                <td style="{{ $cssInfoLabel }}">N&deg; Pr&eacute;stamo:</td>
                <td style="{{ $cssInfoValor }}">{{ $numeroPrestamo }}</td>
            </tr>
            <tr>
                <td style="{{ $cssInfoLabel }}">Registrado por:</td>
                <td style="{{ $cssInfoValor }}">{{ $usuarioRegistro }}</td>
            </tr>
        </table>
    </div>

    <div class="separator"></div>

    {{-- Info de la entidad --}}
    <div style="padding: 2px 0 6px;">
        <table>
            <tr>
                <td style="{{ $cssInfoLabel }}">{{ $esCliente ? 'Cliente:' : 'Proveedor:' }}</td>
                <td style="{{ $cssInfoValor }}">{{ $entidadNombre }}</td>
            </tr>
            <tr>
                <td style="{{ $cssInfoLabel }}">{{ strlen($entidadDocumento) === 11 ? 'RUC:' : 'DNI:' }}</td>
                <td style="{{ $cssInfoValor }}">{{ $entidadDocumento }}</td>
            </tr>
        </table>
    </div>

    <div class="separator"></div>

    {{-- Tabla de productos devueltos --}}
    <div style="padding-top: 4px;">
        <table>
            <thead>
                <tr style="border-bottom: {{ $borderThin }}px solid {{ $colorBorde }};">
                    <th style="{{ $cssTablaHeader }} text-align: left;">Descripci&oacute;n</th>
                    <th style="{{ $cssTablaHeader }} text-align: left;">Unid.</th>
                    <th style="{{ $cssTablaHeader }} text-align: right;">Cant.</th>
                </tr>
            </thead>
            <tbody>
                @foreach($productos as $i => $p)
                <tr style="border-bottom: {{ $borderThin }}px solid {{ $colorBorde }};{{ $i % 2 !== 0 ? ' background-color: #f9f9f9;' : '' }}">
                    <td style="{{ $cssTablaFila }} padding: 3px 0;">{{ $p['nombre'] }}</td>
                    <td style="{{ $cssTablaFila }} padding: 3px 0;">{{ $p['unidad'] }}</td>
                    <td style="{{ $cssTablaFila }} padding: 3px 0; text-align: right;">{{ \App\Helpers\Formato::cantidad($p['cantidad']) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Observaciones --}}
    <div style="margin-top: 4px;">
        <div style="{{ $cssObsLabel }}">Observaciones:</div>
        <div style="{{ $cssObsValor }}">{{ $observaciones }}</div>
    </div>
</body>
</html>
