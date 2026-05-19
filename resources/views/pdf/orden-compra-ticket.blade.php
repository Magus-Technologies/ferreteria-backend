<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Ticket {{ $numeroDocumento }}</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 3mm;
        }
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 7pt;
            color: #000;
            line-height: 1.3;
            width: 74mm;
            margin: 0;
            padding: 0;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }
        .separator { border-top: 1px dashed #000; margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; }
        .label { font-weight: bold; text-transform: uppercase; font-size: 5pt; }
        .value { font-size: 5pt; }
    </style>
</head>
<body>
    {{-- Header: Logo + Empresa --}}
    <div class="text-center" style="margin-bottom: 4px;">
        @if($logoPath)
            <img src="{{ $logoPath }}" style="max-height: 120px; max-width: 180px;" alt="Logo">
        @endif
        <div style="margin-top: 2px;">
            <div class="text-bold" style="font-size: 9pt;">{{ $empresa->razon_social }}</div>
            <div class="text-bold">R.U.C. {{ $empresa->ruc }}</div>
            <div>{{ $empresa->direccion }}</div>
            <div><span class="text-bold">Cel:</span> {{ $empresa->telefono }}</div>
            <div><span class="text-bold">Email:</span> {{ $empresa->email }}</div>
        </div>
    </div>

    <div class="separator"></div>

    {{-- Tipo documento y numero --}}
    <div class="text-center text-bold" style="font-size: 9pt; padding: 4px 0;">
        {{ $tipoDocumentoTitulo }}<br>
        {{ $numeroDocumento }}
    </div>

    <div class="separator"></div>

    {{-- Info general --}}
    <div style="padding: 2px 0 6px;">
        <table>
            @foreach($filas as $fila)
                @foreach($fila as $k => $v)
                <tr>
                    <td class="label" style="width: 45%;">{{ $k }}:</td>
                    <td class="value">{{ $v }}</td>
                </tr>
                @endforeach
            @endforeach
        </table>
    </div>

    <div class="separator"></div>

    {{-- Proveedor --}}
    <div style="padding: 2px 0 6px;">
        <table>
            @foreach($filasProveedor as $fila)
                @foreach($fila as $k => $v)
                <tr>
                    <td class="label" style="width: 45%;">{{ $k }}:</td>
                    <td class="value">{{ $v }}</td>
                </tr>
                @endforeach
            @endforeach
        </table>
    </div>

    <div class="separator"></div>

    {{-- Tabla de productos --}}
    @php
        $colsSel = $columnas ?? ['codigo', 'producto', 'marca', 'unidad', 'cantidad', 'precio', 'flete'];
        $colsTicket = [
            'codigo' => 'C&oacute;d.',
            'producto' => 'Descripci&oacute;n',
            'marca' => 'Marca',
            'unidad' => 'Unid.',
            'cantidad' => 'Cant.',
            'precio' => 'P.Unit',
            'flete' => 'Flete',
        ];
        $colsActivas = array_filter(array_keys($colsTicket), fn($k) => in_array($k, $colsSel));
        if (empty($colsActivas)) $colsActivas = ['producto'];
    @endphp
    <div style="padding-top: 4px;">
        <table>
            <thead>
                <tr style="border-bottom: 1px solid #000;">
                    @foreach($colsActivas as $c)
                    <th class="text-bold" style="font-size: 6pt; text-align: left;">{!! $colsTicket[$c] !!}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($productos as $i => $p)
                <tr style="border-bottom: 1px solid #000;{{ $i % 2 !== 0 ? ' background-color: #f9f9f9;' : '' }}">
                    @foreach($colsActivas as $c)
                    <td style="font-size: 6pt; padding: 3px 0;">
                        @if($c === 'codigo'){{ $p['codigo'] }}
                        @elseif($c === 'producto'){{ $p['nombre'] }}
                        @elseif($c === 'marca'){{ $p['marca'] }}
                        @elseif($c === 'unidad'){{ $p['unidad'] }}
                        @elseif($c === 'cantidad'){{ number_format($p['cantidad'], 2) }}
                        @elseif($c === 'precio'){{ $monedaSimbolo }} {{ number_format($p['precio'], 2) }}
                        @elseif($c === 'flete'){{ $monedaSimbolo }} {{ number_format($p['flete'], 2) }}
                        @endif
                    </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Totales --}}
    @php
        $verSubtotal = $columnas === null || in_array('subtotal', $columnas);
        $verFlete = ($columnas === null || in_array('flete', $columnas)) && ($calculos['flete_total'] ?? 0) > 0;
        $verPercepcion = ($calculos['percepcion'] ?? 0) > 0;
        $verTotal = $columnas === null || in_array('total', $columnas);
    @endphp
    <div style="margin-top: 4px;">
        <table>
            @if($verSubtotal)
            <tr style="border-bottom: 1px solid #000;">
                <td class="text-bold" style="font-size: 7pt;">SUBTOTAL</td>
                <td class="text-right" style="font-size: 7pt;">{{ $monedaSimbolo }} {{ number_format($calculos['subtotal'] ?? 0, 2) }}</td>
            </tr>
            @endif
            @if($verFlete)
            <tr style="border-bottom: 1px solid #000;">
                <td class="text-bold" style="font-size: 7pt;">FLETE</td>
                <td class="text-right" style="font-size: 7pt;">{{ $monedaSimbolo }} {{ number_format($calculos['flete_total'], 2) }}</td>
            </tr>
            @endif
            @if($verPercepcion)
            <tr style="border-bottom: 1px solid #000;">
                <td class="text-bold" style="font-size: 7pt;">PERCEPCI&Oacute;N</td>
                <td class="text-right" style="font-size: 7pt;">{{ $monedaSimbolo }} {{ number_format($calculos['percepcion'], 2) }}</td>
            </tr>
            @endif
            @if($verTotal)
            <tr>
                <td class="text-bold" style="font-size: 7pt;">TOTAL</td>
                <td class="text-right text-bold" style="font-size: 7pt;">{{ $monedaSimbolo }} {{ number_format($calculos['total'] ?? 0, 2) }}</td>
            </tr>
            @endif
        </table>
    </div>

    {{-- SON --}}
    @if(!empty($son))
    <div style="font-size: 6pt; margin-top: 4px;">
        <span class="text-bold">SON:</span> {{ $son }}
    </div>
    @endif

    {{-- Observaciones --}}
    <div style="font-size: 7pt; margin-top: 4px;">
        <span class="text-bold">Observaciones:</span><br>
        {{ $observaciones }}
    </div>
</body>
</html>
