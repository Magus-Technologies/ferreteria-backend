<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `cobroventa.fecha_anulacion` y `pagodecompra.fecha_anulacion` eran DATE, así que
     * las anulaciones perdían la hora y salían a las 12:00 AM en el kardex de finanzas.
     * Se cambian a DATETIME para conservar la hora de la anulación (el código de anular
     * pasa a guardar `now()` con hora). Las anulaciones existentes (sin hora recuperable)
     * quedan como estaban; solo las nuevas tendrán hora real.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE cobroventa MODIFY fecha_anulacion DATETIME NULL');
        DB::statement('ALTER TABLE pagodecompra MODIFY fecha_anulacion DATETIME NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE cobroventa MODIFY fecha_anulacion DATE NULL');
        DB::statement('ALTER TABLE pagodecompra MODIFY fecha_anulacion DATE NULL');
    }
};
