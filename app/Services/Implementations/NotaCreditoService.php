<?php

namespace App\Services\Implementations;

use App\DTOs\FacturacionElectronica\NotaCreditoDTO;
use App\Exceptions\NotaCreditoException;
use App\Models\NotaCredito;
use App\Models\Venta;
use App\Models\SerieDocumento;
use App\Repositories\Interfaces\NotaCreditoRepositoryInterface;
use App\Repositories\Interfaces\ComprobanteElectronicoRepositoryInterface;
use App\Repositories\Interfaces\MotivoNotaRepositoryInterface;
use App\Services\Interfaces\NotaCreditoServiceInterface;
use App\Services\Interfaces\GreenterServiceInterface;
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
        private GreenterServiceInterface $greenterService,
        private XmlStorageServiceInterface $xmlStorageService
    ) {}

    public function crear(NotaCreditoDTO $dto): NotaCredito
    {
        try {
            DB::beginTransaction();

            $venta = $this->validarYObtenerVenta($dto->ventaId);
            $motivo = $this->validarYObtenerMotivo($dto->motivoId);
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

            DB::commit();

            Log::info('Nota de crédito creada exitosamente', [
                'nota_credito_id' => $notaCredito->id,
                'serie' => $notaCredito->serie,
                'numero' => $notaCredito->numero,
            ]);

            return $notaCredito->fresh(['venta', 'motivo', 'usuario', 'almacen']);

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

            Log::info('Nota de crédito actualizada', ['nota_credito_id' => $id]);

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

            Log::info('Nota de crédito cancelada', [
                'nota_credito_id' => $id,
                'motivo' => $motivo,
            ]);

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

            $dataGreenter = $this->prepararDatosParaGreenter($notaCredito);
            $resultado = $this->greenterService->generarYEnviarNotaCredito($dataGreenter);

            if (!$resultado['success']) {
                throw NotaCreditoException::notaCreditoNoEnviable('Error al generar XML o enviar a SUNAT');
            }

            $ruc = config('greenter.ruc');
            $nombreXml = $this->xmlStorageService->generarNombreXml($ruc, '07', $notaCredito->serie, $notaCredito->numero);
            $nombreCdr = $this->xmlStorageService->generarNombreCdr($ruc, '07', $notaCredito->serie, $notaCredito->numero);

            $xmlPath = $this->xmlStorageService->guardarXml($resultado['xml'], $nombreXml);
            $cdrPath = $this->xmlStorageService->guardarCdr($resultado['cdr'], $nombreCdr);

            $comprobante = $this->comprobanteRepository->findByDocumento('nc', $notaCredito->id);

            if (!$comprobante) {
                $comprobante = $this->comprobanteRepository->create([
                    'tipo_documento' => 'nc',
                    'documento_id' => $notaCredito->id,
                    'serie' => $notaCredito->serie,
                    'numero' => $notaCredito->numero,
                    'fecha_emision' => $notaCredito->fecha,
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

            Log::info('Nota de crédito enviada a SUNAT', [
                'nota_credito_id' => $id,
                'modo' => $resultado['modo'] ?? 'DESCONOCIDO',
                'modo_envio' => $modoEnvio,
            ]);

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

        $ruc = config('greenter.ruc');
        
        return $this->greenterService->consultarEstado(
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

        if (!$comprobante || !$comprobante->xml_path) {
            throw NotaCreditoException::datosIncompletos('XML no disponible');
        }

        return $this->xmlStorageService->obtenerXml($comprobante->xml_path);
    }

    public function obtenerCdr(string $id): string
    {
        $notaCredito = $this->notaCreditoRepository->findById($id);

        if (!$notaCredito) {
            throw NotaCreditoException::notaCreditoNoEncontrada($id);
        }

        $comprobante = $notaCredito->comprobanteElectronico;

        if (!$comprobante || !$comprobante->cdr_path) {
            throw NotaCreditoException::datosIncompletos('CDR no disponible');
        }

        return $this->xmlStorageService->obtenerCdr($comprobante->cdr_path);
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

        if ($venta->estado !== 'completado') {
            return [
                'valido' => false,
                'mensaje' => 'La venta debe estar completada',
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

        if ($venta->estado !== 'completado') {
            throw NotaCreditoException::ventaNoValida('La venta debe estar completada');
        }

        return $venta;
    }

    private function validarYObtenerMotivo(int $motivoId)
    {
        $motivo = $this->motivoRepository->findById($motivoId);

        if (!$motivo) {
            throw NotaCreditoException::motivoNoEncontrado($motivoId);
        }

        if (!$motivo->esNotaCredito()) {
            throw NotaCreditoException::motivoNoValido('El motivo debe ser de tipo crédito');
        }

        if (!$motivo->activo) {
            throw NotaCreditoException::motivoNoValido('El motivo no está activo');
        }

        return $motivo;
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
        $cliente = $venta->cliente;
        $motivo = $notaCredito->motivo;

        return [
            'serie' => $notaCredito->serie,
            'numero' => (string) $notaCredito->numero,
            'fecha' => $notaCredito->fecha->format('Y-m-d'),
            'tipo_doc_afectado' => $venta->tipo_documento === '01' ? '01' : '03',
            'num_doc_afectado' => "{$venta->serie}-{$venta->numero}",
            'cod_motivo' => $motivo->codigo,
            'des_motivo' => $motivo->descripcion,
            'tipo_moneda' => 'PEN',
            'mto_oper_gravadas' => $notaCredito->monto_subtotal,
            'mto_igv' => $notaCredito->monto_igv,
            'total' => $notaCredito->monto_total,
            'monto_en_letras' => $this->convertirNumeroALetras($notaCredito->monto_total),
            'cliente' => [
                'tipo_doc' => $cliente->tipo_documento === 'ruc' ? '6' : '1',
                'num_doc' => $cliente->numero_documento,
                'razon_social' => $cliente->razon_social ?? $cliente->nombre,
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
