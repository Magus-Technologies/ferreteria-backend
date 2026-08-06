<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE movimientos_internos ADD COLUMN saldo_origen_anterior DOUBLE NULL AFTER monto');
        DB::statement('ALTER TABLE movimientos_internos ADD COLUMN saldo_origen_actual DOUBLE NULL AFTER saldo_origen_anterior');
        DB::statement('ALTER TABLE movimientos_internos ADD COLUMN saldo_destino_anterior DOUBLE NULL AFTER saldo_origen_actual');
        DB::statement('ALTER TABLE movimientos_internos ADD COLUMN saldo_destino_actual DOUBLE NULL AFTER saldo_destino_anterior');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE movimientos_internos DROP COLUMN saldo_origen_anterior');
        DB::statement('ALTER TABLE movimientos_internos DROP COLUMN saldo_origen_actual');
        DB::statement('ALTER TABLE movimientos_internos DROP COLUMN saldo_destino_anterior');
        DB::statement('ALTER TABLE movimientos_internos DROP COLUMN saldo_destino_actual');
    }
};
