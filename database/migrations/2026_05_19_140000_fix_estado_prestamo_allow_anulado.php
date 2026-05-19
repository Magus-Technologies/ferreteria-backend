<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * La columna `estado_prestamo` venía definida como ENUM en la BD y no
     * incluía el valor 'anulado', por lo que al anular un préstamo MySQL
     * lanzaba: "Data truncated for column 'estado_prestamo'".
     *
     * Se convierte a VARCHAR(20) para que coincida con el tipo de la
     * variable usada en los triggers (v_nuevo_estado VARCHAR(20)) y para
     * eliminar de raíz el truncamiento ante futuros estados. Convertir
     * ENUM -> VARCHAR conserva todos los datos existentes.
     */
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE `prestamos` MODIFY COLUMN `estado_prestamo` VARCHAR(20) NOT NULL DEFAULT 'pendiente'"
        );
    }

    public function down(): void
    {
        // Revertir al ENUM de estados conocidos. Si existen filas con
        // estado 'anulado', primero se normalizan para no perder la fila.
        DB::statement("UPDATE `prestamos` SET `estado_prestamo` = 'vencido' WHERE `estado_prestamo` = 'anulado'");
        DB::statement(
            "ALTER TABLE `prestamos` MODIFY COLUMN `estado_prestamo` ENUM('pendiente','pagado_parcial','pagado_total','vencido') NOT NULL DEFAULT 'pendiente'"
        );
    }
};
