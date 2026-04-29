<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Venta;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\ProductoAlmacen;
use App\Models\Almacen;
use App\Models\UnidadDerivadaInmutable;
use App\Models\KardexFacturacion;
use App\Models\User;
use App\Models\SerieDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class KardexFacturacionVentaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Crear datos de prueba
        $this->user = User::factory()->create();
        $this->almacen = Almacen::factory()->create();
        $this->cliente = Cliente::factory()->create([
            'tipo_documento' => 'dni',
            'numero_documento' => '12345678',
        ]);
        
        $this->producto = Producto::factory()->create();
        $this->productoAlmacen = ProductoAlmacen::factory()->create([
            'producto_id' => $this->producto->id,
            'almacen_id' => $this->almacen->id,
            'stock_fraccion' => 100,
            'costo' => 10.50,
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
    }

    /** @test */
    public function it_registers_venta_in_kardex_facturacion_when_created()
    {
        // Arrange
        $ventaData = [
            'tipo_documento' => '03',
            'forma_de_pago' => 'co',
            'tipo_moneda' => 's',
            'fecha' => now()->toDateString(),
            'estado_de_venta' => 'cr',
            'tipo_despacho' => 'et',
            'cliente_id' => $this->cliente->id,
            'user_id' => $this->user->id,
            'almacen_id' => $this->almacen->id,
            'productos_por_almacen' => [
                [
                    'producto_almacen_id' => $this->productoAlmacen->id,
                    'costo' => 10.50,
                    'unidades_derivadas' => [
                        [
                            'unidad_derivada_inmutable_id' => $this->unidadDerivada->id,
                            'unidad_derivada_inmutable_name' => 'UNIDAD',
                            'factor' => 1,
                            'cantidad' => 5,
                            'cantidad_pendiente' => 0,
                            'precio' => 15.00,
                        ],
                    ],
                ],
            ],
            'despliegue_de_pago_ventas' => [],
        ];

        // Act
        $response = $this->actingAs($this->user)
            ->postJson('/api/ventas', $ventaData);

        // Assert
        $response->assertStatus(201);
        
        $venta = Venta::first();
        $this->assertNotNull($venta);
        
        // Verificar que se registró en kardex_facturacion
        $kardexRecords = KardexFacturacion::where('referencia_id', $venta->id)->get();
        
        $this->assertCount(1, $kardexRecords, 'Debe haber 1 registro en kardex_facturacion');
        
        $kardex = $kardexRecords->first();
        $this->assertEquals('venta', $kardex->tipo);
        $this->assertEquals('VENTA', $kardex->movimiento);
        $this->assertEquals($this->producto->id, $kardex->producto_id);
        $this->assertEquals($this->almacen->id, $kardex->almacen_id);
        $this->assertEquals(5, $kardex->cantidad);
        $this->assertEquals(5, $kardex->cantidad_fraccion);
        $this->assertEquals(15.00, $kardex->precio);
        $this->assertEquals(10.50, $kardex->costo);
        $this->assertEquals(0, $kardex->entrada);
        $this->assertEquals(5, $kardex->salida);
        $this->assertStringContainsString('Boleta', $kardex->documento);
    }

    /** @test */
    public function it_does_not_register_venta_en_espera_in_kardex()
    {
        // Arrange
        $ventaData = [
            'tipo_documento' => '03',
            'forma_de_pago' => 'co',
            'tipo_moneda' => 's',
            'fecha' => now()->toDateString(),
            'estado_de_venta' => 'ee', // En espera
            'cliente_id' => $this->cliente->id,
            'user_id' => $this->user->id,
            'almacen_id' => $this->almacen->id,
            'productos_por_almacen' => [
                [
                    'producto_almacen_id' => $this->productoAlmacen->id,
                    'costo' => 10.50,
                    'unidades_derivadas' => [
                        [
                            'unidad_derivada_inmutable_id' => $this->unidadDerivada->id,
                            'unidad_derivada_inmutable_name' => 'UNIDAD',
                            'factor' => 1,
                            'cantidad' => 5,
                            'cantidad_pendiente' => 5,
                            'precio' => 15.00,
                        ],
                    ],
                ],
            ],
            'despliegue_de_pago_ventas' => [],
        ];

        // Act
        $response = $this->actingAs($this->user)
            ->postJson('/api/ventas', $ventaData);

        // Assert
        $response->assertStatus(201);
        
        $venta = Venta::first();
        $this->assertNotNull($venta);
        
        // Verificar que NO se registró en kardex_facturacion
        $kardexCount = KardexFacturacion::where('referencia_id', $venta->id)->count();
        $this->assertEquals(0, $kardexCount, 'No debe haber registros en kardex para ventas en espera');
    }

    /** @test */
    public function it_registers_devolucion_when_venta_is_cancelled()
    {
        // Arrange - Crear una venta primero
        $venta = Venta::factory()->create([
            'tipo_documento' => '03',
            'estado_de_venta' => 'cr',
            'almacen_id' => $this->almacen->id,
            'stock_aplicado' => true,
        ]);
        
        $productoAlmacenVenta = \App\Models\ProductoAlmacenVenta::factory()->create([
            'venta_id' => $venta->id,
            'producto_almacen_id' => $this->productoAlmacen->id,
            'costo' => 10.50,
        ]);
        
        $unidadVenta = \App\Models\UnidadDerivadaInmutableVenta::factory()->create([
            'producto_almacen_venta_id' => $productoAlmacenVenta->id,
            'unidad_derivada_inmutable_id' => $this->unidadDerivada->id,
            'cantidad' => 5,
            'factor' => 1,
            'precio' => 15.00,
        ]);

        // Limpiar kardex antes de anular
        KardexFacturacion::truncate();

        // Act - Anular la venta
        $response = $this->actingAs($this->user)
            ->deleteJson("/api/ventas/{$venta->id}");

        // Assert
        $response->assertStatus(200);
        
        // Verificar que se registró la devolución en kardex_facturacion
        $kardexRecords = KardexFacturacion::where('referencia_id', $venta->id)->get();
        
        $this->assertCount(1, $kardexRecords, 'Debe haber 1 registro de devolución en kardex_facturacion');
        
        $kardex = $kardexRecords->first();
        $this->assertEquals('venta', $kardex->tipo);
        $this->assertEquals('DEVOLUCIÓN', $kardex->movimiento);
        $this->assertEquals(5, $kardex->entrada);
        $this->assertEquals(0, $kardex->salida);
        $this->assertStringContainsString('Anulación', $kardex->documento);
    }
}
