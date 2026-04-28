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
            $table->integer('orden')->default(0)->after('almacen_id');
        });

        Schema::table('kardex_facturacions', function (Blueprint $table) {
            $table->integer('orden')->default(0)->after('almacen_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kardex_inventarios', function (Blueprint $table) {
            $table->dropColumn('orden');
        });

        Schema::table('kardex_facturacions', function (Blueprint $table) {
            $table->dropColumn('orden');
        });
    }
};
