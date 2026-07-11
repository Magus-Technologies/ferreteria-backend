<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagodecompra', function ($table) {
            $table->date('fecha_pago_referencial')->nullable()->after('fecha');
        });
    }

    public function down(): void
    {
        Schema::table('pagodecompra', function ($table) {
            $table->dropColumn('fecha_pago_referencial');
        });
    }
};
