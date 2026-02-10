<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f3f4f6;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #1f2937; padding: 30px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px;">GRUPO MI REDENTOR S.A.C.</h1>
                            <p style="color: #9ca3af; margin: 10px 0 0 0; font-size: 14px;">{{ $subject }}</p>
                        </td>
                    </tr>

                    <!-- Contenido -->
                    <tr>
                        <td style="padding: 30px;">
                            <p style="margin: 0 0 15px 0; font-size: 16px; color: #1f2937;">
                                Estimado/a,
                            </p>
                            <p style="margin: 0 0 15px 0; font-size: 14px; color: #4b5563; line-height: 1.6;">
                                Se ha registrado el {{ $apertura->estado === 'cerrada' ? 'cierre' : 'arqueo' }} de caja con los siguientes datos:
                            </p>
                            <table width="100%" style="margin: 20px 0; border: 1px solid #e5e7eb; border-radius: 4px;">
                                <tr>
                                    <td style="padding: 12px; border-bottom: 1px solid #e5e7eb; background-color: #f9fafb;">
                                        <strong style="color: #1f2937;">Fecha de Apertura:</strong>
                                    </td>
                                    <td style="padding: 12px; border-bottom: 1px solid #e5e7eb;">
                                        {{ \Carbon\Carbon::parse($apertura->fecha_apertura)->format('d/m/Y H:i') }}
                                    </td>
                                </tr>
                                @if($apertura->fecha_cierre)
                                <tr>
                                    <td style="padding: 12px; border-bottom: 1px solid #e5e7eb; background-color: #f9fafb;">
                                        <strong style="color: #1f2937;">Fecha de Cierre:</strong>
                                    </td>
                                    <td style="padding: 12px; border-bottom: 1px solid #e5e7eb;">
                                        {{ \Carbon\Carbon::parse($apertura->fecha_cierre)->format('d/m/Y H:i') }}
                                    </td>
                                </tr>
                                @endif
                                <tr>
                                    <td style="padding: 12px; border-bottom: 1px solid #e5e7eb; background-color: #f9fafb;">
                                        <strong style="color: #1f2937;">Usuario:</strong>
                                    </td>
                                    <td style="padding: 12px; border-bottom: 1px solid #e5e7eb;">
                                        {{ $apertura->user->name ?? 'N/A' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px; background-color: #f9fafb;">
                                        <strong style="color: #1f2937;">Estado:</strong>
                                    </td>
                                    <td style="padding: 12px;">
                                        <span style="color: {{ $apertura->estado === 'cerrada' ? '#16a34a' : '#f59e0b' }}; font-weight: bold;">
                                            {{ strtoupper($apertura->estado) }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin: 20px 0 0 0; font-size: 14px; color: #6b7280;">
                                Este es un correo de notificación automática del registro de arqueo de caja.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 20px 30px; background-color: #f9fafb; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="color: #6b7280; margin: 0; font-size: 12px;">
                                Este es un correo automático, por favor no responder.
                            </p>
                            <p style="color: #9ca3af; margin: 10px 0 0 0; font-size: 11px;">
                                Generado: {{ \Carbon\Carbon::now()->format('d/m/Y H:i:s') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
