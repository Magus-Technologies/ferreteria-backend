<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Agregar 'GENERADO' al enum de acciones del historial: se usa cuando un vale
        // de PRÓXIMA COMPRA genera un código (VCC-) para canjear en una venta posterior.
        DB::statement("ALTER TABLE vales_compra_historial MODIFY COLUMN accion ENUM('CREADO','MODIFICADO','ACTIVADO','PAUSADO','FINALIZADO','APLICADO','STOCK_AGOTADO','GENERADO') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE vales_compra_historial MODIFY COLUMN accion ENUM('CREADO','MODIFICADO','ACTIVADO','PAUSADO','FINALIZADO','APLICADO','STOCK_AGOTADO') NOT NULL");
    }
};
