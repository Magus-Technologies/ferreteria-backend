<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Activar todos los motivos de nota (crédito y débito)
        DB::table('motivo_nota')->update(['estado' => 1]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No revertir - mantener los motivos activos
        // En producción, los motivos deben estar activos
    }
};
