<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('direccion_empresa', function (Blueprint $table) {
            $table->boolean('es_principal')->default(false)->after('empresa_id');
        });

        DB::statement("INSERT INTO direccion_empresa (empresa_id, es_principal, alias, direccion, ubigeo_id, departamento, provincia, distrito, created_at, updated_at)
            SELECT id, 1, 'Principal', direccion, ubigeo_id, departamento, provincia, distrito, NOW(), NOW()
            FROM empresa WHERE direccion IS NOT NULL AND direccion != ''");

        Schema::table('empresa', function (Blueprint $table) {
            $table->dropColumn(['direccion', 'ubigeo_id', 'departamento', 'provincia', 'distrito']);
        });
    }

    public function down(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->string('direccion', 191)->nullable();
            $table->unsignedBigInteger('ubigeo_id')->nullable();
            $table->string('departamento', 100)->nullable();
            $table->string('provincia', 100)->nullable();
            $table->string('distrito', 100)->nullable();
        });

        DB::statement("UPDATE empresa e
            JOIN direccion_empresa d ON d.empresa_id = e.id AND d.es_principal = 1
            SET e.direccion = d.direccion, e.ubigeo_id = d.ubigeo_id,
                e.departamento = d.departamento, e.provincia = d.provincia, e.distrito = d.distrito");

        Schema::table('direccion_empresa', function (Blueprint $table) {
            $table->dropColumn('es_principal');
        });
    }
};
