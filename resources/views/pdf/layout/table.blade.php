{{--
    Tabla de productos generica
    Recibe:
        $columnas = [
            ['label' => 'ITEM', 'width' => '5%', 'align' => 'center'],
            ...
        ]
        $filas = [
            ['col1', 'col2', ...],
        ]
        $minFilas = 10  (opcional, para rellenar espacio vacio)
--}}
<table style="width: 100%; border: 2px solid #fadc06; border-collapse: collapse;">
    {{-- Header --}}
    <tr style="background-color: #fadc06;">
        @foreach($columnas as $col)
            <td style="
                padding: 4px;
                font-size: 7pt;
                font-weight: bold;
                text-align: {{ $col['align'] ?? 'center' }};
                width: {{ $col['width'] }};
                border-right: 1px solid #fadc06;
            ">
                {{ $col['label'] }}
            </td>
        @endforeach
    </tr>

    {{-- Filas de datos --}}
    @foreach($filas as $fila)
        <tr style="border-bottom: 1px solid #fadc06;">
            @foreach($fila as $i => $celda)
                <td style="
                    padding: 3px;
                    font-size: 7pt;
                    text-align: {{ $columnas[$i]['align'] ?? 'left' }};
                    border-right: {{ $loop->last ? 'none' : '1px solid #fadc06' }};
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
                style="height: {{ ($minFilas - count($filas)) * 20 }}px; border-bottom: 1px solid #fadc06;">
            </td>
        </tr>
    @endif
</table>
