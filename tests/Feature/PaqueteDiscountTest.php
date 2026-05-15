<?php

namespace Tests\Feature;

use App\Models\Paquete;
use App\Models\PaqueteProducto;
use App\Models\Producto;
use App\Models\UnidadDerivada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaqueteDiscountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Test que el endpoint index() retorna todos los descuentos
     */
    public function test_index_returns_all_discount_fields()
    {
        // Crear un paquete con productos que tengan descuentos
        $paquete = Paquete::factory()->create([
            'nombre' => 'Test Paquete',
            'activo' => true,
        ]);

        // Obtener un producto existente
        $producto = Producto::first();
        $unidadDerivada = UnidadDerivada::first();

        if (!$producto || !$unidadDerivada) {
            $this->markTestSkipped('No hay productos o unidades derivadas en la base de datos');
        }

        // Crear un producto de paquete con descuentos
        PaqueteProducto::create([
            'paquete_id' => $paquete->id,
            'producto_id' => $producto->id,
            'unidad_derivada_id' => $unidadDerivada->id,
            'cantidad' => 1,
            'tipo_precio' => 'publico',
            'precio_publico' => 100.00,
            'precio_especial' => 95.00,
            'precio_minimo' => 90.00,
            'precio_ultimo' => 85.00,
            'descuento_publico' => 10.00,
            'descuento_especial' => 5.00,
            'descuento_minimo' => 0.00,
            'descuento_ultimo' => 0.00,
        ]);

        // Llamar al endpoint index
        $response = $this->getJson('/api/paquetes?activo=true&per_page=50');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'nombre',
                    'productos' => [
                        '*' => [
                            'id',
                            'paquete_id',
                            'producto_id',
                            'cantidad',
                            'tipo_precio',
                            'precio_publico',
                            'precio_especial',
                            'precio_minimo',
                            'precio_ultimo',
                            'descuento_publico',
                            'descuento_especial',
                            'descuento_minimo',
                            'descuento_ultimo',
                        ]
                    ]
                ]
            ]
        ]);

        // Verificar que los descuentos están presentes
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        
        $paqueteData = collect($data)->firstWhere('id', $paquete->id);
        $this->assertNotNull($paqueteData);
        
        $productoData = $paqueteData['productos'][0];
        $this->assertEquals(10.00, $productoData['descuento_publico']);
        $this->assertEquals(5.00, $productoData['descuento_especial']);
        $this->assertEquals(0.00, $productoData['descuento_minimo']);
        $this->assertEquals(0.00, $productoData['descuento_ultimo']);
    }

    /**
     * Test que el endpoint show() retorna todos los descuentos
     */
    public function test_show_returns_all_discount_fields()
    {
        $paquete = Paquete::factory()->create([
            'nombre' => 'Test Paquete Show',
            'activo' => true,
        ]);

        $producto = Producto::first();
        $unidadDerivada = UnidadDerivada::first();

        if (!$producto || !$unidadDerivada) {
            $this->markTestSkipped('No hay productos o unidades derivadas en la base de datos');
        }

        PaqueteProducto::create([
            'paquete_id' => $paquete->id,
            'producto_id' => $producto->id,
            'unidad_derivada_id' => $unidadDerivada->id,
            'cantidad' => 2,
            'tipo_precio' => 'especial',
            'precio_publico' => 100.00,
            'precio_especial' => 95.00,
            'precio_minimo' => 90.00,
            'precio_ultimo' => 85.00,
            'descuento_publico' => 15.00,
            'descuento_especial' => 8.00,
            'descuento_minimo' => 2.00,
            'descuento_ultimo' => 1.00,
        ]);

        // Llamar al endpoint show
        $response = $this->getJson("/api/paquetes/{$paquete->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'nombre',
                'productos' => [
                    '*' => [
                        'id',
                        'paquete_id',
                        'producto_id',
                        'cantidad',
                        'tipo_precio',
                        'precio_publico',
                        'precio_especial',
                        'precio_minimo',
                        'precio_ultimo',
                        'descuento_publico',
                        'descuento_especial',
                        'descuento_minimo',
                        'descuento_ultimo',
                    ]
                ]
            ]
        ]);

        $data = $response->json('data');
        $productoData = $data['productos'][0];
        
        $this->assertEquals(15.00, $productoData['descuento_publico']);
        $this->assertEquals(8.00, $productoData['descuento_especial']);
        $this->assertEquals(2.00, $productoData['descuento_minimo']);
        $this->assertEquals(1.00, $productoData['descuento_ultimo']);
    }

    /**
     * Test que el endpoint byProducto() retorna todos los descuentos
     */
    public function test_by_producto_returns_all_discount_fields()
    {
        $producto = Producto::first();
        
        if (!$producto) {
            $this->markTestSkipped('No hay productos en la base de datos');
        }

        $paquete = Paquete::factory()->create([
            'nombre' => 'Test Paquete By Producto',
            'activo' => true,
        ]);

        $unidadDerivada = UnidadDerivada::first();

        if (!$unidadDerivada) {
            $this->markTestSkipped('No hay unidades derivadas en la base de datos');
        }

        PaqueteProducto::create([
            'paquete_id' => $paquete->id,
            'producto_id' => $producto->id,
            'unidad_derivada_id' => $unidadDerivada->id,
            'cantidad' => 1,
            'tipo_precio' => 'minimo',
            'precio_publico' => 100.00,
            'precio_especial' => 95.00,
            'precio_minimo' => 90.00,
            'precio_ultimo' => 85.00,
            'descuento_publico' => 20.00,
            'descuento_especial' => 10.00,
            'descuento_minimo' => 5.00,
            'descuento_ultimo' => 0.00,
        ]);

        // Llamar al endpoint byProducto
        $response = $this->getJson("/api/paquetes/by-producto/{$producto->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'nombre',
                    'productos' => [
                        '*' => [
                            'id',
                            'paquete_id',
                            'producto_id',
                            'cantidad',
                            'tipo_precio',
                            'precio_publico',
                            'precio_especial',
                            'precio_minimo',
                            'precio_ultimo',
                            'descuento_publico',
                            'descuento_especial',
                            'descuento_minimo',
                            'descuento_ultimo',
                        ]
                    ]
                ]
            ]
        ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        
        $paqueteData = collect($data)->firstWhere('id', $paquete->id);
        $this->assertNotNull($paqueteData);
        
        $productoData = $paqueteData['productos'][0];
        $this->assertEquals(20.00, $productoData['descuento_publico']);
        $this->assertEquals(10.00, $productoData['descuento_especial']);
        $this->assertEquals(5.00, $productoData['descuento_minimo']);
        $this->assertEquals(0.00, $productoData['descuento_ultimo']);
    }

    /**
     * Test que todos los campos de precio también están presentes
     */
    public function test_all_price_fields_present()
    {
        $paquete = Paquete::factory()->create([
            'nombre' => 'Test Paquete Precios',
            'activo' => true,
        ]);

        $producto = Producto::first();
        $unidadDerivada = UnidadDerivada::first();

        if (!$producto || !$unidadDerivada) {
            $this->markTestSkipped('No hay productos o unidades derivadas en la base de datos');
        }

        PaqueteProducto::create([
            'paquete_id' => $paquete->id,
            'producto_id' => $producto->id,
            'unidad_derivada_id' => $unidadDerivada->id,
            'cantidad' => 1,
            'tipo_precio' => 'publico',
            'precio_publico' => 100.00,
            'precio_especial' => 95.00,
            'precio_minimo' => 90.00,
            'precio_ultimo' => 85.00,
            'descuento_publico' => 10.00,
            'descuento_especial' => 5.00,
            'descuento_minimo' => 0.00,
            'descuento_ultimo' => 0.00,
        ]);

        $response = $this->getJson("/api/paquetes/{$paquete->id}");
        $response->assertStatus(200);

        $productoData = $response->json('data.productos.0');
        
        // Verificar que todos los campos de precio están presentes
        $this->assertNotNull($productoData['precio_publico']);
        $this->assertNotNull($productoData['precio_especial']);
        $this->assertNotNull($productoData['precio_minimo']);
        $this->assertNotNull($productoData['precio_ultimo']);
        
        // Verificar que todos los campos de descuento están presentes
        $this->assertNotNull($productoData['descuento_publico']);
        $this->assertNotNull($productoData['descuento_especial']);
        $this->assertNotNull($productoData['descuento_minimo']);
        $this->assertNotNull($productoData['descuento_ultimo']);
        
        // Verificar valores
        $this->assertEquals(100.00, $productoData['precio_publico']);
        $this->assertEquals(95.00, $productoData['precio_especial']);
        $this->assertEquals(90.00, $productoData['precio_minimo']);
        $this->assertEquals(85.00, $productoData['precio_ultimo']);
    }
}
