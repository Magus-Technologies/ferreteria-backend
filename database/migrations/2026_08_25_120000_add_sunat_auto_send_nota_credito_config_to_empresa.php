<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->boolean('sunat_auto_send_nota_credito_enabled')->default(false)->after('sunat_auto_send_boleta_after_days');
            $table->unsignedTinyInteger('sunat_auto_send_nota_credito_after_days')->default(0)->after('sunat_auto_send_nota_credito_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->dropColumn([
                'sunat_auto_send_nota_credito_enabled',
                'sunat_auto_send_nota_credito_after_days',
            ]);
        });
    }
};
