<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('almacen', function (Blueprint $table) {
            $table->string('direccion', 500)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('almacen', function (Blueprint $table) {
            $table->dropColumn('direccion');
        });
    }
};
