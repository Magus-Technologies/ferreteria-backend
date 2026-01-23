<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('desplieguedepago', function (Blueprint $table) {
            $table->string('nombre_titular', 191)->nullable()->after('numero_celular');
        });
    }

    public function down(): void
    {
        Schema::table('desplieguedepago', function (Blueprint $table) {
            $table->dropColumn('nombre_titular');
        });
    }
};
