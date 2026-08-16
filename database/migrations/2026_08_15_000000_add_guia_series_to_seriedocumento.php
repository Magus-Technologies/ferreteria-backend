<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Agrega tipos de serie para Guía de Remisión (remitente y transportista)
        // al enum de seriedocumento.
        DB::statement(
            "ALTER TABLE seriedocumento MODIFY tipo_documento enum('01','03','nv','in','sa','rc','nd','nc','gr','gt') NOT NULL"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE seriedocumento MODIFY tipo_documento enum('01','03','nv','in','sa','rc','nd','nc') NOT NULL"
        );
    }
};
