<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // For MySQL, we need to modify the enum to add 'escalar'
        DB::statement("ALTER TABLE approval_history MODIFY action ENUM('pasar','aprobar','rechazar','escalar')");
    }

    public function down(): void
    {
        // Revert to original enum without 'escalar'
        DB::statement("ALTER TABLE approval_history MODIFY action ENUM('pasar','aprobar','rechazar')");
    }
};
