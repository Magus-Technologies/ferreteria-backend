<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proveedor', function (Blueprint $table) {
            $table->string('tipo_proveedor', 20)->default('empresa')->after('id');
            $table->string('ruc', 191)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('proveedor', function (Blueprint $table) {
            $table->dropColumn('tipo_proveedor');
            $table->string('ruc', 191)->nullable(false)->change();
        });
    }
};
