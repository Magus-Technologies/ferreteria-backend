<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `cobroventa.fecha` era DATE, así que truncaba la hora del cobro (se mostraba
     * 12:00 AM en kardex de finanzas, ventas por cobrar, etc.). Se cambia a DATETIME
     * para guardar la hora real (el front y el controller ya envían la hora; solo se
     * perdía por el tipo de columna) y se recupera la hora de los cobros existentes
     * desde el ULID del id, que codifica el instante de creación.
     */
    public function up(): void
    {
        // 1. Cambiar el tipo de columna a DATETIME (conserva NOT NULL).
        DB::statement('ALTER TABLE cobroventa MODIFY fecha DATETIME NOT NULL');

        // 2. Backfill de la hora perdida en los cobros existentes: se decodifica el
        //    timestamp (ms UTC) embebido en el ULID y se convierte a hora de Perú.
        //    Solo se rellena cuando el día del ULID coincide con el día de `fecha`,
        //    para no alterar cobros cuya fecha elegida difiere del día de creación.
        $rows = DB::table('cobroventa')->select('id', 'fecha')->get();
        foreach ($rows as $row) {
            $ms = $this->ulidToMs((string) $row->id);
            if ($ms === null) {
                continue;
            }

            $dtLima = \Carbon\Carbon::createFromTimestampMs($ms, 'UTC')->setTimezone('America/Lima');
            $fechaDia = \Carbon\Carbon::parse($row->fecha)->format('Y-m-d');

            if ($dtLima->format('Y-m-d') === $fechaDia) {
                DB::table('cobroventa')->where('id', $row->id)->update([
                    'fecha' => $dtLima->format('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE cobroventa MODIFY fecha DATE NOT NULL');
    }

    /**
     * Decodifica los milisegundos UTC del timestamp embebido en un ULID
     * (primeros 10 chars, Crockford base32).
     */
    private function ulidToMs(string $ulid): ?int
    {
        if (strlen($ulid) < 10) {
            return null;
        }

        $enc = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $time = strtoupper(substr($ulid, 0, 10));
        $ms = 0;
        for ($i = 0; $i < 10; $i++) {
            $idx = strpos($enc, $time[$i]);
            if ($idx === false) {
                return null;
            }
            $ms = $ms * 32 + $idx;
        }

        return $ms;
    }
};
