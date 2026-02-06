<?php

namespace App\Services\Implementations;

use App\DTOs\FacturacionElectronica\FacturaDTO;
use App\Exceptions\FacturaException;
use App\Models\Venta;
use App\Models\ComprobanteElectronico;
use App\Repositories\Interfaces\ComprobanteElectronicoRepositoryInterface;
use App\Services\Interfaces\FacturaServiceInterface;
use App\Services\Interfaces\GreenterServiceInterface;
use App\Services\Interfaces\XmlStorageServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio para gestión de Facturas y Boletas Electrónicas
 * 
 * IMPORTANTE:
 * - Envío MANUAL disponible siempre
 * - Envío AUTOMÁTICO después de 5 días (configurado en Job)
 * - Usa tabla 'venta' existente + 'comprobantes_electronicos'
 */
class FacturaService implements FacturaServiceInterface
{
    public function __construct(
        private ComprobanteElectronicoRepositoryInterface $comprobanteRepository,
        private GreenterServiceInterface $greenterService,
        private XmlStorageServiceInterface $xmlStorageService
    ) {}

    public function generarComprobanteDesdeVenta(FacturaDTO $dto): array
    {
        try {
            DB::beginTransaction();

            $venta = $this->validarYObtenerVenta($dto->ventaId);

            // Verificar si ya existe comprobante
            $comprobanteExistente = $this->comprobanteRepository->findByDocumento(
                $venta->tipo_documento,
                $venta->id
            );

            if ($comprobanteExistente) {
                DB::rollBack();
                return [
                    'success' => false,
                    'mensaje' => 'Ya existe un comprobante electrónico para esta venta',
                    'comprobante' => $comprobanteExistente,
                ];
            }

            // Preparar datos para generar XML
            $dataGreenter = $this->prepararDatosParaGreenter($venta);

            // SOLO GENERAR XML (NO ENVIAR A SUNAT)
            $xml = $this->greenterService->generarXmlFactura($dataGreenter);
            $hashCpe = hash('sha256', $xml);

            // Guardar XML
            $ruc = config('greenter.ruc');
            $tipoDoc = $venta->tipo_documento === '01' ? '01' : '03';
            $nombreXml = $this->xmlStorageService->generarNombreXml($ruc, $tipoDoc, $venta->serie, $venta->numero);
            $xmlPath = $this->xmlStorageService->guardarXml($xml, $nombreXml);

            // Crear registro de comprobante en estado pendiente (NO ENVIADO)
            $comprobante = $this->comprobanteRepository->create([
                'tipo_documento' => $venta->tipo_documento,
                'documento_id' => $venta->id,
                'serie' => $venta->serie,
                'numero' => $venta->numero,
                'fecha_emision' => $venta->fecha ?? now(),
                'estado_sunat' => 'pendiente', // PENDIENTE - No enviado aún
                'xml_path' => $xmlPath,
                'hash_cpe' => $hashCpe,
            ]);

            DB::commit();

            Log::info('Comprobante electrónico creado con XML generado (NO ENVIADO)', [
                'comprobante_id' => $comprobante->id,
                'venta_id' => $venta->id,
                'tipo' => $venta->tipo_documento,
                'xml_generado' => true,
                'enviado_sunat' => false,
            ]);

            return [
                'success' => true,
                'mensaje' => 'XML generado correctamente. El comprobante se enviará automáticamente a SUNAT después de 5 días, o puede enviarlo manualmente.',
                'comprobante' => $comprobante,
                'venta' => $venta,
                'xml_generado' => true,
                'enviado_sunat' => false,
            ];

        } catch (FacturaException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al generar comprobante', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw FacturaException::errorAlGuardar($e->getMessage());
        }
    }

    public function obtenerPorVentaId(string $ventaId): ?array
    {
        $venta = Venta::with(['cliente', 'almacen', 'usuario'])->find($ventaId);

        if (!$venta) {
            return null;
        }

        $comprobante = $this->comprobanteRepository->findByDocumento(
            $venta->tipo_documento,
            $venta->id
        );

        return [
            'venta' => $venta,
            'comprobante' => $comprobante,
        ];
    }

    public function listar(array $filtros = []): Collection
    {
        $query = ComprobanteElectronico::with(['venta'])
            ->whereIn('tipo_documento', ['01', '03']);

        $this->applyFilters($query, $filtros);

        return $query->orderBy('fecha_emision', 'desc')->get();
    }

    public function listarPaginado(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        $query = ComprobanteElectronico::with(['venta'])
            ->whereIn('tipo_documento', ['01', '03']);

        $this->applyFilters($query, $filtros);

        return $query->orderBy('fecha_emision', 'desc')->paginate($porPagina);
    }

    public function enviarASunat(string $ventaId, string $modoEnvio = 'manual'): array
    {
        try {
            DB::beginTransaction();

            $venta = $this->validarYObtenerVenta($ventaId);
            $comprobante = $this->comprobanteRepository->findByDocumento(
                $venta->tipo_documento,
                $venta->id
            );

            if (!$comprobante) {
                throw FacturaException::datosIncompletos('No existe comprobante electrónico para esta venta');
            }

            if ($comprobante->estado_sunat === 'enviado' || $comprobante->estado_sunat === 'aceptado') {
                throw FacturaException::comprobanteYaEnviado();
            }

            // Preparar datos para Greenter
            $dataGreenter = $this->prepararDatosParaGreenter($venta);

            // Generar y enviar a SUNAT
            $resultado = $this->greenterService->generarYEnviarFactura($dataGreenter);

            if (!$resultado['success']) {
                throw FacturaException::facturaNoEnviable('Error al generar XML o enviar a SUNAT');
            }

            // Guardar archivos XML y CDR
            $ruc = config('greenter.ruc');
            $tipoDoc = $venta->tipo_documento === '01' ? '01' : '03';
            $nombreXml = $this->xmlStorageService->generarNombreXml($ruc, $tipoDoc, $venta->serie, $venta->numero);
            $nombreCdr = $this->xmlStorageService->generarNombreCdr($ruc, $tipoDoc, $venta->serie, $venta->numero);

            $xmlPath = $this->xmlStorageService->guardarXml($resultado['xml'], $nombreXml);
            $cdrPath = $this->xmlStorageService->guardarCdr(base64_decode($resultado['cdr']), $nombreCdr);

            // Actualizar comprobante
            $this->comprobanteRepository->update($comprobante->id, [
                'estado_sunat' => 'enviado',
                'xml_path' => $xmlPath,
                'cdr_path' => $cdrPath,
                'hash_cpe' => $resultado['hash_cpe'],
                'hash_cdr' => $resultado['hash_cdr'] ?? null,
                'codigo_sunat' => $resultado['codigo_sunat'] ?? null,
                'mensaje_sunat' => $resultado['mensaje_sunat'] ?? null,
                'fecha_envio_sunat' => now(),
            ]);

            // Registrar intento de envío
            $this->comprobanteRepository->registrarIntentoEnvio(
                $comprobante->id,
                true,
                $resultado['codigo_sunat'] ?? '0',
                $resultado['mensaje_sunat'] ?? 'Enviado correctamente',
                null,
                $modoEnvio
            );

            DB::commit();

            Log::info('Factura enviada a SUNAT', [
                'venta_id' => $ventaId,
                'tipo' => $venta->tipo_documento,
                'modo' => $resultado['modo'] ?? 'DESCONOCIDO',
                'modo_envio' => $modoEnvio,
            ]);

            return [
                'success' => true,
                'mensaje' => 'Factura enviada correctamente a SUNAT',
                'modo' => $resultado['modo'] ?? 'DESCONOCIDO',
                'codigo_sunat' => $resultado['codigo_sunat'] ?? null,
                'mensaje_sunat' => $resultado['mensaje_sunat'] ?? null,
            ];

        } catch (FacturaException $e) {
            DB::rollBack();
            
            if (isset($comprobante)) {
                $this->comprobanteRepository->registrarIntentoEnvio(
                    $comprobante->id,
                    false,
                    null,
                    null,
                    $e->getMessage(),
                    $modoEnvio
                );
            }

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error al enviar factura a SUNAT', [
                'venta_id' => $ventaId,
                'error' => $e->getMessage(),
            ]);

            throw FacturaException::facturaNoEnviable($e->getMessage());
        }
    }

    public function consultarEstadoSunat(string $ventaId): array
    {
        $venta = $this->validarYObtenerVenta($ventaId);
        $ruc = config('greenter.ruc');
        $tipoDoc = $venta->tipo_documento === '01' ? '01' : '03';
        
        return $this->greenterService->consultarEstado(
            $ruc,
            $tipoDoc,
            $venta->serie,
            (string) $venta->numero
        );
    }

    public function obtenerXml(string $ventaId): string
    {
        $venta = $this->validarYObtenerVenta($ventaId);
        $comprobante = $this->comprobanteRepository->findByDocumento(
            $venta->tipo_documento,
            $venta->id
        );

        if (!$comprobante || !$comprobante->xml_path) {
            throw FacturaException::datosIncompletos('XML no disponible');
        }

        return $this->xmlStorageService->obtenerXml($comprobante->xml_path);
    }

    public function obtenerCdr(string $ventaId): string
    {
        $venta = $this->validarYObtenerVenta($ventaId);
        $comprobante = $this->comprobanteRepository->findByDocumento(
            $venta->tipo_documento,
            $venta->id
        );

        if (!$comprobante || !$comprobante->cdr_path) {
            throw FacturaException::datosIncompletos('CDR no disponible');
        }

        return $this->xmlStorageService->obtenerCdr($comprobante->cdr_path);
    }

    public function validarVentaParaFacturacion(string $ventaId): array
    {
        $venta = Venta::find($ventaId);

        if (!$venta) {
            return [
                'valido' => false,
                'mensaje' => 'Venta no encontrada',
            ];
        }

        if (!in_array($venta->tipo_documento, ['01', '03'])) {
            return [
                'valido' => false,
                'mensaje' => 'Solo facturas (01) y boletas (03) pueden enviarse a SUNAT',
            ];
        }

        if ($venta->estado_de_venta !== 'completado') {
            return [
                'valido' => false,
                'mensaje' => 'La venta debe estar completada',
            ];
        }

        return [
            'valido' => true,
            'mensaje' => 'Venta válida para facturación electrónica',
            'venta' => $venta,
        ];
    }

    private function validarYObtenerVenta(string $ventaId): Venta
    {
        $venta = Venta::with(['cliente', 'almacen', 'usuario', 'productosAlmacenVenta.productoAlmacen.producto'])
            ->find($ventaId);

        if (!$venta) {
            throw FacturaException::ventaNoEncontrada($ventaId);
        }

        if (!in_array($venta->tipo_documento, ['01', '03'])) {
            throw FacturaException::ventaNoValida('Solo facturas (01) y boletas (03) son válidas');
        }

        if ($venta->estado_de_venta !== 'completado') {
            throw FacturaException::ventaNoValida('La venta debe estar completada');
        }

        return $venta;
    }

    private function prepararDatosParaGreenter(Venta $venta): array
    {
        $cliente = $venta->cliente;
        
        // Calcular totales
        $subtotal = 0;
        $igv = 0;
        $items = [];

        foreach ($venta->productosAlmacenVenta as $index => $detalle) {
            $producto = $detalle->productoAlmacen->producto;
            $valorUnitario = $detalle->precio_unitario / 1.18; // Sin IGV
            $valorVenta = $valorUnitario * $detalle->cantidad;
            $igvItem = $valorVenta * 0.18;

            $subtotal += $valorVenta;
            $igv += $igvItem;

            $items[] = [
                'codigo' => $producto->codigo ?? 'PROD' . ($index + 1),
                'unidad' => 'NIU',
                'cantidad' => $detalle->cantidad,
                'descripcion' => $producto->nombre,
                'mto_base_igv' => round($valorVenta, 2),
                'igv' => round($igvItem, 2),
                'valor_venta' => round($valorVenta, 2),
                'valor_unitario' => round($valorUnitario, 2),
                'precio_unitario' => round($detalle->precio_unitario, 2),
            ];
        }

        $total = $subtotal + $igv;

        return [
            'tipo_doc' => $venta->tipo_documento === '01' ? '01' : '03',
            'serie' => $venta->serie,
            'numero' => (string) $venta->numero,
            'fecha' => $venta->fecha->format('Y-m-d'),
            'tipo_moneda' => 'PEN',
            'mto_oper_gravadas' => round($subtotal, 2),
            'mto_igv' => round($igv, 2),
            'total' => round($total, 2),
            'monto_en_letras' => $this->convertirNumeroALetras($total),
            'cliente' => [
                'tipo_doc' => $cliente->tipo_documento === 'ruc' ? '6' : '1',
                'num_doc' => $cliente->numero_documento,
                'razon_social' => $cliente->razon_social ?? $cliente->nombre,
                'direccion' => $cliente->direccion ?? '',
            ],
            'items' => $items,
        ];
    }

    private function applyFilters($query, array $filtros): void
    {
        if (!empty($filtros['estado_sunat'])) {
            $query->where('estado_sunat', $filtros['estado_sunat']);
        }

        if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
            $query->whereBetween('fecha_emision', [$filtros['fecha_inicio'], $filtros['fecha_fin']]);
        }

        if (!empty($filtros['serie'])) {
            $query->where('serie', $filtros['serie']);
        }

        if (!empty($filtros['search'])) {
            $search = $filtros['search'];
            $query->where(function ($q) use ($search) {
                $q->where('serie', 'like', "%{$search}%")
                    ->orWhere('numero', 'like', "%{$search}%");
            });
        }
    }

    private function convertirNumeroALetras(float $numero): string
    {
        $entero = floor($numero);
        $decimales = round(($numero - $entero) * 100);
        return "SON: " . strtoupper($this->numeroALetrasBasico($entero)) . " CON {$decimales}/100 SOLES";
    }

    private function numeroALetrasBasico(int $numero): string
    {
        if ($numero === 0) return "CERO";
        if ($numero === 1) return "UNO";
        if ($numero < 100) return "VARIOS";
        if ($numero < 1000) return "CIENTOS";
        return "MILES";
    }
}
