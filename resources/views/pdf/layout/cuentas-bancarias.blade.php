{{-- Tabla de cuentas bancarias reutilizable --}}
@php($est = $est ?? [])
@php($bloques = $bloques ?? [])
@php($colorTema = $est['color_tema'] ?? '#fadc06')
@php($colorBorde = $est['color_borde'] ?? '#fadc06')
@php($borderPx = $est['border_px'] ?? 2)
@php($borderThin = $est['border_thin_px'] ?? 1)
@php($cssLabel = $bloques['obs_label']['css'] ?? 'font-size: 9pt; font-weight: bold;')
@php($cssHeader = $bloques['tabla_header']['css'] ?? 'font-size: 6pt; font-weight: bold; text-align: center;')
@php($cssFila = $bloques['tabla_fila']['css'] ?? 'font-size: 5pt; text-align: center;')

<div style="text-align: right; margin-top: 10px;">
    <div style="{{ $cssLabel }} text-align: left; margin-bottom: 5px; width: 55%; display: inline-block;">
        CUENTAS:
    </div>
    <table style="width: 55%; margin-left: auto; border: {{ $borderPx }}px solid {{ $colorTema }}; border-collapse: collapse;">
        <tr style="border-bottom: {{ $borderThin }}px solid {{ $colorBorde }}; background-color: {{ $colorTema }};">
            <td style="{{ $cssHeader }} width: 25%; padding: 2px; border-right: {{ $borderThin }}px solid {{ $colorBorde }};">ENTIDAD</td>
            <td style="{{ $cssHeader }} width: 25%; padding: 2px; border-right: {{ $borderThin }}px solid {{ $colorBorde }};">CUENTA</td>
            <td style="{{ $cssHeader }} width: 25%; padding: 2px; border-right: {{ $borderThin }}px solid {{ $colorBorde }};">NUMERO</td>
            <td style="{{ $cssHeader }} width: 25%; padding: 2px;">CCI</td>
        </tr>
        <tr style="border-bottom: {{ $borderThin }}px solid {{ $colorBorde }};">
            <td style="{{ $cssFila }} padding: 2px; border-right: {{ $borderThin }}px solid {{ $colorBorde }};">BCP</td>
            <td style="{{ $cssFila }} padding: 2px; border-right: {{ $borderThin }}px solid {{ $colorBorde }};">AHORROS</td>
            <td style="{{ $cssFila }} padding: 2px; border-right: {{ $borderThin }}px solid {{ $colorBorde }};">57099829303065</td>
            <td style="{{ $cssFila }} padding: 2px;">00257000998279306504</td>
        </tr>
        <tr style="border-bottom: {{ $borderThin }}px solid {{ $colorBorde }};">
            <td style="{{ $cssFila }} padding: 2px; border-right: {{ $borderThin }}px solid {{ $colorBorde }};">BBVA</td>
            <td style="{{ $cssFila }} padding: 2px; border-right: {{ $borderThin }}px solid {{ $colorBorde }};">AHORROS</td>
            <td style="{{ $cssFila }} padding: 2px; border-right: {{ $borderThin }}px solid {{ $colorBorde }};">57099829303065</td>
            <td style="{{ $cssFila }} padding: 2px;">00257000998279306504</td>
        </tr>
        <tr style="border-bottom: {{ $borderThin }}px solid {{ $colorBorde }};">
            <td style="{{ $cssFila }} padding: 2px; border-right: {{ $borderThin }}px solid {{ $colorBorde }};">SCOTIABANK</td>
            <td style="{{ $cssFila }} padding: 2px; border-right: {{ $borderThin }}px solid {{ $colorBorde }};">AHORROS</td>
            <td style="{{ $cssFila }} padding: 2px; border-right: {{ $borderThin }}px solid {{ $colorBorde }};">7117529613</td>
            <td style="{{ $cssFila }} padding: 2px;">00940830711752961369</td>
        </tr>
        <tr>
            <td style="{{ $cssFila }} padding: 2px; border-right: {{ $borderThin }}px solid {{ $colorBorde }};">INTERBANK</td>
            <td style="{{ $cssFila }} padding: 2px; border-right: {{ $borderThin }}px solid {{ $colorBorde }};">AHORROS</td>
            <td style="{{ $cssFila }} padding: 2px; border-right: {{ $borderThin }}px solid {{ $colorBorde }};">6003004488177</td>
            <td style="{{ $cssFila }} padding: 2px;">00360000600344881774</td>
        </tr>
    </table>
</div>
