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
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            // Eliminar el índice que usa tokenable_id
            $table->dropIndex('personal_access_tokens_tokenable_type_tokenable_id_index');

            // Cambiar tokenable_id de bigint a varchar
            $table->string('tokenable_id', 191)->change();

            // Recrear el índice con el nuevo tipo de dato
            $table->index(['tokenable_type', 'tokenable_id'], 'personal_access_tokens_tokenable_type_tokenable_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            // Eliminar el índice
            $table->dropIndex('personal_access_tokens_tokenable_type_tokenable_id_index');

            // Revertir tokenable_id a bigint
            $table->unsignedBigInteger('tokenable_id')->change();

            // Recrear el índice original
            $table->index(['tokenable_type', 'tokenable_id'], 'personal_access_tokens_tokenable_type_tokenable_id_index');
        });
    }
};
