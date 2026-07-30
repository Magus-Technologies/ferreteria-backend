@extends('pdf.layout.document')

@section('content')
    @php
        $colorTema = $est['color_tema'] ?? '#fadc06';
        $colorBorde = $est['color_borde'] ?? '#fadc06';
        $borderThin = $est['border_thin_px'] ?? 1;
        $borderPx = $est['border_px'] ?? 2;
        $padPx = $est['pad_px'] ?? 4;

        $cssObsLabel = $bloques['obs_label']['css'] ?? '';
        $cssObsValor = $bloques['obs_valor']['css'] ?? '';
        $cssTotalLabel = $bloques['total_label']['css'] ?? '';
        $cssTotalValor = $bloques['total_valor']['css'] ?? '';

        $esCliente = $prestamo->tipo_entidad === 'CLIENTE';
        $entidad = $esCliente ? $prestamo->cliente : $prestamo->proveedor;
        $entidadNombre = $entidad?->razon_social
            ?: trim(($entidad?->nombres ?? '') . ' ' . ($entidad?->apellidos ?? ''))
            ?: 'ENTIDAD GENERAL';
        $entidadDocumento = $prestamo->ruc_dni ?: ($entidad?->numero_documento ?? '');
        $docLabel = strlen($entidadDocumento) === 11 ? 'RUC' : 'DNI';
    @endphp

    {{-- Header (mantiene la caja amarilla del tema) --}}
    @include('pdf.layout.header', [
        'empresa' => $empresa,
        'logoPath' => $logoPath,
        'tipoDocumentoTitulo' => $tipoDocumentoTitulo,
        'numeroDocumento' => $numeroDocumento,
    ])

    {{-- Info general + entidad --}}
    @include('pdf.layout.info-grid', ['filas' => $filas])

    {{-- Texto intro propio del préstamo --}}
    <div style="font-size: 8pt; margin-bottom: 6px; margin-top: 4px;">
        Por medio del presente documento se deja constancia del siguiente {{ strtolower($tipoOperacion) }} de bienes:
    </div>

    {{-- Tabla de productos --}}
    @php
        $almacenNombre = $prestamo->almacen->name ?? 'A1';
        $colsSel = $columnas ?? ['ubicacion', 'codigo', 'cantidad', 'unidad', 'producto', 'costo', 'importe'];

        $headerColumnas = [['label' => 'ITEM', 'width' => '5%', 'align' => 'center']];
        if (in_array('ubicacion', $colsSel)) $headerColumnas[] = ['label' => 'UBI.', 'width' => '5%', 'align' => 'center'];
        if (in_array('codigo', $colsSel)) $headerColumnas[] = ['label' => 'CODIGO', 'width' => '10%', 'align' => 'center'];
        if (in_array('cantidad', $colsSel)) $headerColumnas[] = ['label' => 'CANT.', 'width' => '7%', 'align' => 'center'];
        if (in_array('unidad', $colsSel)) $headerColumnas[] = ['label' => 'UNIDAD', 'width' => '8%', 'align' => 'center'];
        if (in_array('producto', $colsSel)) $headerColumnas[] = ['label' => 'DESCRIPCION', 'width' => 'auto', 'align' => 'left'];
        if (in_array('costo', $colsSel)) $headerColumnas[] = ['label' => 'COSTO UNI.', 'width' => '12%', 'align' => 'right'];
        if (in_array('importe', $colsSel)) $headerColumnas[] = ['label' => 'IMPORTE', 'width' => '12%', 'align' => 'right'];

        $filasProductos = collect($productos)->map(function ($p, $i) use ($almacenNombre, $colsSel) {
            $fila = [$i + 1];
            if (in_array('ubicacion', $colsSel)) $fila[] = $almacenNombre;
            if (in_array('codigo', $colsSel)) $fila[] = $p['codigo'];
            if (in_array('cantidad', $colsSel)) $fila[] = \App\Helpers\Formato::cantidad($p['cantidad']);
            if (in_array('unidad', $colsSel)) $fila[] = $p['unidad'];
            if (in_array('producto', $colsSel)) $fila[] = $p['nombre'];
            if (in_array('costo', $colsSel)) $fila[] = number_format($p['costo'], 2);
            if (in_array('importe', $colsSel)) $fila[] = number_format($p['subtotal'], 2);
            return $fila;
        })->toArray();
    @endphp
    @include('pdf.layout.table', [
        'columnas' => $headerColumnas,
        'filas' => $filasProductos,
        'minFilas' => 8,
    ])

    {{-- Observaciones + Totales del préstamo (sin SON ni IGV) --}}
    <table style="width: 100%; margin-top: 8px; border-collapse: collapse;">
        <tr>
            <td style="width: 60%; padding-right: 10px; vertical-align: top;">
                <div style="border: {{ $borderPx }}px solid {{ $colorBorde }}; padding: 8px; border-radius: 6px; min-height: 80px;">
                    <div style="{{ $cssObsLabel }} margin-bottom: 4px;">OBSERVACIONES:</div>
                    <div style="{{ $cssObsValor }} line-height: 1.5;">
                        {{ $observaciones }}
                    </div>
                </div>
            </td>
            <td style="width: 40%; vertical-align: top;">
                <table style="width: 100%; border-collapse: collapse;">
                    @foreach($totales as $total)
                        <tr>
                            <td style="{{ $cssTotalLabel }}
                                width: 60%;
                                border: {{ $borderThin }}px solid {{ $colorBorde }};
                                padding: {{ $padPx + 2 }}px;
                            ">
                                {{ $total['label'] }}
                            </td>
                            <td style="{{ $cssTotalValor }}
                                width: 40%;
                                border: {{ $borderThin }}px solid {{ $colorBorde }};
                                padding: {{ $padPx + 2 }}px;
                                text-align: right;
                            ">
                                {{ $total['valor'] }}
                            </td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    {{-- Firmas: un préstamo se firma, no se "agradece la compra" --}}
    <table style="width: 100%; margin-top: 40px; border-collapse: collapse;">
        <tr>
            <td style="width: 50%; text-align: center; padding: 0 20px;">
                <div style="border-top: 1.5px solid {{ $colorBorde }}; padding-top: 4px; font-size: 8pt; font-weight: bold; text-transform: uppercase;">
                    {{ $esCliente ? 'Recibido por el Cliente' : 'Entregado por el Proveedor' }}
                </div>
                <div style="font-size: 7pt; margin-top: 2px;">
                    {{ $entidadNombre }}
                </div>
                @if($entidadDocumento)
                    <div style="font-size: 7pt;">{{ $docLabel }}: {{ $entidadDocumento }}</div>
                @endif
            </td>
            <td style="width: 50%; text-align: center; padding: 0 20px;">
                <div style="border-top: 1.5px solid {{ $colorBorde }}; padding-top: 4px; font-size: 8pt; font-weight: bold; text-transform: uppercase;">
                    {{ $esCliente ? 'Entregado por la Empresa' : 'Recibido por la Empresa' }}
                </div>
                <div style="font-size: 7pt; margin-top: 2px;">
                    {{ $empresa->razon_social }}
                </div>
                <div style="font-size: 7pt;">R.U.C. {{ $empresa->ruc }}</div>
            </td>
        </tr>
    </table>
@endsection
