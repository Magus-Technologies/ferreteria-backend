<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagodecompra', function (Blueprint $table) {
            $table->decimal('tipo_de_cambio', 10, 4)->nullable()->after('monto');
        });
    }

    public function down(): void
    {
        Schema::table('pagodecompra', function (Blueprint $table) {
            $table->dropColumn('tipo_de_cambio');
        });
    }
};
