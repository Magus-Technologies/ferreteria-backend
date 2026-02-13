<?php

namespace App\Console\Commands;

use App\Jobs\EnviarComprobantesASunatJob;
use Illuminate\Console\Command;

class EnviarComprobantesASunat extends Command
{
    protected $signature = 'sunat:enviar-facturas';
    protected $description = 'Enviar comprobantes pendientes a SUNAT (Facturas: 3 días, Boletas: 7 días). NO envía notas de débito/crédito';

    public function handle(): int
    {
        $this->info('Iniciando envío automático de comprobantes a SUNAT...');
        $this->info('Facturas (01): se envían después de 3 días calendario');
        $this->info('Boletas (03): se envían después de 5 MINUTOS (TESTING)');
        $this->warn('NOTA: Las notas de débito/crédito se envían manualmente.');

        EnviarComprobantesASunatJob::dispatch();

        $this->info('Job de envío automático despachado exitosamente.');
        $this->info('Los comprobantes se procesarán en segundo plano.');

        return Command::SUCCESS;
    }
}
