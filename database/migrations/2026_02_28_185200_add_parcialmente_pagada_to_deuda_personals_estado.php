<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE deuda_personals MODIFY COLUMN estado ENUM('pendiente', 'parcialmente_pagada', 'pagada', 'anulado') DEFAULT 'pendiente'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE deuda_personals MODIFY COLUMN estado ENUM('pendiente', 'pagado', 'anulado') DEFAULT 'pendiente'");
    }
};
