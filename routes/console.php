<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ========================================
// TAREAS PROGRAMADAS (CRON)
// ========================================

// Enviar FACTURAS a SUNAT automáticamente después de 5 días
// IMPORTANTE: Solo envía facturas (01, 03), NO notas de débito/crédito
// Se ejecuta diariamente a las 2:00 AM
Schedule::command('sunat:enviar-facturas')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();
