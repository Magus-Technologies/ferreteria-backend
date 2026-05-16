<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Safe migration: only adds column if it doesn't exist
     */
    public function up(): void
    {
        // Check if column already exists before adding
        $columns = DB::select("SHOW COLUMNS FROM cotizacion LIKE 'recomendado_por_id'");
        if (count($columns) === 0) {
            Schema::table('cotizacion', function (Blueprint $table) {
                $table->unsignedBigInteger('recomendado_por_id')->nullable()->after('almacen_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if column exists before dropping
        $columns = DB::select("SHOW COLUMNS FROM cotizacion LIKE 'recomendado_por_id'");
        if (count($columns) > 0) {
            Schema::table('cotizacion', function (Blueprint $table) {
                $table->dropColumn('recomendado_por_id');
            });
        }
    }
};