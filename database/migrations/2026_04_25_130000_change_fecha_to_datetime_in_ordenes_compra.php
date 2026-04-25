<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE ordenes_compra MODIFY COLUMN fecha DATETIME(3) NOT NULL');
        DB::statement('ALTER TABLE ordenes_compra MODIFY COLUMN fecha_vencimiento DATETIME(3) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ordenes_compra MODIFY COLUMN fecha DATE NOT NULL');
        DB::statement('ALTER TABLE ordenes_compra MODIFY COLUMN fecha_vencimiento DATE NULL');
    }
};
