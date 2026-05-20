<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requerimientos_internos', function (Blueprint $table) {
            $table->boolean('afecta_calendario')->default(false)->after('observaciones');
            $table->unsignedBigInteger('assigned_cargo_id')->nullable()->index()->after('cargo');
            $table->enum('approval_state', ['pendiente','en_revision','aprobado','rechazado'])->default('pendiente')->after('assigned_cargo_id');
            $table->unsignedBigInteger('approved_by')->nullable()->index()->after('approval_state');
            $table->dateTime('approved_at')->nullable()->after('approved_by');
            $table->text('approval_note')->nullable()->after('approved_at');

            // FK to catalogo_cargos if exists
            if (Schema::hasTable('catalogo_cargos')) {
                $table->foreign('assigned_cargo_id')->references('id')->on('catalogo_cargos')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('requerimientos_internos', function (Blueprint $table) {
            if (Schema::hasColumn('requerimientos_internos', 'assigned_cargo_id')) {
                $table->dropForeign(['assigned_cargo_id']);
                $table->dropColumn(['assigned_cargo_id']);
            }

            if (Schema::hasColumn('requerimientos_internos', 'afecta_calendario')) {
                $table->dropColumn('afecta_calendario');
            }

            if (Schema::hasColumn('requerimientos_internos', 'approval_state')) {
                $table->dropColumn('approval_state');
            }

            if (Schema::hasColumn('requerimientos_internos', 'approved_by')) {
                $table->dropColumn('approved_by');
            }

            if (Schema::hasColumn('requerimientos_internos', 'approved_at')) {
                $table->dropColumn('approved_at');
            }

            if (Schema::hasColumn('requerimientos_internos', 'approval_note')) {
                $table->dropColumn('approval_note');
            }
        });
    }
};
