<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('INSERT INTO contacto_empresa (empresa_id, cargo, nombre, email, celular, created_at, updated_at)
            SELECT id, "gerente", gerente_nombre, gerente_email, gerente_celular, NOW(), NOW()
            FROM empresa WHERE gerente_nombre IS NOT NULL OR gerente_email IS NOT NULL OR gerente_celular IS NOT NULL');

        DB::statement('INSERT INTO contacto_empresa (empresa_id, cargo, nombre, email, celular, created_at, updated_at)
            SELECT id, "facturacion", facturacion_nombre, facturacion_email, facturacion_celular, NOW(), NOW()
            FROM empresa WHERE facturacion_nombre IS NOT NULL OR facturacion_email IS NOT NULL OR facturacion_celular IS NOT NULL');

        DB::statement('INSERT INTO contacto_empresa (empresa_id, cargo, nombre, email, celular, created_at, updated_at)
            SELECT id, "contabilidad", contabilidad_nombre, contabilidad_email, contabilidad_celular, NOW(), NOW()
            FROM empresa WHERE contabilidad_nombre IS NOT NULL OR contabilidad_email IS NOT NULL OR contabilidad_celular IS NOT NULL');

        DB::statement('INSERT INTO termino_empresa (empresa_id, tipo, contenido, created_at, updated_at)
            SELECT id, "comprobantes_ventas", terminos_comprobantes_ventas, NOW(), NOW()
            FROM empresa WHERE terminos_comprobantes_ventas IS NOT NULL');

        DB::statement('INSERT INTO termino_empresa (empresa_id, tipo, contenido, created_at, updated_at)
            SELECT id, "letras_cambio", terminos_letras_cambio, NOW(), NOW()
            FROM empresa WHERE terminos_letras_cambio IS NOT NULL');

        DB::statement('INSERT INTO termino_empresa (empresa_id, tipo, contenido, created_at, updated_at)
            SELECT id, "guias_remision", terminos_guias_remision, NOW(), NOW()
            FROM empresa WHERE terminos_guias_remision IS NOT NULL');

        DB::statement('INSERT INTO termino_empresa (empresa_id, tipo, contenido, created_at, updated_at)
            SELECT id, "cotizaciones", terminos_cotizaciones, NOW(), NOW()
            FROM empresa WHERE terminos_cotizaciones IS NOT NULL');

        DB::statement('INSERT INTO termino_empresa (empresa_id, tipo, contenido, created_at, updated_at)
            SELECT id, "ordenes_compras", terminos_ordenes_compras, NOW(), NOW()
            FROM empresa WHERE terminos_ordenes_compras IS NOT NULL');

        Schema::table('empresa', function (Blueprint $table) {
            $table->string('sol_user', 50)->nullable()->after('imprimir_impuestos_boleta');
            $table->string('sol_pass', 100)->nullable()->after('sol_user');
            $table->string('sunat_client_id', 100)->nullable()->after('sol_pass');
            $table->string('sunat_secret_client', 255)->nullable()->after('sunat_client_id');
        });

        Schema::table('empresa', function (Blueprint $table) {
            $table->dropColumn([
                'gerente_nombre',
                'gerente_email',
                'gerente_celular',
                'facturacion_nombre',
                'facturacion_email',
                'facturacion_celular',
                'contabilidad_nombre',
                'contabilidad_email',
                'contabilidad_celular',
                'terminos_comprobantes_ventas',
                'terminos_letras_cambio',
                'terminos_guias_remision',
                'terminos_cotizaciones',
                'terminos_ordenes_compras',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->dropColumn(['sol_user', 'sol_pass', 'sunat_client_id', 'sunat_secret_client']);
        });

        Schema::table('empresa', function (Blueprint $table) {
            $table->string('gerente_nombre', 191)->nullable();
            $table->string('gerente_email', 191)->nullable();
            $table->string('gerente_celular', 50)->nullable();
            $table->string('facturacion_nombre', 191)->nullable();
            $table->string('facturacion_email', 191)->nullable();
            $table->string('facturacion_celular', 50)->nullable();
            $table->string('contabilidad_nombre', 191)->nullable();
            $table->string('contabilidad_email', 191)->nullable();
            $table->string('contabilidad_celular', 50)->nullable();
            $table->text('terminos_comprobantes_ventas')->nullable();
            $table->text('terminos_letras_cambio')->nullable();
            $table->text('terminos_guias_remision')->nullable();
            $table->text('terminos_cotizaciones')->nullable();
            $table->text('terminos_ordenes_compras')->nullable();
        });

        DB::statement('UPDATE empresa e
            LEFT JOIN contacto_empresa c ON c.empresa_id = e.id AND c.cargo = "gerente"
            SET e.gerente_nombre = c.nombre, e.gerente_email = c.email, e.gerente_celular = c.celular');

        DB::statement('UPDATE empresa e
            LEFT JOIN contacto_empresa c ON c.empresa_id = e.id AND c.cargo = "facturacion"
            SET e.facturacion_nombre = c.nombre, e.facturacion_email = c.email, e.facturacion_celular = c.celular');

        DB::statement('UPDATE empresa e
            LEFT JOIN contacto_empresa c ON c.empresa_id = e.id AND c.cargo = "contabilidad"
            SET e.contabilidad_nombre = c.nombre, e.contabilidad_email = c.email, e.contabilidad_celular = c.celular');

        DB::statement('UPDATE empresa e
            LEFT JOIN termino_empresa t ON t.empresa_id = e.id AND t.tipo = "comprobantes_ventas"
            SET e.terminos_comprobantes_ventas = t.contenido');

        DB::statement('UPDATE empresa e
            LEFT JOIN termino_empresa t ON t.empresa_id = e.id AND t.tipo = "letras_cambio"
            SET e.terminos_letras_cambio = t.contenido');

        DB::statement('UPDATE empresa e
            LEFT JOIN termino_empresa t ON t.empresa_id = e.id AND t.tipo = "guias_remision"
            SET e.terminos_guias_remision = t.contenido');

        DB::statement('UPDATE empresa e
            LEFT JOIN termino_empresa t ON t.empresa_id = e.id AND t.tipo = "cotizaciones"
            SET e.terminos_cotizaciones = t.contenido');

        DB::statement('UPDATE empresa e
            LEFT JOIN termino_empresa t ON t.empresa_id = e.id AND t.tipo = "ordenes_compras"
            SET e.terminos_ordenes_compras = t.contenido');

        Schema::dropIfExists('termino_empresa');
        Schema::dropIfExists('contacto_empresa');
    }
};
