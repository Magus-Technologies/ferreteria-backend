<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\NotaCredito;
use App\Models\NotaDebito;
use App\Models\MotivoNota;
use App\Models\User;
use App\Models\Empresa;
use App\Models\Venta;
use App\Models\ComprobanteElectronico;
use Carbon\Carbon;
use Illuminate\Support\Str;

class NotaCreditoDebitoTestSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        if (!$user) {
            $this->command->error('No hay usuarios en la base de datos');
            return;
        }

        $empresa = Empresa::first();

        $motivosNC = MotivoNota::where('tipo', 'NC')->where('estado', 1)->get();
        $motivosND = MotivoNota::where('tipo', 'ND')->where('estado', 1)->get();

        if ($motivosNC->isEmpty()) {
            $this->command->warn('No hay motivos de nota de crédito activos - creando motivos de prueba');
            $motivosNC = $this->crearMotivos('NC');
        }

        if ($motivosND->isEmpty()) {
            $this->command->warn('No hay motivos de nota de débito activos - creando motivos de prueba');
            $motivosND = $this->crearMotivos('ND');
        }

        $almacenId = 1;

        $this->command->info('Creando notas de crédito de prueba...');
        $this->crearNotasCredito($user, $empresa, $motivosNC, $almacenId);

        $this->command->info('Creando notas de débito de prueba...');
        $this->crearNotasDebito($user, $empresa, $motivosND, $almacenId);

        $this->command->info("✓ Seeder completado");
    }

    private function crearMotivos(string $tipo): \Illuminate\Database\Eloquent\Collection
    {
        $motivosData = $tipo === 'NC'
            ? [
                ['codigo_sunat' => '01', 'descripcion' => 'Anulación de la operación'],
                ['codigo_sunat' => '02', 'descripcion' => 'Anulación del comprobante'],
                ['codigo_sunat' => '03', 'descripcion' => 'Corrección por error en el RUC'],
                ['codigo_sunat' => '04', 'descripcion' => 'Descuento global'],
                ['codigo_sunat' => '05', 'descripcion' => 'Descuento por ítem'],
                ['codigo_sunat' => '06', 'descripcion' => 'Devolución por ítem'],
                ['codigo_sunat' => '07', 'descripcion' => 'Devolución total'],
                ['codigo_sunat' => '08', 'descripcion' => 'Bonificación'],
                ['codigo_sunat' => '09', 'descripcion' => 'Ajuste por cambio de precio'],
                ['codigo_sunat' => '10', 'descripcion' => 'Otros'],
            ]
            : [
                ['codigo_sunat' => '01', 'descripcion' => 'Interés por mora'],
                ['codigo_sunat' => '02', 'descripcion' => 'Ajuste de precio'],
                ['codigo_sunat' => '03', 'descripcion' => 'Cargo adicional por gastos'],
                ['codigo_sunat' => '04', 'descripcion' => 'Corrección del documento'],
                ['codigo_sunat' => '05', 'descripcion' => 'Otros'],
            ];

        $motivos = collect();
        foreach ($motivosData as $data) {
            $motivos->add(MotivoNota::create([
                'codigo_sunat' => $data['codigo_sunat'],
                'descripcion' => $data['descripcion'],
                'tipo' => $tipo,
                'estado' => 1,
            ]));
        }

        return $motivos;
    }

    private function crearNotasCredito($user, $empresa, $motivosNC, $almacenId): void
    {
        $comprobantesRef = ComprobanteElectronico::where('tipo_comprobante', '01')
            ->where('estado_sunat', 'ACEPTADO')
            ->limit(10)
            ->get();

        if ($comprobantesRef->isEmpty()) {
            $comprobantesRef = $this->crearComprobantesReferencia($user);
        }

        $fechas = [
            Carbon::now()->subDays(30),
            Carbon::now()->subDays(15),
            Carbon::now()->subDays(7),
            Carbon::now()->subDays(3),
            Carbon::now()->subDays(1),
            Carbon::now(),
        ];

        $estados = ['borrador', 'pendiente', 'aceptado'];
        $venta = \App\Models\Venta::first();

        foreach ($fechas as $index => $fecha) {
            $motivo = $motivosNC->random();
            $compRef = $comprobantesRef->random();
            $estado = $estados[array_rand($estados)];

            $subtotal = rand(50, 500) + (rand(0, 99) / 100);
            $igv = $subtotal * 0.18;
            $total = $subtotal + $igv;

            $lastNC = NotaCredito::where('serie', 'FC')->orderBy('numero', 'desc')->first();
            $nextNum = $lastNC ? $lastNC->numero + 1 : 1;
            $numero = str_pad($nextNum, 8, '0', STR_PAD_LEFT);

            $venta = \App\Models\Venta::first();
            $notaId = (string) Str::ulid();

            NotaCredito::create([
                'id' => $notaId,
                'tipo_documento' => 'nc',
                'serie' => 'FC',
                'numero' => $nextNum,
                'venta_id' => $venta ? $venta->id : null,
                'comprobante_id_referencia' => $compRef->id,
                'motivo_id' => $motivo->id,
                'descripcion' => $motivo->descripcion,
                'monto_total' => round($total, 2),
                'monto_igv' => round($igv, 2),
                'monto_subtotal' => round($subtotal, 2),
                'referencia_documento' => $compRef->numero_completo ?? ($compRef->serie . '-' . str_pad($compRef->correlativo, 8, '0', STR_PAD_LEFT)),
                'fecha' => $fecha,
                'estado' => $estado,
                'usuario_id' => $user->id,
                'almacen_id' => $almacenId,
                'observaciones' => "Nota de crédito de prueba - {$motivo->descripcion}",
            ]);

            $this->command->info("  ✓ NC FC-{$numero} - {$motivo->descripcion} - Estado: {$estado}");
        }
    }

    private function crearNotasDebito($user, $empresa, $motivosND, $almacenId): void
    {
        $comprobantesRef = ComprobanteElectronico::where('tipo_comprobante', '01')
            ->where('estado_sunat', 'ACEPTADO')
            ->limit(10)
            ->get();

        if ($comprobantesRef->isEmpty()) {
            $comprobantesRef = $this->crearComprobantesReferencia($user);
        }

        $fechas = [
            Carbon::now()->subDays(28),
            Carbon::now()->subDays(14),
            Carbon::now()->subDays(6),
            Carbon::now()->subDays(2),
            Carbon::now(),
        ];

        $estados = ['borrador', 'pendiente', 'aceptado'];
        $venta = \App\Models\Venta::first();

        foreach ($fechas as $index => $fecha) {
            $motivo = $motivosND->random();
            $compRef = $comprobantesRef->random();
            $estado = $estados[array_rand($estados)];

            $subtotal = rand(20, 200) + (rand(0, 99) / 100);
            $igv = $subtotal * 0.18;
            $total = $subtotal + $igv;

            $lastND = NotaDebito::where('serie', 'FD')->orderBy('numero', 'desc')->first();
            $nextNum = $lastND ? $lastND->numero + 1 : 1;
            $numero = str_pad($nextNum, 8, '0', STR_PAD_LEFT);

            $notaId = (string) Str::ulid();

            NotaDebito::create([
                'id' => $notaId,
                'tipo_documento' => 'nd',
                'serie' => 'FD',
                'numero' => $nextNum,
                'venta_id' => $venta ? $venta->id : null,
                'comprobante_id_referencia' => $compRef->id,
                'motivo_id' => $motivo->id,
                'descripcion' => $motivo->descripcion,
                'monto_total' => round($total, 2),
                'monto_igv' => round($igv, 2),
                'monto_subtotal' => round($subtotal, 2),
                'referencia_documento' => $compRef->numero_completo ?? ($compRef->serie . '-' . str_pad($compRef->correlativo, 8, '0', STR_PAD_LEFT)),
                'fecha' => $fecha,
                'estado' => $estado,
                'usuario_id' => $user->id,
                'almacen_id' => $almacenId,
                'observaciones' => "Nota de débito de prueba - {$motivo->descripcion}",
            ]);

            $this->command->info("  ✓ ND FD-{$numero} - {$motivo->descripcion} - Estado: {$estado}");
        }
    }

    private function crearComprobantesReferencia($user)
    {
        $this->command->warn('  No hay comprobantes de referencia - creando 5 de prueba...');

        $cliente = \App\Models\Cliente::first();
        if (!$cliente) {
            $cliente = \App\Models\Cliente::create([
                'name' => 'CLIENTE PRUEBA',
                'numero_documento' => '20456789012',
                'tipo_documento' => '6',
                'razon_social' => 'CLIENTE PRUEBA SA',
                'direccion' => 'AV. EJEMPLO 123 - LIMA',
            ]);
        }

        $comprobantes = collect();

        for ($i = 1; $i <= 5; $i++) {
            $correlativo = $i;
            $serie = 'F001';
            $numeroCompleto = "{$serie}-" . str_pad($correlativo, 8, '0', STR_PAD_LEFT);
            $subtotal = rand(100, 1000);
            $igv = round($subtotal * 0.18, 2);
            $total = $subtotal + $igv;

            $comp = ComprobanteElectronico::create([
                'user_id' => $user->id,
                'venta_id' => null,
                'cliente_id' => $cliente->id,
                'tipo_comprobante' => '01',
                'serie' => $serie,
                'correlativo' => $correlativo,
                'estado_sunat' => 'ACEPTADO',
                'fecha_emision' => Carbon::now()->subDays(rand(1, 30)),
                'fecha_vencimiento' => Carbon::now()->addDays(30),
                'cliente_tipo_documento' => '6',
                'cliente_numero_documento' => '20456789012',
                'cliente_razon_social' => 'CLIENTE PRUEBA ' . $i,
                'cliente_direccion' => 'AV. EJEMPLO 123 - LIMA',
                'moneda' => 'PEN',
                'tipo_cambio' => 1.0000,
                'operacion_gravada' => $subtotal,
                'operacion_exonerada' => 0,
                'operacion_inafecta' => 0,
                'operacion_gratuita' => 0,
                'total_igv' => $igv,
                'total_isc' => 0,
                'total_otros_tributos' => 0,
                'total_descuentos' => 0,
                'total_cargos' => 0,
                'total_anticipos' => 0,
                'importe_total' => $total,
                'monto_en_letras' => 'CIENTO VEINTICINCO CON 00/100 SOLES',
                'forma_pago' => 'CONTADO',
            ]);
            $comprobantes->add($comp);
        }

        return $comprobantes;
    }
}