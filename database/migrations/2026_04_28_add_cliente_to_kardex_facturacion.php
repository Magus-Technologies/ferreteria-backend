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
        Schema::table('kardex_facturacions', function (Blueprint $table) {
            $table->string('cliente_nombre')->nullable()->after('producto_codigo');
            $table->bigInteger('cliente_id')->nullable()->after('almacen_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kardex_facturacions', function (Blueprint $table) {
            $table->dropColumn(['cliente_nombre', 'cliente_id']);
        });
    }
};
