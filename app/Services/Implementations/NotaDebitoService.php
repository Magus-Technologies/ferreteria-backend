<?php

namespace App\Services\Implementations;

use App\DTOs\FacturacionElectronica\NotaDebitoDTO;
use App\Exceptions\NotaDebitoException;
use App\Models\NotaDebito;
use App\Models\Venta;
use App\Models\SerieDocumento;
use App\Repositories\Interfaces\NotaDebitoRepositoryInterface;
use App\Repositories\Interfaces\ComprobanteElectronicoRepositoryInterface;
use App\Repositories\Interfaces\MotivoNotaRepositoryInterface;
use App\Services\Interfaces\NotaDebitoServiceInterface;
use App\Services\Interfaces\GreenterServiceInterface;
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
        private GreenterServiceInterface $greenterService,
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

            // 3. Obtener serie y número
            $serie = $this->obtenerSerie($dto->serie, $dto->almacenId);
            $numero = $dto->numero ?? $this->notaDebitoRepository->getSiguienteNumero($serie->serie);

            // 4. Verificar que no exista duplicado
            if ($this->notaDebitoRepository->existeSerieNumero($serie->serie, $numero)) {
                throw NotaDebitoException::datosIncompletos("Ya existe una nota de débito con serie {$serie->serie} y número {$numero}");
            }

            // 5. Calcular totales
            $totales = $this->calcularTotales($dto->items ?? []);

            // 6. Crear nota de débito
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

            // 7. Actualizar correlativo de serie
            $serie->increment('correlativo');

            DB::commit();

            Log::info('Nota de débito creada exitosamente', [
                'nota_debito_id' => $notaDebito->id,
                'serie' => $notaDebito->serie,
                'numero' => $notaDebito->numero,
            ]);

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

            Log::info('Nota de débito actualizada', ['nota_debito_id' => $id]);

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

            Log::info('Nota de débito cancelada', [
                'nota_debito_id' => $id,
                'motivo' => $motivo,
            ]);

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
            $resultado = $this->greenterService->generarYEnviarNotaDebito($dataGreenter);

            if (!$resultado['success']) {
                throw NotaDebitoException::notaDebitoNoEnviable('Error al generar XML o enviar a SUNAT');
            }

            // Guardar archivos XML y CDR
            $ruc = config('greenter.ruc');
            $nombreXml = $this->xmlStorageService->generarNombreXml($ruc, '08', $notaDebito->serie, $notaDebito->numero);
            $nombreCdr = $this->xmlStorageService->generarNombreCdr($ruc, '08', $notaDebito->serie, $notaDebito->numero);

            $xmlPath = $this->xmlStorageService->guardarXml($resultado['xml'], $nombreXml);
            $cdrPath = $this->xmlStorageService->guardarCdr(base64_decode($resultado['cdr']), $nombreCdr);

            // Crear o actualizar comprobante electrónico
            $comprobante = $this->comprobanteRepository->findByDocumento('nd', $notaDebito->id);

            if (!$comprobante) {
                $comprobante = $this->comprobanteRepository->create([
                    'tipo_documento' => 'nd',
                    'documento_id' => $notaDebito->id,
                    'serie' => $notaDebito->serie,
                    'numero' => $notaDebito->numero,
                    'fecha_emision' => $notaDebito->fecha,
                    'estado_sunat' => 'enviado',
                    'xml_path' => $xmlPath,
                    'cdr_path' => $cdrPath,
                    'hash_cpe' => $resultado['hash_cpe'],
                    'hash_cdr' => $resultado['hash_cdr'] ?? null,
                    'codigo_sunat' => $resultado['codigo_sunat'] ?? null,
                    'mensaje_sunat' => $resultado['mensaje_sunat'] ?? null,
                    'fecha_envio_sunat' => now(),
                ]);
            } else {
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

            Log::info('Nota de débito enviada a SUNAT', [
                'nota_debito_id' => $id,
                'modo' => $resultado['modo'] ?? 'DESCONOCIDO',
                'modo_envio' => $modoEnvio,
            ]);

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

        $ruc = config('greenter.ruc');
        
        return $this->greenterService->consultarEstado(
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

        // Validar que la venta esté completada
        if ($venta->estado !== 'completado') {
            return [
                'valido' => false,
                'mensaje' => 'La venta debe estar completada',
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

        if ($venta->estado !== 'completado') {
            throw NotaDebitoException::ventaNoValida('La venta debe estar completada');
        }

        return $venta;
    }

    private function validarYObtenerMotivo(int $motivoId)
    {
        $motivo = $this->motivoRepository->findById($motivoId);

        if (!$motivo) {
            throw NotaDebitoException::motivoNoEncontrado($motivoId);
        }

        if (!$motivo->esNotaDebito()) {
            throw NotaDebitoException::motivoNoValido('El motivo debe ser de tipo débito');
        }

        if (!$motivo->activo) {
            throw NotaDebitoException::motivoNoValido('El motivo no está activo');
        }

        return $motivo;
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
            'cod_motivo' => $motivo->codigo,
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
        // Implementación básica - se puede mejorar con una librería
        $entero = floor($numero);
        $decimales = round(($numero - $entero) * 100);

        return "SON: " . strtoupper($this->numeroALetrasBasico($entero)) . " CON {$decimales}/100 SOLES";
    }

    private function numeroALetrasBasico(int $numero): string
    {
        // Implementación muy básica - se recomienda usar una librería como luecano/numero-a-letras
        if ($numero === 0) return "CERO";
        if ($numero === 1) return "UNO";
        if ($numero < 100) return "VARIOS";
        if ($numero < 1000) return "CIENTOS";
        return "MILES";
    }
}
