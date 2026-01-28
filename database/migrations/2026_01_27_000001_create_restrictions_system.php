<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Crea el nuevo sistema de restricciones (lista negra) en lugar de permisos (lista blanca).
     * Por defecto, todos tienen acceso a todo. Solo se guardan las RESTRICCIONES.
     */
    public function up(): void
    {
        // Tabla de restricciones (reemplaza a permission)
        Schema::create("restriction", function (Blueprint $table) {
            $table->id();
            $table->string("name")->unique(); // ej: "venta.create"
            $table->string("descripcion");
            // No necesita timestamps, es data de catálogo
        });

        // Tabla intermedia: restricciones de roles
        Schema::create("_restrictiontorole", function (Blueprint $table) {
            $table->unsignedBigInteger("a"); // restriction_id
            $table->integer("b"); // role_id (int NOT NULL AUTO_INCREMENT)

            $table
                ->foreign("a")
                ->references("id")
                ->on("restriction")
                ->onDelete("cascade");
            $table
                ->foreign("b")
                ->references("id")
                ->on("role")
                ->onDelete("cascade");

            $table->primary(["a", "b"]);
        });

        // Tabla intermedia: restricciones de usuarios
        Schema::create("_restrictiontouser", function (Blueprint $table) {
            $table->unsignedBigInteger("a"); // restriction_id
            $table->string("b", 191); // user_id (varchar, not integer)

            $table
                ->foreign("a")
                ->references("id")
                ->on("restriction")
                ->onDelete("cascade");
            $table
                ->foreign("b")
                ->references("id")
                ->on("user")
                ->onDelete("cascade");

            $table->primary(["a", "b"]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists("_restrictiontouser");
        Schema::dropIfExists("_restrictiontorole");
        Schema::dropIfExists("restriction");
    }
};
