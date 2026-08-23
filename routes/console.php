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

// Enviar FACTURAS/BOLETAS a SUNAT automáticamente según los días configurados
// en Mi Empresa → SUNAT. IMPORTANTE: Solo envía facturas (01) y boletas (03),
// NO notas de débito/crédito.
//
// Corre 5 veces por día (2am, 11am, 2pm, 6pm, 8pm) en vez de
// una sola vez de madrugada: si UN comprobante falla al enviarse (SUNAT caída,
// etc.) queda esperando hasta la PRÓXIMA corrida de este mismo cron — antes
// eso significaba esperar hasta el día siguiente a las 2 AM, sin que nadie
// se entere a tiempo para reintentarlo a mano antes de que se pase del plazo
// legal. Repetirlo cada ~3h reduce ese riesgo sin peligro de duplicar envíos:
// el job solo toca comprobantes que siguen `PENDIENTE` sin fecha_envio_sunat.
Schedule::command('sunat:enviar-facturas')
    ->cron('0 2,11,14,18,20 * * *')
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
