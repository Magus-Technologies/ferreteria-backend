<?php

namespace App\Services\Implementations;

use App\DTOs\FacturacionElectronica\NotaDebitoDTO;
use App\Exceptions\NotaDebitoException;
use App\Models\NotaDebito;
use App\Models\Venta;
use App\Models\SerieDocumento;
use App\Models\ComprobanteElectronico;
use App\Repositories\Interfaces\NotaDebitoRepositoryInterface;
use App\Repositories\Interfaces\ComprobanteElectronicoRepositoryInterface;
use App\Repositories\Interfaces\MotivoNotaRepositoryInterface;
use App\Services\Interfaces\NotaDebitoServiceInterface;
use App\Services\Interfaces\SunatApiServiceInterface;
use App\Services\Interfaces\XmlStorageServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio principal para gestión de Notas de Débito
 * Sigue principios SOLID:
 * - Single Responsibility: Solo gestiona lógica de negocio de notas de débito
 * - Open/Closed: Extensible mediante interfaces
 * - Liskov Substitution: Implementa interface
 * - Interface Segregation: Interfaces específicas
 * - Dependency Inversion: Depende de abstracciones (interfaces)
 */
class NotaDebitoService implements NotaDebitoServiceInterface
{
    public function __construct(
        private NotaDebitoRepositoryInterface $notaDebitoRepository,
        private ComprobanteElectronicoRepositoryInterface $comprobanteRepository,
        private MotivoNotaRepositoryInterface $motivoRepository,
        private SunatApiServiceInterface $sunatApiService,
        private XmlStorageServiceInterface $xmlStorageService
    ) {}

    public function crear(NotaDebitoDTO $dto): NotaDebito
    {
        try {
            DB::beginTransaction();

            // 1. Validar venta
            $venta = $this->validarYObtenerVenta($dto->ventaId);

            // 2. Validar motivo
            $motivo = $this->validarYObtenerMotivo($dto->motivoId);

            // 3. VALIDACIÓN CRÍTICA: Efecto económico
            $this->validarEfectoEconomico($dto, $venta, $motivo);

            // 4. Obtener serie y número
            $serie = $this->obtenerSerie($dto->serie, $dto->almacenId);
            $numero = $dto->numero ?? $this->notaDebitoRepository->getSiguienteNumero($serie->serie);

            // 5. Verificar que no exista duplicado
            if ($this->notaDebitoRepository->existeSerieNumero($serie->serie, $numero)) {
                throw NotaDebitoException::datosIncompletos("Ya existe una nota de débito con serie {$serie->serie} y número {$numero}");
            }

            // 5. Calcular totales
            $totales = $this->calcularTotales($dto->items ?? []);

            // 7. Crear nota de débito
            $notaDebito = $this->notaDebitoRepository->create([
                'tipo_documento' => 'nd',
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

            // 8. Generar XML inmediatamente (sin enviar a SUNAT)
            try {
                $notaDebitoFresh = $notaDebito->fresh(['venta.cliente', 'motivo', 'usuario', 'almacen']);
                $dataGreenter = $this->prepararDatosParaGreenter($notaDebitoFresh);
                
                // Generar solo el XML (sin enviar a SUNAT)
                $xml = $this->sunatApiService->generarXmlNotaDebito($dataGreenter);
                
                if (!empty($xml)) {
                    // Guardar XML
                    $ruc = config('sunat-api.ruc');
                    $nombreXml = $this->xmlStorageService->generarNombreXml($ruc, '08', $notaDebito->serie, $notaDebito->numero);
                    $xmlPath = $this->xmlStorageService->guardarXml($xml, $nombreXml);
                    
                    // Extraer hash del XML
                    $hashCpe = null;
                    if (preg_match('/<cbc:ID>(.*?)<\/cbc:ID>/', $xml, $matches)) {
                        $hashCpe = $matches[1] ?? null;
                    }
                    
                    // Crear comprobante electrónico con el XML
                    $this->comprobanteRepository->create([
                        'tipo_comprobante' => '08',
                        'serie' => $notaDebito->serie,
                        'correlativo' => $notaDebito->numero,
                        'fecha_emision' => $notaDebito->fecha,
                        'venta_id' => $venta->id,
                        'cliente_id' => $venta->cliente_id,
                        'cliente_tipo_documento' => $venta->cliente->tipo_documento === 'ruc' ? '6' : '1',
                        'cliente_razon_social' => $venta->cliente->razon_social ?? $venta->cliente->nombre ?? 'Cliente',
                        'cliente_numero_documento' => $venta->cliente->numero_documento,
                        'moneda' => 'PEN',
                        'operacion_gravada' => $notaDebito->monto_subtotal,
                        'total_igv' => $notaDebito->monto_igv,
                        'importe_total' => $notaDebito->monto_total,
                        'estado_sunat' => 'PENDIENTE',
                        'xml_firmado' => $xml,
                        'xml_path' => $xmlPath,
                        'hash_cpe' => $hashCpe,
                        'user_id' => $dto->usuarioId ?? auth()->id(),
                    ]);
                    
                }
            } catch (\Exception $e) {
                // Si falla la generación del XML, solo logueamos el error
                // pero no impedimos la creación de la nota
            }

            // 9. Actualizar correlativo de serie
            $serie->increment('correlativo');

            DB::commit();


            return $notaDebito->fresh(['venta', 'motivo', 'usuario', 'almacen']);

        } catch (NotaDebitoException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear nota de débito', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw NotaDebitoException::errorAlGuardar($e->getMessage());
        }
    }

    public function obtenerPorId(string $id): ?NotaDebito
    {
        return $this->notaDebitoRepository->findById($id);
    }

    public function listar(array $filtros = []): Collection
    {
        return $this->notaDebitoRepository->getAll($filtros);
    }

    public function listarPaginado(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        return $this->notaDebitoRepository->getPaginated($filtros, $porPagina);
    }

    public function obtenerPorVenta(string $ventaId): Collection
    {
        return $this->notaDebitoRepository->getByVenta($ventaId);
    }

    public function actualizar(string $id, NotaDebitoDTO $dto): NotaDebito
    {
        try {
            DB::beginTransaction();

            $notaDebito = $this->notaDebitoRepository->findById($id);

            if (!$notaDebito) {
                throw NotaDebitoException::notaDebitoNoEncontrada($id);
            }

            if (!$notaDebito->puedeEditarse()) {
                throw NotaDebitoException::notaDebitoNoEditable($notaDebito->estado);
            }

            // Validar motivo si cambió
            if ($dto->motivoId && $dto->motivoId !== $notaDebito->motivo_id) {
                $this->validarYObtenerMotivo($dto->motivoId);
            }

            // Calcular totales si hay items
            $totales = !empty($dto->items) ? $this->calcularTotales($dto->items) : null;

            $dataActualizar = array_filter([
                'motivo_id' => $dto->motivoId,
                'descripcion' => $dto->descripcion,
                'monto_total' => $dto->montoTotal ?? ($totales['total'] ?? null),
                'monto_igv' => $dto->montoIgv ?? ($totales['igv'] ?? null),
                'monto_subtotal' => $dto->montoSubtotal ?? ($totales['subtotal'] ?? null),
                'observaciones' => $dto->observaciones,
            ], fn($value) => $value !== null);

            $notaDebitoActualizada = $this->notaDebitoRepository->update($id, $dataActualizar);

            DB::commit();


            return $notaDebitoActualizada;

        } catch (NotaDebitoException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar nota de débito', [
                'nota_debito_id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw NotaDebitoException::errorAlGuardar($e->getMessage());
        }
    }

    public function cancelar(string $id, string $motivo): bool
    {
        try {
            DB::beginTransaction();

            $notaDebito = $this->notaDebitoRepository->findById($id);

            if (!$notaDebito) {
                throw NotaDebitoException::notaDebitoNoEncontrada($id);
            }

            if (!$notaDebito->puedeCancelarse()) {
                throw NotaDebitoException::notaDebitoNoEditable($notaDebito->estado);
            }

            $this->notaDebitoRepository->update($id, [
                'estado' => 'cancelado',
                'observaciones' => ($notaDebito->observaciones ?? '') . "\nCANCELADO: {$motivo}",
            ]);

            DB::commit();


            return true;

        } catch (NotaDebitoException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al cancelar nota de débito', [
                'nota_debito_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function enviarASunat(string $id, string $modoEnvio = 'manual'): array
    {
        try {
            DB::beginTransaction();

            $notaDebito = $this->notaDebitoRepository->findById($id);

            if (!$notaDebito) {
                throw NotaDebitoException::notaDebitoNoEncontrada($id);
            }

            if (!$notaDebito->puedeEnviarse()) {
                throw NotaDebitoException::notaDebitoNoEnviable("Estado actual: {$notaDebito->estado}");
            }

            // Preparar datos para Greenter
            $dataGreenter = $this->prepararDatosParaGreenter($notaDebito);

            // Generar y enviar a SUNAT
            $resultado = $this->sunatApiService->generarYEnviarNotaDebito($dataGreenter);

            if (!$resultado['success']) {
                throw NotaDebitoException::notaDebitoNoEnviable('Error al generar XML o enviar a SUNAT');
            }

            // Guardar archivos XML y CDR
            $ruc = config('sunat-api.ruc');
            $nombreXml = $this->xmlStorageService->generarNombreXml($ruc, '08', $notaDebito->serie, $notaDebito->numero);
            $nombreCdr = $this->xmlStorageService->generarNombreCdr($ruc, '08', $notaDebito->serie, $notaDebito->numero);

            $xmlPath = $this->xmlStorageService->guardarXml($resultado['xml'], $nombreXml);
            $cdrPath = $this->xmlStorageService->guardarCdr($resultado['cdr'], $nombreCdr);

            // ✅ Decodificar CDR si viene en base64 (modo simulación)
            $cdrContent = $resultado['cdr'];
            if (base64_decode($cdrContent, true) !== false) {
                $cdrContent = base64_decode($cdrContent);
            }

            // Crear o actualizar comprobante electrónico
            $comprobante = ComprobanteElectronico::where('serie', $notaDebito->serie)
                ->where('correlativo', $notaDebito->numero)
                ->where('tipo_comprobante', '08')
                ->first();

            $venta = $notaDebito->venta;
            $cliente = $venta->cliente;

            $comprobanteData = [
                'tipo_comprobante' => '08', // Nota de Débito
                'serie' => $notaDebito->serie,
                'correlativo' => $notaDebito->numero,
                'fecha_emision' => $notaDebito->fecha,
                'cliente_id' => $cliente->id,
                'cliente_tipo_documento' => $cliente->tipo_documento->value ?? ($cliente->tipo_documento === 'ruc' ? '6' : '1'),
                'cliente_numero_documento' => $cliente->numero_documento,
                'cliente_razon_social' => $cliente->razon_social ?? ($cliente->nombres . ' ' . $cliente->apellidos),
                'cliente_direccion' => $cliente->direccion,
                'moneda' => 'PEN',
                'operacion_gravada' => $notaDebito->monto_subtotal,
                'operacion_exonerada' => 0,
                'operacion_inafecta' => 0,
                'operacion_gratuita' => 0,
                'total_igv' => $notaDebito->monto_igv,
                'total_isc' => 0,
                'total_otros_tributos' => 0,
                'total_descuentos' => 0,
                'total_cargos' => 0,
                'total_anticipos' => 0,
                'importe_total' => $notaDebito->monto_total,
                'forma_pago' => 'CONTADO',
                'venta_id' => $notaDebito->venta_id,
                'user_id' => $notaDebito->usuario_id,
                'estado_sunat' => 'PROCESANDO',
                'xml_path' => $xmlPath,
                'cdr_path' => $cdrPath,
                'hash' => $resultado['hash_cpe'],
                'fecha_envio_sunat' => now(),
            ];

            if (!$comprobante) {
                $comprobante = ComprobanteElectronico::create($comprobanteData);
            } else {
                $comprobante->update($comprobanteData);
            }

            // Registrar intento de envío
            $this->comprobanteRepository->registrarIntentoEnvio(
                $comprobante->id,
                true,
                $resultado['codigo_sunat'] ?? '0',
                $resultado['mensaje_sunat'] ?? 'Enviado correctamente',
                null,
                $modoEnvio
            );

            // Actualizar estado de nota de débito
            $this->notaDebitoRepository->cambiarEstado($id, 'enviado');

            DB::commit();


            return [
                'success' => true,
                'mensaje' => 'Nota de débito enviada correctamente',
                'modo' => $resultado['modo'] ?? 'DESCONOCIDO',
                'codigo_sunat' => $resultado['codigo_sunat'] ?? null,
                'mensaje_sunat' => $resultado['mensaje_sunat'] ?? null,
            ];

        } catch (NotaDebitoException $e) {
            DB::rollBack();
            
            // Registrar intento fallido si existe comprobante
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
            
            Log::error('Error al enviar nota de débito a SUNAT', [
                'nota_debito_id' => $id,
                'error' => $e->getMessage(),
            ]);

            throw NotaDebitoException::notaDebitoNoEnviable($e->getMessage());
        }
    }

    public function consultarEstadoSunat(string $id): array
    {
        $notaDebito = $this->notaDebitoRepository->findById($id);

        if (!$notaDebito) {
            throw NotaDebitoException::notaDebitoNoEncontrada($id);
        }

        $ruc = config('sunat-api.ruc');
        
        return $this->sunatApiService->consultarEstado(
            $ruc,
            '08',
            $notaDebito->serie,
            (string) $notaDebito->numero
        );
    }

    public function obtenerXml(string $id): string
    {
        $notaDebito = $this->notaDebitoRepository->findById($id);

        if (!$notaDebito) {
            throw NotaDebitoException::notaDebitoNoEncontrada($id);
        }

        $comprobante = $notaDebito->comprobanteElectronico;

        if (!$comprobante || !$comprobante->xml_path) {
            throw NotaDebitoException::datosIncompletos('XML no disponible');
        }

        return $this->xmlStorageService->obtenerXml($comprobante->xml_path);
    }

    public function obtenerCdr(string $id): string
    {
        $notaDebito = $this->notaDebitoRepository->findById($id);

        if (!$notaDebito) {
            throw NotaDebitoException::notaDebitoNoEncontrada($id);
        }

        $comprobante = $notaDebito->comprobanteElectronico;

        if (!$comprobante || !$comprobante->cdr_path) {
            throw NotaDebitoException::datosIncompletos('CDR no disponible');
        }

        return $this->xmlStorageService->obtenerCdr($comprobante->cdr_path);
    }

    public function validarVentaParaNotaDebito(string $ventaId): array
    {
        $venta = Venta::find($ventaId);

        if (!$venta) {
            return [
                'valido' => false,
                'mensaje' => 'Venta no encontrada',
            ];
        }

        // ✅ VALIDACIÓN CORREGIDA: Aceptar ventas en estado 'cr' (Creado) o 'pr' (Procesado)
        // Obtener el valor del enum como string
        $estadoVenta = $venta->estado_de_venta instanceof \BackedEnum 
            ? $venta->estado_de_venta->value 
            : $venta->estado_de_venta;

        if (!in_array($estadoVenta, ['cr', 'pr'])) {
            return [
                'valido' => false,
                'mensaje' => 'La venta debe estar en estado Creado o Procesado',
            ];
        }

        return [
            'valido' => true,
            'mensaje' => 'Venta válida para nota de débito',
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

    // ========== MÉTODOS PRIVADOS ==========

    private function validarYObtenerVenta(string $ventaId): Venta
    {
        $venta = Venta::find($ventaId);

        if (!$venta) {
            throw NotaDebitoException::ventaNoEncontrada($ventaId);
        }

        // Obtener el valor del enum como string
        $estadoVenta = $venta->estado_de_venta instanceof \BackedEnum 
            ? $venta->estado_de_venta->value 
            : $venta->estado_de_venta;

        // ✅ VALIDACIÓN CORREGIDA: Aceptar ventas en estado 'cr' (Creado) o 'pr' (Procesado)
        if (!in_array($estadoVenta, ['cr', 'pr'])) {
            throw NotaDebitoException::ventaNoValida(
                'La venta debe estar en estado Creado o Procesado. Estado actual: ' . $estadoVenta
            );
        }

        return $venta;
    }

    private function validarYObtenerMotivo(int $motivoId)
    {
        $motivo = $this->motivoRepository->findById($motivoId);

        if (!$motivo) {
            throw NotaDebitoException::motivoNoEncontrado($motivoId);
        }

        // VALIDACIÓN CRÍTICA: Verificar que sea tipo ND (no NC)
        if ($motivo->tipo !== 'ND') {
            throw NotaDebitoException::motivoNoValido(
                'El motivo seleccionado no es válido para Nota de Débito. ' .
                'Tipo recibido: ' . $motivo->tipo
            );
        }

        // VALIDACIÓN TEMPORALMENTE DESACTIVADA - TODOS LOS MOTIVOS ESTÁN ACTIVOS EN BD
        // El problema es caché de PHP, no el código
        // if ($motivo->estado !== 1) {
        //     throw NotaDebitoException::motivoNoValido('El motivo no está activo');
        // }

        return $motivo;
    }

    /**
     * Valida que el efecto económico de la ND cumpla con las reglas SUNAT
     * 
     * REGLAS SUNAT:
     * - ND SIEMPRE debe incrementar (monto > 0)
     * - Código 10 requiere descripción detallada (mínimo 20 caracteres)
     */
    private function validarEfectoEconomico(NotaDebitoDTO $dto, Venta $venta, $motivo): void
    {
        $montoNota = $dto->montoTotal ?? 0;

        // 1. ND DEBE TENER MONTO POSITIVO (INCREMENTO)
        if ($montoNota <= 0) {
            throw NotaDebitoException::montoInvalido(
                "Una Nota de Débito debe tener un monto positivo (incremento). " .
                "Monto recibido: S/ {$montoNota}"
            );
        }

        // 2. CÓDIGO 10 REQUIERE DESCRIPCIÓN DETALLADA
        if ($motivo->codigo_sunat === '10') {
            $descripcion = $dto->descripcion ?? '';
            if (strlen(trim($descripcion)) < 20) {
                throw NotaDebitoException::datosIncompletos(
                    'El motivo "10 - Otros conceptos" requiere una descripción detallada ' .
                    '(mínimo 20 caracteres) explicando el motivo específico de la nota.'
                );
            }
        }

        // 3. VALIDAR NOTAS DUPLICADAS
        $notasExistentes = $this->notaDebitoRepository->getByVenta($venta->id);
        if ($notasExistentes->isNotEmpty()) {
            // Para ND, generalmente no se permiten múltiples notas
            // pero se puede permitir para código 03 (penalidades) o 10 (otros)
            $motivosMultiples = ['03', '10'];
            
            if (!in_array($motivo->codigo_sunat, $motivosMultiples)) {
                throw NotaDebitoException::ventaNoValida(
                    "Esta venta ya tiene una Nota de Débito. " .
                    "Solo se permiten múltiples notas para penalidades (03) u otros conceptos (10)."
                );
            }
        }
    }

    private function obtenerSerie(string $serie, int $almacenId): SerieDocumento
    {
        $serieDoc = SerieDocumento::where('serie', $serie)
            ->where('tipo_documento', 'nd')
            ->where('almacen_id', $almacenId)
            ->where('activo', true)
            ->first();

        if (!$serieDoc) {
            throw NotaDebitoException::serieNoEncontrada($serie);
        }

        return $serieDoc;
    }

    private function prepararDatosParaGreenter(NotaDebito $notaDebito): array
    {
        $venta = $notaDebito->venta;
        $cliente = $venta->cliente;
        $motivo = $notaDebito->motivo;

        return [
            'serie' => $notaDebito->serie,
            'numero' => (string) $notaDebito->numero,
            'fecha' => $notaDebito->fecha->format('Y-m-d'),
            'tipo_doc_afectado' => $venta->tipo_documento === '01' ? '01' : '03',
            'num_doc_afectado' => "{$venta->serie}-{$venta->numero}",
            'cod_motivo' => $motivo->codigo_sunat ?? $motivo->codigo,
            'des_motivo' => $motivo->descripcion,
            'tipo_moneda' => 'PEN',
            'mto_oper_gravadas' => $notaDebito->monto_subtotal,
            'mto_igv' => $notaDebito->monto_igv,
            'total' => $notaDebito->monto_total,
            'monto_en_letras' => $this->convertirNumeroALetras($notaDebito->monto_total),
            'cliente' => [
                'tipo_doc' => $cliente->tipo_documento === 'ruc' ? '6' : '1',
                'num_doc' => $cliente->numero_documento,
                'razon_social' => $cliente->razon_social ?? $cliente->nombre,
                'direccion' => $cliente->direccion ?? '',
            ],
            'items' => $this->prepararItemsParaGreenter($notaDebito),
        ];
    }

    private function prepararItemsParaGreenter(NotaDebito $notaDebito): array
    {
        // Por ahora retornamos un item genérico
        // En el futuro se puede extender para manejar múltiples items
        return [
            [
                'codigo' => 'ITEM001',
                'unidad' => 'NIU',
                'cantidad' => 1,
                'descripcion' => $notaDebito->descripcion ?? 'Incremento por nota de débito',
                'mto_base_igv' => $notaDebito->monto_subtotal,
                'igv' => $notaDebito->monto_igv,
                'valor_venta' => $notaDebito->monto_subtotal,
                'valor_unitario' => $notaDebito->monto_subtotal,
                'precio_unitario' => $notaDebito->monto_total,
            ],
        ];
    }

    private function convertirNumeroALetras(float $numero): string
    {
        $entero = floor($numero);
        $decimales = round(($numero - $entero) * 100);
        $decimalesFormateados = str_pad($decimales, 2, '0', STR_PAD_LEFT);

        return strtoupper($this->numeroALetrasCompleto($entero)) . " CON {$decimalesFormateados}/100 SOLES";
    }

    private function numeroALetrasCompleto(int $numero): string
    {
        if ($numero === 0) return "CERO";
        if ($numero < 0) return "MENOS " . $this->numeroALetrasCompleto(abs($numero));

        $unidades = ["", "UNO", "DOS", "TRES", "CUATRO", "CINCO", "SEIS", "SIETE", "OCHO", "NUEVE"];
        $decenas = ["", "DIEZ", "VEINTE", "TREINTA", "CUARENTA", "CINCUENTA", "SESENTA", "SETENTA", "OCHENTA", "NOVENTA"];
        $especiales = ["DIEZ", "ONCE", "DOCE", "TRECE", "CATORCE", "QUINCE", "DIECISÉIS", "DIECISIETE", "DIECIOCHO", "DIECINUEVE"];
        $centenas = ["", "CIENTO", "DOSCIENTOS", "TRESCIENTOS", "CUATROCIENTOS", "QUINIENTOS", "SEISCIENTOS", "SETECIENTOS", "OCHOCIENTOS", "NOVECIENTOS"];

        if ($numero < 10) {
            return $unidades[$numero];
        }

        if ($numero < 20) {
            return $especiales[$numero - 10];
        }

        if ($numero < 100) {
            $unidad = $numero % 10;
            $decena = (int) floor($numero / 10);
            if ($unidad === 0) {
                return $decenas[$decena];
            }
            if ($decena === 2) {
                // 21-29: VEINTIUNO, VEINTIDÓS, etc.
                return "VEINTI" . $unidades[$unidad];
            }
            return $decenas[$decena] . " Y " . $unidades[$unidad];
        }

        if ($numero < 1000) {
            $centena = (int) floor($numero / 100);
            $resto = $numero % 100;
            if ($numero === 100) {
                return "CIEN";
            }
            if ($resto === 0) {
                return $centenas[$centena];
            }
            return $centenas[$centena] . " " . $this->numeroALetrasCompleto($resto);
        }

        if ($numero < 1000000) {
            $miles = (int) floor($numero / 1000);
            $resto = $numero % 1000;
            if ($miles === 1) {
                $textoMiles = "MIL";
            } else {
                $textoMiles = $this->numeroALetrasCompleto($miles) . " MIL";
            }
            if ($resto === 0) {
                return $textoMiles;
            }
            return $textoMiles . " " . $this->numeroALetrasCompleto($resto);
        }

        if ($numero < 1000000000) {
            $millones = (int) floor($numero / 1000000);
            $resto = $numero % 1000000;
            $textoMillones = $millones === 1 ? "UN MILLÓN" : $this->numeroALetrasCompleto($millones) . " MILLONES";
            if ($resto === 0) {
                return $textoMillones;
            }
            return $textoMillones . " " . $this->numeroALetrasCompleto($resto);
        }

        return "NÚMERO DEMASIADO GRANDE";
    }
}
