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
        if (!Schema::hasColumn('compra', 'orden_compra_id')) {
            Schema::table('compra', function (Blueprint $table) {
                $table->unsignedBigInteger('orden_compra_id')->nullable()->after('id');
                
                if (Schema::hasTable('ordenes_compra')) {
                    $table->foreign('orden_compra_id')->references('id')->on('ordenes_compra')->onDelete('set null')->onUpdate('cascade');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('compra', 'orden_compra_id')) {
            Schema::table('compra', function (Blueprint $table) {
                $table->dropForeign(['orden_compra_id']);
                $table->dropColumn('orden_compra_id');
            });
        }
    }
};
