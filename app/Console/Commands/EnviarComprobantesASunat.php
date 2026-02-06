<?php

namespace App\Console\Commands;

use App\Jobs\EnviarComprobantesASunatJob;
use Illuminate\Console\Command;

class EnviarComprobantesASunat extends Command
{
    protected $signature = 'sunat:enviar-facturas';
    protected $description = 'Enviar FACTURAS pendientes a SUNAT (más de 5 días). NO envía notas de débito/crédito';

    public function handle(): int
    {
        $this->info('Iniciando envío automático de FACTURAS a SUNAT...');
        $this->warn('NOTA: Solo se envían FACTURAS. Las notas de débito/crédito se envían manualmente.');

        EnviarComprobantesASunatJob::dispatch();

        $this->info('Job de envío automático despachado exitosamente.');
        $this->info('Las facturas se procesarán en segundo plano.');

        return Command::SUCCESS;
    }
}
