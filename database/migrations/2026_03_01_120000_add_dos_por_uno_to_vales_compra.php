<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE vales_compra MODIFY COLUMN tipo_promocion ENUM('SORTEO','DESCUENTO_MISMA_COMPRA','DESCUENTO_PROXIMA_COMPRA','PRODUCTO_GRATIS','DOS_POR_UNO') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE vales_compra MODIFY COLUMN tipo_promocion ENUM('SORTEO','DESCUENTO_MISMA_COMPRA','DESCUENTO_PROXIMA_COMPRA','PRODUCTO_GRATIS') NOT NULL");
    }
};
