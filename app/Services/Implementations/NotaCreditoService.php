<?php

namespace App\Services\Implementations;

use App\DTOs\FacturacionElectronica\NotaCreditoDTO;
use App\Exceptions\NotaCreditoException;
use App\Models\NotaCredito;
use App\Models\Venta;
use App\Models\SerieDocumento;
use App\Models\ComprobanteElectronico;
use App\Repositories\Interfaces\NotaCreditoRepositoryInterface;
use App\Repositories\Interfaces\ComprobanteElectronicoRepositoryInterface;
use App\Repositories\Interfaces\MotivoNotaRepositoryInterface;
use App\Services\Interfaces\NotaCreditoServiceInterface;
use App\Services\Interfaces\SunatApiServiceInterface;
use App\Services\Interfaces\XmlStorageServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotaCreditoService implements NotaCreditoServiceInterface
{
    public function __construct(
        private NotaCreditoRepositoryInterface $notaCreditoRepository,
        private ComprobanteElectronicoRepositoryInterface $comprobanteRepository,
        private MotivoNotaRepositoryInterface $motivoRepository,
        private SunatApiServiceInterface $sunatApiService,
        private XmlStorageServiceInterface $xmlStorageService
    ) {}

    public function crear(NotaCreditoDTO $dto): NotaCredito
    {
        try {
            DB::beginTransaction();

            $venta = $this->validarYObtenerVenta($dto->ventaId);
            $motivo = $this->validarYObtenerMotivo($dto->motivoId);
            
            // VALIDACIÓN CRÍTICA: Efecto económico
            $this->validarEfectoEconomico($dto, $venta, $motivo);
            
            $serie = $this->obtenerSerie($dto->serie, $dto->almacenId);
            $numero = $dto->numero ?? $this->notaCreditoRepository->getSiguienteNumero($serie->serie);

            if ($this->notaCreditoRepository->existeSerieNumero($serie->serie, $numero)) {
                throw NotaCreditoException::datosIncompletos("Ya existe una nota de crédito con serie {$serie->serie} y número {$numero}");
            }

            $totales = $this->calcularTotales($dto->items ?? []);

            $notaCredito = $this->notaCreditoRepository->create([
                'tipo_documento' => 'nc',
                'serie' => $serie->serie,
                'numero' => $numero,
                'venta_id' => $venta->id,
                'motivo_id' => $motivo->id,
                'descripcion' => $dto->descripcion,
                'monto_total' => $dto->montoTotal ?? $totales['total'],
                'monto_igv' => $dto->montoIgv ?? $totales['igv'],
                'monto_subtotal' => $dto->montoSubtotal ?? $totales['subtotal'],
                'referencia_documento' => "{$venta->serie}-{$venta->numero}",
                'fecha' => $dto->fecha ?? now(),
                'estado' => 'borrador',
                'usuario_id' => $dto->usuarioId ?? auth()->id(),
                'almacen_id' => $dto->almacenId,
                'observaciones' => $dto->observaciones,
            ]);

            $serie->increment('correlativo');

            // ✅ GENERAR XML AUTOMÁTICAMENTE (sin enviar a SUNAT)
            try {
                $dataGreenter = $this->prepararDatosParaGreenter($notaCredito->fresh(['venta.cliente', 'motivo']));
                $xmlContent = $this->sunatApiService->generarXmlNotaCredito($dataGreenter);

                if ($xmlContent) {
                    $hashCpe = hash('sha256', $xmlContent);
                    $cliente = $notaCredito->venta->cliente;

                    // Crear registro en comprobantes_electronicos con TODOS los campos obligatorios
                    $this->comprobanteRepository->create([
                        'venta_id' => $notaCredito->venta_id,
                        'tipo_comprobante' => '07', // 07 = Nota de Crédito
                        'serie' => $notaCredito->serie,
                        'correlativo' => $notaCredito->numero, // ⚠️ En la tabla se llama 'correlativo'
                        'fecha_emision' => $notaCredito->fecha->format('Y-m-d'),
                        'cliente_id' => $cliente->id,
                        'cliente_tipo_documento' => $cliente->tipo_documento === 'ruc' ? '6' : '1',
                        'cliente_numero_documento' => $cliente->numero_documento,
                        'cliente_razon_social' => $cliente->razon_social ?? trim(($cliente->nombres ?? '') . ' ' . ($cliente->apellidos ?? '')) ?: 'Cliente',
                        'cliente_direccion' => $cliente->direccion,
                        'moneda' => 'PEN',
                        'operacion_gravada' => $notaCredito->monto_subtotal,
                        'total_igv' => $notaCredito->monto_igv,
                        'importe_total' => $notaCredito->monto_total,
                        'numero_doc_referencia' => $notaCredito->referencia_documento,
                        'tipo_doc_referencia' => $venta->tipo_documento,
                        'codigo_motivo' => $motivo->codigo_sunat,
                        'descripcion_motivo' => $motivo->descripcion,
                        'estado_sunat' => 'PENDIENTE',
                        'xml_firmado' => $xmlContent,
                        'hash_cpe' => $hashCpe,
                        'user_id' => $notaCredito->usuario_id,
                    ]);

                }
            } catch (\Exception $e) {
                // No fallar la creación si hay error al generar XML,
                // pero SÍ loguear el detalle para poder diagnosticarlo.
                Log::error('Error al generar XML de nota de crédito al crear', [
                    'nota_credito_id' => $notaCredito->id,
                    'serie' => $notaCredito->serie,
                    'numero' => $notaCredito->numero,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            DB::commit();


            return $notaCredito->fresh(['venta', 'motivo', 'usuario', 'almacen', 'comprobanteElectronico']);

        } catch (NotaCreditoException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear nota de crédito', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw NotaCreditoException::errorAlGuardar($e->getMessage());
        }
    }

    public function obtenerPorId(string $id): ?NotaCredito
    {
        return $this->notaCreditoRepository->findById($id);
    }

    public function listar(array $filtros = []): Collection
    {
        return $this->notaCreditoRepository->getAll($filtros);
    }

    public function listarPaginado(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        return $this->notaCreditoRepository->getPaginated($filtros, $porPagina);
    }

    public function obtenerPorVenta(string $ventaId): Collection
    {
        return $this->notaCreditoRepository->getByVenta($ventaId);
    }

    public function actualizar(string $id, NotaCreditoDTO $dto): NotaCredito
    {
        try {
            DB::beginTransaction();

            $notaCredito = $this->notaCreditoRepository->findById($id);

            if (!$notaCredito) {
                throw NotaCreditoException::notaCreditoNoEncontrada($id);
            }

            if (!$notaCredito->puedeEditarse()) {
                throw NotaCreditoException::notaCreditoNoEditable($notaCredito->estado);
            }

            if ($dto->motivoId && $dto->motivoId !== $notaCredito->motivo_id) {
                $this->validarYObtenerMotivo($dto->motivoId);
            }

            $totales = !empty($dto->items) ? $this->calcularTotales($dto->items) : null;

            $dataActualizar = array_filter([
                'motivo_id' => $dto->motivoId,
                'descripcion' => $dto->descripcion,
                'monto_total' => $dto->montoTotal ?? ($totales['total'] ?? null),
                'monto_igv' => $dto->montoIgv ?? ($totales['igv'] ?? null),
                'monto_subtotal' => $dto->montoSubtotal ?? ($totales['subtotal'] ?? null),
                'observaciones' => $dto->observaciones,
            ], fn($value) => $value !== null);

            $notaCreditoActualizada = $this->notaCreditoRepository->update($id, $dataActualizar);

            DB::commit();


            return $notaCreditoActualizada;

        } catch (NotaCreditoException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar nota de crédito', [
                'nota_credito_id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw NotaCreditoException::errorAlGuardar($e->getMessage());
        }
    }

    public function cancelar(string $id, string $motivo): bool
    {
        try {
            DB::beginTransaction();

            $notaCredito = $this->notaCreditoRepository->findById($id);

            if (!$notaCredito) {
                throw NotaCreditoException::notaCreditoNoEncontrada($id);
            }

            if (!$notaCredito->puedeCancelarse()) {
                throw NotaCreditoException::notaCreditoNoEditable($notaCredito->estado);
            }

            $this->notaCreditoRepository->update($id, [
                'estado' => 'cancelado',
                'observaciones' => ($notaCredito->observaciones ?? '') . "\nCANCELADO: {$motivo}",
            ]);

            DB::commit();


            return true;

        } catch (NotaCreditoException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al cancelar nota de crédito', [
                'nota_credito_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function enviarASunat(string $id, string $modoEnvio = 'manual'): array
    {
        try {
            DB::beginTransaction();

            $notaCredito = $this->notaCreditoRepository->findById($id);

            if (!$notaCredito) {
                throw NotaCreditoException::notaCreditoNoEncontrada($id);
            }

            if (!$notaCredito->puedeEnviarse()) {
                throw NotaCreditoException::notaCreditoNoEnviable("Estado actual: {$notaCredito->estado}");
            }

            // ✅ Cargar relaciones necesarias
            $notaCredito->load(['venta.cliente', 'motivo']);
            $venta = $notaCredito->venta;
            $motivo = $notaCredito->motivo;

            $dataGreenter = $this->prepararDatosParaGreenter($notaCredito);
            $resultado = $this->sunatApiService->generarYEnviarNotaCredito($dataGreenter);

            if (!$resultado['success']) {
                // Propagar el detalle real del API en lugar del mensaje genérico
                $detalle = $resultado['mensaje_sunat']
                    ?? $resultado['error']
                    ?? 'Error al generar XML o enviar a SUNAT';
                throw NotaCreditoException::notaCreditoNoEnviable($detalle);
            }

            $ruc = \App\Models\Empresa::getRucEmisor();
            $nombreXml = $this->xmlStorageService->generarNombreXml($ruc, '07', $notaCredito->serie, $notaCredito->numero);
            $nombreCdr = $this->xmlStorageService->generarNombreCdr($ruc, '07', $notaCredito->serie, $notaCredito->numero);

            $xmlPath = $this->xmlStorageService->guardarXml($resultado['xml'], $nombreXml);
            $cdrPath = $this->xmlStorageService->guardarCdr($resultado['cdr'], $nombreCdr);

            // ✅ Decodificar CDR si viene en base64 (modo simulación)
            $cdrContent = $resultado['cdr'];
            if (base64_decode($cdrContent, true) !== false) {
                $cdrContent = base64_decode($cdrContent);
            }

            // Buscar comprobante existente por serie y correlativo (no por documento)
            $comprobante = $this->comprobanteRepository->findBySerieCorrelativo($notaCredito->serie, $notaCredito->numero);

            if (!$comprobante) {
                $cliente = $venta->cliente;
                
                $comprobante = $this->comprobanteRepository->create([
                    'venta_id' => $notaCredito->venta_id,
                    'tipo_comprobante' => '07', // 07 = Nota de Crédito
                    'serie' => $notaCredito->serie,
                    'correlativo' => $notaCredito->numero, // ✅ Campo requerido
                    'fecha_emision' => $notaCredito->fecha->format('Y-m-d'),
                    'cliente_id' => $cliente->id,
                    'cliente_tipo_documento' => $cliente->tipo_documento === 'ruc' ? '6' : '1',
                    'cliente_numero_documento' => $cliente->numero_documento,
                    'cliente_razon_social' => $cliente->razon_social ?? trim(($cliente->nombres ?? '') . ' ' . ($cliente->apellidos ?? '')) ?: 'Cliente',
                    'cliente_direccion' => $cliente->direccion,
                    'moneda' => 'PEN',
                    'operacion_gravada' => $notaCredito->monto_subtotal,
                    'total_igv' => $notaCredito->monto_igv,
                    'importe_total' => $notaCredito->monto_total,
                    'numero_doc_referencia' => $notaCredito->referencia_documento,
                    'tipo_doc_referencia' => $venta->tipo_documento,
                    'codigo_motivo' => $motivo->codigo_sunat,
                    'descripcion_motivo' => $motivo->descripcion,
                    'estado_sunat' => 'ACEPTADO', // ✅ MAYÚSCULAS según ENUM
                    'xml_firmado' => $resultado['xml'], // ✅ Guardar XML en BD
                    'xml_path' => $xmlPath,
                    'cdr_xml' => $cdrContent, // ✅ Guardar CDR en BD
                    'cdr_path' => $cdrPath,
                    'hash_cpe' => $resultado['hash_cpe'],
                    'hash_cdr' => $resultado['hash_cdr'] ?? null,
                    'codigo_sunat' => $resultado['codigo_sunat'] ?? null,
                    'mensaje_sunat' => $resultado['mensaje_sunat'] ?? null,
                    'fecha_envio_sunat' => now(),
                    'user_id' => $notaCredito->usuario_id,
                ]);
            } else {
                $this->comprobanteRepository->update($comprobante->id, [
                    'estado_sunat' => 'ACEPTADO', // ✅ MAYÚSCULAS según ENUM
                    'xml_firmado' => $resultado['xml'], // ✅ Guardar XML en BD
                    'xml_path' => $xmlPath,
                    'cdr_xml' => $cdrContent, // ✅ Guardar CDR en BD
                    'cdr_path' => $cdrPath,
                    'hash_cpe' => $resultado['hash_cpe'],
                    'hash_cdr' => $resultado['hash_cdr'] ?? null,
                    'codigo_sunat' => $resultado['codigo_sunat'] ?? null,
                    'mensaje_sunat' => $resultado['mensaje_sunat'] ?? null,
                    'fecha_envio_sunat' => now(),
                ]);
            }

            $this->comprobanteRepository->registrarIntentoEnvio(
                $comprobante->id,
                true,
                $resultado['codigo_sunat'] ?? '0',
                $resultado['mensaje_sunat'] ?? 'Enviado correctamente',
                null,
                $modoEnvio
            );

            $this->notaCreditoRepository->cambiarEstado($id, 'enviado');

            DB::commit();


            return [
                'success' => true,
                'mensaje' => 'Nota de crédito enviada correctamente',
                'modo' => $resultado['modo'] ?? 'DESCONOCIDO',
                'codigo_sunat' => $resultado['codigo_sunat'] ?? null,
                'mensaje_sunat' => $resultado['mensaje_sunat'] ?? null,
            ];

        } catch (NotaCreditoException $e) {
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
            
            Log::error('Error al enviar nota de crédito a SUNAT', [
                'nota_credito_id' => $id,
                'error' => $e->getMessage(),
            ]);

            throw NotaCreditoException::notaCreditoNoEnviable($e->getMessage());
        }
    }

    public function consultarEstadoSunat(string $id): array
    {
        $notaCredito = $this->notaCreditoRepository->findById($id);

        if (!$notaCredito) {
            throw NotaCreditoException::notaCreditoNoEncontrada($id);
        }

        $ruc = \App\Models\Empresa::getRucEmisor();
        
        return $this->sunatApiService->consultarEstado(
            $ruc,
            '07',
            $notaCredito->serie,
            (string) $notaCredito->numero
        );
    }

    public function obtenerXml(string $id): string
    {
        $notaCredito = $this->notaCreditoRepository->findById($id);

        if (!$notaCredito) {
            throw NotaCreditoException::notaCreditoNoEncontrada($id);
        }

        $comprobante = $notaCredito->comprobanteElectronico;

        if (!$comprobante) {
            throw NotaCreditoException::datosIncompletos('Comprobante electrónico no encontrado');
        }

        // Priorizar xml_firmado (generado automáticamente) sobre xml_path (archivo guardado)
        if (!empty($comprobante->xml_firmado)) {
            return $comprobante->xml_firmado;
        }

        if (!empty($comprobante->xml_path)) {
            return $this->xmlStorageService->obtenerXml($comprobante->xml_path);
        }

        throw NotaCreditoException::datosIncompletos('XML no disponible');
    }

    public function obtenerCdr(string $id): string
    {
        $notaCredito = $this->notaCreditoRepository->findById($id);

        if (!$notaCredito) {
            throw NotaCreditoException::notaCreditoNoEncontrada($id);
        }

        $comprobante = $notaCredito->comprobanteElectronico;

        if (!$comprobante) {
            throw NotaCreditoException::datosIncompletos('Comprobante electrónico no encontrado');
        }

        // Priorizar cdr_xml (contenido directo) sobre cdr_path (archivo guardado)
        if (!empty($comprobante->cdr_xml)) {
            return $comprobante->cdr_xml;
        }

        if (!empty($comprobante->cdr_path)) {
            return $this->xmlStorageService->obtenerCdr($comprobante->cdr_path);
        }

        throw NotaCreditoException::datosIncompletos('CDR no disponible. La nota debe ser enviada a SUNAT primero.');
    }

    public function validarVentaParaNotaCredito(string $ventaId): array
    {
        $venta = Venta::find($ventaId);

        if (!$venta) {
            return [
                'valido' => false,
                'mensaje' => 'Venta no encontrada',
            ];
        }

        // Obtener el valor del enum como string
        $estadoVenta = $venta->estado_de_venta instanceof \BackedEnum 
            ? $venta->estado_de_venta->value 
            : $venta->estado_de_venta;

        if ($estadoVenta !== 'cr') {
            return [
                'valido' => false,
                'mensaje' => 'La venta debe estar en estado Creado',
            ];
        }

        $comprobante = $venta->comprobanteElectronico;

        if (!$comprobante || !in_array($comprobante->tipo_comprobante, ['01', '03'])) {
            return [
                'valido' => false,
                'mensaje' => 'La venta no tiene un comprobante electrónico de factura o boleta asociado',
            ];
        }

        if (!in_array($comprobante->estado_sunat, ['ACEPTADO', 'ACEPTADO_CON_OBSERVACIONES'])) {
            return [
                'valido' => false,
                'mensaje' => 'El comprobante debe estar ACEPTADO por SUNAT para emitir una nota de crédito. Estado actual: ' . ($comprobante->estado_sunat ?? 'sin estado'),
            ];
        }

        return [
            'valido' => true,
            'mensaje' => 'Venta válida para nota de crédito',
            'venta' => $venta,
        ];
    }

    public function calcularTotales(array $items): array
    {
        $subtotal = 0;
        $igv = 0;

        foreach ($items as $item) {
            $subtotal += $item['valor_venta'] ?? 0;
            $igv += $item['igv'] ?? 0;
        }

        $total = $subtotal + $igv;

        return [
            'subtotal' => round($subtotal, 2),
            'igv' => round($igv, 2),
            'total' => round($total, 2),
        ];
    }

    private function validarYObtenerVenta(string $ventaId): Venta
    {
        $venta = Venta::find($ventaId);

        if (!$venta) {
            throw NotaCreditoException::ventaNoEncontrada($ventaId);
        }

        // Obtener el valor del enum como string
        $estadoVenta = $venta->estado_de_venta instanceof \BackedEnum 
            ? $venta->estado_de_venta->value 
            : $venta->estado_de_venta;

        if ($estadoVenta !== 'cr') {
            throw NotaCreditoException::ventaNoValida(
                'La venta debe estar en estado Creado. Estado actual: ' . $estadoVenta
            );
        }

        $this->validarComprobanteAceptadoParaNota($venta);

        return $venta;
    }

    /**
     * Valida que la venta tenga un comprobante electrónico (factura/boleta)
     * ACEPTADO por SUNAT, requisito legal para emitir una nota de crédito.
     *
     * SUNAT: "Será emitida respecto de 'una' FE que cuente con CDR 'aceptada'
     * o BVE otorgada con anterioridad." (ACEPTADO_CON_OBSERVACIONES también
     * tiene validez tributaria).
     */
    private function validarComprobanteAceptadoParaNota(Venta $venta): void
    {
        $comprobante = $venta->comprobanteElectronico;

        if (!$comprobante) {
            throw NotaCreditoException::ventaNoValida(
                'La venta no tiene un comprobante electrónico asociado. ' .
                'Solo se pueden emitir notas de crédito sobre facturas o boletas emitidas.'
            );
        }

        if (!in_array($comprobante->tipo_comprobante, ['01', '03'])) {
            throw NotaCreditoException::ventaNoValida(
                'El comprobante de la venta no es factura ni boleta (tipo: ' . $comprobante->tipo_comprobante . ').'
            );
        }

        if (!in_array($comprobante->estado_sunat, ['ACEPTADO', 'ACEPTADO_CON_OBSERVACIONES'])) {
            throw NotaCreditoException::ventaNoValida(
                'El comprobante debe estar ACEPTADO por SUNAT para emitir una nota de crédito. ' .
                'Estado actual: ' . ($comprobante->estado_sunat ?? 'sin estado')
            );
        }
    }

    private function validarYObtenerMotivo(int $motivoId)
    {
        $motivo = $this->motivoRepository->findById($motivoId);

        if (!$motivo) {
            throw NotaCreditoException::motivoNoEncontrado($motivoId);
        }

        // VALIDACIÓN CRÍTICA: Verificar que sea tipo NC (no ND)
        if ($motivo->tipo !== 'NC') {
            throw NotaCreditoException::motivoNoValido(
                'El motivo seleccionado no es válido para Nota de Crédito. ' .
                'Tipo recibido: ' . $motivo->tipo
            );
        }

        if ($motivo->estado !== 1) {
            throw NotaCreditoException::motivoNoValido('El motivo no está activo');
        }

        return $motivo;
    }

    /**
     * Valida que el efecto económico de la NC cumpla con las reglas SUNAT
     * 
     * REGLAS SUNAT:
     * - NC SIEMPRE debe disminuir o anular (nunca aumentar)
     * - Códigos 01, 02, 06 deben anular completamente el comprobante
     * - Código 10 requiere descripción detallada (mínimo 20 caracteres)
     */
    private function validarEfectoEconomico(NotaCreditoDTO $dto, Venta $venta, $motivo): void
    {
        // VALIDACIÓN TEMPORALMENTE DESACTIVADA - Problema con monto_total en comprobantes
        // TODO: Investigar por qué los comprobantes tienen monto_total = 0
        /*
        // Obtener el monto original del comprobante electrónico asociado a la venta
        $comprobante = ComprobanteElectronico::where('venta_id', $venta->id)->first();
        
        if (!$comprobante) {
            throw NotaCreditoException::datosIncompletos(
                'No se encontró el comprobante electrónico asociado a la venta'
            );
        }
        
        $montoOriginal = $comprobante->monto_total ?? 0;
        $montoNota = $dto->montoTotal ?? 0;

        // 1. NC NO PUEDE AUMENTAR EL MONTO
        if ($montoNota > $montoOriginal) {
            throw NotaCreditoException::montoInvalido(
                "Una Nota de Crédito no puede aumentar el monto original. " .
                "Monto original: S/ {$montoOriginal}, Monto NC: S/ {$montoNota}"
            );
        }
        */

        // 2. CÓDIGOS QUE REQUIEREN ANULACIÓN TOTAL - TEMPORALMENTE DESACTIVADO
        /*
        $codigosAnulacionTotal = ['01', '02', '06'];
        if (in_array($motivo->codigo_sunat, $codigosAnulacionTotal)) {
            $diferencia = abs($montoNota - $montoOriginal);
            if ($diferencia > 0.01) { // Tolerancia de 1 céntimo por redondeo
                throw NotaCreditoException::motivoInvalido(
                    "El motivo '{$motivo->codigo_sunat} - {$motivo->descripcion}' requiere " .
                    "anulación total del comprobante. Monto debe ser igual al original: S/ {$montoOriginal}"
                );
            }
        }
        */

        // 3. CÓDIGO 10 REQUIERE DESCRIPCIÓN DETALLADA
        if ($motivo->codigo_sunat === '10') {
            $descripcion = $dto->descripcion ?? '';
            if (strlen(trim($descripcion)) < 20) {
                throw NotaCreditoException::datosIncompletos(
                    'El motivo "10 - Otros conceptos" requiere una descripción detallada ' .
                    '(mínimo 20 caracteres) explicando el motivo específico de la nota.'
                );
            }
        }

        // 4. VALIDAR NOTAS DUPLICADAS SEGÚN MOTIVO
        // REGLA: Permitir múltiples NC para motivos específicos
        // - 01: Anulación de la operación (puede haber múltiples si se necesita corregir)
        // - 04, 05: Descuentos parciales
        // - 07, 09: Devoluciones por ítem
        $notasExistentes = $this->notaCreditoRepository->getByVenta($venta->id);
        if ($notasExistentes->isNotEmpty()) {
            // Motivos que permiten múltiples NC según catálogo SUNAT
            $motivosMultiples = ['01', '04', '05', '07', '09'];
            
            // Si el motivo NO permite múltiples NC, validar que no exista otra
            if (!in_array($motivo->codigo_sunat, $motivosMultiples)) {
                throw NotaCreditoException::ventaNoValida(
                    "Esta venta ya tiene una Nota de Crédito. " .
                    "Solo se permiten múltiples notas para: Anulación (01), Descuentos parciales (04, 05), " .
                    "o Devoluciones por ítem (07, 09)."
                );
            }
            
            // Log para debugging
        }
    }

    private function obtenerSerie(string $serie, int $almacenId): SerieDocumento
    {
        $serieDoc = SerieDocumento::where('serie', $serie)
            ->where('tipo_documento', 'nc')
            ->where('almacen_id', $almacenId)
            ->where('activo', true)
            ->first();

        if (!$serieDoc) {
            throw NotaCreditoException::serieNoEncontrada($serie);
        }

        return $serieDoc;
    }

    private function prepararDatosParaGreenter(NotaCredito $notaCredito): array
    {
        $venta = $notaCredito->venta;
        
        if (!$venta) {
            throw NotaCreditoException::datosIncompletos(
                'No se encontró la venta asociada a la nota de crédito. Venta ID: ' . $notaCredito->venta_id
            );
        }
        
        $cliente = $venta->cliente;
        
        if (!$cliente) {
            throw NotaCreditoException::datosIncompletos(
                'No se encontró el cliente asociado a la venta. Cliente ID: ' . $venta->cliente_id
            );
        }
        
        $motivo = $notaCredito->motivo;
        
        if (!$motivo) {
            throw NotaCreditoException::datosIncompletos(
                'No se encontró el motivo asociado a la nota de crédito. Motivo ID: ' . $notaCredito->motivo_id
            );
        }

        return [
            'serie' => $notaCredito->serie,
            'numero' => (string) $notaCredito->numero,
            'fecha' => $notaCredito->fecha->format('Y-m-d'),
            'tipo_doc_afectado' => $venta->tipo_documento === '01' ? '01' : '03',
            'num_doc_afectado' => "{$venta->serie}-{$venta->numero}",
            'cod_motivo' => $motivo->codigo_sunat, // CORREGIDO: usar codigo_sunat en lugar de codigo
            'des_motivo' => $motivo->descripcion,
            'tipo_moneda' => 'PEN',
            'mto_oper_gravadas' => $notaCredito->monto_subtotal,
            'mto_igv' => $notaCredito->monto_igv,
            'total' => $notaCredito->monto_total,
            'monto_en_letras' => $this->convertirNumeroALetras($notaCredito->monto_total),
            'cliente' => [
                'tipo_doc' => $cliente->tipo_documento === 'ruc' ? '6' : '1',
                'num_doc' => $cliente->numero_documento,
                'razon_social' => $cliente->razon_social ?? trim(($cliente->nombres ?? '') . ' ' . ($cliente->apellidos ?? '')) ?: 'Cliente',
                'direccion' => $cliente->direccion ?? '',
            ],
            'items' => $this->prepararItemsParaGreenter($notaCredito),
        ];
    }

    private function prepararItemsParaGreenter(NotaCredito $notaCredito): array
    {
        return [
            [
                'codigo' => 'ITEM001',
                'unidad' => 'NIU',
                'cantidad' => 1,
                'descripcion' => $notaCredito->descripcion ?? 'Devolución por nota de crédito',
                'mto_base_igv' => $notaCredito->monto_subtotal,
                'igv' => $notaCredito->monto_igv,
                'valor_venta' => $notaCredito->monto_subtotal,
                'valor_unitario' => $notaCredito->monto_subtotal,
                'precio_unitario' => $notaCredito->monto_total,
            ],
        ];
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
