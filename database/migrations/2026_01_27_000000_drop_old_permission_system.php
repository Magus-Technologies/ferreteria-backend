<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Elimina el sistema VIEJO de permisos (lista blanca).
     * Se ejecuta ANTES de crear el nuevo sistema de restricciones.
     */
    public function up(): void
    {
        // 1. Eliminar tablas intermedias primero (tienen foreign keys)
        Schema::dropIfExists('_permissiontouser');
        Schema::dropIfExists('_permissiontorole');

        // 2. Eliminar tabla principal
        Schema::dropIfExists('permission');

        // Nota: NO eliminar las tablas de Spatie (permissions, role_has_permissions, etc.)
        // porque pueden estar siendo usadas por otros módulos
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No podemos revertir porque perdimos los datos
        // Este es un cambio destructivo que requiere backup
        throw new Exception('No se puede revertir esta migración. Restaura desde backup si es necesario.');
    }
};
