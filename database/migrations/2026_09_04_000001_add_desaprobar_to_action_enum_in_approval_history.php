<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE approval_history MODIFY action ENUM('pasar','aprobar','rechazar','escalar','reasignar','desaprobar')");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE approval_history MODIFY action ENUM('pasar','aprobar','rechazar','escalar','reasignar')");
    }
};
