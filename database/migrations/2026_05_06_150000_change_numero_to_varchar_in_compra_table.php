<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE compra MODIFY numero VARCHAR(20) DEFAULT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE compra MODIFY numero INT DEFAULT NULL');
    }
};
