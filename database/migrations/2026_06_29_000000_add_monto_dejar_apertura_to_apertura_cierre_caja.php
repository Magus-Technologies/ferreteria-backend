<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apertura_cierre_caja', function (Blueprint $table) {
            if (!Schema::hasColumn('apertura_cierre_caja', 'monto_dejar_apertura')) {
                $table->decimal('monto_dejar_apertura', 10, 2)
                    ->nullable()
                    ->after('diferencia_total')
                    ->comment('Monto de efectivo que se deja para la próxima apertura de caja');
            }

            if (!Schema::hasColumn('apertura_cierre_caja', 'dejar_apertura_asignado_a')) {
                $table->string('dejar_apertura_asignado_a', 191)
                    ->nullable()
                    ->after('monto_dejar_apertura')
                    ->comment('Usuario al que se ha asignado este efectivo para su apertura');
            }

            // La tabla real de usuarios es `user` (id varchar/ulid), no `users` (bigint).
            $table->foreign('dejar_apertura_asignado_a')
                ->references('id')
                ->on('user')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('apertura_cierre_caja', function (Blueprint $table) {
            $table->dropForeign(['dejar_apertura_asignado_a']);
            $table->dropColumn(['monto_dejar_apertura', 'dejar_apertura_asignado_a']);
        });
    }
};
