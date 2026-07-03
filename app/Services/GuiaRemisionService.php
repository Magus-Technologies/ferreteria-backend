<?php

namespace App\Services;

use App\Models\GuiaRemision;
use App\Models\DetalleGuiaRemision;
use App\Models\ProductoAlmacen;
use App\Models\Transportista;
use App\Services\Interfaces\SunatApiServiceInterface;
use App\Services\Interfaces\XmlStorageServiceInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GuiaRemisionService
{
    public function __construct(
        private SunatApiServiceInterface $sunatApiService,
        private XmlStorageServiceInterface $xmlStorageService,
    ) {}
    /**
     * Crear una nueva guía de remisión con sus detalles
     */
    public function crear(array $data): GuiaRemision
    {
        return DB::transaction(function () use ($data) {
            // Generar ULID para la guía
            $guiaId = (string) Str::ulid();

            // Auto-generar serie y número si no se proporcionan
            if (empty($data['serie']) || empty($data['numero'])) {
                $tipoGuia = $data['tipo_guia'] ?? 'ELECTRONICA_REMITENTE';
                $serie = match ($tipoGuia) {
                    'ELECTRONICA_TRANSPORTISTA' => 'V001',
                    'FISICA' => 'TF01',
                    default => 'T001', // ELECTRONICA_REMITENTE
                };
                $maxNumero = GuiaRemision::where('serie', $serie)->max('numero') ?? 0;
                $data['serie'] = $serie;
                $data['numero'] = $maxNumero + 1;
            }

            // Crear guía de remisión
            $guia = GuiaRemision::create([
                'id' => $guiaId,
                'venta_id' => $data['venta_id'] ?? null,
                'entrega_id' => $data['entrega_id'] ?? null,
                'tipo_guia' => $data['tipo_guia'],
                'serie' => $data['serie'],
                'numero' => $data['numero'],
                'fecha_emision' => $data['fecha_emision'],
                'fecha_traslado' => $data['fecha_traslado'],
                'afecta_stock' => $data['afecta_stock'] ?? false,
                'cliente_id' => $data['cliente_id'] ?? null,
                'comprador_id' => $data['comprador_id'] ?? null,
                'motivo_traslado_id' => $data['motivo_traslado_id'],
                'modalidad_transporte' => $data['modalidad_transporte'],
                'transportista_ruc' => $data['transportista_ruc'] ?? null,
                'transportista_razon_social' => $data['transportista_razon_social'] ?? null,
                'transportista_nro_mtc' => $data['transportista_nro_mtc'] ?? null,
                'vehiculo_placa' => $data['vehiculo_placa'] ?? null,
                'chofer_id' => $data['chofer_id'] ?? null,
                'punto_partida' => $data['punto_partida'],
                'punto_llegada' => $data['punto_llegada'],
                'almacen_origen_id' => $data['almacen_origen_id'],
                'almacen_destino_id' => $data['almacen_destino_id'] ?? null,
                'referencia' => $data['referencia'] ?? null,
                'observaciones' => $data['observaciones'] ?? null,
                'estado' => 'BORRADOR',
                'user_id' => $data['user_id'],
            ]);

            // Auto-registrar/actualizar en el catálogo de transportistas
            // (razon_social es NOT NULL en el catálogo, así que exigimos ambos datos)
            if (!empty($data['transportista_ruc']) && !empty($data['transportista_razon_social'])) {
                Transportista::updateOrCreate(
                    ['ruc' => $data['transportista_ruc']],
                    [
                        'razon_social' => $data['transportista_razon_social'] ?? null,
                        'nro_mtc' => $data['transportista_nro_mtc'] ?? null,
                    ]
                );
            }

            // Crear detalles de la guía
            $this->crearDetalles($guiaId, $data['detalles'], $data['entrega_id'] ?? null);

            // Si afecta stock, descontar del almacén de origen
            if ($data['afecta_stock'] ?? false) {
                $this->afectarStock($data['detalles'], 'descontar');
            }

            return $guia->load([
                'venta',
                'cliente',
                'comprador',
                'motivoTraslado',
                'chofer',
                'almacenOrigen',
                'almacenDestino',
                'user',
                'detalles.producto.marca',
                'detalles.unidadDerivadaInmutable',
            ]);
        });
    }

    /**
     * Actualizar una guía de remisión (solo si está en BORRADOR)
     */
    public function actualizar(GuiaRemision $guia, array $data): GuiaRemision
    {
        if (!$guia->puedeEditarse()) {
            throw new \Exception('Solo se pueden editar guías en estado BORRADOR');
        }

        $guia->update($data);

        return $guia->fresh([
            'venta',
            'cliente',
            'motivoTraslado',
            'chofer',
            'almacenOrigen',
            'almacenDestino',
            'user',
            'detalles.producto.marca',
            'detalles.unidadDerivadaInmutable',
        ]);
    }

    /**
     * Emitir una guía (cambiar estado de BORRADOR a EMITIDA)
     */
    public function emitir(GuiaRemision $guia): GuiaRemision
    {
        return DB::transaction(function () use ($guia) {
            if ($guia->estado !== 'BORRADOR') {
                throw new \Exception('Solo se pueden emitir guías en estado BORRADOR');
            }

            $guia->update(['estado' => 'EMITIDA']);

            // Generar XML y QR automáticamente para guías electrónicas
            if ($guia->tipo_guia !== 'FISICA') {
                try {
                    $this->generarXml($guia);
                } catch (\Exception $e) {
                }
            }

            return $guia->fresh([
                'venta',
                'cliente',
                'motivoTraslado',
                'chofer',
                'almacenOrigen',
                'almacenDestino',
                'user',
                'detalles.producto.marca',
                'detalles.unidadDerivadaInmutable',
            ]);
        });
    }

    /**
     * Anular una guía (cambiar estado de EMITIDA a ANULADA)
     */
    public function anular(GuiaRemision $guia, string $motivoAnulacion): GuiaRemision
    {
        return DB::transaction(function () use ($guia, $motivoAnulacion) {
            if (!$guia->puedeAnularse()) {
                throw new \Exception('Solo se pueden anular guías en estado EMITIDA');
            }

            // Si afectó stock, revertir el descuento
            if ($guia->afecta_stock) {
                $detalles = $guia->detalles->map(function ($detalle) {
                    return [
                        'producto_almacen_id' => $detalle->producto_almacen_id,
                        'cantidad' => $detalle->cantidad,
                        'factor' => $detalle->factor,
                    ];
                })->toArray();

                $this->afectarStock($detalles, 'incrementar');
            }

            // Revertir cantidad_guiada en las líneas de venta (y en la entrega
            // de origen, si la guía nació de una).
            foreach ($guia->detalles as $detalle) {
                if ($detalle->unidad_derivada_venta_id) {
                    DB::table('unidadderivadainmutableventa')
                        ->where('id', $detalle->unidad_derivada_venta_id)
                        ->decrement('cantidad_guiada', (float) $detalle->cantidad);

                    if ($guia->entrega_id) {
                        DB::table('entrega_detalle')
                            ->where('entrega_id', $guia->entrega_id)
                            ->where('unidad_derivada_venta_id', $detalle->unidad_derivada_venta_id)
                            ->decrement('cantidad_guiada', (float) $detalle->cantidad);
                    }
                }
            }

            $guia->update([
                'estado' => 'ANULADA',
                'fecha_anulacion' => now(),
                'motivo_anulacion' => $motivoAnulacion,
            ]);

            return $guia->fresh([
                'venta',
                'cliente',
                'motivoTraslado',
                'chofer',
                'almacenOrigen',
                'almacenDestino',
                'user',
                'detalles.producto.marca',
                'detalles.unidadDerivadaInmutable',
            ]);
        });
    }

    /**
     * Eliminar una guía (solo si está en BORRADOR)
     */
    public function eliminar(GuiaRemision $guia): void
    {
        DB::transaction(function () use ($guia) {
            if ($guia->estado !== 'BORRADOR') {
                throw new \Exception('Solo se pueden eliminar guías en estado BORRADOR');
            }

            // Eliminar detalles
            DetalleGuiaRemision::where('guia_remision_id', $guia->id)->delete();

            // Eliminar guía
            $guia->delete();
        });
    }

    // =========================================================================
    // SUNAT / XML / QR
    // =========================================================================

    /**
     * Preparar datos de la guía para Greenter
     */
    public function prepararDatosParaGreenter(GuiaRemision $guia): array
    {
        $guia->loadMissing([
            'cliente',
            'comprador',
            'motivoTraslado',
            'chofer',
            'detalles.producto',
            'detalles.unidadDerivadaInmutable',
        ]);

        // Destinatario
        $codigoMotivo = $guia->motivoTraslado->codigo ?? '01';
        $cliente = $guia->cliente;
        $destinatario = [
            'tipo_doc' => '1', // DNI por defecto
            'num_doc' => '00000000',
            'razon_social' => 'VARIOS',
        ];

        // Motivo 08: Traslado entre establecimientos — destinatario es la misma empresa
        if ($codigoMotivo === '08') {
            $destinatario = [
                'tipo_doc' => '6', // RUC
                'num_doc' => \App\Models\Empresa::getRucEmisor(),
                'razon_social' => config('sunat-api.razon_social'),
            ];
        } elseif ($cliente) {
            $tipoDoc = '1'; // DNI
            if ($cliente->tipo_cliente?->value === 'e') {
                $tipoDoc = '6'; // RUC
            }
            $destinatario = [
                'tipo_doc' => $tipoDoc,
                'num_doc' => $cliente->numero_documento ?? '00000000',
                'razon_social' => $cliente->razon_social
                    ? $cliente->razon_social
                    : (trim(($cliente->nombres ?? '') . ' ' . ($cliente->apellidos ?? '')) ?: 'VARIOS'),
            ];
        }

        // Chofer — hay dos fuentes posibles:
        //   1) `chofer` (tabla externa) → se usa para transporte PÚBLICO
        //      o GRE-Transportista. Tiene DNI + licencia + MTC.
        //   2) `userChofer` (USER de la empresa, despachador interno) → se
        //      usa para transporte PRIVADO. Sus datos viven en la tabla
        //      `user` (numero_documento, name, licencia_conducir).
        //
        // Si el usuario llenó ambos por error, prioridad: chofer externo
        // gana en PÚBLICO/Transportista; user gana en PRIVADO.
        $choferes = [];
        $esTransportePrivado = $guia->modalidad_transporte === 'PRIVADO';

        if ($esTransportePrivado && $guia->userChofer) {
            $user = $guia->userChofer;
            $nameParts = explode(' ', trim($user->name ?? ''), 2);
            $choferes[] = [
                'tipo' => 'Principal',
                'tipo_doc' => '1', // DNI
                'nro_doc' => $user->numero_documento ?? '',
                'nombres' => $nameParts[0] ?? '',
                'apellidos' => $nameParts[1] ?? '',
                'licencia' => $user->licencia_conducir ?? null,
            ];
        } elseif ($guia->chofer) {
            $nameParts = explode(' ', trim($guia->chofer->name), 2);
            $choferes[] = [
                'tipo' => 'Principal',
                'tipo_doc' => '1', // DNI
                'nro_doc' => $guia->chofer->dni ?? '',
                'nombres' => $nameParts[0] ?? '',
                'apellidos' => $nameParts[1] ?? '',
                'licencia' => $guia->chofer->licencia ?? null,
            ];
        }

        // Items
        $items = [];
        foreach ($guia->detalles as $detalle) {
            $items[] = [
                'codigo' => $detalle->producto?->cod_producto ?? (string) $detalle->producto_id,
                'descripcion' => $detalle->producto?->name ?? 'Producto',
                'unidad' => $this->mapearUnidadSunat($detalle->unidad_derivada_inmutable_name),
                'cantidad' => (float) $detalle->cantidad,
            ];
        }

        // Comprador (para motivos 03 y 14: venta con entrega a terceros)
        $comprador = null;
        if (in_array($codigoMotivo, ['03', '14']) && $guia->comprador) {
            $compradorCliente = $guia->comprador;
            $tipoDocComprador = '1'; // DNI
            if ($compradorCliente->tipo_cliente?->value === 'e') {
                $tipoDocComprador = '6'; // RUC
            }
            $comprador = [
                'tipo_doc' => $tipoDocComprador,
                'num_doc' => $compradorCliente->numero_documento ?? '00000000',
                'razon_social' => $compradorCliente->razon_social
                    ? $compradorCliente->razon_social
                    : (trim(($compradorCliente->nombres ?? '') . ' ' . ($compradorCliente->apellidos ?? '')) ?: 'VARIOS'),
            ];
        }

        // Determinar si es GRE-Transportista
        $esTransportista = $guia->tipo_guia === 'ELECTRONICA_TRANSPORTISTA';

        // Para GRE-Transportista: la empresa emisora actúa como transportista.
        // Para transporte PÚBLICO (tercerizado) en GRE-Remitente: el
        // transportista es la empresa tercera guardada en la guía
        // (Catálogo N° 18 SUNAT exige su RUC y razón social).
        $esPublico = $guia->modalidad_transporte === 'PUBLICO';
        $transportista = null;
        if ($esTransportista) {
            $transportista = [
                'tipo_doc' => '6', // RUC
                'num_doc' => \App\Models\Empresa::getRucEmisor(),
                'razon_social' => config('sunat-api.razon_social'),
                'nro_mtc' => config('sunat-api.nro_mtc', null),
            ];
        } elseif ($esPublico && $guia->transportista_ruc) {
            $transportista = [
                'tipo_doc' => '6', // RUC
                'num_doc' => $guia->transportista_ruc,
                'razon_social' => $guia->transportista_razon_social,
                'nro_mtc' => $guia->transportista_nro_mtc,
            ];
        }

        // Remitente — solo en GRE-Transportista. Es el cliente que contrata
        // el servicio de transporte (dueño de la mercadería). Se mapea a
        // Greenter setTercero al armar el XML SUNAT '31'.
        $remitente = null;
        if ($esTransportista && $guia->remitente) {
            $remitenteCliente = $guia->remitente;
            $tipoDocRem = '1'; // DNI
            if ($remitenteCliente->tipo_cliente?->value === 'e') {
                $tipoDocRem = '6'; // RUC
            }
            $remitente = [
                'tipo_doc' => $tipoDocRem,
                'num_doc' => $remitenteCliente->numero_documento ?? '00000000',
                'razon_social' => $remitenteCliente->razon_social
                    ? $remitenteCliente->razon_social
                    : (trim(($remitenteCliente->nombres ?? '') . ' ' . ($remitenteCliente->apellidos ?? '')) ?: 'VARIOS'),
            ];
        }

        return [
            'tipo_guia' => $guia->tipo_guia,
            'serie' => $guia->serie ?? ($esTransportista ? 'V001' : 'T001'),
            'correlativo' => str_pad($guia->numero ?? '1', 8, '0', STR_PAD_LEFT),
            'fecha_emision' => $guia->fecha_emision->format('Y-m-d'),
            'fecha_traslado' => $guia->fecha_traslado->format('Y-m-d'),
            'cod_traslado' => $codigoMotivo,
            'des_traslado' => $guia->motivoTraslado->descripcion ?? 'Venta',
            'mod_traslado' => $guia->modalidad_transporte === 'PRIVADO' ? '02' : '01',
            'peso_total' => max(0.01, (float) $guia->detalles->sum('peso_total')),
            'ubigeo_partida' => config('sunat-api.ubigeo'),
            'direccion_partida' => $guia->punto_partida,
            'ubigeo_llegada' => config('sunat-api.ubigeo'),
            'direccion_llegada' => $guia->punto_llegada,
            'vehiculo_placa' => $guia->vehiculo_placa,
            'destinatario' => $destinatario,
            'comprador' => $comprador,
            'remitente' => $remitente,
            'transportista' => $transportista,
            'choferes' => $choferes,
            'items' => $items,
            'observacion' => $guia->observaciones,
        ];
    }

    /**
     * Obtener código SUNAT del tipo de documento según tipo_guia.
     * '09' = GRE-Remitente, '31' = GRE-Transportista
     */
    private function getTipoDocSunat(GuiaRemision $guia): string
    {
        return $guia->tipo_guia === 'ELECTRONICA_TRANSPORTISTA' ? '31' : '09';
    }

    /**
     * Generar XML y QR para una guía (al emitir)
     */
    public function generarXml(GuiaRemision $guia): GuiaRemision
    {
        $dataGreenter = $this->prepararDatosParaGreenter($guia);
        $tipoDocSunat = $this->getTipoDocSunat($guia);

        // Generar XML
        $xml = $this->sunatApiService->generarXmlGuiaRemision($dataGreenter);
        $hashCpe = hash('sha256', $xml);

        // Generar QR
        $codigoQr = $this->generarCodigoQR($guia, $hashCpe);

        // Guardar XML en storage
            $ruc = \App\Models\Empresa::getRucEmisor();
        $nombreXml = $this->xmlStorageService->generarNombreXml(
            $ruc, $tipoDocSunat, $dataGreenter['serie'], $dataGreenter['correlativo']
        );
        $xmlPath = $this->xmlStorageService->guardarXml($xml, $nombreXml);

        // Actualizar guía
        $guia->update([
            'sunat_codigo_hash' => $hashCpe,
            'sunat_xml_path' => $xmlPath,
            'sunat_codigo_qr' => $codigoQr,
        ]);

        return $guia->fresh();
    }

    /**
     * Enviar guía de remisión a SUNAT
     */
    public function enviarASunat(GuiaRemision $guia): array
    {
        if ($guia->estado !== 'EMITIDA') {
            throw new \Exception('Solo se pueden enviar guías en estado EMITIDA');
        }

        if ($guia->sunat_estado === 'ACEPTADO') {
            throw new \Exception('Esta guía ya fue aceptada por SUNAT');
        }

        if ($guia->tipo_guia === 'FISICA') {
            throw new \Exception('Las guías físicas no se envían a SUNAT');
        }

        try {
            DB::beginTransaction();

            $dataGreenter = $this->prepararDatosParaGreenter($guia);
            $tipoDocSunat = $this->getTipoDocSunat($guia);
            $resultado = $this->sunatApiService->generarYEnviarGuiaRemision($dataGreenter);

            if (!$resultado['success']) {
                throw new \Exception('Error al enviar guía a SUNAT');
            }

            // Guardar XML y CDR
        $ruc = \App\Models\Empresa::getRucEmisor();
            $nombreXml = $this->xmlStorageService->generarNombreXml(
                $ruc, $tipoDocSunat, $dataGreenter['serie'], $dataGreenter['correlativo']
            );
            $nombreCdr = $this->xmlStorageService->generarNombreCdr(
                $ruc, $tipoDocSunat, $dataGreenter['serie'], $dataGreenter['correlativo']
            );

            $xmlPath = $this->xmlStorageService->guardarXml($resultado['xml'], $nombreXml);
            $cdrPath = $this->xmlStorageService->guardarCdr($resultado['cdr'], $nombreCdr);

            // Regenerar QR con hash actualizado
            $codigoQr = $this->generarCodigoQR($guia, $resultado['hash_cpe']);

            // Actualizar guía
            $guia->update([
                'sunat_estado' => 'ACEPTADO',
                'sunat_codigo_hash' => $resultado['hash_cpe'],
                'sunat_xml_path' => $xmlPath,
                'sunat_cdr_xml' => $resultado['cdr'],
                'sunat_cdr_path' => $cdrPath,
                'sunat_codigo_qr' => $codigoQr,
                'sunat_fecha_envio' => now(),
                'sunat_mensaje' => $resultado['mensaje_sunat'] ?? 'Aceptado',
            ]);

            DB::commit();


            return [
                'success' => true,
                'mensaje' => 'Guía de remisión enviada correctamente a SUNAT',
                'modo' => $resultado['modo'],
                'codigo_sunat' => $resultado['codigo_sunat'] ?? null,
                'mensaje_sunat' => $resultado['mensaje_sunat'] ?? null,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al enviar guía a SUNAT', [
                'guia_id' => $guia->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Generar código QR para guía de remisión
     * Formato: RUC|TIPO_DOC|SERIE|NUMERO|FECHA|HASH
     */
    private function generarCodigoQR(GuiaRemision $guia, string $hashCpe): ?string
    {
        try {
            $qrText = implode('|', [
                \App\Models\Empresa::getRucEmisor(),
                $this->getTipoDocSunat($guia),
                $guia->serie ?? 'T001',
                $guia->numero ?? '0',
                $guia->fecha_emision->format('Y-m-d'),
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
     * Mapear nombre de unidad a código SUNAT
     */
    private function mapearUnidadSunat(?string $unidadNombre): string
    {
        if (!$unidadNombre) return 'NIU';

        $mapa = [
            'unidad' => 'NIU',
            'unidades' => 'NIU',
            'kilogramo' => 'KGM',
            'kilogramos' => 'KGM',
            'kg' => 'KGM',
            'metro' => 'MTR',
            'metros' => 'MTR',
            'litro' => 'LTR',
            'litros' => 'LTR',
            'caja' => 'BX',
            'cajas' => 'BX',
            'bolsa' => 'BG',
            'bolsas' => 'BG',
            'pieza' => 'NIU',
            'piezas' => 'NIU',
            'rollo' => 'NIU',
            'rollos' => 'NIU',
            'paquete' => 'PK',
            'paquetes' => 'PK',
            'galón' => 'GLL',
            'galon' => 'GLL',
            'galones' => 'GLL',
        ];

        return $mapa[strtolower(trim($unidadNombre))] ?? 'NIU';
    }

    /**
     * Crear detalles de la guía
     */
    private function crearDetalles(string $guiaId, array $detalles, ?int $entregaId = null): void
    {
        foreach ($detalles as $detalle) {
            // Resolver unidad_derivada_inmutable_id por nombre si el ID no existe en la tabla inmutable
            $inmutableId = $detalle['unidad_derivada_inmutable_id'];
            $inmutableName = $detalle['unidad_derivada_inmutable_name'];
            $existsInmutable = DB::table('unidadderivadainmutable')->where('id', $inmutableId)->exists();
            if (!$existsInmutable && $inmutableName) {
                $resolved = DB::table('unidadderivadainmutable')->where('name', $inmutableName)->first();
                if ($resolved) {
                    $inmutableId = $resolved->id;
                }
            }

            DetalleGuiaRemision::create([
                'guia_remision_id' => $guiaId,
                'producto_id' => $detalle['producto_id'],
                'producto_almacen_id' => $detalle['producto_almacen_id'],
                'unidad_derivada_inmutable_id' => $inmutableId,
                'unidad_derivada_inmutable_name' => $inmutableName,
                'factor' => $detalle['factor'],
                'cantidad' => $detalle['cantidad'],
                'peso_total' => $detalle['peso_total'] ?? null,
                'unidad_derivada_venta_id' => $detalle['unidad_derivada_venta_id'] ?? null,
            ]);

            // Incrementar cantidad_guiada en la línea de venta correspondiente
            if (!empty($detalle['unidad_derivada_venta_id'])) {
                DB::table('unidadderivadainmutableventa')
                    ->where('id', $detalle['unidad_derivada_venta_id'])
                    ->increment('cantidad_guiada', (float) $detalle['cantidad']);

                // Y también en el detalle de ESA entrega (si la guía nació de una),
                // para poder calcular el restante por guiar de cada entrega.
                if ($entregaId && !empty($detalle['cantidad'])) {
                    DB::table('entrega_detalle')
                        ->where('entrega_id', $entregaId)
                        ->where('unidad_derivada_venta_id', $detalle['unidad_derivada_venta_id'])
                        ->increment('cantidad_guiada', (float) $detalle['cantidad']);
                }
            }
        }
    }

    /**
     * Afectar el stock del almacén (descontar o incrementar)
     */
    private function afectarStock(array $detalles, string $accion): void
    {
        foreach ($detalles as $detalle) {
            $productoAlmacen = ProductoAlmacen::findOrFail($detalle['producto_almacen_id']);
            $cantidadBase = (float) $detalle['cantidad'] * (float) $detalle['factor'];

            if ($accion === 'descontar') {
                $productoAlmacen->decrement('stock', $cantidadBase);
            } elseif ($accion === 'incrementar') {
                $productoAlmacen->increment('stock', $cantidadBase);
            }
        }
    }
}
