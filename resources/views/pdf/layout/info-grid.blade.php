{{--
    Grid de informacion (ej: datos del cliente, proveedor, etc.)
    Recibe: $filas = [
        ['label1' => 'valor1', 'label2' => 'valor2'],
        ['label1' => 'valor1', 'label2' => 'valor2'],
    ]
--}}
<table style="width: 100%; border: 0.5px solid #fadc06; margin-bottom: 10px; border-collapse: collapse;">
    @foreach($filas as $fila)
        <tr style="min-height: 18px;">
            @foreach($fila as $label => $valor)
                <td style="width: {{ $loop->first ? '12%' : '15%' }}; padding: 4px; font-size: 7pt; font-weight: bold;">
                    {{ $label }}
                </td>
                <td style="width: {{ $loop->first ? '38%' : '35%' }}; padding: 4px; font-size: 7pt;">
                    : {{ $valor }}
                </td>
            @endforeach
        </tr>
    @endforeach
</table>
