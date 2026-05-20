{{--
    Grid de informacion (ej: datos del cliente, proveedor, etc.)
    Recibe: $filas = [
        ['label1' => 'valor1', 'label2' => 'valor2'],
    ]
--}}
@php($est = $est ?? [])
@php($bloques = $bloques ?? [])
@php($colorBorde = $est['color_borde'] ?? '#fadc06')
@php($padPx = $est['pad_px'] ?? 4)
@php($borderThin = $est['border_thin_px'] ?? 1)
@php($cssLabel = $bloques['info_label']['css'] ?? '')
@php($cssValor = $bloques['info_valor']['css'] ?? '')

<table style="width: 100%; border: {{ $borderThin }}px solid {{ $colorBorde }}; margin-bottom: 10px; border-collapse: collapse;">
    @foreach($filas as $fila)
        <tr style="min-height: 18px;">
            @foreach($fila as $label => $valor)
                <td style="{{ $cssLabel }} width: {{ $loop->first ? '12%' : '15%' }}; padding: {{ $padPx }}px;">
                    {{ $label }}
                </td>
                <td style="{{ $cssValor }} width: {{ $loop->first ? '38%' : '35%' }}; padding: {{ $padPx }}px;">
                    : {{ $valor }}
                </td>
            @endforeach
        </tr>
    @endforeach
</table>
