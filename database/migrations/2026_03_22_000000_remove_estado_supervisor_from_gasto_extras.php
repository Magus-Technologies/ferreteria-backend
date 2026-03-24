<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gastos_extras', function (Blueprint $table) {
            $table->dropForeign(['supervisor_id']);
            $table->dropColumn(['estado', 'supervisor_id']);
        });
    }

    public function down(): void
    {
        Schema::table('gastos_extras', function (Blueprint $table) {
            $table->enum('estado', ['pendiente', 'aprobado', 'anulado'])->default('pendiente')->after('concepto');
            $table->string('supervisor_id', 191)->nullable()->after('user_id');
            $table->foreign('supervisor_id')->references('id')->on('user')->nullOnDelete();
        });
    }
};
