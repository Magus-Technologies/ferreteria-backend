{{--
    Seccion de SON + observaciones + totales
    Recibe:
        $son         = 'texto del monto en letras'
        $moneda      = 'SOLES' | 'DOLARES'
        $observaciones = 'texto'
        $totales     = [ ['label' => 'SUBTOTAL', 'valor' => '100.00'], ... ]
--}}
@php($est = $est ?? [])
@php($bloques = $bloques ?? [])
@php($msg = $msg ?? [])
@php($colorBorde = $est['color_borde'] ?? '#fadc06')
@php($borderPx = $est['border_px'] ?? 2)
@php($borderThin = $est['border_thin_px'] ?? 1)
@php($padPx = $est['pad_px'] ?? 4)
@php($labelObs = $msg['label_observaciones'] ?? 'OBSERVACIONES')
@php($cssSon = $bloques['son']['css'] ?? '')
@php($cssObsLabel = $bloques['obs_label']['css'] ?? '')
@php($cssObsValor = $bloques['obs_valor']['css'] ?? '')
@php($cssTotalLabel = $bloques['total_label']['css'] ?? '')
@php($cssTotalValor = $bloques['total_valor']['css'] ?? '')

{{-- SON --}}
<table style="width: 100%; border-left: {{ $borderPx }}px solid {{ $colorBorde }}; border-right: {{ $borderPx }}px solid {{ $colorBorde }}; border-bottom: {{ $borderPx }}px solid {{ $colorBorde }};">
    <tr>
        <td style="{{ $cssSon }} padding: {{ $padPx + 2 }}px;">
            SON: {{ $son }} {{ $moneda ?? 'SOLES' }}.
        </td>
    </tr>
</table>

{{-- Observaciones + Totales --}}
<table style="width: 100%; min-height: 120px;">
    <tr>
        {{-- Observaciones --}}
        <td style="width: 65%; padding: 15px; vertical-align: middle;">
            <div style="border: {{ $borderPx }}px solid {{ $colorBorde }}; border-radius: 8px; padding: 8px;">
                <div style="{{ $cssObsLabel }} margin-bottom: 4px;">
                    {{ $labelObs }}
                </div>
                <div style="{{ $cssObsValor }} line-height: 1.5;">
                    {{ $observaciones ?? '- NINGUNA' }}
                </div>
            </div>
        </td>

        {{-- Totales --}}
        <td style="width: 35%; vertical-align: top;">
            <table style="width: 100%; border-collapse: collapse;">
                @foreach($totales as $total)
                    <tr style="border-bottom: {{ $borderThin }}px solid {{ $colorBorde }};">
                        <td style="{{ $cssTotalLabel }}
                            width: 60%;
                            border-left: {{ $borderThin }}px solid {{ $colorBorde }};
                            border-right: {{ $borderThin }}px solid {{ $colorBorde }};
                            padding: {{ $padPx + 2 }}px;
                        ">
                            {{ $total['label'] }}
                        </td>
                        <td style="{{ $cssTotalValor }}
                            width: 40%;
                            border-right: {{ $borderThin }}px solid {{ $colorBorde }};
                            padding: {{ $padPx + 2 }}px;
                        ">
                            {{ $total['valor'] }}
                        </td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>
