{{--
    Footer del documento
    Recibe:
        $codigoQr    = 'url o data uri del QR' (opcional)
        $mensajeFinal = 'texto' (opcional)
        $consultaUrl  = 'url pública de consulta' (opcional)
--}}
<div style="text-align: center; margin-top: 15px;">
    @if(!empty($codigoQr))
        <table style="margin: 0 auto;">
            <tr>
                <td style="vertical-align: middle; padding-right: 15px;">
                    <img src="{{ $codigoQr }}" style="width: 80px; height: 80px;" />
                </td>
                <td style="vertical-align: middle; text-align: center;">
                    <div style="font-size: 7pt; color: #666; margin-bottom: 2px;">
                        Representacion impresa del comprobante electronico
                    </div>
                    <div style="font-size: 8pt; font-weight: bold;">
                        {{ $mensajeFinal ?? 'GRACIAS POR SU PREFERENCIA!' }}
                    </div>
                </td>
            </tr>
        </table>
    @else
        <div style="font-size: 8pt; font-weight: bold;">
            {{ $mensajeFinal ?? 'GRACIAS POR SU PREFERENCIA!' }}
        </div>
    @endif

    @if(!empty($consultaUrl))
        <div style="margin-top: 8px; padding-top: 5px; border-top: 1px solid #ddd;">
            <div style="font-size: 7pt; color: #666;">Consulte su documento en:</div>
            <div style="font-size: 7pt; font-weight: bold; color: #333;">
                {{ $consultaUrl }}
            </div>
        </div>
    @endif
</div>
