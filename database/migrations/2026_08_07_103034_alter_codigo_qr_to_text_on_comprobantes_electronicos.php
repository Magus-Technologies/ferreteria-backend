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
        Schema::table('comprobantes_electronicos', function (Blueprint $table) {
            // El QR se guarda como data URI base64 de un PNG 200px (~1400 chars),
            // no entraba en varchar(500) y rompía la generación del comprobante.
            $table->text('codigo_qr')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comprobantes_electronicos', function (Blueprint $table) {
            $table->string('codigo_qr', 500)->nullable()->change();
        });
    }
};
