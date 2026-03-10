<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Vale {{ $vale->codigo }}</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 3mm;
        }
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 7pt;
            color: #1F2937;
            line-height: 1.3;
            width: 74mm;
            margin: 0;
            padding: 0;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }
        .text-upper { text-transform: uppercase; }

        .outer-border {
            border: 2.5px solid #D97706;
            border-radius: 8px;
            padding: 4px;
        }
        .inner-border {
            border: 1px dashed #D97706;
            border-radius: 6px;
            padding: 8px;
        }

        /* Decorativo */
        .deco {
            font-size: 7pt;
            text-align: center;
            letter-spacing: 2px;
            color: #D97706;
            margin: 2px 0;
        }

        /* Empresa */
        .empresa-nombre {
            font-size: 9pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            color: #1F2937;
        }
        .empresa-datos {
            font-size: 5.5pt;
            text-align: center;
            color: #6B7280;
        }

        /* Banner tipo */
        .banner {
            background-color: #D97706;
            width: 100%;
            padding: 5px 8px;
            border-radius: 4px;
            text-align: center;
            margin: 5px 0;
        }
        .banner-text {
            font-size: 12pt;
            font-weight: bold;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 3px;
        }
        .banner-sub {
            font-size: 7pt;
            color: #fff;
            margin-top: 1px;
        }

        /* Codigo */
        .codigo-box {
            background-color: #FEF3C7;
            padding: 4px 14px;
            border-radius: 4px;
            border: 1.5px solid #D97706;
            text-align: center;
            display: inline-block;
            margin: 3px 0;
        }
        .codigo-label {
            font-size: 5.5pt;
            color: #92400E;
            text-transform: uppercase;
            font-weight: bold;
        }
        .codigo-texto {
            font-size: 13pt;
            font-weight: bold;
            letter-spacing: 4px;
            color: #92400E;
        }

        /* Nombre */
        .nombre {
            font-size: 8pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            color: #1F2937;
            margin: 3px 0 1px;
        }
        .desc {
            font-size: 6pt;
            text-align: center;
            color: #6B7280;
            font-style: italic;
            margin-bottom: 3px;
        }

        /* Hero beneficio */
        .hero-box {
            width: 100%;
            border: 2.5px solid #D97706;
            border-radius: 6px;
            padding: 8px 6px;
            text-align: center;
            background-color: #FEF3C7;
            margin: 4px 0;
        }
        .hero-texto {
            font-size: 20pt;
            font-weight: bold;
            color: #92400E;
        }
        .hero-sub {
            font-size: 7pt;
            color: #92400E;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: bold;
        }

        /* Separadores */
        .sep {
            border-top: 1px dashed #D97706;
            margin: 4px 0;
        }
        .sep-fuerte {
            border-top: 2px solid #D97706;
            margin: 4px auto;
            width: 70%;
        }

        /* Info box */
        .info-box {
            width: 100%;
            background-color: #FEF3C7;
            border-radius: 4px;
            padding: 6px;
            margin: 3px 0;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 1.5px 0;
            font-size: 6pt;
        }
        .info-label {
            font-weight: bold;
            text-transform: uppercase;
            color: #92400E;
        }
        .info-val {
            text-align: right;
            color: #1F2937;
            font-weight: bold;
        }

        /* Listas */
        .lista-title {
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #92400E;
            margin-bottom: 2px;
        }
        .lista-item {
            font-size: 6pt;
            padding-left: 6px;
            color: #1F2937;
            margin-bottom: 1px;
        }

        /* Badges */
        .badges-row {
            text-align: center;
            margin: 3px 0;
        }
        .badge {
            background-color: #D97706;
            color: #fff;
            font-size: 5.5pt;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 3px;
            display: inline-block;
            margin: 1px;
        }

        /* Condiciones */
        .cond-box {
            width: 100%;
            border: 1px solid #D1D5DB;
            border-radius: 4px;
            padding: 5px;
            text-align: center;
            margin: 3px 0;
        }
        .cond-title {
            font-size: 6pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #6B7280;
            margin-bottom: 1px;
        }
        .cond-item {
            font-size: 5pt;
            color: #9CA3AF;
        }

        /* Footer */
        .footer {
            font-size: 5pt;
            text-align: center;
            color: #9CA3AF;
            margin-top: 2px;
        }
    </style>
</head>
<body>
    <div class="outer-border">
        <div class="inner-border">

            {{-- Decorativo --}}
            <div class="deco">- - - - - - - - - - -</div>

            {{-- Empresa --}}
            <div style="margin-bottom: 4px;">
                <div class="empresa-nombre">
                    {{ $empresa->razon_social ?? $empresa->nombre_comercial ?? 'FERRETERIA' }}
                </div>
                @if($empresa->ruc)
                    <div class="empresa-datos">RUC: {{ $empresa->ruc }}</div>
                @endif
                @if($empresa->direccion)
                    <div class="empresa-datos">{{ $empresa->direccion }}</div>
                @endif
                @if($empresa->telefono)
                    <div class="empresa-datos">Tel: {{ $empresa->telefono }}</div>
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
                    <div class="codigo-label">Codigo</div>
                    <div class="codigo-texto">{{ $vale->codigo }}</div>
                </div>
            </div>

            {{-- Nombre --}}
            <div class="nombre">{{ $vale->nombre }}</div>
            @if($vale->descripcion)
                <div class="desc">{{ $vale->descripcion }}</div>
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
                        <td class="info-label">Compra minima</td>
                        <td class="info-val">{{ $cantidadMinima }} und.</td>
                    </tr>
                    <tr>
                        <td class="info-label">Modalidad</td>
                        <td class="info-val">{{ $modalidadLabel }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Desde</td>
                        <td class="info-val">{{ $fechaInicio }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Hasta</td>
                        <td class="info-val">{{ $fechaFin }}</td>
                    </tr>
                    @if($vale->tipo_promocion === 'DESCUENTO_PROXIMA_COMPRA' && $vale->fecha_validez_vale)
                        <tr>
                            <td class="info-label">Vale valido hasta</td>
                            <td class="info-val">{{ \App\Services\Pdf\PdfService::formatFecha($vale->fecha_validez_vale) }}</td>
                        </tr>
                    @endif
                    @if($vale->usa_limite_por_cliente && $vale->limite_usos_cliente)
                        <tr>
                            <td class="info-label">Max. por cliente</td>
                            <td class="info-val">{{ $vale->limite_usos_cliente }} uso(s)</td>
                        </tr>
                    @endif
                    @if($vale->usa_limite_stock)
                        <tr>
                            <td class="info-label">Disponibles</td>
                            <td class="info-val">{{ $vale->stock_disponible ?? 0 }}</td>
                        </tr>
                    @endif
                </table>
            </div>

            {{-- Productos --}}
            @if($productos && count($productos) > 0)
                <div style="margin: 3px 0;">
                    <div class="lista-title">Productos aplicables</div>
                    @foreach($productos as $p)
                        <div class="lista-item">- {{ $p->cod_producto }}: {{ $p->name }}</div>
                    @endforeach
                </div>
            @endif

            {{-- Categorias --}}
            @if($categorias && count($categorias) > 0)
                <div style="margin: 3px 0;">
                    <div class="lista-title">Categorias aplicables</div>
                    @foreach($categorias as $c)
                        <div class="lista-item">- {{ $c->name }}</div>
                    @endforeach
                </div>
            @endif

            {{-- Precios badges --}}
            @if(count($precios) > 0)
                <div style="margin: 3px 0;">
                    <div class="lista-title text-center">Aplica a precios</div>
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
                <div class="cond-title">Condiciones de uso</div>
                <div class="cond-item">Valido unicamente en tienda</div>
                <div class="cond-item">No acumulable con otras promociones</div>
                <div class="cond-item">Sujeto a disponibilidad de stock</div>
            </div>

            {{-- Decorativo --}}
            <div class="deco">- - - - - - - - - - -</div>

            {{-- Footer --}}
            <div class="footer">
                Generado el {{ now()->format('d/m/Y H:i') }}
            </div>

        </div>
    </div>
</body>
</html>
