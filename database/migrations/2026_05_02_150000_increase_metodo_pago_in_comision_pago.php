<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comision_pago', function (Blueprint $table) {
            $table->string('metodo_pago', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('comision_pago', function (Blueprint $table) {
            $table->string('metodo_pago', 50)->nullable()->change();
        });
    }
};
