<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_entrega', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 100)->unique();
            $table->json('valor');
            $table->timestamps();
        });

        DB::table('configuracion_entrega')->insert([
            'clave'      => 'roles_entrega_tienda',
            'valor'      => json_encode(['ALMACENERO']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_entrega');
    }
};
