<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CierreCajaTestSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('Iniciando Seeder de prueba para Cierre de Caja...');

        // 1. Obtener la apertura activa más reciente
        $apertura = DB::table('apertura_cierre_caja')
            ->where('estado', 'abierta')
            ->orderBy('fecha_apertura', 'desc')
            ->first();

        if (!$apertura) {
            // Si no hay, crear una para el primer usuario
            $user = DB::table('user')->first();
            $almacen = DB::table('almacen')->first();
            $cajaPrincipal = DB::table('cajas_principales')->first();
            $subCaja = DB::table('sub_cajas')->where('estado', 1)->first();

            if (!$user || !$almacen || !$cajaPrincipal || !$subCaja) {
                $this->command->error('Faltan datos base para crear una apertura.');
                return;
            }

            $aperturaId = (string) Str::ulid();
            DB::table('apertura_cierre_caja')->insert([
                'id' => $aperturaId,
                'caja_principal_id' => $cajaPrincipal->id,
                'sub_caja_id' => $subCaja->id,
                'user_id' => $user->id,
                'monto_apertura' => 1000.00,
                'fecha_apertura' => Carbon::now()->startOfDay()->addHours(8),
                'estado' => 'abierta',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $apertura = DB::table('apertura_cierre_caja')->find($aperturaId);
            $this->command->info('Creada nueva apertura activa.');
        }

        $user = DB::table('user')->where('id', $apertura->user_id)->first();
        $subCaja = DB::table('sub_cajas')->where('id', $apertura->sub_caja_id)->first();
        $almacen = DB::table('almacen')->first();
        $cliente = DB::table('cliente')->where('numero_documento', '99999999')->first() 
                   ?? DB::table('cliente')->first();

        $this->command->info("Usando Apertura: {$apertura->id}");
        $this->command->info("Usuario: {$user->name}");
        $this->command->info("Sub-Caja: {$subCaja->nombre}");


        // 3. Obtener TODOS los Despliegues de Pago Activos
        $despliegues = DB::table('desplieguedepago as dp')
            ->join('metododepago as mp', 'dp.metodo_de_pago_id', '=', 'mp.id')
            ->where('dp.activo', 1)
            ->where('dp.mostrar', 1)
            ->select('dp.id', 'dp.name as despliegue_nombre', 'mp.name as banco_nombre', 'mp.id as metodo_id')
            ->get();

        if ($despliegues->isEmpty()) {
            $this->command->error('No se encontraron métodos de pago activos.');
            return;
        }

        $this->command->info('Generando ventas para ' . $despliegues->count() . ' métodos de pago...');

        // 4. Crear una Venta por cada Despliegue
        foreach ($despliegues as $idx => $dp) {
            $ventaId = (string) Str::ulid();
            $monto = rand(50, 150) + ($idx * 10.5);
            $numero = rand(1000, 99999);

            DB::table('venta')->insert([
                'id' => $ventaId,
                'tipo_documento' => '03',
                'serie' => 'TEST',
                'numero' => $numero,
                'descripcion' => "Venta de prueba: {$dp->banco_nombre} - {$dp->despliegue_nombre}",
                'forma_de_pago' => 'co',
                'tipo_moneda' => 's',
                'fecha' => now(),
                'estado_de_venta' => 'cr',
                'cliente_id' => $cliente->id,
                'user_id' => $user->id,
                'almacen_id' => $almacen->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->registrarPago($ventaId, $dp->id, $monto, $subCaja->id, $user->id, "REF-{$dp->despliegue_nombre}", 'OP-' . rand(1000, 9999));
            $this->command->info("Venta {$idx}: {$dp->banco_nombre} ({$dp->despliegue_nombre}) - S/. " . number_format($monto, 2));
        }

        // 5. Crear una Venta Mixta (Efectivo + primer otro método)
        $efectivo = $despliegues->first(fn($d) => stripos($d->banco_nombre, 'efectivo') !== false);
        $otro = $despliegues->first(fn($d) => stripos($d->banco_nombre, 'efectivo') === false);

        if ($efectivo && $otro) {
            $ventaId = (string) Str::ulid();
            DB::table('venta')->insert([
                'id' => $ventaId,
                'tipo_documento' => '03',
                'serie' => 'TEST',
                'numero' => rand(1000, 99999),
                'descripcion' => "Venta Mixta de prueba",
                'forma_de_pago' => 'co',
                'tipo_moneda' => 's',
                'fecha' => now(),
                'estado_de_venta' => 'cr',
                'cliente_id' => $cliente->id,
                'user_id' => $user->id,
                'almacen_id' => $almacen->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->registrarPago($ventaId, $efectivo->id, 100.00, $subCaja->id, $user->id, "MIXTO-EFE");
            $this->registrarPago($ventaId, $otro->id, 50.00, $subCaja->id, $user->id, "MIXTO-OTRO", 'OP-MIX');
            $this->command->info("Venta Mixta creada (S/. 150.00)");
        }

        // 6. Crear Traslado a Bóveda (usando el primer método de efectivo)
        if ($efectivo) {
            DB::table('traslados_boveda')->insert([
                'id' => (string) Str::ulid(),
                'apertura_cierre_caja_id' => $apertura->id,
                'sub_caja_id' => $subCaja->id,
                'despliegue_pago_id' => $efectivo->id,
                'vendedor_id' => $user->id,
                'supervisor_id' => $user->id,
                'monto' => 75.50,
                'justificacion' => 'Traslado de prueba a bóveda (Sobrante del día)',
                'fecha_traslado' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->command->info('Traslado a Bóveda registrado (S/. 75.50).');
        }

        $this->command->info('Traslado a Bóveda de S/. 40.00 registrado.');
        $this->command->info('Seeder completado con éxito. Ahora puedes revisar el cierre de caja en el sistema.');
    }

    private function registrarPago($ventaId, $despliegueId, $monto, $subCajaId, $userId, $referencia, $numOp = null)
    {
        $numOpId = null;
        if ($numOp) {
            $numOpId = (string) Str::ulid();
            DB::table('numeros_operacion_pago')->insert([
                'id' => $numOpId,
                'venta_id' => $ventaId,
                'despliegue_pago_id' => $despliegueId,
                'numero_operacion' => $numOp,
                'monto' => $monto,
                'sobrecargo_aplicado' => 0,
                'monto_total' => $monto,
                'fecha_operacion' => now(),
                'user_id' => $userId,
            ]);
        }

        DB::table('desplieguedepagoventa')->insert([
            'venta_id' => $ventaId,
            'despliegue_de_pago_id' => $despliegueId,
            'monto' => $monto,
            'numero_operacion_id' => $numOpId,
            'sobrecargo_aplicado' => 0,
            'referencia' => $referencia,
        ]);

        // Transacción de caja
        DB::table('transacciones_caja')->insert([
            'id' => 'txn_' . Str::random(20),
            'sub_caja_id' => $subCajaId,
            'tipo_transaccion' => 'ingreso',
            'monto' => $monto,
            'saldo_anterior' => 0, // No trackeamos saldo exacto en el seeder
            'saldo_nuevo' => $monto,
            'descripcion' => "Cobro de venta {$ventaId}",
            'despliegue_pago_id' => $despliegueId,
            'referencia_id' => $ventaId,
            'referencia_tipo' => 'venta',
            'user_id' => $userId,
            'fecha' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
