<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Reporte de Ventas por Cobrar</title>
    <style>
        @page {
            margin: 15mm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 9pt;
            color: #000;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
        }
        .header img {
            max-height: 60px;
            margin-bottom: 5px;
        }
        .header h2 {
            margin: 5px 0;
            font-size: 14pt;
        }
        .header .subtitle {
            font-size: 10pt;
            color: #666;
        }
        .info-section {
            margin-bottom: 15px;
            font-size: 8pt;
        }
        .info-section table {
            width: 100%;
        }
        .info-section td {
            padding: 2px 5px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table.data-table th {
            background-color: #f0f0f0;
            border: 1px solid #000;
            padding: 5px 3px;
            font-size: 7pt;
            text-align: left;
            font-weight: bold;
        }
        table.data-table td {
            border: 1px solid #ccc;
            padding: 4px 3px;
            font-size: 7pt;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .totals {
            margin-top: 15px;
            text-align: right;
        }
        .totals table {
            float: right;
            width: 300px;
        }
        .totals td {
            padding: 3px 8px;
            font-size: 9pt;
        }
        .totals .label {
            font-weight: bold;
            text-align: right;
        }
        .totals .grand-total {
            font-weight: bold;
            font-size: 11pt;
            border-top: 2px solid #000;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 7pt;
            color: #666;
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="header">
        @if($logoPath)
            <img src="{{ $logoPath }}" alt="Logo">
        @endif
        <h2>REPORTE DE VENTAS POR COBRAR</h2>
        <div class="subtitle">{{ $empresa->razon_social }}</div>
        <div class="subtitle">RUC: {{ $empresa->ruc }}</div>
    </div>

    {{-- Información del reporte --}}
    <div class="info-section">
        <table>
            <tr>
                <td style="width: 50%;"><strong>Fecha de generación:</strong> {{ now()->format('d/m/Y H:i') }}</td>
                <td style="width: 50%;"><strong>Total de registros:</strong> {{ count($ventas) }}</td>
            </tr>
            @if($filtros)
            <tr>
                <td colspan="2"><strong>Filtros aplicados:</strong> {{ $filtros }}</td>
            </tr>
            @endif
        </table>
    </div>

    {{-- Tabla de datos --}}
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 3%;">#</th>
                <th style="width: 10%;">Fecha</th>
                <th style="width: 12%;">Documento</th>
                <th style="width: 25%;">Cliente</th>
                <th style="width: 10%;">RUC/DNI</th>
                <th style="width: 8%;" class="text-right">Total</th>
                <th style="width: 8%;" class="text-right">Cobrado</th>
                <th style="width: 8%;" class="text-right">Saldo</th>
                <th style="width: 8%;">Vencimiento</th>
                <th style="width: 8%;">Días Mora</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalNeto = 0;
                $totalCobrado = 0;
                $totalSaldo = 0;
            @endphp
            @foreach($ventas as $index => $venta)
            @php
                $totalNeto += $venta['total_neto'];
                $totalCobrado += $venta['total_cobrado'];
                $totalSaldo += $venta['saldo_pendiente'];
            @endphp
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $venta['fecha'] }}</td>
                <td>{{ $venta['documento'] }}</td>
                <td>{{ $venta['cliente'] }}</td>
                <td>{{ $venta['cliente_doc'] }}</td>
                <td class="text-right">S/. {{ number_format($venta['total_neto'], 2) }}</td>
                <td class="text-right">S/. {{ number_format($venta['total_cobrado'], 2) }}</td>
                <td class="text-right">S/. {{ number_format($venta['saldo_pendiente'], 2) }}</td>
                <td class="text-center">{{ $venta['fecha_vencimiento'] }}</td>
                <td class="text-center">{{ $venta['dias_mora'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totales --}}
    <div class="totals">
        <table>
            <tr>
                <td class="label">Total Facturado:</td>
                <td class="text-right">S/. {{ number_format($totalNeto, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Total Cobrado:</td>
                <td class="text-right">S/. {{ number_format($totalCobrado, 2) }}</td>
            </tr>
            <tr class="grand-total">
                <td class="label">Saldo Pendiente:</td>
                <td class="text-right">S/. {{ number_format($totalSaldo, 2) }}</td>
            </tr>
        </table>
    </div>

    {{-- Footer --}}
    <div class="footer">
        <p>Reporte generado automáticamente - {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>
