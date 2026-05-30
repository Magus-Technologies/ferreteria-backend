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

// Cerrar automáticamente las cajas que quedaron abiertas del día anterior
// Se ejecuta diariamente a las 11:59 PM
Schedule::command('cajas:cerrar-olvidadas')
    ->dailyAt('23:59')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Liberar stock de cotizaciones cuya fecha de vencimiento ha pasado
// Se ejecuta diariamente a las 3:00 AM
Schedule::command('reservas:liberar-expiradas')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Notificar a usuarios sobre cotizaciones y préstamos próximos a vencer
// Se ejecuta diariamente a las 8:00 AM
Schedule::command('notificaciones:vencimientos')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Marcar como FINALIZADO los vales de compra cuya fecha_fin ya pasó
// Se ejecuta diariamente a las 00:05 (apenas inicia el nuevo día)
Schedule::command('vales:finalizar-vencidos')
    ->dailyAt('00:05')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();
