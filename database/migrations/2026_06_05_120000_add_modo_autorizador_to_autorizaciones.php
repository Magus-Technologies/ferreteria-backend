<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Config: cómo se elige al autorizador
        //  - usuario:    un usuario fijo (autorizador_id) [comportamiento previo]
        //  - cargo:      cualquier usuario de un cargo del organigrama (cargo_autorizador)
        //  - jerarquia:  el cargo padre directo del solicitante en el organigrama
        Schema::table('autorizaciones_config', function (Blueprint $table) {
            $table->enum('tipo_autorizador', ['usuario', 'cargo', 'jerarquia'])
                ->default('usuario')
                ->after('autorizador_id');
            $table->string('cargo_autorizador', 100)
                ->nullable()
                ->after('tipo_autorizador')
                ->comment('codigo de catalogo_cargos cuando tipo_autorizador = cargo');
        });

        // Solicitud: a qué cargo se dirigió (cuando no hay un usuario específico).
        // Permite que cualquier usuario de ese cargo la vea y la autorice.
        Schema::table('solicitudes_autorizacion', function (Blueprint $table) {
            $table->string('cargo_autorizador', 100)
                ->nullable()
                ->after('autorizador_id')
                ->comment('codigo de catalogo_cargos destino de la solicitud');
            $table->index('cargo_autorizador', 'idx_sa_cargo_autorizador');
        });
    }

    public function down(): void
    {
        Schema::table('autorizaciones_config', function (Blueprint $table) {
            $table->dropColumn(['tipo_autorizador', 'cargo_autorizador']);
        });

        Schema::table('solicitudes_autorizacion', function (Blueprint $table) {
            $table->dropIndex('idx_sa_cargo_autorizador');
            $table->dropColumn('cargo_autorizador');
        });
    }
};
