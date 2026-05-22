{{--
    Grid de informacion (ej: datos del cliente, proveedor, etc.)
    Recibe:
        $filas = [
            ['label1' => 'valor1', 'label2' => 'valor2'],
            // Fila especial con título de sección (ocupa toda la fila):
            ['__titulo' => 'Proveedor'],
        ]
        $sinMargen = true (opcional) para encadenar varios info-grids sin separación
--}}
@php($est = $est ?? [])
@php($bloques = $bloques ?? [])
@php($colorBorde = $est['color_borde'] ?? '#fadc06')
@php($padPx = $est['pad_px'] ?? 4)
@php($borderThin = $est['border_thin_px'] ?? 1)
@php($cssLabel = $bloques['info_label']['css'] ?? '')
@php($cssValor = $bloques['info_valor']['css'] ?? '')
@php($marginBottom = isset($sinMargen) && $sinMargen ? '0' : '10px')

<table style="width: 100%; border: {{ $borderThin }}px solid {{ $colorBorde }}; margin-bottom: {{ $marginBottom }}; border-collapse: collapse;">
    @foreach($filas as $fila)
        @if(isset($fila['__titulo']))
            {{-- Fila de título de sección: ocupa las 4 columnas --}}
            <tr>
                <td colspan="4" style="{{ $cssLabel }} padding: {{ $padPx }}px {{ $padPx + 4 }}px; background: #f5f5f5; border-top: {{ $borderThin }}px solid {{ $colorBorde }}; border-bottom: {{ $borderThin }}px solid {{ $colorBorde }};">
                    {{ $fila['__titulo'] }}
                </td>
            </tr>
        @else
            <tr style="min-height: 18px;">
                @php($celdas = 0)
                @foreach($fila as $label => $valor)
                    <td style="{{ $cssLabel }} width: {{ $loop->first ? '12%' : '15%' }}; padding: {{ $padPx }}px;">
                        {{ $label }}
                    </td>
                    <td style="{{ $cssValor }} width: {{ $loop->first ? '38%' : '35%' }}; padding: {{ $padPx }}px;">
                        : {{ $valor }}
                    </td>
                    @php($celdas++)
                @endforeach
                {{-- Rellenar celdas faltantes para que la fila siempre tenga 4 columnas --}}
                @if($celdas < 2)
                    @for($i = $celdas; $i < 2; $i++)
                        <td style="width: 15%; padding: {{ $padPx }}px;"></td>
                        <td style="width: 35%; padding: {{ $padPx }}px;"></td>
                    @endfor
                @endif
            </tr>
        @endif
    @endforeach
</table>
