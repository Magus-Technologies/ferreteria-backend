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
        Schema::table('movimientos_internos', function (Blueprint $table) {
            $table->string('numero_operacion', 100)->nullable()->after('comprobante');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movimientos_internos', function (Blueprint $table) {
            $table->dropColumn('numero_operacion');
        });
    }
};
