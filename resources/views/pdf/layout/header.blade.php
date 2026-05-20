{{-- Header del documento: logo + empresa + caja de documento --}}
@php($logosExtras = $logosExtras ?? [])
@php($est = $est ?? [])
@php($bloques = $bloques ?? [])
@php($colorTema = $est['color_tema'] ?? '#fadc06')
@php($borderPx = $est['border_px'] ?? 2)
@php($bgTipoColor = $colorTema)

@php($cssEmpresaRazon = $bloques['empresa_razon']['css'] ?? '')
@php($cssEmpresaDir = $bloques['empresa_direccion']['css'] ?? '')
@php($cssCajaRuc = $bloques['caja_ruc']['css'] ?? '')
@php($cssCajaTipo = $bloques['caja_tipo']['css'] ?? '')
@php($cssCajaNumero = $bloques['caja_numero']['css'] ?? '')

<table style="width: 100%; margin-bottom: 10px;">
    <tr>
        {{-- Logo principal + logos extras (Nota de Venta) --}}
        @if(!empty($logoPath) || !empty($logosExtras))
            <td style="width: 230px; vertical-align: middle; padding-right: 3px;">
                @if(!empty($logoPath))
                    <img src="{{ $logoPath }}" style="width: 230px;" />
                @endif
                @if(!empty($logosExtras))
                    <table style="margin-top: 4px; border-collapse: collapse;">
                        <tr>
                            @foreach($logosExtras as $extra)
                                @if(!empty($extra['logo_path']))
                                    <td style="padding-right: 4px; vertical-align: middle;">
                                        <img src="{{ $extra['logo_path'] }}" style="height: 30px;" />
                                    </td>
                                @endif
                            @endforeach
                        </tr>
                    </table>
                @endif
            </td>
        @endif

        {{-- Info empresa --}}
        <td style="vertical-align: middle; line-height: 1.4; max-width: 200px;">
            <div style="{{ $cssEmpresaRazon }} margin-bottom: 2px;">
                {{ $empresa->razon_social }}
            </div>
            <div style="{{ $cssEmpresaDir }}">
                <div>{{ $empresa->direccion }}</div>
                @if($empresa->celular)
                    <div style="margin-top: 2px;">Cel: {{ $empresa->celular }}</div>
                @endif
                <div>Email: {{ $empresa->email }}</div>
            </div>
        </td>

        {{-- Caja de documento --}}
        <td style="width: 240px; vertical-align: middle; text-align: right;">
            <div style="width: 240px; margin-left: auto; border: {{ $borderPx }}px solid {{ $colorTema }}; border-radius: 12px; overflow: hidden;">
                <div style="{{ $cssCajaRuc }} padding: 8px 8px 6px;">
                    R.U.C. {{ $empresa->ruc }}
                </div>
                <div style="{{ $cssCajaTipo }} background-color: {{ $bgTipoColor }}; padding: 7px 8px;">
                    {{ $tipoDocumentoTitulo }}
                </div>
                <div style="{{ $cssCajaNumero }} padding: 6px 8px 8px;">
                    {{ $numeroDocumento }}
                </div>
            </div>
        </td>
    </tr>
</table>
