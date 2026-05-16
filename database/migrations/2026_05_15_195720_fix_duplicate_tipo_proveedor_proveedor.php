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
        // Check if column already exists
        $columns = DB::select("SHOW COLUMNS FROM proveedor LIKE 'tipo_proveedor'");
        if (count($columns) === 0) {
            Schema::table('proveedor', function (Blueprint $table) {
                $table->string('tipo_proveedor', 20)->default('empresa')->after('id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if column exists before dropping
        $columns = DB::select("SHOW COLUMNS FROM proveedor LIKE 'tipo_proveedor'");
        if (count($columns) > 0) {
            Schema::table('proveedor', function (Blueprint $table) {
                $table->dropColumn('tipo_proveedor');
            });
        }
    }
};