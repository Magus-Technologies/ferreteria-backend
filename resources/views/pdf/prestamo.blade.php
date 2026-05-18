@extends('pdf.layout.document')

@section('content')
    {{-- Header --}}
    @include('pdf.layout.header', [
        'empresa' => $empresa,
        'logoPath' => $logoPath,
        'tipoDocumentoTitulo' => $tipoDocumentoTitulo,
        'numeroDocumento' => $numeroDocumento,
    ])

    {{-- Info de la entidad --}}
    @include('pdf.layout.info-grid', ['filas' => $filas])

    {{-- Texto intro --}}
    <div style="font-size: 7pt; margin-bottom: 8px; margin-top: 5px;">
        De nuestra consideracion: Por medio de la presente se detalla el siguiente {{ strtolower($tipoOperacion) }}:
    </div>

    {{-- Tabla de productos --}}
    @php
        $almacenNombre = $prestamo->almacen->name ?? 'A1';

        // Columnas seleccionadas (si no se pasa nada, se muestran todas)
        $colsSel = $columnas ?? ['ubicacion', 'codigo', 'cantidad', 'unidad', 'producto', 'costo', 'importe'];

        // Encabezados dinámicos: ITEM siempre visible (numeración)
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
            if (in_array('cantidad', $colsSel)) $fila[] = number_format($p['cantidad'], 0);
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
        'minFilas' => 10,
    ])

    {{-- SON + Observaciones + Totales --}}
    @include('pdf.layout.totales', [
        'son' => 'TOTAL DEL ' . strtoupper($tipoOperacion),
        'moneda' => '',
        'observaciones' => $observaciones,
        'totales' => $totales,
    ])

    {{-- Footer --}}
    <div style="text-align: center; margin-top: 15px; font-size: 8pt;">
        Sin otro particular, esperando su pronta respuesta.
        <span style="font-weight: bold;">GRACIAS POR SU PREFERENCIA! DIOS LES BENDIGA!</span>
    </div>

    {{-- Tabla de cuentas bancarias --}}
    @include('pdf.layout.cuentas-bancarias')
@endsection
