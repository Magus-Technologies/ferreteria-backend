<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CheckTrasladosSeeder extends Seeder
{
    public function run()
    {
        $aperturaId = '01KR6HAERE7RMA51RY9C2C5M8N';
        $count = DB::table('traslados_boveda')->where('apertura_cierre_caja_id', $aperturaId)->count();
        $this->command->info("Traslados para apertura {$aperturaId}: {$count}");
        
        $all = DB::table('traslados_boveda')->get();
        foreach ($all as $t) {
            $this->command->info("Traslado: ID={$t->id}, Apertura={$t->apertura_cierre_caja_id}, Monto={$t->monto}, Fecha={$t->fecha_traslado}");
        }
    }
}
