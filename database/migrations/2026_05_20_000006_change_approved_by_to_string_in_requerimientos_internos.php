<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Use raw SQL to change the column type to VARCHAR
        DB::statement('ALTER TABLE requerimientos_internos MODIFY approved_by VARCHAR(255) NULL');
    }

    public function down(): void
    {
        // Revert back to unsignedBigInteger
        DB::statement('ALTER TABLE requerimientos_internos MODIFY approved_by BIGINT UNSIGNED NULL');
    }
};
