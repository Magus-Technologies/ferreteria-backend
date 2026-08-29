<?php

namespace App\Services;

use App\Models\GuiaRemision;
use App\Models\DetalleGuiaRemision;
use App\Models\ProductoAlmacen;
use App\Models\SerieDocumento;
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

            $tipoGuia = $data['tipo_guia'] ?? 'ELECTRONICA_REMITENTE';

            // Si hay una serie configurada para guía (gr = remitente, gt = transportista)
            // en el almacén de origen, usarla; si no, caer a los defaults históricos.
            $tipoSerie = match ($tipoGuia) {
                'ELECTRONICA_TRANSPORTISTA' => 'gt',
                'ELECTRONICA_REMITENTE' => 'gr',
                default => null, // FISICA no usa series electrónicas
            };

            // El número de una guía ELECTRÓNICA lo asigna SIEMPRE el servidor.
            //
            // Antes se respetaba el `numero` que mandaba el cliente y solo se
            // generaba uno si venía vacío — pero el formulario SIEMPRE lo manda:
            // lo toma del endpoint de preview (`siguiente-numero/preview`), que
            // calcula el próximo número SIN reservarlo. Eso rompía de tres formas,
            // y las tres se dieron juntas (aparecieron 3 guías con T001-1):
            //   1. `SerieDocumento.correlativo` no se incrementaba NUNCA, porque
            //      ese incremento vivía dentro del bloque que se salteaba. El
            //      preview devolvía siempre el mismo número.
            //   2. Dos formularios abiertos a la vez leían el mismo número.
            //   3. Las guías duplicadas comparten `sunat_xml_path`
            //      (RUC-09-SERIE-CORRELATIVO.xml): se pisaban el XML entre ellas.
            //
            // Las FÍSICAS sí respetan lo que se cargue a mano: son talonarios
            // preimpresos y el número lo pone el papel, no el sistema.
            $esFisica = $tipoGuia === 'FISICA';
            $numeroManualValido = $esFisica && !empty($data['serie']) && !empty($data['numero']);

            if (! $numeroManualValido) {
                $serieDoc = null;
                if ($tipoSerie && !empty($data['almacen_origen_id'])) {
                    // lockForUpdate: dos creaciones simultáneas se serializan en
                    // vez de leer las dos el mismo correlativo.
                    $serieDoc = SerieDocumento::where('tipo_documento', $tipoSerie)
                        ->where('almacen_id', $data['almacen_origen_id'])
                        ->where('activo', true)
                        ->orderBy('created_at', 'desc')
                        ->lockForUpdate()
                        ->first();
                }

                if ($serieDoc) {
                    // Incrementar PRIMERO y usar el valor ya reservado: si se
                    // calculaba aparte del incremento, un fallo posterior dejaba
                    // el correlativo y la guía desalineados.
                    $serieDoc->increment('correlativo');
                    $data['serie'] = $serieDoc->serie;
                    $data['numero'] = (int) $serieDoc->refresh()->correlativo;
                } else {
                    $serie = match ($tipoGuia) {
                        'ELECTRONICA_TRANSPORTISTA' => 'V001',
                        'FISICA' => 'TF01',
                        default => 'T001', // ELECTRONICA_REMITENTE
                    };
                    $maxNumero = GuiaRemision::where('serie', $serie)
                        ->lockForUpdate()
                        ->max('numero') ?? 0;
                    $data['serie'] = $serie;
                    $data['numero'] = $maxNumero + 1;
                }
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
                // FALTABAN. El formulario los manda y StoreGuiaRemisionRequest
                // los valida, pero al no estar en este array se descartaban en
                // silencio y quedaban NULL en la base:
                //   - user_chofer_id: el despachador en transporte PRIVADO. Sin
                //     él, el PDF imprimía "Chofer: -" y el XML salía SIN
                //     DriverPerson (prepararDatosParaGreenter lee esta relación).
                //     Además el vehículo se deduce de este user, así que también
                //     se perdía la placa.
                //   - remitente_id: el dueño de la mercadería en GRE-Transportista,
                //     que va al `setTercero` del XML.
                'user_chofer_id' => $data['user_chofer_id'] ?? null,
                'remitente_id' => $data['remitente_id'] ?? null,
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
            // (razon_social es NOT NULL en el catálogo, así que exigimos ambos datos).
            // OJO: no se envía 'estado' acá a propósito, así el upsert nunca
            // reactiva ni desactiva un transportista existente por el solo
            // hecho de usarse en una guía; el estado se administra aparte.
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
        // Con la guía ya declarada en SUNAT no se toca nada: el documento
        // vigente es el que ella selló (o el que está procesando un ticket).
        if (in_array($guia->sunat_estado, ['ACEPTADO', 'PENDIENTE'], true)) {
            throw new \Exception('La guía ya fue enviada a SUNAT y no puede modificarse.');
        }

        if ($guia->estado === 'ANULADA') {
            throw new \Exception('Una guía anulada no puede modificarse.');
        }

        if (!$guia->puedeEditarse()) {
            // EMITIDA pero todavía NO declarada en SUNAT. Antes esto se
            // rechazaba de plano, y dejaba sin salida a las guías emitidas sin
            // despachador: el chofer y la placa no se podían cargar por ningún
            // lado, y sin ellos SUNAT rechaza una GRE de transporte privado.
            //
            // Se permite editar, pero NUNCA la serie ni el número: identifican
            // al documento ya impreso, y pisarlos a mano es justamente lo que
            // generó guías duplicadas (varias T001-1 compartiendo el mismo
            // archivo XML). El correlativo lo asigna el servidor al crear.
            unset($data['serie'], $data['numero']);
        }

        $guia->update($data);
        $guia->refresh();

        // Si ya estaba EMITIDA, su XML quedó viejo: fue generado al emitir, con
        // los datos de antes. Sin esto había que acordarse de apretar
        // "Regenerar XML" a mano después de cada edición — y si no lo hacías,
        // el documento electrónico seguía sin el chofer que acabás de cargar.
        // No aplica a BORRADOR (todavía no tiene XML) ni a FISICA (no lleva).
        if ($guia->estado === 'EMITIDA' && $guia->tipo_guia !== 'FISICA') {
            try {
                $this->generarXml($guia);
                $guia->refresh();
            } catch (\Exception $e) {
                // La edición ya se guardó: no se revierte por un fallo al
                // regenerar. Queda el botón manual como salida.
                Log::error('Error al regenerar XML tras editar la guía', [
                    'guia_id' => $guia->id,
                    'error' => $e->getMessage(),
                ]);
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
            // El chofer de transporte PRIVADO es un user, y su vehículo cuelga
            // de él: sin esto se resolvía con lazy loading (o quedaba en null).
            'userChofer.vehiculo',
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
            // La placa cargada a mano manda; si no hay, se cae a la del vehículo
            // asignado al despachador. Sin este respaldo, una guía de transporte
            // PRIVADO (donde el vehículo se elige junto con el despachador y no
            // se tipea aparte) salía SIN placa, y SUNAT la exige para GRE.
            'vehiculo_placa' => $guia->vehiculo_placa
                ?: ($guia->userChofer?->vehiculo?->placa ?: null),
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
     * Enviar guía de remisión a SUNAT.
     *
     * La GRE-API de SUNAT es ASÍNCRONA: este paso solo entrega un ticket,
     * no un CDR — recién se sabe si SUNAT aceptó/rechazó consultando ese
     * ticket después (ver `consultarEstadoSunat`). Por eso acá NO se marca
     * `ACEPTADO`: queda en `PENDIENTE` con el ticket guardado.
     */
    public function enviarASunat(GuiaRemision $guia): array
    {
        if ($guia->estado !== 'EMITIDA') {
            throw new \Exception('Solo se pueden enviar guías en estado EMITIDA');
        }

        if ($guia->sunat_estado === 'ACEPTADO') {
            throw new \Exception('Esta guía ya fue aceptada por SUNAT');
        }

        if ($guia->sunat_estado === 'PENDIENTE') {
            throw new \Exception('Esta guía ya fue enviada y está pendiente de confirmación (ticket ' . $guia->sunat_ticket . '). Consultá su estado en vez de reenviarla.');
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
                throw new \Exception($resultado['mensaje_sunat'] ?? 'Error al enviar guía a SUNAT');
            }

            $ruc = \App\Models\Empresa::getRucEmisor();
            $nombreXml = $this->xmlStorageService->generarNombreXml(
                $ruc, $tipoDocSunat, $dataGreenter['serie'], $dataGreenter['correlativo']
            );
            $xmlPath = $this->xmlStorageService->guardarXml($resultado['xml'], $nombreXml);

            // Regenerar QR con hash actualizado
            $codigoQr = $this->generarCodigoQR($guia, $resultado['hash_cpe']);

            $guia->update([
                'sunat_estado' => 'PENDIENTE',
                'sunat_codigo_hash' => $resultado['hash_cpe'],
                'sunat_xml_path' => $xmlPath,
                'sunat_ticket' => $resultado['ticket'] ?? null,
                'sunat_codigo_qr' => $codigoQr,
                'sunat_fecha_envio' => now(),
                'sunat_mensaje' => $resultado['mensaje_sunat'] ?? 'Enviado a SUNAT, pendiente de confirmación',
            ]);

            DB::commit();

            return [
                'success' => true,
                'mensaje' => 'Guía enviada a SUNAT — pendiente de confirmación del ticket',
                'modo' => $resultado['modo'],
                'ticket' => $resultado['ticket'] ?? null,
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
     * Consulta en SUNAT el resultado del ticket de una guía ya enviada
     * (`sunat_estado === 'PENDIENTE'`). Si SUNAT todavía la está
     * procesando, `success` viene en `false` — eso NO se interpreta como
     * rechazo: se deja en `PENDIENTE` para reintentar la consulta más
     * tarde (el job automático la vuelve a consultar en cada corrida).
     * Solo se marca `ACEPTADO` cuando SUNAT confirma con CDR.
     */
    public function consultarEstadoSunat(GuiaRemision $guia): array
    {
        if (empty($guia->sunat_ticket)) {
            throw new \Exception('Esta guía no tiene un ticket SUNAT pendiente de consulta');
        }

        if ($guia->sunat_estado === 'ACEPTADO') {
            return [
                'success' => true,
                'estado' => 'ACEPTADO',
                'mensaje' => 'La guía ya estaba aceptada por SUNAT',
            ];
        }

        $resultado = $this->sunatApiService->consultarTicketGuia($guia->sunat_ticket);

        if (!$resultado['success']) {
            $mensaje = $resultado['mensaje_sunat'] ?? 'SUNAT todavía está procesando el ticket';

            // Distinguir "todavía procesando" de un RECHAZO definitivo.
            //
            // Greenter marca con el código 98 el ticket que SUNAT aún no
            // terminó de procesar (ExtService::isPending); cualquier otro
            // código es una respuesta final — típicamente un rechazo, por
            // ejemplo cuando la serie-número ya existe en SUNAT.
            //
            // Antes ambos casos dejaban la guía en PENDIENTE para siempre, y
            // eso la trababa: con un ticket "en curso" no se puede editar,
            // reenviar ni regenerar. Una guía rechazada quedaba sin salida.
            // Marcándola RECHAZADO se vuelve a habilitar todo eso.
            $siguePendiente = str_contains($mensaje, '[98]')
                || str_contains($mensaje, 'aún no ha terminado')
                || str_contains($mensaje, 'aun no ha terminado');

            $guia->update([
                'sunat_mensaje' => $mensaje,
                ...($siguePendiente ? [] : ['sunat_estado' => 'RECHAZADO']),
            ]);

            return [
                'success' => false,
                'estado' => $guia->fresh()->sunat_estado,
                'mensaje' => $mensaje,
            ];
        }

        $dataGreenter = $this->prepararDatosParaGreenter($guia);
        $tipoDocSunat = $this->getTipoDocSunat($guia);
        $ruc = \App\Models\Empresa::getRucEmisor();
        $nombreCdr = $this->xmlStorageService->generarNombreCdr(
            $ruc, $tipoDocSunat, $dataGreenter['serie'], $dataGreenter['correlativo']
        );

        $cdrContent = $resultado['cdr'] ?? '';
        if ($cdrContent !== '' && base64_decode($cdrContent, true) !== false) {
            $cdrContent = base64_decode($cdrContent);
        }
        $cdrPath = $cdrContent !== '' ? $this->xmlStorageService->guardarCdr($cdrContent, $nombreCdr) : null;

        $guia->update([
            'sunat_estado' => 'ACEPTADO',
            'sunat_cdr_xml' => $cdrContent !== '' ? $cdrContent : null,
            'sunat_cdr_path' => $cdrPath,
            'sunat_mensaje' => $resultado['mensaje_sunat'] ?? 'Aceptado',
        ]);

        return [
            'success' => true,
            'estado' => 'ACEPTADO',
            'mensaje' => $resultado['mensaje_sunat'] ?? 'Aceptado',
        ];
    }

    /**
     * Obtener el XML firmado de una guía (desde el archivo en storage)
     */
    public function obtenerXml(GuiaRemision $guia): string
    {
        if (empty($guia->sunat_xml_path)) {
            throw new \Exception('La guía no tiene XML generado');
        }

        return $this->xmlStorageService->obtenerXml($guia->sunat_xml_path);
    }

    /**
     * Obtener el CDR de una guía. Prioriza el contenido en BD
     * (el API lo entrega en base64 de un zip); si no, lee el archivo.
     */
    public function obtenerCdr(GuiaRemision $guia): string
    {
        if (!empty($guia->sunat_cdr_xml)) {
            $decodificado = base64_decode($guia->sunat_cdr_xml, true);
            return $decodificado !== false ? $decodificado : $guia->sunat_cdr_xml;
        }

        if (empty($guia->sunat_cdr_path)) {
            throw new \Exception('La guía no tiene CDR');
        }

        return $this->xmlStorageService->obtenerCdr($guia->sunat_cdr_path);
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
