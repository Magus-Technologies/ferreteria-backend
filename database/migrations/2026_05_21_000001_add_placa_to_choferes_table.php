<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('choferes', 'placa')) {
            Schema::table('choferes', function (Blueprint $table) {
                $table->string('placa', 20)->nullable()->after('licencia');
            });
        }
    }

    public function down(): void
    {
        Schema::table('choferes', function (Blueprint $table) {
            $table->dropColumn('placa');
        });
    }
};
