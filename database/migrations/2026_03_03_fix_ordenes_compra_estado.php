<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Fix any NULL estado values to 'pendiente'
        DB::table('ordenes_compra')
            ->whereNull('estado')
            ->update(['estado' => 'pendiente']);
    }

    public function down(): void
    {
        // No rollback needed
    }
};
