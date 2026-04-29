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
        Schema::table('kardex_inventarios', function (Blueprint $table) {
            $table->string('proveedor_nombre')->nullable()->after('producto');
            $table->bigInteger('proveedor_id')->nullable()->after('producto_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kardex_inventarios', function (Blueprint $table) {
            $table->dropColumn(['proveedor_nombre', 'proveedor_id']);
        });
    }
};
