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
            $table->decimal('costo_anterior', 16, 4)->nullable()->after('costo');
            $table->decimal('costo_actual', 16, 4)->nullable()->after('costo_anterior');
        });

        Schema::table('kardex_facturacions', function (Blueprint $table) {
            $table->decimal('costo_anterior', 16, 4)->nullable()->after('costo');
            $table->decimal('costo_actual', 16, 4)->nullable()->after('costo_anterior');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kardex_inventarios', function (Blueprint $table) {
            $table->dropColumn(['costo_anterior', 'costo_actual']);
        });

        Schema::table('kardex_facturacions', function (Blueprint $table) {
            $table->dropColumn(['costo_anterior', 'costo_actual']);
        });
    }
};
