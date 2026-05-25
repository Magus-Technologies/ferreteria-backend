<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Vale {{ $vale->codigo }}</title>
    @php
        $est = $est ?? [];
        $bloques = $bloques ?? [];
        // El vale tiene un esquema decorativo propio (naranja). Si el editor
        // define un color_tema, se aplica como acento; si no, se usa naranja.
        $colorTema = $est['color_tema'] ?? '#D97706';
        $fuente = $est['fuente'] ?? 'Helvetica';
        $fontPt = $est['font_pt'] ?? 7;

        $cssEmpresaRazon = $bloques['empresa_razon']['css'] ?? '';
        $cssEmpresaDir = $bloques['empresa_direccion']['css'] ?? '';
        $cssCajaTipo = $bloques['caja_tipo']['css'] ?? '';
        $cssCajaNumero = $bloques['caja_numero']['css'] ?? '';
        $cssInfoLabel = $bloques['info_label']['css'] ?? '';
        $cssInfoValor = $bloques['info_valor']['css'] ?? '';
        $cssDespedida = $bloques['despedida_footer']['css'] ?? '';
    @endphp
    <style>
        @page {
            size: 80mm auto;
            margin: 3mm;
        }
        {!! $font_face_css ?? '' !!}
        body {
            font-family: "{{ $fuente }}", Arial, sans-serif;
            font-size: {{ $fontPt }}pt;
            color: #1F2937;
            line-height: 1.3;
            width: 74mm;
            margin: 0;
            padding: 0;
        }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }

        .outer-border {
            border: 2.5px solid {{ $colorTema }};
            border-radius: 8px;
            padding: 4px;
        }
        .inner-border {
            border: 1px dashed {{ $colorTema }};
            border-radius: 6px;
            padding: 8px;
        }
        .deco {
            text-align: center;
            letter-spacing: 2px;
            color: {{ $colorTema }};
            margin: 2px 0;
        }
        .banner {
            background-color: {{ $colorTema }};
            width: 100%;
            padding: 5px 8px;
            border-radius: 4px;
            text-align: center;
            margin: 5px 0;
        }
        .banner-text {
            font-weight: bold;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 3px;
            font-size: {{ $fontPt + 5 }}pt;
        }
        .banner-sub {
            color: #fff;
            margin-top: 1px;
            font-size: {{ $fontPt }}pt;
        }
        .codigo-box {
            background-color: #FEF3C7;
            padding: 4px 14px;
            border-radius: 4px;
            border: 1.5px solid {{ $colorTema }};
            text-align: center;
            display: inline-block;
            margin: 3px 0;
        }
        .hero-box {
            width: 100%;
            border: 2.5px solid {{ $colorTema }};
            border-radius: 6px;
            padding: 8px 6px;
            text-align: center;
            background-color: #FEF3C7;
            margin: 4px 0;
        }
        .hero-texto {
            font-size: {{ $fontPt + 13 }}pt;
            font-weight: bold;
            color: #92400E;
        }
        .hero-sub {
            color: #92400E;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: bold;
            font-size: {{ $fontPt }}pt;
        }
        .sep { border-top: 1px dashed {{ $colorTema }}; margin: 4px 0; }
        .sep-fuerte {
            border-top: 2px solid {{ $colorTema }};
            margin: 4px auto;
            width: 70%;
        }
        .info-box {
            width: 100%;
            background-color: #FEF3C7;
            border-radius: 4px;
            padding: 6px;
            margin: 3px 0;
        }
        .info-table { width: 100%; border-collapse: collapse; }
        .info-table td { padding: 1.5px 0; }
        .badges-row { text-align: center; margin: 3px 0; }
        .badge {
            background-color: {{ $colorTema }};
            color: #fff;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 3px;
            display: inline-block;
            margin: 1px;
            font-size: {{ max(5, $fontPt - 1) }}pt;
        }
        .cond-box {
            width: 100%;
            border: 1px solid #D1D5DB;
            border-radius: 4px;
            padding: 5px;
            text-align: center;
            margin: 3px 0;
        }
    </style>
</head>
<body>
    <div class="outer-border">
        <div class="inner-border">

            <div class="deco">- - - - - - - - - - -</div>

            {{-- Empresa --}}
            <div style="margin-bottom: 4px;">
                <div style="{{ $cssEmpresaRazon }}">
                    {{ $empresa->razon_social ?? $empresa->nombre_comercial ?? 'FERRETERIA' }}
                </div>
                @if($empresa->ruc)
                    <div style="{{ $cssEmpresaDir }}">RUC: {{ $empresa->ruc }}</div>
                @endif
                @if($empresa->direccion)
                    <div style="{{ $cssEmpresaDir }}">{{ $empresa->direccion }}</div>
                @endif
                @if($empresa->telefono)
                    <div style="{{ $cssEmpresaDir }}">Tel: {{ $empresa->telefono }}</div>
                @endif
            </div>

            {{-- Banner tipo --}}
            <div class="banner">
                <div class="banner-text">{{ $tipoLabel }}</div>
                <div class="banner-sub">Vale de Compra</div>
            </div>

            {{-- Codigo --}}
            <div class="text-center">
                <div class="codigo-box">
                    <div style="{{ $cssInfoLabel }} color: #92400E;">Codigo</div>
                    <div style="{{ $cssCajaNumero }} font-size: {{ $fontPt + 6 }}pt; letter-spacing: 4px; color: #92400E;">{{ $vale->codigo }}</div>
                </div>
            </div>

            {{-- Nombre --}}
            <div class="text-center text-bold" style="margin: 3px 0 1px; text-transform: uppercase; font-size: {{ $fontPt + 1 }}pt;">{{ $vale->nombre }}</div>
            @if($vale->descripcion)
                <div class="text-center" style="font-style: italic; color: #6B7280; margin-bottom: 3px; font-size: {{ max(5, $fontPt - 1) }}pt;">{{ $vale->descripcion }}</div>
            @endif

            {{-- HERO beneficio --}}
            <div class="hero-box">
                <div class="hero-texto">{{ $beneficioPrincipal }}</div>
                <div class="hero-sub">{{ $beneficioDetalle }}</div>
            </div>

            <div class="sep-fuerte"></div>

            {{-- Info --}}
            <div class="info-box">
                <table class="info-table">
                    <tr>
                        <td style="{{ $cssInfoLabel }} color: #92400E;">Precio minimo</td>
                        <td style="{{ $cssInfoValor }} text-align: right; font-weight: bold;">S/ {{ number_format((float) $cantidadMinima, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="{{ $cssInfoLabel }} color: #92400E;">Modalidad</td>
                        <td style="{{ $cssInfoValor }} text-align: right; font-weight: bold;">{{ $modalidadLabel }}</td>
                    </tr>
                    <tr>
                        <td style="{{ $cssInfoLabel }} color: #92400E;">Desde</td>
                        <td style="{{ $cssInfoValor }} text-align: right; font-weight: bold;">{{ $fechaInicio }}</td>
                    </tr>
                    <tr>
                        <td style="{{ $cssInfoLabel }} color: #92400E;">Hasta</td>
                        <td style="{{ $cssInfoValor }} text-align: right; font-weight: bold;">{{ $fechaFin }}</td>
                    </tr>
                    @if($vale->tipo_promocion === 'DESCUENTO_PROXIMA_COMPRA' && $vale->fecha_validez_vale)
                        <tr>
                            <td style="{{ $cssInfoLabel }} color: #92400E;">Vale valido hasta</td>
                            <td style="{{ $cssInfoValor }} text-align: right; font-weight: bold;">{{ \App\Services\Pdf\PdfService::formatFecha($vale->fecha_validez_vale) }}</td>
                        </tr>
                    @endif
                    @if($vale->usa_limite_por_cliente && $vale->limite_usos_cliente)
                        <tr>
                            <td style="{{ $cssInfoLabel }} color: #92400E;">Max. por cliente</td>
                            <td style="{{ $cssInfoValor }} text-align: right; font-weight: bold;">{{ $vale->limite_usos_cliente }} uso(s)</td>
                        </tr>
                    @endif
                    @if($vale->usa_limite_stock)
                        <tr>
                            <td style="{{ $cssInfoLabel }} color: #92400E;">Disponibles</td>
                            <td style="{{ $cssInfoValor }} text-align: right; font-weight: bold;">{{ $vale->stock_disponible ?? 0 }}</td>
                        </tr>
                    @endif
                </table>
            </div>

            {{-- Productos --}}
            @if($productos && count($productos) > 0)
                <div style="margin: 3px 0;">
                    <div style="{{ $cssInfoLabel }} color: #92400E; margin-bottom: 2px;">Productos aplicables</div>
                    @foreach($productos as $p)
                        <div style="padding-left: 6px; margin-bottom: 1px; font-size: {{ max(5, $fontPt - 1) }}pt;">- {{ $p->cod_producto }}: {{ $p->name }}</div>
                    @endforeach
                </div>
            @endif

            {{-- Categorias --}}
            @if($categorias && count($categorias) > 0)
                <div style="margin: 3px 0;">
                    <div style="{{ $cssInfoLabel }} color: #92400E; margin-bottom: 2px;">Categorias aplicables</div>
                    @foreach($categorias as $c)
                        <div style="padding-left: 6px; margin-bottom: 1px; font-size: {{ max(5, $fontPt - 1) }}pt;">- {{ $c->name }}</div>
                    @endforeach
                </div>
            @endif

            {{-- Precios badges --}}
            @if(count($precios) > 0)
                <div style="margin: 3px 0;">
                    <div class="text-center" style="{{ $cssInfoLabel }} color: #92400E;">Aplica a precios</div>
                    <div class="badges-row">
                        @foreach($precios as $precio)
                            <span class="badge">{{ $precio }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="sep"></div>

            {{-- Condiciones --}}
            <div class="cond-box">
                <div style="{{ $cssInfoLabel }} color: #6B7280; margin-bottom: 1px;">Condiciones de uso</div>
                <div style="color: #9CA3AF; font-size: {{ max(5, $fontPt - 2) }}pt;">Valido unicamente en tienda</div>
                <div style="color: #9CA3AF; font-size: {{ max(5, $fontPt - 2) }}pt;">No acumulable con otras promociones</div>
                <div style="color: #9CA3AF; font-size: {{ max(5, $fontPt - 2) }}pt;">Sujeto a disponibilidad de stock</div>
            </div>

            <div class="deco">- - - - - - - - - - -</div>

            <div style="{{ $cssDespedida }} margin-top: 2px;">
                Generado el {{ now()->format('d/m/Y H:i') }}
            </div>

        </div>
    </div>
</body>
</html>
