<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role', function (Blueprint $table) {
            $table->string('rol_sistema', 50)->nullable()->after('descripcion');
        });

        DB::table('role')->where('name', 'admin_global')->update(['rol_sistema' => 'ADMINISTRADOR']);
        DB::table('role')->where('name', 'vendedor')->update(['rol_sistema' => 'VENDEDOR']);
        DB::table('role')->where('name', 'almacenero')->update(['rol_sistema' => 'ALMACENERO']);
        DB::table('role')->where('name', 'contador')->update(['rol_sistema' => 'CONTADOR']);
        DB::table('role')->where('name', 'despachador')->update(['rol_sistema' => 'DESPACHADOR']);
    }

    public function down(): void
    {
        Schema::table('role', function (Blueprint $table) {
            $table->dropColumn('rol_sistema');
        });
    }
};
