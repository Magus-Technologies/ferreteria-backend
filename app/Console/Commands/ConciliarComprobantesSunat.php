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

        $pendientes = ComprobanteElectronico::whereNotIn('estado_sunat', ['ACEPTADO', 'ACEPTADO_CON_OBSERVACIONES', 'ANULADO'])
            ->whereIn('tipo_comprobante', ['01', '03'])
            ->whereNotNull('venta_id')
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
            $this->table(
                ['Serie-Número', 'Tipo', 'Estado actual', 'Emitido'],
                $pendientes->map(fn ($c) => [
                    $c->serie.'-'.$c->correlativo,
                    $c->tipo_comprobante === '01' ? 'Factura' : 'Boleta',
                    $c->estado_sunat ?? '(sin estado)',
                    optional($c->created_at)->format('d/m/Y H:i'),
                ])->all(),
            );

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
