<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guia_remision', function (Blueprint $table) {
            $table->string('sunat_xml_path', 255)->nullable()->after('sunat_codigo_hash');
            $table->string('sunat_cdr_path', 255)->nullable()->after('sunat_cdr_xml');
            $table->longText('sunat_codigo_qr')->nullable()->after('sunat_cdr_path');
        });
    }

    public function down(): void
    {
        Schema::table('guia_remision', function (Blueprint $table) {
            $table->dropColumn(['sunat_xml_path', 'sunat_cdr_path', 'sunat_codigo_qr']);
        });
    }
};
