 {--
    Tabla de productos generica
    Recibe:
        $columnas = [
            ['label' => 'ITEM', 'width' => '5%', 'align' => 'center'],
        ]
        $filas = [['col1', 'col2', ...]]
        $minFilas = 10  (opcional)
--}}
@php($est = $est ?? [])
@php($bloques = $bloques ?? [])
@php($colorTema = $est['color_tema'] ?? '#fadc06')
@php($colorBorde = $est['color_borde'] ?? '#fadc06')
@php($borderPx = $est['border_px'] ?? 2)
@php($borderThin = $est['border_thin_px'] ?? 1)
@php($padPx = $est['pad_px'] ?? 4)
@php($cssHeader = $bloques['tabla_header']['css'] ?? '')
@php($cssFila = $bloques['tabla_fila']['css'] ?? '')

<table style="width: 100%; border: {{ $borderPx }}px solid {{ $colorBorde }}; border-collapse: collapse;">
    {{-- Header --}}
    <tr style="background-color: {{ $colorTema }};">
        @foreach($columnas as $col)
            <td style="{{ $cssHeader }}
                padding: {{ $padPx }}px;
                width: {{ $col['width'] }};
                text-align: {{ $col['align'] ?? 'center' }};
                border-right: {{ $borderThin }}px solid {{ $colorBorde }};
            ">
                {{ $col['label'] }}
            </td>
        @endforeach
    </tr>

    {{-- Filas de datos --}}
    @foreach($filas as $fila)
        <tr style="border-bottom: {{ $borderThin }}px solid {{ $colorBorde }};">
            @foreach($fila as $i => $celda)
                <td style="{{ $cssFila }}
                    padding: {{ max(2, $padPx - 1) }}px;
                    text-align: {{ $columnas[$i]['align'] ?? 'left' }};
                    border-right: {{ $loop->last ? 'none' : $borderThin.'px solid '.$colorBorde }};
                ">
                    {{ $celda }}
                </td>
            @endforeach
        </tr>
    @endforeach

    {{-- Espacio vacio si hay pocas filas --}}
    @if(isset($minFilas) && count($filas) < $minFilas)
        <tr>
            <td colspan="{{ count($columnas) }}"
                style="height: {{ ($minFilas - count($filas)) * 20 }}px; border-bottom: {{ $borderThin }}px solid {{ $colorBorde }};">
            </td>
        </tr>
    @endif
</table>
