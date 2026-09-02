<?php

namespace App\Console\Commands;

use App\Models\ComprobanteElectronico;
use App\Services\Interfaces\FacturaServiceInterface;
use Illuminate\Console\Command;

/**
 * Reconcilia comprobantes que figuran como NO enviados pero que SUNAT ya puede
 * tener registrados.
 *
 * Por qué existe: el envío ocurría dentro de una transacción, así que un fallo
 * posterior (disco, QR) revertía el estado local aunque SUNAT ya hubiera
 * aceptado el comprobante. Quedaron documentos "pendientes" que en realidad
 * están declarados, y no hay forma de distinguirlos mirando la base.
 *
 * Cómo lo resuelve SIN adivinar: reenvía. SUNAT es la única fuente de verdad.
 *   - Si ya lo tiene  -> responde 1033/2109 y el servicio concilia a ACEPTADO
 *   - Si no lo tiene  -> lo acepta y queda ACEPTADO
 * En ambos casos el estado final es correcto. Nunca se marca ACEPTADO a ciegas:
 * eso escondería un comprobante que jamás se declaró.
 *
 * Uso:
 *   php artisan sunat:conciliar                  # simulación: solo lista
 *   php artisan sunat:conciliar --ejecutar       # reenvía de verdad
 *   php artisan sunat:conciliar --ejecutar --limite=20
 */
class ConciliarComprobantesSunat extends Command
{
    protected $signature = 'sunat:conciliar
                            {--ejecutar : Reenvía de verdad. Sin esta bandera solo simula.}
                            {--limite=50 : Máximo de comprobantes a procesar en esta corrida.}
                            {--espera=2 : Segundos de pausa entre envíos, para no saturar SUNAT.}';

    protected $description = 'Reconcilia con SUNAT los comprobantes que figuran como no enviados';

    public function handle(FacturaServiceInterface $facturaService): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $limite = (int) $this->option('limite');
        $espera = (int) $this->option('espera');

        // Solo PENDIENTE. Es el único estado que significa "no sabemos si llegó".
        //
        // Se usa una lista BLANCA a propósito, no una negra: con whereNotIn
        // cualquier estado nuevo o no contemplado entra por descarte al reenvío.
        // Así fue como se colaron las BAJA_ACEPTADA — boletas ANULADAS cuya baja
        // SUNAT ya aceptó. Reenviar una de esas seria intentar re-declarar un
        // documento dado de baja: un problema tributario, no un error de estado.
        //
        // Los demás estados NO se tocan:
        //   ACEPTADO / ACEPTADO_CON_OBSERVACIONES -> ya está resuelto
        //   BAJA_ACEPTADA / ANULADO               -> anulado, no se reenvía
        //   RECHAZADO                             -> SUNAT lo rechazó por reglas;
        //                                            hay que corregir el documento,
        //                                            reenviarlo igual no sirve
        $pendientes = ComprobanteElectronico::where('estado_sunat', 'PENDIENTE')
            ->whereIn('tipo_comprobante', ['01', '03'])
            ->whereNotNull('venta_id')
            // Una venta anulada no se declara: se da de baja, que es otro flujo.
            ->whereHas('venta', fn ($q) => $q->where('estado_de_venta', '!=', 'an'))
            ->orderBy('created_at')
            ->limit($limite)
            ->get();

        if ($pendientes->isEmpty()) {
            $this->info('No hay comprobantes por conciliar.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line(sprintf('Comprobantes a revisar: %d', $pendientes->count()));

        if (! $ejecutar) {
            $this->warn('MODO SIMULACIÓN — no se envía nada. Agregá --ejecutar para hacerlo de verdad.');
            $this->newLine();
            // "PENDIENTE" solo dice que no se confirmó, no si llegó o no. El
            // historial de intentos SÍ lo insinúa, y evita mandar a ciegas:
            //   - un intento con 1033/2109 es prueba de que SUNAT ya lo tiene
            //   - sin ningún intento, es casi seguro que nunca salió
            $filas = $pendientes->map(function ($c) {
                $intentos = \App\Models\IntentoEnvioSunat::where('comprobante_id', $c->id)
                    ->orderByDesc('fecha_intento')
                    ->get();

                $ultimo = $intentos->first();
                $mensaje = (string) ($ultimo->mensaje_respuesta ?? '');

                if ($intentos->isEmpty()) {
                    $diagnostico = 'NUNCA SE INTENTÓ';
                } elseif (str_contains($mensaje, '1033') || str_contains($mensaje, '2109')
                    || stripos($mensaje, 'ya fue registrado') !== false
                ) {
                    $diagnostico = 'YA ESTÁ EN SUNAT';
                } elseif ($ultimo->resultado === 'exitoso') {
                    $diagnostico = 'ACEPTADO SIN GUARDAR';
                } else {
                    $diagnostico = 'FALLÓ AL ENVIAR';
                }

                return [
                    $c->serie.'-'.$c->correlativo,
                    $c->tipo_comprobante === '01' ? 'Factura' : 'Boleta',
                    optional($c->created_at)->format('d/m H:i'),
                    $intentos->count(),
                    $diagnostico,
                    mb_substr(preg_replace('/\s+/', ' ', $mensaje), 0, 45),
                ];
            })->all();

            $this->table(
                ['Serie-Número', 'Tipo', 'Emitido', 'Intentos', 'Diagnóstico', 'Último mensaje de SUNAT'],
                $filas,
            );

            $resumen = collect($filas)->countBy(4);
            $this->newLine();
            $this->line('Qué dice el historial de intentos:');
            foreach ($resumen as $etiqueta => $cantidad) {
                $this->line(sprintf('  %-22s %d', $etiqueta, $cantidad));
            }
            $this->newLine();
            $this->line('  YA ESTÁ EN SUNAT      -> el reenvío solo corrige el estado (1033)');
            $this->line('  ACEPTADO SIN GUARDAR  -> SUNAT respondió OK pero no se persistió');
            $this->line('  FALLÓ AL ENVIAR       -> mirá el mensaje: puede ser transporte o reglas');
            $this->line('  NUNCA SE INTENTÓ      -> no salió nunca; el reenvío lo declara por primera vez');

            return self::SUCCESS;
        }

        $conciliados = 0;
        $enviados = 0;
        $fallidos = 0;

        $barra = $this->output->createProgressBar($pendientes->count());
        $barra->start();

        foreach ($pendientes as $comprobante) {
            $etiqueta = $comprobante->serie.'-'.$comprobante->correlativo;

            try {
                $resultado = $facturaService->enviarASunat($comprobante->venta_id, 'conciliacion');

                // El servicio devuelve 1033 cuando SUNAT ya lo tenía registrado.
                if (str_contains((string) ($resultado['codigo_sunat'] ?? ''), '1033')
                    || str_contains((string) ($resultado['codigo_sunat'] ?? ''), '2109')
                ) {
                    $conciliados++;
                    $this->newLine();
                    $this->line("  <fg=yellow>YA ESTABA EN SUNAT</> {$etiqueta} — estado corregido");
                } else {
                    $enviados++;
                    $this->newLine();
                    $this->line("  <fg=green>ENVIADO AHORA</>      {$etiqueta} — no había llegado");
                }
            } catch (\Throwable $e) {
                $fallidos++;
                $this->newLine();
                $this->line("  <fg=red>SIN RESOLVER</>       {$etiqueta} — ".substr($e->getMessage(), 0, 90));
            }

            $barra->advance();

            if ($espera > 0) {
                sleep($espera);
            }
        }

        $barra->finish();
        $this->newLine(2);

        $this->line('Resumen:');
        $this->line("  Ya estaban en SUNAT (estado corregido): <fg=yellow>{$conciliados}</>");
        $this->line("  No habían llegado y se enviaron ahora:  <fg=green>{$enviados}</>");
        $this->line("  Quedan sin resolver:                    <fg=red>{$fallidos}</>");

        if ($fallidos > 0) {
            $this->newLine();
            $this->warn('Los que quedan sin resolver necesitan revisión manual: revisá el detalle de arriba.');
        }

        return self::SUCCESS;
    }
}
