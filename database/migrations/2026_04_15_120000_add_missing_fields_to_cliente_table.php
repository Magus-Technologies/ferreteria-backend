<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cliente', function (Blueprint $table) {
            if (!Schema::hasColumn('cliente', 'celular')) {
                $table->string('celular', 20)->nullable()->after('telefono');
            }
            if (!Schema::hasColumn('cliente', 'horario_atencion')) {
                $table->string('horario_atencion', 191)->nullable()->after('celular');
            }
            if (!Schema::hasColumn('cliente', 'fecha_nacimiento')) {
                $table->date('fecha_nacimiento')->nullable()->after('email');
            }
            if (!Schema::hasColumn('cliente', 'puntos')) {
                $table->integer('puntos')->default(0)->after('fecha_nacimiento');
            }
            if (!Schema::hasColumn('cliente', 'centimos')) {
                $table->decimal('centimos', 10, 2)->default(0)->after('puntos');
            }
            if (!Schema::hasColumn('cliente', 'contacto_referencia')) {
                $table->string('contacto_referencia', 191)->nullable()->after('centimos');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cliente', function (Blueprint $table) {
            $table->dropColumn([
                'celular',
                'horario_atencion',
                'fecha_nacimiento',
                'puntos',
                'centimos',
                'contacto_referencia',
            ]);
        });
    }
};
