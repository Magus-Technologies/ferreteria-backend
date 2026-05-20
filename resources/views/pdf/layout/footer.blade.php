{{--
    Footer del documento
    Recibe:
        $codigoQr         = 'url o data uri del QR' (opcional)
        $mensajeFinal     = 'texto plano' (opcional)
        $mensajeFinalHtml = 'HTML enriquecido de plantilla' (opcional)
        $consultaUrl      = 'url publica de consulta' (opcional)
--}}
@php($mensajeFinalHtml = $mensajeFinalHtml ?? null)
@php($est = $est ?? [])
@php($bloques = $bloques ?? [])
@php($msg = $msg ?? [])
@php($leyendaRep = $msg['leyenda_representacion'] ?? 'Representacion impresa del comprobante electronico')
@php($leyendaConsulta = $msg['leyenda_consulta'] ?? 'Consulte su documento en:')
@php($cssDespedida = $bloques['despedida_footer']['css'] ?? '')
@php($cssConsultaLey = $bloques['consulta_leyenda']['css'] ?? '')
@php($cssConsultaUrl = $bloques['consulta_url']['css'] ?? '')

<div style="text-align: center; margin-top: 15px;">
    @if(!empty($codigoQr))
        <table style="margin: 0 auto;">
            <tr>
                <td style="vertical-align: middle; padding-right: 15px;">
                    <img src="{{ $codigoQr }}" style="width: 80px; height: 80px;" />
                </td>
                <td style="vertical-align: middle;">
                    <div style="{{ $cssConsultaLey }} margin-bottom: 2px;">
                        {{ $leyendaRep }}
                    </div>
                    @if(!empty($mensajeFinalHtml))
                        <div style="{{ $cssDespedida }}">{!! $mensajeFinalHtml !!}</div>
                    @else
                        <div style="{{ $cssDespedida }}">
                            {{ $mensajeFinal ?? 'GRACIAS POR SU PREFERENCIA!' }}
                        </div>
                    @endif
                </td>
            </tr>
        </table>
    @else
        @if(!empty($mensajeFinalHtml))
            <div style="{{ $cssDespedida }}">{!! $mensajeFinalHtml !!}</div>
        @else
            <div style="{{ $cssDespedida }}">
                {{ $mensajeFinal ?? 'GRACIAS POR SU PREFERENCIA!' }}
            </div>
        @endif
    @endif

    @if(!empty($consultaUrl))
        <div style="margin-top: 8px; padding-top: 5px; border-top: 1px solid #ddd;">
            <div style="{{ $cssConsultaLey }}">{{ $leyendaConsulta }}</div>
            <div style="{{ $cssConsultaUrl }}">
                {{ $consultaUrl }}
            </div>
        </div>
    @endif
</div>
