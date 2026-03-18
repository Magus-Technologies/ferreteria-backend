<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compra', function (Blueprint $table) {
            $table->string('gasto_extra_id', 191)->nullable()->after('egreso_dinero_id');

            if (Schema::hasTable('gastos_extras')) {
                $table->foreign('gasto_extra_id')
                    ->references('id')
                    ->on('gastos_extras')
                    ->onDelete('set null')
                    ->onUpdate('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('compra', function (Blueprint $table) {
            $table->dropForeign(['gasto_extra_id']);
            $table->dropColumn('gasto_extra_id');
        });
    }
};
