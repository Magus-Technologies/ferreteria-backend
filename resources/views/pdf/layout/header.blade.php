{{-- Header del documento: logo + empresa + caja de documento --}}
<table style="width: 100%; margin-bottom: 10px;">
    <tr>
        {{-- Logo --}}
        @if(!empty($logoPath))
            <td style="width: 230px; vertical-align: middle; padding-right: 3px; ">
                <img src="{{ $logoPath }}" style="width: 230px;" />
            </td>
        @endif

        {{-- Info empresa --}}
        <td style="vertical-align: middle; text-align: center; font-size: 6.5pt; line-height: 1.4; max-width: 200px;">
            <div style="font-size: 9pt; font-weight: bold; margin-bottom: 2px;">
                {{ $empresa->razon_social }}
            </div>
            <div>{{ $empresa->direccion }}</div>
            @if($empresa->celular)
                <div style="margin-top: 2px;">Cel: {{ $empresa->celular }}</div>
            @endif
            <div>Email: {{ $empresa->email }}</div>
        </td>

        {{-- Caja de documento --}}
        <td style="width: 240px; vertical-align: middle; text-align: right;">
            <div style="width: 240px; margin-left: auto; border: 2px solid #fadc06; border-radius: 12px; overflow: hidden;">
                <div style="text-align: center; font-size: 10pt; font-weight: bold; padding: 8px 8px 6px;">
                    R.U.C. {{ $empresa->ruc }}
                </div>
                <div style="text-align: center; font-size: 11pt; font-weight: bold; background-color: #fadc06; padding: 7px 8px;">
                    {{ $tipoDocumentoTitulo }}
                </div>
                <div style="text-align: center; font-size: 11pt; font-weight: bold; padding: 6px 8px 8px;">
                    {{ $numeroDocumento }}
                </div>
            </div>
        </td>
    </tr>
</table>
