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
        Schema::table('metododepago', function (Blueprint $table) {
            $table->decimal('monto_inicial', 9, 2)->default(0)->after('monto')
                ->comment('Monto inicial con el que se registró la cuenta bancaria');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('metododepago', function (Blueprint $table) {
            $table->dropColumn('monto_inicial');
        });
    }
};
