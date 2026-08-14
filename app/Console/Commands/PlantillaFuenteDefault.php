<?php

namespace App\Console\Commands;

use App\Models\FuentePersonalizada;
use App\Models\PlantillaImpresion;
use App\Models\PlantillaImpresionDetalle;
use Illuminate\Console\Command;

/**
 * Deja una fuente y un tamano como predeterminados para TODOS los PDF de una
 * empresa: escribe el nivel base (plantilla_impresion) y, salvo que se indique
 * lo contrario, tambien pisa los detalles por comprobante que ya existan.
 *
 * Ejemplos:
 *   php artisan plantillas:fuente-default prueba-1 --empresa=1
 *   php artisan plantillas:fuente-default prueba-1 --empresa=1 --tamano=8
 *   php artisan plantillas:fuente-default prueba-1 --empresa=1 --solo-base
 *   php artisan plantillas:fuente-default prueba-1 --empresa=1 --dry-run
 */
class PlantillaFuenteDefault extends Command
{
    protected $signature = 'plantillas:fuente-default
        {fuente : Nombre de la fuente tal como aparece en Fuentes Personalizadas}
        {--empresa=1 : ID de la empresa}
        {--tamano=8 : Tamano base en pt}
        {--solo-base : No tocar los comprobantes que ya tienen configuracion propia}
        {--dry-run : Mostrar que haria, sin guardar}';

    protected $description = 'Define la fuente y el tamano por defecto de todos los PDF de una empresa';

    public function handle(): int
    {
        $fuente   = (string) $this->argument('fuente');
        $empresa  = (int) $this->option('empresa');
        $tamano   = (int) $this->option('tamano');
        $soloBase = (bool) $this->option('solo-base');
        $dryRun   = (bool) $this->option('dry-run');

        if ($tamano < 6 || $tamano > 14) {
            $this->error("El tamano debe estar entre 6 y 14 (recibido: $tamano).");
            return self::FAILURE;
        }

        // La fuente debe existir para esta empresa, o los PDF saldrian sin ella
        $existe = FuentePersonalizada::where('empresa_id', $empresa)
            ->whereRaw('LOWER(nombre) = ?', [strtolower($fuente)])
            ->first();

        if (!$existe) {
            $this->error("La empresa $empresa no tiene una fuente llamada '$fuente'.");
            $disponibles = FuentePersonalizada::where('empresa_id', $empresa)->pluck('nombre');
            $this->line($disponibles->isEmpty()
                ? '  No hay fuentes cargadas. Subela primero desde Gestionar Fuentes Personalizadas.'
                : '  Disponibles: ' . $disponibles->implode(', '));
            return self::FAILURE;
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '') . "Empresa $empresa  ->  fuente '$fuente', tamano {$tamano}pt");

        // 1) Nivel base: lo heredan todos los comprobantes sin configuracion propia
        $base = PlantillaImpresion::firstOrNew(['empresa_id' => $empresa]);
        $estilosBase = array_merge($base->estilos ?? [], [
            'fuente'      => $fuente,
            'tamano_base' => $tamano,
        ]);

        if (!$dryRun) {
            $base->empresa_id = $empresa;
            $base->estilos = $estilosBase;
            $base->save();
        }
        $this->line('  base (todos los comprobantes sin detalle propio): OK');

        // 2) Detalles por comprobante: pisan a la base, hay que actualizarlos
        if ($soloBase) {
            $this->warn('  --solo-base: los comprobantes con configuracion propia se dejan como estan.');
            return self::SUCCESS;
        }

        $detalles = PlantillaImpresionDetalle::where('empresa_id', $empresa)->get();
        if ($detalles->isEmpty()) {
            $this->line('  no hay comprobantes con configuracion propia.');
            return self::SUCCESS;
        }

        foreach ($detalles as $detalle) {
            $estilos = $detalle->estilos ?? [];
            $antes = ($estilos['fuente'] ?? '-') . '/' . ($estilos['tamano_base'] ?? '-');

            $estilos['fuente'] = $fuente;
            $estilos['tamano_base'] = $tamano;

            if (!$dryRun) {
                $detalle->estilos = $estilos;
                $detalle->save();
            }

            $this->line(sprintf('  %-18s %-8s %s -> %s/%s',
                $detalle->comprobante, $detalle->formato, $antes, $fuente, $tamano));
        }

        $this->info(($dryRun ? '[DRY-RUN] Nada se guardo. ' : '') . 'Listo.');

        return self::SUCCESS;
    }
}
