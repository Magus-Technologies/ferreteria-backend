<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->boolean('sunat_auto_send_factura_enabled')->default(false)->after('sunat_modo');
            $table->unsignedTinyInteger('sunat_auto_send_factura_after_days')->default(3)->after('sunat_auto_send_factura_enabled');
            $table->boolean('sunat_auto_send_boleta_enabled')->default(false)->after('sunat_auto_send_factura_after_days');
            $table->unsignedTinyInteger('sunat_auto_send_boleta_after_days')->default(0)->after('sunat_auto_send_boleta_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->dropColumn([
                'sunat_auto_send_factura_enabled',
                'sunat_auto_send_factura_after_days',
                'sunat_auto_send_boleta_enabled',
                'sunat_auto_send_boleta_after_days',
            ]);
        });
    }
};
