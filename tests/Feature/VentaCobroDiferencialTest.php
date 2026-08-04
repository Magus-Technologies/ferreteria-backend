<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Venta;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\ProductoAlmacen;
use App\Models\Almacen;
use App\Models\UnidadDerivadaInmutable;
use App\Models\User;
use App\Models\SerieDocumento;
use App\Models\DespliegueDePagoVenta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * Verifica el modelo "cobro diferencial": al editar una venta al contado
 * ya cobrada, solo se cobra/devuelve la diferencia contra lo ya cobrado —
 * nunca se vuelve a pedir el total completo. Ver memoria
 * ventas_modelo_cobro_diferencial.md.
 */
class VentaCobroDiferencialTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $almacen;
    protected $cliente;
    protected $productoAlmacen;
    protected $unidadDerivada;
    protected $efectivoId;
    protected $tarjetaId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->almacen = Almacen::factory()->create();
        $this->cliente = Cliente::factory()->create([
            'tipo_documento' => 'dni',
            'numero_documento' => '12345678',
        ]);

        $producto = Producto::factory()->create();
        $this->productoAlmacen = ProductoAlmacen::factory()->create([
            'producto_id' => $producto->id,
            'almacen_id' => $this->almacen->id,
            'stock_fraccion' => 1000,
            'costo' => 5,
        ]);

        $this->unidadDerivada = UnidadDerivadaInmutable::factory()->create([
            'name' => 'UNIDAD',
        ]);

        SerieDocumento::factory()->create([
            'tipo_documento' => '03',
            'almacen_id' => $this->almacen->id,
            'serie' => 'B001',
            'correlativo' => 100,
            'activo' => true,
        ]);

        // Métodos de pago mínimos: Efectivo y Tarjeta, cada uno con su
        // metododepago (no hay factories para estas tablas legacy).
        $metodoEfectivo = (string) Str::ulid();
        DB::table('metododepago')->insert(['id' => $metodoEfectivo, 'name' => 'Caja Principal', 'monto' => 0, 'monto_inicial' => 0, 'activo' => 1]);
        $this->efectivoId = (string) Str::ulid();
        DB::table('desplieguedepago')->insert([
            'id' => $this->efectivoId, 'name' => 'Efectivo', 'metodo_de_pago_id' => $metodoEfectivo,
            'adicional' => 0, 'mostrar' => 1, 'activo' => 1, 'requiere_numero_serie' => 0,
            'sobrecargo_porcentaje' => 0, 'tipo_sobrecargo' => 'ninguno', 'distribuir_en_precios' => 0,
        ]);

        $metodoTarjeta = (string) Str::ulid();
        DB::table('metododepago')->insert(['id' => $metodoTarjeta, 'name' => 'Banco', 'monto' => 0, 'monto_inicial' => 0, 'activo' => 1]);
        $this->tarjetaId = (string) Str::ulid();
        DB::table('desplieguedepago')->insert([
            'id' => $this->tarjetaId, 'name' => 'Tarjeta', 'metodo_de_pago_id' => $metodoTarjeta,
            'adicional' => 0, 'mostrar' => 1, 'activo' => 1, 'requiere_numero_serie' => 0,
            'sobrecargo_porcentaje' => 0, 'tipo_sobrecargo' => 'ninguno', 'distribuir_en_precios' => 0,
        ]);
    }

    private function productosPayload(float $cantidad, float $precio): array
    {
        return [[
            'producto_almacen_id' => $this->productoAlmacen->id,
            'costo' => 5,
            'unidades_derivadas' => [[
                'unidad_derivada_inmutable_id' => $this->unidadDerivada->id,
                'unidad_derivada_inmutable_name' => 'UNIDAD',
                'factor' => 1,
                'cantidad' => $cantidad,
                'cantidad_pendiente' => 0,
                'precio' => $precio,
            ]],
        ]];
    }

    private function ventaBasePayload(): array
    {
        return [
            'tipo_documento' => '03',
            'forma_de_pago' => 'co',
            'tipo_moneda' => 's',
            'fecha' => now()->toDateString(),
            'estado_de_venta' => 'cr',
            'tipo_despacho' => 'et',
            'cliente_id' => $this->cliente->id,
            'user_id' => $this->user->id,
            'almacen_id' => $this->almacen->id,
        ];
    }

    /** @test */
    public function full_diferencial_flow_cobro_y_devolucion()
    {
        // 1) Crear venta de 5 x S/10 = S/50, cobrada en efectivo al crear.
        $create = $this->actingAs($this->user)->postJson('/api/ventas', $this->ventaBasePayload() + [
            'productos_por_almacen' => $this->productosPayload(5, 10),
            'despliegue_de_pago_ventas' => [
                ['despliegue_de_pago_id' => $this->efectivoId, 'monto' => 50],
            ],
        ]);
        $create->assertStatus(201);
        $ventaId = $create->json('data.id');

        $filas = DespliegueDePagoVenta::where('venta_id', $ventaId)->get();
        $this->assertCount(1, $filas);
        $this->assertSame('inicial', $filas[0]->tipo);
        $this->assertEquals(50, (float) $filas[0]->monto);

        // 2) Editar subiendo a 8 unidades → total S/80. NO se envían métodos
        // de pago: la edición debe guardarse sin volver a registrar caja.
        $update1 = $this->actingAs($this->user)->putJson("/api/ventas/{$ventaId}", $this->ventaBasePayload() + [
            'productos_por_almacen' => $this->productosPayload(8, 10),
        ]);
        $update1->assertStatus(200);

        $this->assertCount(1, DespliegueDePagoVenta::where('venta_id', $ventaId)->get(), 'La edición sin pagos no debe tocar desplieguedepagoventa');

        // 2b) Si el frontend igual reenviara despliegue_de_pago_ventas (caso
        // que ya no debe ocurrir), el backend debe rechazarlo — es la causa
        // raíz del bug de duplicación en caja.
        $updateRechazado = $this->actingAs($this->user)->putJson("/api/ventas/{$ventaId}", $this->ventaBasePayload() + [
            'productos_por_almacen' => $this->productosPayload(8, 10),
            'despliegue_de_pago_ventas' => [
                ['despliegue_de_pago_id' => $this->efectivoId, 'monto' => 80],
            ],
        ]);
        $updateRechazado->assertStatus(422);
        $updateRechazado->assertJsonPath('error', 'VENTA_YA_COBRADA');

        // 3) Cobrar la diferencia (S/30). Un monto incorrecto debe rechazarse.
        $malMonto = $this->actingAs($this->user)->postJson("/api/ventas/{$ventaId}/cobrar-diferencia", [
            'despliegue_de_pago_ventas' => [
                ['despliegue_de_pago_id' => $this->efectivoId, 'monto' => 25],
            ],
            'user_id' => $this->user->id,
        ]);
        $malMonto->assertStatus(422);

        $cobroDiferencia = $this->actingAs($this->user)->postJson("/api/ventas/{$ventaId}/cobrar-diferencia", [
            'despliegue_de_pago_ventas' => [
                ['despliegue_de_pago_id' => $this->efectivoId, 'monto' => 30],
            ],
            'user_id' => $this->user->id,
        ]);
        $cobroDiferencia->assertStatus(201);
        $cobroDiferencia->assertJsonPath('diferencia_cobrada', 30);

        $filas = DespliegueDePagoVenta::where('venta_id', $ventaId)->orderBy('id')->get();
        $this->assertCount(2, $filas);
        $this->assertSame('diferencia', $filas[1]->tipo);
        $this->assertEquals(30, (float) $filas[1]->monto);
        $this->assertEquals(80, (float) $filas->sum('monto'), 'Suma neta debe ser 80 (50 inicial + 30 diferencia)');

        // No debe poder volver a cobrar diferencia si ya no queda ninguna.
        $sinDiferencia = $this->actingAs($this->user)->postJson("/api/ventas/{$ventaId}/cobrar-diferencia", [
            'despliegue_de_pago_ventas' => [
                ['despliegue_de_pago_id' => $this->efectivoId, 'monto' => 1],
            ],
            'user_id' => $this->user->id,
        ]);
        $sinDiferencia->assertStatus(422);

        // 4) Editar bajando a 3 unidades → total S/30. Ahora hay que devolver S/50.
        $update2 = $this->actingAs($this->user)->putJson("/api/ventas/{$ventaId}", $this->ventaBasePayload() + [
            'productos_por_almacen' => $this->productosPayload(3, 10),
        ]);
        $update2->assertStatus(200);

        // 5) Devolución por un método NO usado en la venta → rechazado.
        $devolucionMetodoInvalido = $this->actingAs($this->user)->postJson("/api/ventas/{$ventaId}/devolver-diferencia", [
            'despliegue_de_pago_ventas' => [
                ['despliegue_de_pago_id' => $this->tarjetaId, 'monto' => 50],
            ],
            'user_id' => $this->user->id,
        ]);
        $devolucionMetodoInvalido->assertStatus(422);

        // Devolución por el método correcto (efectivo, el mismo con el que se cobró).
        $devolucion = $this->actingAs($this->user)->postJson("/api/ventas/{$ventaId}/devolver-diferencia", [
            'despliegue_de_pago_ventas' => [
                ['despliegue_de_pago_id' => $this->efectivoId, 'monto' => 50],
            ],
            'user_id' => $this->user->id,
        ]);
        $devolucion->assertStatus(201);
        $devolucion->assertJsonPath('monto_devuelto', 50);

        $filas = DespliegueDePagoVenta::where('venta_id', $ventaId)->orderBy('id')->get();
        $this->assertCount(3, $filas);
        $this->assertSame('devolucion', $filas[2]->tipo);
        $this->assertEquals(-50, (float) $filas[2]->monto, 'La devolución se guarda con monto negativo');
        $this->assertEquals(30, (float) $filas->sum('monto'), 'Suma neta debe ser 30 (50 + 30 - 50)');

        // 6) Historial: debe traer ediciones (venta_historial) y cobros
        // (las 3 filas de desplieguedepagoventa: inicial, diferencia, devolución).
        $historial = $this->actingAs($this->user)->getJson("/api/ventas/{$ventaId}/historial");
        $historial->assertStatus(200);
        // Solo update1 y update2 escriben historial (updateRechazado fue 422, no llegó a VentaHistorial::registrar).
        $historial->assertJsonCount(2, 'data.ediciones');
        $historial->assertJsonCount(3, 'data.cobros');
        $tipos = collect($historial->json('data.cobros'))->pluck('tipo')->all();
        $this->assertEquals(['inicial', 'diferencia', 'devolucion'], $tipos);
    }
}
