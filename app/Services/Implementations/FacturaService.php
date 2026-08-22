<?php

namespace App\Services\Implementations;

use App\DTOs\FacturacionElectronica\FacturaDTO;
use App\Exceptions\FacturaException;
use App\Models\Venta;
use App\Models\ComprobanteElectronico;
use App\Repositories\Interfaces\ComprobanteElectronicoRepositoryInterface;
use App\Services\Interfaces\FacturaServiceInterface;
use App\Services\Interfaces\SunatApiServiceInterface;
use App\Services\Interfaces\XmlStorageServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;

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
        private SunatApiServiceInterface $sunatApiService,
        private XmlStorageServiceInterface $xmlStorageService
    ) {}

    public function generarComprobanteDesdeVenta(FacturaDTO $dto): array
    {
        try {

            DB::beginTransaction();

            $venta = $this->validarYObtenerVenta($dto->ventaId);
            $cliente = $venta->cliente;


            // Convertir enum a string
            $tipoDocumento = $venta->tipo_documento instanceof \BackedEnum 
                ? $venta->tipo_documento->value 
                : $venta->tipo_documento;

            // Verificar si ya existe comprobante con la misma serie y correlativo
            $comprobanteExistente = $this->comprobanteRepository->findBySerieCorrelativo(
                $venta->serie,
                $venta->numero
            );

            if ($comprobanteExistente) {
                DB::rollBack();
                return [
                    'success' => false,
                    'mensaje' => 'Ya existe un comprobante electrónico con esta serie y número',
                    'comprobante' => $comprobanteExistente,
                ];
            }

            // findBySerieCorrelativo (arriba) usa el scope normal de Eloquent,
            // que EXCLUYE soft-deletes — así que no ve una fila soft-deleted
            // con esta misma serie+correlativo (ej. de un intento anterior
            // que se "borró" con delete() en vez de forceDelete(), ver
            // VentaController::update()). El índice único de la base SÍ ve
            // esa fila (sigue físicamente ahí), así que el insert de abajo
            // fallaba con "Duplicate entry" en vez de generar de nuevo.
            // Se purga acá cualquier resto soft-deleted antes de insertar.
            \App\Models\ComprobanteElectronico::onlyTrashed()
                ->where('serie', $venta->serie)
                ->where('correlativo', $venta->numero)
                ->get()
                ->each(function ($fila) {
                    \App\Models\DetalleComprobanteElectronico::where('comprobante_electronico_id', $fila->id)->delete();
                    $fila->forceDelete();
                });

            // Preparar datos para generar XML
            $dataGreenter = $this->prepararDatosParaGreenter($venta, false); // false = NO validar aún (solo generar XML)


            // SOLO GENERAR XML (NO ENVIAR A SUNAT)
            $xml = $this->sunatApiService->generarXmlFactura($dataGreenter);

            $hashCpe = hash('sha256', $xml);

            // Generar código QR para el comprobante (formato SUNAT)
            $codigoQr = $this->generarCodigoQR(
                $venta,
                $tipoDocumento,
                $dataGreenter['mto_igv'],
                $dataGreenter['total'],
                $hashCpe
            );

            // Guardar XML
            $ruc = \App\Models\Empresa::getRucEmisor();
            $nombreXml = $this->xmlStorageService->generarNombreXml($ruc, $tipoDocumento, $venta->serie, $venta->numero);
            $xmlPath = $this->xmlStorageService->guardarXml($xml, $nombreXml);

            // Calcular totales desde dataGreenter
            $operacionGravada = $dataGreenter['mto_oper_gravadas'];
            $totalIgv = $dataGreenter['mto_igv'];
            $importeTotal = $dataGreenter['total'];

            // Determinar tipo y numero de documento del cliente (catalogo 06).
            // Clientes sin documento real van como tipo '0' / numero '0'.
            [$clienteTipoDoc, $clienteNumDoc] = $this->docClienteParaSunat($cliente);

            // Crear registro de comprobante en estado pendiente (NO ENVIADO)
            // Usar el user_id directamente (ahora soporta ULIDs)
            $userId = auth()->id() ?? $venta->user_id;


            $comprobante = $this->comprobanteRepository->create([
                'venta_id' => $venta->id,
                'tipo_comprobante' => $tipoDocumento,
                'serie' => $venta->serie,
                'correlativo' => $venta->numero,
                'fecha_emision' => $venta->fecha->format('Y-m-d'),
                'hora_emision' => $venta->fecha,
                'cliente_id' => $cliente->id,
                'cliente_tipo_documento' => $clienteTipoDoc,
                'cliente_numero_documento' => $clienteNumDoc,
                'cliente_razon_social' => $cliente->razon_social ?? trim(($cliente->nombres ?? '') . ' ' . ($cliente->apellidos ?? '')) ?: 'Cliente',
                'cliente_direccion' => $cliente->direccion,
                'cliente_email' => $cliente->email,
                'cliente_telefono' => $cliente->telefono,
                'moneda' => $venta->tipo_moneda->value === 's' ? 'PEN' : 'USD',
                'tipo_cambio' => $venta->tipo_de_cambio ?? 1.0000,
                'operacion_gravada' => $operacionGravada,
                'operacion_exonerada' => 0.00,
                'operacion_inafecta' => 0.00,
                'operacion_gratuita' => 0.00,
                'total_igv' => $totalIgv,
                'total_isc' => 0.00,
                'total_otros_tributos' => 0.00,
                'total_descuentos' => 0.00,
                'total_cargos' => 0.00,
                'total_anticipos' => 0.00,
                'importe_total' => $importeTotal,
                'monto_en_letras' => $this->convertirNumeroALetras($importeTotal),
                'forma_pago' => $venta->forma_de_pago === 'co' ? 'CONTADO' : 'CREDITO',
                'estado_sunat' => 'PENDIENTE',
                'xml_firmado' => $xml,
                'xml_path' => $xmlPath,
                'hash_cpe' => $hashCpe,
                'codigo_qr' => $codigoQr,
                'user_id' => $userId,
            ]);


            // Guardar detalles del comprobante
            $this->guardarDetallesComprobante($comprobante, $venta, $dataGreenter['items']);

            DB::commit();

            // Envío automático INMEDIATO si está habilitado y "after_days" = 0.
            // Si after_days >= 1, el comprobante se envía luego vía el job programado
            // (sunat:enviar-facturas, diario a las 2:00 AM).
            $enviadoSunat = false;
            $tipoAutoSend = $tipoDocumento === '01' ? 'factura' : 'boleta';
            $empresaAutoSend = \App\Models\Empresa::first();
            $autoEnabled = (bool) ($empresaAutoSend?->{"sunat_auto_send_{$tipoAutoSend}_enabled"} ?? false);
            $autoAfterDays = (int) ($empresaAutoSend?->{"sunat_auto_send_{$tipoAutoSend}_after_days"} ?? 3);
            if ($autoEnabled && $autoAfterDays === 0) {
                try {
                    $this->enviarASunat($venta->id, 'automatico');
                    $enviadoSunat = true;
                } catch (\Exception $e) {
                    // No fallar la creación de la venta si el envío falla; queda PENDIENTE.
                    Log::error("Error enviando {$tipoAutoSend} {$venta->id} al momento de generar: " . $e->getMessage());
                }
            }


            return [
                'success' => true,
                'mensaje' => 'XML generado correctamente. El comprobante se enviará automáticamente a SUNAT después de 5 días, o puede enviarlo manualmente.',
                'comprobante' => $comprobante,
                'venta' => $venta,
                'xml_generado' => true,
                'enviado_sunat' => $enviadoSunat,
            ];

        } catch (FacturaException $e) {
            DB::rollBack();
            Log::error('❌ [FacturaService] FacturaException capturada', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ [FacturaService] Exception general capturada', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw FacturaException::errorAlGuardar($e->getMessage());
        }
    }

    public function obtenerPorVentaId(string $ventaId): ?array
    {
        $venta = Venta::with(['cliente', 'almacen', 'user'])->find($ventaId);

        if (!$venta) {
            return null;
        }

        // Buscar comprobante por serie y número (ya que no hay venta_id en producción)
        $comprobante = $this->comprobanteRepository->findBySerieCorrelativo(
            $venta->serie,
            $venta->numero
        );

        return [
            'venta' => $venta,
            'comprobante' => $comprobante,
        ];
    }

    public function listar(array $filtros = []): Collection
    {
        $query = ComprobanteElectronico::with(['cliente'])
            ->whereIn('tipo_comprobante', ['01', '03']);

        $this->applyFilters($query, $filtros);

        return $query->orderBy('fecha_emision', 'desc')->get();
    }

    public function listarPaginado(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        $query = ComprobanteElectronico::with(['cliente'])
            ->whereIn('tipo_comprobante', ['01', '03']);

        $this->applyFilters($query, $filtros);

        return $query->orderBy('fecha_emision', 'desc')->paginate($porPagina);
    }

    public function enviarASunat(string $ventaId, string $modoEnvio = 'manual'): array
    {
        try {
            DB::beginTransaction();

            $venta = $this->validarYObtenerVenta($ventaId);
            
            // Buscar comprobante por serie y correlativo
            $comprobante = $this->comprobanteRepository->findBySerieCorrelativo(
                $venta->serie,
                $venta->numero
            );

            if (!$comprobante) {
                throw FacturaException::datosIncompletos('No existe comprobante electrónico para esta venta');
            }

            if (in_array($comprobante->estado_sunat, ['ACEPTADO', 'ACEPTADO_CON_OBSERVACIONES'])) {
                throw FacturaException::comprobanteYaEnviado();
            }

            // Preparar datos para Greenter
            $dataGreenter = $this->prepararDatosParaGreenter($venta, true); // true = validar para SUNAT

            // Generar y enviar a SUNAT
            $resultado = $this->sunatApiService->generarYEnviarFactura($dataGreenter);

            if (!$resultado['success']) {
                // Propagar el detalle real del API en lugar del mensaje genérico
                $detalle = $resultado['mensaje_sunat']
                    ?? $resultado['error']
                    ?? 'Error al generar XML o enviar a SUNAT';
                throw FacturaException::facturaNoEnviable($detalle);
            }

            // Guardar archivos XML y CDR
            $ruc = \App\Models\Empresa::getRucEmisor();
            $tipoDoc = $venta->tipo_documento === '01' ? '01' : '03';
            $nombreXml = $this->xmlStorageService->generarNombreXml($ruc, $tipoDoc, $venta->serie, $venta->numero);
            $nombreCdr = $this->xmlStorageService->generarNombreCdr($ruc, $tipoDoc, $venta->serie, $venta->numero);

            $xmlPath = $this->xmlStorageService->guardarXml($resultado['xml'], $nombreXml);
            $cdrPath = $this->xmlStorageService->guardarCdr($resultado['cdr'], $nombreCdr);

            // ✅ Decodificar CDR si viene en base64 (modo simulación)
            $cdrContent = $resultado['cdr'];
            if (base64_decode($cdrContent, true) !== false) {
                $cdrContent = base64_decode($cdrContent);
            }

            // Regenerar QR con el hash actualizado de SUNAT
            $dataGreenter = $this->prepararDatosParaGreenter($venta, false);
            $codigoQrActualizado = $this->generarCodigoQR(
                $venta,
                $tipoDoc,
                $dataGreenter['mto_igv'],
                $dataGreenter['total'],
                $resultado['hash_cpe']
            );

            // Actualizar comprobante
            $this->comprobanteRepository->update($comprobante->id, [
                'estado_sunat' => 'ACEPTADO',
                'xml_firmado' => $resultado['xml'], // ✅ Guardar XML en BD
                'xml_path' => $xmlPath,
                'cdr_xml' => $cdrContent, // ✅ Guardar CDR en BD
                'cdr_path' => $cdrPath,
                'hash_cpe' => $resultado['hash_cpe'],
                'codigo_qr' => $codigoQrActualizado,
                'codigo_respuesta_sunat' => $resultado['codigo_sunat'] ?? null,
                'mensaje_respuesta_sunat' => $resultado['mensaje_sunat'] ?? null,
                'fecha_envio_sunat' => now(),
                'fecha_respuesta_sunat' => now(),
                'user_envio_id' => auth()->id(),
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


            // NO devolver XML ni CDR en la respuesta para evitar problemas de encoding
            return [
                'success' => true,
                'mensaje' => 'Factura enviada correctamente a SUNAT',
                'modo' => $resultado['modo'] ?? 'DESCONOCIDO',
                'codigo_sunat' => $resultado['codigo_sunat'] ?? null,
                'mensaje_sunat' => $resultado['mensaje_sunat'] ?? null,
                'hash_cpe' => $resultado['hash_cpe'] ?? null,
                'hash_cdr' => $resultado['hash_cdr'] ?? null,
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
        $ruc = \App\Models\Empresa::getRucEmisor();
        $tipoDoc = $venta->tipo_documento === '01' ? '01' : '03';
        
        return $this->sunatApiService->consultarEstado(
            $ruc,
            $tipoDoc,
            $venta->serie,
            (string) $venta->numero
        );
    }

    public function obtenerXml(string $ventaId): string
    {
        $venta = $this->validarYObtenerVenta($ventaId);
        $comprobante = $this->comprobanteRepository->findBySerieCorrelativo(
            $venta->serie,
            $venta->numero
        );

        if (!$comprobante || !$comprobante->xml_path) {
            throw FacturaException::datosIncompletos('XML no disponible');
        }

        return $this->xmlStorageService->obtenerXml($comprobante->xml_path);
    }

    public function obtenerCdr(string $ventaId): string
    {
        
        $venta = $this->validarYObtenerVenta($ventaId);
        $comprobante = $this->comprobanteRepository->findBySerieCorrelativo(
            $venta->serie,
            $venta->numero
        );


        if (!$comprobante || !$comprobante->cdr_path) {
            throw FacturaException::datosIncompletos('CDR no disponible');
        }


        $cdr = $this->xmlStorageService->obtenerCdr($comprobante->cdr_path);
        
        
        return $cdr;
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
        $venta = Venta::with([
            'cliente',
            'almacen',
            'user',
            'productosAlmacenVenta.productoAlmacen.producto.unidadMedida', // ✅ Cargar unidad de medida
            'productosAlmacenVenta.unidadesDerivadas',
            // Servicios de la venta: también son líneas del comprobante. Sin esto
            // se emitía el XML SIN los servicios y el total quedaba incompleto.
            'serviciosVenta.servicio',
        ])->find($ventaId);

        if (!$venta) {
            throw FacturaException::ventaNoEncontrada($ventaId);
        }

        // Obtener el valor del enum como string
        $tipoDocumento = $venta->tipo_documento instanceof \BackedEnum 
            ? $venta->tipo_documento->value 
            : $venta->tipo_documento;

        if (!in_array($tipoDocumento, ['01', '03'])) {
            throw FacturaException::ventaNoValida('Las notas de venta no se declaran a SUNAT. Solo facturas (01) y boletas (03).');
        }

        // EN ESPERA: la venta todavía no está confirmada. No tiene serie ni
        // correlativo asignados (se asignan al confirmarla), así que no hay
        // comprobante que enviar — y si igual se intentara, se estaría
        // declarando a SUNAT una venta que puede no llegar a existir.
        //
        // El check de estado que había acá estaba comentado "temporalmente para
        // pruebas", y con él apagado el envío aceptaba cualquier estado. Se
        // reactiva solo para 'ee', que es el caso problemático; el resto sigue
        // pasando como hasta ahora para no romper flujos existentes.
        $estadoVenta = $venta->estado_de_venta instanceof \BackedEnum
            ? $venta->estado_de_venta->value
            : $venta->estado_de_venta;

        if ($estadoVenta === 'ee') {
            throw FacturaException::ventaNoValida('La venta está En Espera: confírmala antes de enviarla a SUNAT.');
        }

        // ✅ Validar que la serie coincida con el tipo de documento
        $primerCaracterSerie = substr($venta->serie, 0, 1);
        if ($tipoDocumento === '01' && $primerCaracterSerie !== 'F') {
            throw FacturaException::ventaNoValida('Las facturas deben tener serie que inicie con F (ej: F001)');
        }
        if ($tipoDocumento === '03' && $primerCaracterSerie !== 'B') {
            throw FacturaException::ventaNoValida('Las boletas deben tener serie que inicie con B (ej: B001)');
        }

        // Comentado temporalmente - permitir cualquier estado para pruebas
        // if ($venta->estado_de_venta !== 'completado') {
        //     throw FacturaException::ventaNoValida('La venta debe estar completada');
        // }

        return $venta;
    }

    private function prepararDatosParaGreenter(Venta $venta, bool $validarParaSunat = false): array
    {
        $cliente = $venta->cliente;
        
        // ✅ Obtener el valor del enum como string
        $tipoDocumento = $venta->tipo_documento instanceof \BackedEnum 
            ? $venta->tipo_documento->value 
            : $venta->tipo_documento;
        
        // ✅ VALIDACIÓN CRÍTICA: DNI vs RUC según tipo de comprobante
        // Solo validar cuando se va a enviar a SUNAT, no al crear la venta
        // Antes comparaba `$cliente->tipo_documento === 'ruc'`, pero esa columna no
        // existe en la tabla `cliente`: la propiedad siempre valía null, así que
        // TODO cliente quedaba como DNI y ninguna factura pasaba la validación de
        // abajo, tuviera RUC o no. La regla vive ahora en el modelo.
        $clienteTipoDoc = $cliente->tipoDocumentoSunat();
        
        if ($validarParaSunat) {
            // Si es Factura (01) y el cliente tiene DNI, lanzar error
            if ($tipoDocumento === '01' && $clienteTipoDoc === '1') {
                throw FacturaException::datosIncompletos(
                    'Las Facturas solo pueden emitirse a clientes con RUC. ' .
                    'Para clientes con DNI debe emitir una Boleta (03).'
                );
            }
            
            // ✅ VALIDACIÓN: Serie debe coincidir con tipo de documento
            $primeraLetraSerie = substr($venta->serie, 0, 1);
            if ($tipoDocumento === '01' && $primeraLetraSerie !== 'F') {
                throw FacturaException::datosIncompletos(
                    "Las Facturas deben tener serie que inicie con 'F' (ej: F001). Serie actual: {$venta->serie}"
                );
            }
            if ($tipoDocumento === '03' && $primeraLetraSerie !== 'B') {
                throw FacturaException::datosIncompletos(
                    "Las Boletas deben tener serie que inicie con 'B' (ej: B001). Serie actual: {$venta->serie}"
                );
            }
        }
        
        // Calcular totales
        $subtotal = 0;
        $igv = 0;
        $items = [];

        foreach ($venta->productosAlmacenVenta as $index => $detalle) {
            $producto = $detalle->productoAlmacen->producto;
            
            // Obtener cantidad y precio desde unidades derivadas
            $unidadesDerivadas = $detalle->unidadesDerivadas;
            
            if ($unidadesDerivadas->isEmpty()) {
                continue;
            }
            
            // Sumar todas las unidades derivadas para este producto
            $cantidadTotal = 0;
            $precioUnitarioPromedio = 0;
            
            // El RECARGO va dentro del precio unitario: es parte de lo que paga el
            // cliente, así que tiene que declararse. Acá se leía solo `precio`, de
            // modo que el comprobante salía SIN recargo — en FT01-15 se le cobraron
            // S/25 de recargo al cliente y a SUNAT se declararon S/0. Es por unidad,
            // igual que en getTotalVenta() y en la pantalla de crear venta.
            foreach ($unidadesDerivadas as $unidad) {
                $cantidadTotal += $unidad->cantidad;
                $precioUnitarioPromedio += (float) $unidad->precio + (float) ($unidad->recargo ?? 0);
            }
            
            // Si hay múltiples unidades, promediar el precio
            if ($unidadesDerivadas->count() > 1) {
                $precioUnitarioPromedio = $precioUnitarioPromedio / $unidadesDerivadas->count();
            }
            
            $valorUnitario = $precioUnitarioPromedio / 1.18; // Sin IGV
            $valorVenta = $valorUnitario * $cantidadTotal;
            $igvItem = $valorVenta * 0.18;

            $subtotal += $valorVenta;
            $igv += $igvItem;

            // Obtener código SUNAT de la unidad de medida del producto
            $codigoUnidadSunat = 'NIU'; // Default
            if ($producto->unidadMedida && $producto->unidadMedida->codigo_sunat) {
                $codigoUnidadSunat = $producto->unidadMedida->codigo_sunat;
            } else {
            }

            $items[] = [
                'codigo' => $producto->cod_producto ?? 'PROD' . ($index + 1),
                'unidad' => $codigoUnidadSunat, // ✅ Usar código SUNAT de la unidad de medida
                'cantidad' => $cantidadTotal,
                'descripcion' => $producto->name ?? 'PRODUCTO',
                'mto_base_igv' => round($valorVenta, 2),
                'igv' => round($igvItem, 2),
                'valor_venta' => round($valorVenta, 2),
                'valor_unitario' => round($valorUnitario, 2),
                'precio_unitario' => round($precioUnitarioPromedio, 2),
            ];
        }

        // Servicios de la venta como líneas del comprobante. Mismo tratamiento
        // que los productos (precio unitario CON IGV → se descompone /1.18), así
        // suman a mto_oper_gravadas, al IGV y al total. Unidad SUNAT "ZZ"
        // (catálogo 03 = unidad de servicio).
        foreach ($venta->serviciosVenta as $sIndex => $sv) {
            $cantidadServicio = (float) $sv->cantidad;
            $precioUnitarioServicio = (float) $sv->precio_unitario;

            if ($cantidadServicio <= 0 || $precioUnitarioServicio <= 0) {
                continue;
            }

            $valorUnitarioServicio = $precioUnitarioServicio / 1.18;
            $valorVentaServicio = $valorUnitarioServicio * $cantidadServicio;
            $igvServicio = $valorVentaServicio * 0.18;

            $subtotal += $valorVentaServicio;
            $igv += $igvServicio;

            $nombreServicio = $sv->servicio->nombre ?? 'SERVICIO';
            $referenciaServicio = trim((string) ($sv->referencia ?? ''));

            $items[] = [
                'codigo' => 'SERV' . ($sv->servicio_id ?? ($sIndex + 1)),
                'unidad' => 'ZZ',
                'cantidad' => $cantidadServicio,
                'descripcion' => $referenciaServicio !== ''
                    ? "{$nombreServicio} ({$referenciaServicio})"
                    : $nombreServicio,
                'mto_base_igv' => round($valorVentaServicio, 2),
                'igv' => round($igvServicio, 2),
                'valor_venta' => round($valorVentaServicio, 2),
                'valor_unitario' => round($valorUnitarioServicio, 2),
                'precio_unitario' => round($precioUnitarioServicio, 2),
            ];
        }

        $total = $subtotal + $igv;

        return [
            'tipo_doc' => $tipoDocumento, // ✅ Fixed: use extracted enum value
            'serie' => $venta->serie,
            'numero' => (string) $venta->numero,
            'fecha' => $venta->fecha->format('Y-m-d'),
            'tipo_moneda' => 'PEN',
            'mto_oper_gravadas' => round($subtotal, 2),
            'mto_igv' => round($igv, 2),
            'total' => round($total, 2),
            'monto_en_letras' => $this->convertirNumeroALetras($total),
            'cliente' => (function () use ($cliente) {
                [$tipoDoc, $numDoc] = $this->docClienteParaSunat($cliente);
                return [
                    'tipo_doc' => $tipoDoc,
                    'num_doc' => $numDoc,
                    'razon_social' => $cliente->razon_social ?? trim(($cliente->nombres ?? '') . ' ' . ($cliente->apellidos ?? '')) ?: 'CLIENTE',
                    'direccion' => $cliente->direccion ?? '',
                ];
            })(),
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
                    ->orWhere('correlativo', 'like', "%{$search}%")
                    ->orWhere('cliente_razon_social', 'like', "%{$search}%")
                    ->orWhere('cliente_numero_documento', 'like', "%{$search}%");
            });
        }
    }

    private function convertirNumeroALetras(float $numero): string
    {
        $entero = floor($numero);
        $decimales = round(($numero - $entero) * 100);
        
        $letras = $this->numeroALetras($entero);
        
        return "SON: " . strtoupper($letras) . " CON {$decimales}/100 SOLES";
    }

    private function numeroALetras(int $numero): string
    {
        if ($numero === 0) return "CERO";
        
        $unidades = ["", "UNO", "DOS", "TRES", "CUATRO", "CINCO", "SEIS", "SIETE", "OCHO", "NUEVE"];
        $decenas = ["", "DIEZ", "VEINTE", "TREINTA", "CUARENTA", "CINCUENTA", "SESENTA", "SETENTA", "OCHENTA", "NOVENTA"];
        $especiales = ["DIEZ", "ONCE", "DOCE", "TRECE", "CATORCE", "QUINCE", "DIECISEIS", "DIECISIETE", "DIECIOCHO", "DIECINUEVE"];
        $centenas = ["", "CIENTO", "DOSCIENTOS", "TRESCIENTOS", "CUATROCIENTOS", "QUINIENTOS", "SEISCIENTOS", "SETECIENTOS", "OCHOCIENTOS", "NOVECIENTOS"];
        
        if ($numero < 10) {
            return $unidades[$numero];
        }
        
        if ($numero < 20) {
            return $especiales[$numero - 10];
        }
        
        if ($numero < 100) {
            $decena = floor($numero / 10);
            $unidad = $numero % 10;
            if ($unidad === 0) {
                return $decenas[$decena];
            }
            // ✅ CORREGIDO: 20-29 se escriben juntos (VEINTIUNO, VEINTIDOS, etc.)
            if ($decena === 2) {
                return "VEINTI" . $unidades[$unidad];
            }
            return $decenas[$decena] . " Y " . $unidades[$unidad];
        }
        
        if ($numero === 100) {
            return "CIEN";
        }
        
        if ($numero < 1000) {
            $centena = floor($numero / 100);
            $resto = $numero % 100;
            if ($resto === 0) {
                return $numero === 100 ? "CIEN" : $centenas[$centena];
            }
            return $centenas[$centena] . " " . $this->numeroALetras($resto);
        }
        
        if ($numero < 1000000) {
            $miles = floor($numero / 1000);
            $resto = $numero % 1000;
            $textoMiles = $miles === 1 ? "MIL" : $this->numeroALetras($miles) . " MIL";
            if ($resto === 0) {
                return $textoMiles;
            }
            return $textoMiles . " " . $this->numeroALetras($resto);
        }
        
        // Para números mayores a 1 millón
        $millones = floor($numero / 1000000);
        $resto = $numero % 1000000;
        $textoMillones = $millones === 1 ? "UN MILLON" : $this->numeroALetras($millones) . " MILLONES";
        if ($resto === 0) {
            return $textoMillones;
        }
        return $textoMillones . " " . $this->numeroALetras($resto);
    }

    /**
     * Guardar detalles del comprobante electrónico
     */
    private function guardarDetallesComprobante(ComprobanteElectronico $comprobante, Venta $venta, array $items): void
    {
        foreach ($items as $index => $item) {
            // Buscar el producto correspondiente en la venta
            $productoVenta = $venta->productosAlmacenVenta[$index] ?? null;
            
            if (!$productoVenta) {
                continue;
            }

            $producto = $productoVenta->productoAlmacen->producto;
            $unidadDerivada = $productoVenta->unidadesDerivadas->first();

            // Calcular precio_venta (valor_venta + igv)
            $precioVenta = $item['valor_venta'] + $item['igv'];

            \App\Models\DetalleComprobanteElectronico::create([
                'comprobante_electronico_id' => $comprobante->id, // ✅ Tabla correcta
                'producto_id' => $producto->id, // ✅ Guardar ID del producto
                'unidad_derivada_id' => $unidadDerivada?->id, // ✅ Guardar ID de unidad derivada
                'item' => $index + 1,
                'codigo_producto' => $item['codigo'],
                'codigo_producto_sunat' => $item['codigo'], // Mismo código para SUNAT
                'descripcion' => $item['descripcion'],
                'unidad_medida' => $item['unidad'],
                'cantidad' => $item['cantidad'],
                'valor_unitario' => $item['valor_unitario'], // Precio sin IGV
                'precio_unitario' => $item['precio_unitario'], // Precio con IGV
                'valor_venta' => $item['valor_venta'], // Subtotal sin IGV
                'precio_venta' => $precioVenta, // Subtotal con IGV
                'descuento' => 0.00,
                'cargo' => 0.00,
                'tipo_afectacion_igv' => '10', // Gravado - Operación Onerosa
                'porcentaje_igv' => 18.00,
                'igv' => $item['igv'],
                'isc' => 0.00,
                'otros_tributos' => 0.00,
                'total_impuestos' => $item['igv'],
                'informacion_adicional' => null,
                'es_bonificacion' => false,
            ]);
        }
    }

    /**
     * Generar código QR como base64 Data URI (formato SUNAT)
     * Formato: RUC|TIPO_DOC|SERIE|NUMERO|IGV|TOTAL|FECHA|TIPO_DOC_CLIENTE|NUM_DOC_CLIENTE|HASH
     */
    private function generarCodigoQR(Venta $venta, string $tipoDocumento, float $igv, float $total, string $hashCpe): ?string
    {
        try {
            $cliente = $venta->cliente;
            [$clienteTipoDoc, $clienteNumDoc] = $this->docClienteParaSunat($cliente);

            $qrText = implode('|', [
                \App\Models\Empresa::getRucEmisor(),
                $tipoDocumento,
                $venta->serie,
                $venta->numero,
                number_format($igv, 2, '.', ''),
                number_format($total, 2, '.', ''),
                $venta->fecha->format('Y-m-d'),
                $clienteTipoDoc,
                $clienteNumDoc,
                $hashCpe,
            ]);

            $result = Builder::create()
                ->writer(new PngWriter())
                ->data($qrText)
                ->encoding(new Encoding('UTF-8'))
                ->errorCorrectionLevel(ErrorCorrectionLevel::Medium)
                ->size(200)
                ->margin(10)
                ->build();

            return $result->getDataUri();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Tipo y numero de documento del cliente para SUNAT (catalogo 06).
     *
     * Clientes sin documento real (placeholder "SN-XXXXXXXX" generado por
     * ClienteController al crear sin documento) van como tipo '0'
     * (DOC.TRIB.NO.DOM.SIN.RUC) y numero '0' — el formato que SUNAT acepta
     * para consumidor final no identificado en boletas (< S/ 700).
     *
     * @return array{0: string, 1: string} [tipoDoc, numDoc]
     */
    private function docClienteParaSunat($cliente): array
    {
        $numDoc = (string) ($cliente->numero_documento ?? '');
        if ($numDoc === '' || str_starts_with($numDoc, 'SN-')) {
            return ['0', '0'];
        }

        // Mismo problema que arriba: este `match` iba siempre al default porque
        // `tipo_documento` no existe, y los comprobantes salían con schemeID="1"
        // (DNI) incluso para clientes con RUC de 11 dígitos.
        return [$cliente->tipoDocumentoSunat(), $numDoc];
    }
}
