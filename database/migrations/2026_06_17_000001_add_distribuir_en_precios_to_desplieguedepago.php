<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('desplieguedepago', function (Blueprint $table) {
            $table->boolean('distribuir_en_precios')->default(false)->after('tipo_sobrecargo');
        });

        // Izipay ya distribuía en precios (era el comportamiento hardcodeado)
        DB::table('desplieguedepago')
            ->whereRaw("LOWER(name) REGEXP 'iz[iy]pay'")
            ->update(['distribuir_en_precios' => true]);
    }

    public function down(): void
    {
        Schema::table('desplieguedepago', function (Blueprint $table) {
            $table->dropColumn('distribuir_en_precios');
        });
    }
};
