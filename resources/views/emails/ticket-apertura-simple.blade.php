<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'Ticket de Apertura de Caja' }}</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            max-width: 300px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .ticket {
            background-color: white;
            padding: 15px;
            border: 1px solid #ddd;
        }
        .header {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        .title {
            font-size: 16px;
            font-weight: bold;
            margin: 10px 0;
        }
        .section {
            margin: 10px 0;
            padding: 5px 0;
            border-bottom: 1px dashed #ccc;
        }
        .row {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
            font-size: 12px;
        }
        .label {
            font-weight: bold;
        }
        .table {
            width: 100%;
            margin: 10px 0;
            font-size: 11px;
        }
        .table th {
            text-align: left;
            border-bottom: 1px solid #000;
            padding: 5px 0;
        }
        .table td {
            padding: 3px 0;
        }
        .total {
            font-size: 14px;
            font-weight: bold;
            text-align: right;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid #000;
        }
        .footer {
            text-align: center;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 2px dashed #000;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="header">
            <div class="title">APERTURA DE CAJA</div>
            <div>{{ $apertura->cajaPrincipal->nombre ?? 'N/A' }}</div>
        </div>

        <div class="section">
            <div class="row">
                <span class="label">Fecha:</span>
                <span>{{ $apertura->fecha_apertura ? \Carbon\Carbon::parse($apertura->fecha_apertura)->format('d/m/Y h:i:s a') : 'N/A' }}</span>
            </div>
            <div class="row">
                <span class="label">Usuario:</span>
                <span>{{ $apertura->user->name ?? 'N/A' }}</span>
            </div>
            <div class="row">
                <span class="label">Caja:</span>
                <span>{{ $apertura->cajaPrincipal->nombre ?? 'N/A' }}</span>
            </div>
        </div>

        <div class="section">
            <div class="label" style="margin-bottom: 5px;">MONTO DE APERTURA</div>
            <div class="total">
                S/ {{ number_format($apertura->monto_apertura, 2) }}
            </div>
        </div>

        @php
            $distribuciones = $apertura->distribucionesVendedores ?? collect();
            $cantidadVendedores = $distribuciones->count();
        @endphp

        @if($cantidadVendedores > 0)
            @php
                $tieneDesglose = false;
                foreach($distribuciones as $dist) {
                    if(isset($dist->conteo_billetes_monedas) && !empty($dist->conteo_billetes_monedas)) {
                        $tieneDesglose = true;
                        break;
                    }
                }
            @endphp

            @if($tieneDesglose)
            <div class="section">
                <div class="label" style="margin-bottom: 5px;">DESGLOSE DE DENOMINACIONES</div>
                @foreach($distribuciones as $dist)
                    @if(isset($dist->conteo_billetes_monedas) && !empty($dist->conteo_billetes_monedas))
                        @php
                            $conteo = $dist->conteo_billetes_monedas;
                            $denominaciones = [
                                ['label' => 'Billete S/. 200', 'valor' => 200, 'key' => 'b200'],
                                ['label' => 'Billete S/. 100', 'valor' => 100, 'key' => 'b100'],
                                ['label' => 'Billete S/. 50', 'valor' => 50, 'key' => 'b50'],
                                ['label' => 'Billete S/. 20', 'valor' => 20, 'key' => 'b20'],
                                ['label' => 'Billete S/. 10', 'valor' => 10, 'key' => 'b10'],
                                ['label' => 'Moneda S/. 5', 'valor' => 5, 'key' => 'm5'],
                                ['label' => 'Moneda S/. 2', 'valor' => 2, 'key' => 'm2'],
                                ['label' => 'Moneda S/. 1', 'valor' => 1, 'key' => 'm1'],
                                ['label' => 'Moneda S/. 0.50', 'valor' => 0.5, 'key' => 'm050'],
                                ['label' => 'Moneda S/. 0.20', 'valor' => 0.2, 'key' => 'm020'],
                                ['label' => 'Moneda S/. 0.10', 'valor' => 0.1, 'key' => 'm010'],
                                ['label' => 'Moneda S/. 0.05', 'valor' => 0.05, 'key' => 'm005'],
                            ];
                            $denominacionesConValor = array_filter($denominaciones, function($d) use ($conteo) {
                                return isset($conteo[$d['key']]) && $conteo[$d['key']] > 0;
                            });
                        @endphp

                        @if(count($denominacionesConValor) > 0)
                            @if($cantidadVendedores > 1)
                            <div style="font-weight: bold; margin-top: 10px; font-size: 11px;">{{ $dist->vendedor->name }}:</div>
                            @endif
                            
                            <table class="table" style="margin-top: 5px;">
                                <thead>
                                    <tr>
                                        <th>Denominación</th>
                                        <th style="text-align: center;">Cant.</th>
                                        <th style="text-align: right;">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($denominacionesConValor as $denom)
                                        @php
                                            $cantidad = $conteo[$denom['key']] ?? 0;
                                            $subtotal = $cantidad * $denom['valor'];
                                        @endphp
                                        <tr>
                                            <td>{{ $denom['label'] }}</td>
                                            <td style="text-align: center;">{{ $cantidad }}</td>
                                            <td style="text-align: right;">S/ {{ number_format($subtotal, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div style="text-align: right; font-weight: bold; margin-top: 5px; padding-top: 5px; border-top: 1px solid #000;">
                                Subtotal: S/ {{ number_format($dist->monto, 2) }}
                            </div>
                        @endif
                    @endif
                @endforeach
            </div>
            @endif

            <div class="section">
                <div class="label" style="margin-bottom: 5px;">DISTRIBUCIÓN A VENDEDORES ({{ $cantidadVendedores }})</div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Vendedor</th>
                            <th style="text-align: right;">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($distribuciones as $dist)
                        <tr>
                            <td>{{ $dist->vendedor->name }}</td>
                            <td style="text-align: right;">S/ {{ number_format($dist->monto, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="total">
                    TOTAL DISTRIBUIDO: S/ {{ number_format($apertura->monto_apertura, 2) }}
                </div>
            </div>
        @endif

        <div class="footer">
            <div>✓ Caja aperturada exitosamente</div>
            <div style="margin-top: 5px;">Gracias por usar nuestro sistema</div>
        </div>
    </div>
</body>
</html>
