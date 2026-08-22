<?php

namespace App\Console\Commands;

use App\Models\ComprobanteElectronico;
use App\Models\GuiaRemision;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * FacturaService/NotaCreditoService/NotaDebitoService/GuiaRemisionService
 * guardaban el .zip del CDR con el contenido TODAVÍA en base64 (bug de
 * orden: guardarCdr() se llamaba antes del decode, no con el resultado
 * decodificado). El dato no se perdió — el archivo/columna quedó siendo
 * texto base64 válido, solo con extensión/tipo equivocado, así que se
 * puede reparar in situ decodificándolo una vez más.
 *
 * Detecta archivos/columnas que NO empiezan con la firma de ZIP (PK\x03\x04)
 * pero SÍ decodifican en base64 estricto a algo que sí empieza con esa
 * firma, y los sobreescribe con el binario correcto.
 */
class RepararCdrsBase64 extends Command
{
    protected $signature = 'sunat:reparar-cdrs {--dry-run : Solo mostrar qué se repararía, sin escribir nada}';
    protected $description = 'Repara CDR (.zip) guardados como texto base64 en vez de binario (facturas/boletas, NC, ND y guías)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->repararComprobantes($dryRun);
        $this->repararGuias($dryRun);

        return Command::SUCCESS;
    }

    private function esZipValido(string $contenido): bool
    {
        return str_starts_with($contenido, "PK\x03\x04");
    }

    private function intentarDecodificar(?string $contenido): ?string
    {
        if (!$contenido || $this->esZipValido($contenido)) {
            return null;
        }

        $decodificado = base64_decode($contenido, true);
        if ($decodificado === false || !$this->esZipValido($decodificado)) {
            return null;
        }

        return $decodificado;
    }

    private function repararComprobantes(bool $dryRun): void
    {
        $this->info('Revisando comprobantes_electronicos (facturas/boletas/NC/ND)...');
        $reparados = 0;

        ComprobanteElectronico::whereNotNull('cdr_path')
            ->orWhereNotNull('cdr_xml')
            ->chunkById(100, function ($comprobantes) use (&$reparados, $dryRun) {
                foreach ($comprobantes as $comprobante) {
                    $cambios = [];

                    if ($comprobante->cdr_path && Storage::exists($comprobante->cdr_path)) {
                        $decodificado = $this->intentarDecodificar(Storage::get($comprobante->cdr_path));
                        if ($decodificado !== null) {
                            $cambios['archivo'] = $comprobante->cdr_path;
                            if (!$dryRun) {
                                Storage::put($comprobante->cdr_path, $decodificado);
                            }
                        }
                    }

                    $decodificadoDb = $this->intentarDecodificar($comprobante->cdr_xml);
                    if ($decodificadoDb !== null) {
                        $cambios['columna_cdr_xml'] = true;
                        if (!$dryRun) {
                            $comprobante->update(['cdr_xml' => $decodificadoDb]);
                        }
                    }

                    if ($cambios) {
                        $reparados++;
                        $this->line("  - {$comprobante->serie}-{$comprobante->correlativo} (tipo {$comprobante->tipo_comprobante}): " . implode(', ', array_keys($cambios)));
                    }
                }
            });

        $this->info(($dryRun ? '[DRY-RUN] Se repararían ' : 'Reparados ') . "{$reparados} comprobante(s).");
    }

    private function repararGuias(bool $dryRun): void
    {
        $this->info('Revisando guias_remision...');
        $reparados = 0;

        GuiaRemision::whereNotNull('sunat_cdr_path')
            ->orWhereNotNull('sunat_cdr_xml')
            ->chunkById(100, function ($guias) use (&$reparados, $dryRun) {
                foreach ($guias as $guia) {
                    $cambios = [];

                    if ($guia->sunat_cdr_path && Storage::exists($guia->sunat_cdr_path)) {
                        $decodificado = $this->intentarDecodificar(Storage::get($guia->sunat_cdr_path));
                        if ($decodificado !== null) {
                            $cambios['archivo'] = $guia->sunat_cdr_path;
                            if (!$dryRun) {
                                Storage::put($guia->sunat_cdr_path, $decodificado);
                            }
                        }
                    }

                    $decodificadoDb = $this->intentarDecodificar($guia->sunat_cdr_xml);
                    if ($decodificadoDb !== null) {
                        $cambios['columna_sunat_cdr_xml'] = true;
                        if (!$dryRun) {
                            $guia->update(['sunat_cdr_xml' => $decodificadoDb]);
                        }
                    }

                    if ($cambios) {
                        $reparados++;
                        $this->line("  - Guía {$guia->serie}-{$guia->numero}: " . implode(', ', array_keys($cambios)));
                    }
                }
            });

        $this->info(($dryRun ? '[DRY-RUN] Se repararían ' : 'Reparadas ') . "{$reparados} guía(s).");
    }
}
