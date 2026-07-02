<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE pagodecompra MODIFY COLUMN fecha DATETIME(3) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE pagodecompra MODIFY COLUMN fecha DATE NULL');
    }
};
