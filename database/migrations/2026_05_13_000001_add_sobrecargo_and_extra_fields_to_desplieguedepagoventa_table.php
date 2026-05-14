<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('desplieguedepagoventa', function (Blueprint $table) {
            if (!Schema::hasColumn('desplieguedepagoventa', 'sobrecargo_aplicado')) {
                $table->decimal('sobrecargo_aplicado', 15, 4)->nullable()->default(0)->after('monto');
            }

            if (!Schema::hasColumn('desplieguedepagoventa', 'referencia')) {
                $table->string('referencia', 191)->nullable()->after('sobrecargo_aplicado');
            }

            if (!Schema::hasColumn('desplieguedepagoventa', 'recibe_efectivo')) {
                $table->decimal('recibe_efectivo', 15, 4)->nullable()->after('referencia');
            }
        });
    }

    public function down(): void
    {
        Schema::table('desplieguedepagoventa', function (Blueprint $table) {
            $table->dropColumnIfExists('sobrecargo_aplicado');
            $table->dropColumnIfExists('referencia');
            $table->dropColumnIfExists('recibe_efectivo');
        });
    }
};
