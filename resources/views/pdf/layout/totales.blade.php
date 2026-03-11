{{--
    Seccion de SON + observaciones + totales
    Recibe:
        $son         = 'texto del monto en letras'
        $moneda      = 'SOLES' | 'DOLARES'
        $observaciones = 'texto'
        $totales     = [
            ['label' => 'SUBTOTAL', 'valor' => '100.00'],
            ['label' => 'IGV (18%)', 'valor' => '18.00'],
            ['label' => 'TOTAL', 'valor' => '118.00'],
        ]
--}}

{{-- SON --}}
<table style="width: 100%; border-left: 2px solid #fadc06; border-right: 2px solid #fadc06; border-bottom: 2px solid #fadc06;">
    <tr>
        <td style="padding: 6px; font-size: 7pt; font-weight: bold;">
            SON: {{ $son }} {{ $moneda ?? 'SOLES' }}.
        </td>
    </tr>
</table>

{{-- Observaciones + Totales --}}
<table style="width: 100%; min-height: 120px;">
    <tr>
        {{-- Observaciones --}}
        <td style="width: 65%; padding: 15px; vertical-align: middle;">
            <div style="border: 2px solid #fadc06; border-radius: 8px; padding: 8px;">
                <div style="font-size: 8pt; font-weight: bold; margin-bottom: 4px;">
                    OBSERVACIONES
                </div>
                <div style="font-size: 6pt; line-height: 1.5;">
                    {{ $observaciones ?? '- NINGUNA' }}
                </div>
            </div>
        </td>

        {{-- Totales --}}
        <td style="width: 35%; vertical-align: top;">
            <table style="width: 100%; border-collapse: collapse;">
                @foreach($totales as $total)
                    <tr style="border-bottom: 1px solid #fadc06;">
                        <td style="
                            width: 60%;
                            border-left: 1px solid #fadc06;
                            border-right: 1px solid #fadc06;
                            padding: 6px;
                            font-size: 8pt;
                            font-weight: bold;
                            text-align: right;
                        ">
                            {{ $total['label'] }}
                        </td>
                        <td style="
                            width: 40%;
                            border-right: 1px solid #fadc06;
                            padding: 6px;
                            font-size: 8pt;
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
