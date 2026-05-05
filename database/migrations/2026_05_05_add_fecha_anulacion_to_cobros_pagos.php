<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cobroventa', function (Blueprint $table) {
            $table->date('fecha_anulacion')->nullable()->after('estado');
        });

        Schema::table('pagodecompra', function (Blueprint $table) {
            $table->date('fecha_anulacion')->nullable()->after('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cobroventa', function (Blueprint $table) {
            $table->dropColumn('fecha_anulacion');
        });

        Schema::table('pagodecompra', function (Blueprint $table) {
            $table->dropColumn('fecha_anulacion');
        });
    }
};
