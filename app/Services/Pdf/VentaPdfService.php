<?php

namespace App\Services\Pdf;

use App\Models\ComprobanteElectronico;
use App\Models\Empresa;
use App\Models\FuentePersonalizada;
use App\Models\PlantillaImpresion;
use App\Models\Venta;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Response;

class VentaPdfService
{
    /**
     * Generar PDF de una venta.
     */
    public function generar(string $ventaId, string $formato = 'a4', bool $sinVales = false): Response
    {
        $venta = $this->obtenerVenta($ventaId);
        $empresa = $venta->user->empresa;

        // Sobrecargo total excluyendo métodos que distribuyen en precios (ej. Izipay, Culqui):
        // esos métodos no muestran el sobrecargo como línea separada, lo absorben en el PU.
        $sobrecargoTotal = $venta->despliegueDePagoVentas
            ->reject(fn($dp) => (bool) ($dp->despliegueDePago->distribuir_en_precios ?? false))
            ->sum('sobrecargo_aplicado') ?? 0;

        $productos = $this->prepararProductos($venta, $sobrecargoTotal);

        // Descuento por vales aplicados de tipo DESCUENTO_MISMA_COMPRA
        $descuentoVale = $this->calcularDescuentoValesAplicados($venta, $productos);
        $valesDescuento = $this->prepararValesDescuentoAplicados($venta, $productos);

        // Calcular totales SIN incluir los productos regalados (subtotal=0 igual no afectaría,
        // pero los mantenemos fuera del array que va a calcularTotales para evitar confusiones).
        $calculos = $this->calcularTotales($productos, $sobrecargoTotal, $descuentoVale);

        // Productos gratis por vales tipo PRODUCTO_GRATIS — se anexan al final del array
        // de productos para que se muestren en el ticket sin sumar al total.
        $productosGratis = $this->prepararProductosGratis($venta);
        if (!empty($productosGratis)) {
            $productos = array_merge($productos, $productosGratis);
        }

        $moneda = $venta->tipo_moneda?->value === 's' ? 'SOLES' : 'DOLARES';
        $esNotaVenta = ($venta->tipo_documento->value ?? '') === 'nv';

        if ($formato === 'ticket') {
            return $this->generarTicket($venta, $empresa, $productos, $calculos, $sinVales, $valesDescuento, $moneda, $esNotaVenta);
        }

        $codigoQr = $this->obtenerCodigoQr($venta);
        $consultaUrl = $this->getConsultaUrl();

        $plantilla = PlantillaImpresion::obtenerParaConFormato((int) $empresa->id, 'venta', 'A4');
        $logosExtras = $esNotaVenta ? $this->obtenerLogosExtras($plantilla->logos_nota_venta ?? []) : [];

        $est = $this->resolverEstilos($plantilla->estilos ?? []);
        $msg = array_merge(PlantillaImpresion::DEFAULT_MENSAJES_EXTRA, $plantilla->mensajes_extra ?? []);
        $bloques = $this->resolverEstilosBloques($est, $plantilla->estilos_secciones ?? []);
        $fontFaceCss = FuentePersonalizada::generarFontFaceCss(
            (int) $empresa->id,
            FuentePersonalizada::extraerFuentesUsadas($plantilla->estilos ?? [], $plantilla->estilos_secciones ?? [])
        );
        $observaciones = $venta->descripcion ?: $msg['observaciones_default'];

        $data = [
            'venta' => $venta,
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'tipoDocumentoTitulo' => $this->getTituloDocumento($venta->tipo_documento->value),
            'numeroDocumento' => $this->formatNumeroDocumento($venta),
            'productos' => $productos,
            'calculos' => $calculos,
            'valesDescuento' => $valesDescuento,
            'codigoQr' => $codigoQr,
            'consultaUrl' => $consultaUrl,
            'filas' => $this->prepararInfoCliente($venta, $moneda),
            'son' => PdfService::numeroALetras($calculos['total']),
            'moneda' => $moneda,
            'observaciones' => $observaciones,
            'plantilla' => $plantilla,
            'logosExtras' => $logosExtras,
            'est' => $est,
            'msg' => $msg,
            'bloques' => $bloques,
            'font_face_css' => $fontFaceCss,
            'esNotaVenta' => $esNotaVenta,
        ];

        $filename = "{$venta->tipo_documento->value}-{$venta->serie}-{$venta->numero}.pdf";

        return PdfService::render('pdf.venta', $data, $filename);
    }

    /**
     * Etiqueta del tipo de promoción para el cupón impreso.
     */
    private function tipoValeLabel(?\App\Models\ValeCompra $vale): string
    {
        return match ($vale?->tipo_promocion) {
            'DESCUENTO_MISMA_COMPRA', 'DESCUENTO_PROXIMA_COMPRA' => 'VALE DESCUENTO',
            'PRODUCTO_GRATIS' => 'PRODUCTO GRATIS',
            'DOS_POR_UNO' => '2x1',
            'SORTEO' => 'SORTEO',
            default => strtoupper(str_replace('_', ' ', $vale?->tipo_promocion ?? '')),
        };
    }

    /**
     * Beneficio del cupón impreso, según el tipo de promoción del vale.
     * Para PRODUCTO_GRATIS / DOS_POR_UNO el beneficio no es un monto fijo, así que
     * no se imprime "S/ 0.00 DESCUENTO" sino el beneficio real.
     */
    private function beneficioValeLabel(?\App\Models\ValeCompra $vale): string
    {
        if (!$vale) return '';

        if ($vale->tipo_promocion === 'PRODUCTO_GRATIS') {
            $nombre = $vale->productoGratis?->name;
            $cant = (float) ($vale->cantidad_producto_gratis ?? 1);
            $cantTxt = rtrim(rtrim(number_format($cant, 3, '.', ''), '0'), '.');
            return $nombre ? "GRATIS: {$cantTxt} x {$nombre}" : 'PRODUCTO GRATIS';
        }

        if ($vale->tipo_promocion === 'DOS_POR_UNO') {
            return 'LLEVA 2x1';
        }

        $valor = (float) ($vale->descuento_valor ?? 0);
        if ($valor <= 0) return '';
        return $vale->descuento_tipo === 'PORCENTAJE'
            ? (number_format($valor, 0) . '% DESCUENTO')
            : ('S/ ' . number_format($valor, 2) . ' DESCUENTO');
    }

    /**
     * Generar un vale individual de una venta (para impresión separada).
     */
    public function generarValeIndividual(string $ventaId, int $index): Response
    {
        $venta = $this->obtenerVenta($ventaId);
        $empresa = $venta->user->empresa;

        // Obtener los vales generados (misma lógica que generarTicket)
        $vales = [];
        foreach ($venta->valesAplicados as $va) {
            if (!$va->genera_vale_futuro || !$va->codigo_vale_generado) {
                continue;
            }
            $valeCompra = $va->valeCompra;

            $vales[] = [
                'tipo_label' => $this->tipoValeLabel($valeCompra),
                'nombre' => $valeCompra?->nombre ?? '',
                'beneficio' => $this->beneficioValeLabel($valeCompra),
                'codigo' => $va->codigo_vale_generado,
                'fecha_validez' => $va->fecha_validez_generado
                    ? \Carbon\Carbon::parse($va->fecha_validez_generado)->format('d/m/Y')
                    : null,
            ];
        }

        if (!isset($vales[$index])) {
            abort(404, 'Vale no encontrado');
        }

        $vale = $vales[$index];

        // Generar QR Code como base64
        $qrBase64 = null;
        if ($vale['codigo']) {
            $qrResult = Builder::create()
                ->writer(new PngWriter())
                ->data($vale['codigo'])
                ->encoding(new Encoding('UTF-8'))
                ->errorCorrectionLevel(ErrorCorrectionLevel::Medium)
                ->size(200)
                ->margin(5)
                ->build();
            $qrBase64 = $qrResult->getDataUri();
        }

        // Generar Código de Barras (Code 128) como base64
        $barcodeBase64 = null;
        if ($vale['codigo']) {
            $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
            $barcodePng = $generator->getBarcode($vale['codigo'], $generator::TYPE_CODE_128, 2, 50);
            $barcodeBase64 = 'data:image/png;base64,' . base64_encode($barcodePng);
        }

        $estilos = $this->prepararDatosPlantilla((int) $empresa->id, 'vale-generado', 'Ticket');

        $data = array_merge($estilos, [
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'vale' => $vale,
            'numeroDocumento' => $this->formatNumeroDocumento($venta),
            'fechaEmision' => PdfService::formatFecha($venta->fecha),
            'qrBase64' => $qrBase64,
            'barcodeBase64' => $barcodeBase64,
        ]);

        $filename = "VALE-{$vale['codigo']}.pdf";

        return PdfService::render(
            'pdf.vale-generado-ticket',
            $data,
            $filename,
            'portrait',
            [], // auto-height: ver @page en vale-generado-ticket.blade.php
        );
    }

    private function generarTicket($venta, $empresa, array $productos, array $calculos, bool $sinVales = false, array $valesDescuento = [], string $moneda = 'SOLES', bool $esNotaVenta = false): Response
    {
        $cliente = $venta->cliente;
        $clienteNombre = $cliente?->razon_social
            ?: trim(($cliente?->nombres ?? '') . ' ' . ($cliente?->apellidos ?? ''))
            ?: 'CLIENTES VARIOS';

        $formaPago = match($venta->forma_de_pago) {
            \App\Enums\FormaDePago::Contado => 'Contado',
            \App\Enums\FormaDePago::Credito => 'Crédito',
            default => $venta->forma_de_pago->value ?? '',
        };
        $esCredito = $venta->forma_de_pago === \App\Enums\FormaDePago::Credito;

        // Metodos de pago
        $metodosPago = [];
        foreach ($venta->despliegueDePagoVentas as $dp) {
            $esIzypay = (bool) ($dp->despliegueDePago->distribuir_en_precios ?? false);
            $monto = (float) $dp->monto;
            $sobrecargo = (float) ($dp->sobrecargo_aplicado ?? 0);
            $metodosPago[] = [
                'nombre' => $dp->despliegueDePago->name ?? '',
                'monto' => $monto,
                'sobrecargo_aplicado' => $sobrecargo,
                'mostrar_sobrecargo' => !$esIzypay,
            ];
        }

        // Distribuir el sobrecargo de Izipay en los precios unitarios.
        // El ticket muestra el precio ajustado por unidad (ej. 2.00 → 2.06 con 3%),
        // así el total coincide con lo cobrado por el POS sin línea de comisión separada.
        $sobrecargoIzipay = collect($metodosPago)
            ->filter(fn($mp) => !$mp['mostrar_sobrecargo'])
            ->sum('sobrecargo_aplicado');

        if ($sobrecargoIzipay > 0) {
            // importeNeto = subtotal (base s/IGV) + IGV — es el neto ya con descuentos aplicados
            $importeNeto = ($calculos['subtotal'] ?? 0) + ($calculos['igv'] ?? 0);
            if ($importeNeto > 0) {
                $newImporteNeto = $importeNeto + $sobrecargoIzipay;
                $factor = $newImporteNeto / $importeNeto;

                // Escalar precios unitarios y subtotales de productos
                $productos = array_map(fn($p) => array_merge($p, [
                    'precio'    => round($p['precio'] * $factor, 2),
                    'subtotal'  => round($p['subtotal'] * $factor, 2),
                    'descuento' => round(($p['descuento'] ?? 0) * $factor, 2),
                ]), $productos);

                // Escalar montos de vales descuento (para que el display también cuadre)
                $valesDescuento = array_map(fn($vd) => array_merge($vd, [
                    'monto' => round($vd['monto'] * $factor, 2),
                ]), $valesDescuento);

                // Reconstruir calculos directamente desde newImporteNeto
                // (no llamamos calcularTotales para no perder el descuento de vale)
                $newBase = $newImporteNeto / 1.18;
                $calculos = [
                    'subtotal'        => round($newBase, 2),
                    'igv'             => round($newImporteNeto - $newBase, 2),
                    'sobrecargo'      => 0,
                    'descuento_vale'  => round(($calculos['descuento_vale'] ?? 0) * $factor, 2),
                    'total'           => round($newImporteNeto, 2),
                    'total_descuento' => round(($calculos['total_descuento'] ?? 0) * $factor, 2),
                ];

                // Actualizar monto de Izipay para mostrar el total real cobrado por el POS
                $metodosPago = array_map(fn($mp) => !$mp['mostrar_sobrecargo']
                    ? array_merge($mp, ['monto' => $mp['monto'] + $mp['sobrecargo_aplicado']])
                    : $mp,
                $metodosPago);
            }
        }

        // Sobrecargo visible en el desglose: solo métodos no-Izipay (Izipay ya fue distribuido en precios)
        $sobrecargoVisible = collect($metodosPago)
            ->filter(fn($mp) => $mp['mostrar_sobrecargo'])
            ->sum('sobrecargo_aplicado');

        // Vales aplicados
        $vales = [];
        foreach ($venta->valesAplicados as $va) {
            if (!$va->genera_vale_futuro || !$va->codigo_vale_generado) {
                continue;
            }
            $valeCompra = $va->valeCompra;
            $tipoLabel = $this->tipoValeLabel($valeCompra);
            $beneficio = $this->beneficioValeLabel($valeCompra);

            $codigo = $va->codigo_vale_generado;

            // Generar QR
            $qrUri = null;
            if ($codigo) {
                $qrResult = Builder::create()
                    ->writer(new PngWriter())
                    ->data($codigo)
                    ->encoding(new Encoding('UTF-8'))
                    ->errorCorrectionLevel(ErrorCorrectionLevel::Medium)
                    ->size(200)
                    ->margin(5)
                    ->build();
                $qrUri = $qrResult->getDataUri();
            }

            // Generar Barcode
            $barcodeUri = null;
            if ($codigo) {
                $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
                $barcodePng = $generator->getBarcode($codigo, $generator::TYPE_CODE_128, 2, 50);
                $barcodeUri = 'data:image/png;base64,' . base64_encode($barcodePng);
            }

            $vales[] = [
                'tipo_label' => $tipoLabel,
                'nombre' => $valeCompra?->nombre ?? '',
                'beneficio' => $beneficio,
                'codigo' => $codigo,
                'fecha_validez' => $va->fecha_validez_generado
                    ? \Carbon\Carbon::parse($va->fecha_validez_generado)->format('d/m/Y')
                    : null,
                'qr_base64' => $qrUri,
                'barcode_base64' => $barcodeUri,
            ];
        }

        // Cargar plantilla específica para ticket (si existe) y resolver estilos
        $plantilla = PlantillaImpresion::obtenerParaConFormato((int) $empresa->id, 'venta', 'Ticket');
        $est = $this->resolverEstilos($plantilla->estilos ?? []);
        $msg = array_merge(PlantillaImpresion::DEFAULT_MENSAJES_EXTRA, $plantilla->mensajes_extra ?? []);
        $bloques = $this->resolverEstilosBloques($est, $plantilla->estilos_secciones ?? []);
        $fontFaceCss = FuentePersonalizada::generarFontFaceCss(
            (int) $empresa->id,
            FuentePersonalizada::extraerFuentesUsadas($plantilla->estilos ?? [], $plantilla->estilos_secciones ?? [])
        );

        $fecha = $venta->fecha;

        $data = [
            'titulo' => "Ticket {$this->formatNumeroDocumento($venta)}",
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'tipoDocumentoTitulo' => $this->getTituloDocumento($venta->tipo_documento->value),
            'numeroDocumento' => $this->formatNumeroDocumento($venta),
            'formaPago' => $formaPago,
            'esCredito' => $esCredito,
            'fechaEmision' => PdfService::formatFecha($fecha),
            'hora' => PdfService::formatFecha($fecha, 'H:i:s'),
            'fechaVencimiento' => $venta->fecha_vencimiento
                ? PdfService::formatFecha($venta->fecha_vencimiento)
                : '',
            'numeroGuia' => $venta->numero_guia ?? '',
            'vendedor' => $venta->user->name,
            'recomendadoPor' => $this->getNombreRecomendado($venta),
            'clienteNombre' => $clienteNombre,
            // Placeholder "SN-XXXXXXXX" = cliente registrado sin documento:
            // en el ticket se muestra "—" (la blade etiqueta "DOC:" en ese caso).
            'clienteDocumento' => str_starts_with((string) ($cliente?->numero_documento ?? ''), 'SN-')
                ? '—'
                : ($cliente?->numero_documento ?? '99999999'),
            'clienteDireccion' => $this->resolverDireccionCliente($venta),
            'clienteTelefono' => $this->resolverTelefonosCliente($cliente),
            'metodosPago' => $metodosPago,
            'sobrecargoVisible' => $sobrecargoVisible,
            'productos' => $productos,
            'calculos' => $calculos,
            'son' => PdfService::numeroALetras($calculos['total']),
            'moneda' => $moneda,
            'observaciones' => $venta->descripcion ?: ($msg['observaciones_default'] ?? '- NINGUNA'),
            'vales' => $sinVales ? [] : $vales,
            'valesDescuento' => $valesDescuento,
            'consultaUrl' => $this->getConsultaUrl(),
            'plantilla' => $plantilla,
            'est' => $est,
            'msg' => $msg,
            'bloques' => $bloques,
            'font_face_css' => $fontFaceCss,
            'esNotaVenta' => $esNotaVenta,
        ];

        $filename = "TICKET-{$venta->serie}-{$venta->numero}.pdf";

        return PdfService::render(
            'pdf.venta-ticket',
            $data,
            $filename,
            'portrait',
            [], // auto-height: ver @page en venta-ticket.blade.php
        );
    }

    /**
     * Obtener la venta con todas sus relaciones necesarias.
     */
    private function obtenerVenta(string $ventaId): Venta
    {
        return Venta::with([
            'user.empresa',
            'cliente',
            // Direcciones normalizadas (D1-D4): el cliente ya NO tiene columna
            // plana `direccion`, vive en direcciones_cliente. Sin esto, el PDF
            // mostraba la dirección vacía.
            'cliente.direcciones',
            'recomendadoPor',
            'almacen',
            'productosPorAlmacen.productoAlmacen.producto.marca',
            'productosPorAlmacen.productoAlmacen.producto.unidadMedida',
            'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
            // Servicios de la venta: se listan en el documento y suman al total.
            'serviciosVenta.servicio',
            'despliegueDePagoVentas.despliegueDePago',
            'valesAplicados.valeCompra.productos',
            'valesAplicados.valeCompra.categorias',
        ])->findOrFail($ventaId);
    }

    /**
     * Preparar la lista de productos para la tabla.
     * Los productos se ordenan para que los que pertenecen a un paquete
     * aparezcan agrupados bajo el nombre del paquete.
     */
    private function prepararProductos(Venta $venta, float $sobrecargoTotal = 0): array
    {
        // $sobrecargoTotal se conserva en la firma por compatibilidad pero ya NO se
        // distribuye en los precios unitarios: el ticket muestra el sobrecargo como
        // línea separada (ver calcularTotales). Los productos se muestran con su
        // precio REAL almacenado en la BD.
        $productos = [];

        foreach ($venta->productosPorAlmacen as $pa) {
            $producto = $pa->productoAlmacen->producto;

            foreach ($pa->unidadesDerivadas as $ud) {
                $cantidad = (float) $ud->cantidad;
                $precio = (float) $ud->precio;
                $recargo = (float) ($ud->recargo ?? 0);
                $subtotal = ($precio + $recargo) * $cantidad;

                // La linea guarda la CONFIGURACION del descuento, no soles:
                // con tipo '%' el valor es la TASA (15 = 15%). Antes se tomaba
                // crudo, asi que un 15% se imprimia como S/ 15.00 y ademas
                // arrastraba el total del documento (ver calcularTotales).
                // Formula espejo del front (cards-info-venta.tsx, "Total Descuento").
                $descuentoTipo = $ud->descuento_tipo ?? 'm';
                $descuentoConfig = (float) ($ud->descuento ?? 0);

                if ($descuentoTipo === '%') {
                    $descuento = ($precio + $recargo) * $descuentoConfig * $cantidad / 100;
                } else {
                    // Monto fijo: es el total de la linea. Excepcion: en los
                    // sub-productos de un paquete el monto es POR UNIDAD.
                    $descuento = $pa->paquete_id
                        ? $descuentoConfig * $cantidad
                        : $descuentoConfig;
                }

                $descuento = round($descuento, 2);

                $productos[] = [
                    'producto_id' => $producto->id,
                    'categoria_id' => $producto->categoria_id,
                    'codigo' => $producto->cod_producto ?? '',
                    'nombre' => $producto->name,
                    'marca' => $producto->marca->name ?? '',
                    'unidad' => $ud->unidadDerivadaInmutable->name ?? '',
                    'cantidad' => $cantidad,
                    'precio' => $precio + $recargo,
                    'descuento' => $descuento,
                    // Para que el ticket pueda rotular "Dscto. 15%" y no solo el monto.
                    'descuento_tipo' => $descuentoTipo,
                    'descuento_tasa' => $descuentoTipo === '%' ? $descuentoConfig : 0,
                    'subtotal' => $subtotal,
                    'sobrecargo_porcentaje' => 0,
                    'sobrecargo_valor' => 0,
                    'paquete_id' => $pa->paquete_id,
                    'paquete_nombre' => $pa->paquete_nombre,
                ];
            }
        }

        // Ordenar: primero productos sueltos, luego agrupados por paquete
        usort($productos, function ($a, $b) {
            $aPaq = $a['paquete_id'] ?? 0;
            $bPaq = $b['paquete_id'] ?? 0;
            if ($aPaq === 0 && $bPaq !== 0) return -1;
            if ($aPaq !== 0 && $bPaq === 0) return 1;
            return $aPaq <=> $bPaq;
        });

        // Servicios de la venta: van DESPUÉS de los productos (se agregan tras el
        // usort para que no se mezclen con los paquetes). Se suman a esta misma
        // lista a propósito: calcularTotales() saca el subtotal de acá, así que
        // sin esto el importe del documento quedaba SIN los servicios.
        // producto_id/categoria_id van en null para que los vales con alcance por
        // PRODUCTO o CATEGORÍA no los tomen como base de descuento.
        foreach ($venta->serviciosVenta as $sv) {
            $nombre = $sv->servicio->nombre ?? 'SERVICIO';
            $referencia = trim((string) ($sv->referencia ?? ''));

            $productos[] = [
                'producto_id' => null,
                'categoria_id' => null,
                'codigo' => '',
                'nombre' => $referencia !== '' ? "{$nombre} ({$referencia})" : $nombre,
                'marca' => '',
                'unidad' => 'SERV',
                'cantidad' => (float) $sv->cantidad,
                'precio' => (float) $sv->precio_unitario,
                'descuento' => 0,
                'descuento_tipo' => 'm',
                'descuento_tasa' => 0,
                'subtotal' => (float) $sv->subtotal,
                'sobrecargo_porcentaje' => 0,
                'sobrecargo_valor' => 0,
                'paquete_id' => null,
                'paquete_nombre' => null,
            ];
        }

        return $productos;
    }

    /**
     * Base (S/) sobre la que aplica el descuento de un vale, según su DESTINO
     * (recompensa, PASO 4), independiente de la condición:
     *  - VENTA (o null/legacy): toda la venta (subtotal neto).
     *  - PRODUCTOS: subtotal de los productos en descuento_producto_ids.
     *  - CATEGORIAS: subtotal de los productos de descuento_categoria_ids.
     * Espeja el cálculo del frontend (cards-info-venta.tsx) para que el ticket cuadre.
     */
    private function baseDescuentoScope(\App\Models\ValeCompra $vale, array $productos): float
    {
        $alcance = $vale->descuento_alcance ?: 'VENTA';

        if ($alcance === 'VENTA') {
            $bruto = array_sum(array_column($productos, 'subtotal'));
            $desc = array_sum(array_column($productos, 'descuento'));
            return max(0, $bruto - $desc);
        }

        $prodIds = $vale->descuento_producto_ids ?? [];
        $catIds = $vale->descuento_categoria_ids ?? [];

        $base = 0;
        foreach ($productos as $p) {
            $match = $alcance === 'PRODUCTOS'
                ? in_array($p['producto_id'] ?? null, $prodIds)
                : (isset($p['categoria_id']) && in_array($p['categoria_id'], $catIds));
            if ($match) {
                $base += max(0, (float) ($p['subtotal'] ?? 0) - (float) ($p['descuento'] ?? 0));
            }
        }
        return $base;
    }

    /**
     * Calcular subtotal, IGV y total.
     */
    private function calcularTotales(array $productos, float $sobrecargoTotal = 0, float $descuentoVale = 0): array
    {
        $subtotalBruto = array_sum(array_column($productos, 'subtotal'));
        $totalDescuento = array_sum(array_column($productos, 'descuento'));

        // Importe de los productos (con IGV incluido) menos descuentos por línea.
        $importeProductos = $subtotalBruto - $totalDescuento;

        // Importe neto después del descuento del vale (promoción).
        $importeNeto = max(0, $importeProductos - $descuentoVale);

        // Base e IGV se calculan sobre el importe neto (post descuentos).
        $base = $importeNeto / 1.18;
        $igv = $importeNeto - $base;

        // El total final = neto + sobrecargo.
        $total = $importeNeto + $sobrecargoTotal;

        return [
            'subtotal' => $base,
            'igv' => $igv,
            'sobrecargo' => $sobrecargoTotal,
            'descuento_vale' => $descuentoVale,
            'total' => $total,
            'total_descuento' => $totalDescuento,
        ];
    }

    /**
     * Calcular el descuento total proveniente de vales aplicados a la misma compra.
     * - DESCUENTO_MISMA_COMPRA: PORCENTAJE se aplica sobre el importe bruto post
     *   descuentos por línea; MONTO_FIJO se suma directamente.
     * - DOS_POR_UNO y PRODUCTO_GRATIS (cuando momento_aplicacion = MISMA_COMPRA):
     *   se usa el `descuento_aplicado` ya calculado y persistido por
     *   ValeCompraService::aplicarValeAVenta al crear la venta.
     * DESCUENTO_PROXIMA_COMPRA y SORTEO no generan descuento en esta misma venta.
     */
    private function calcularDescuentoValesAplicados(Venta $venta, array $productos): float
    {
        $descuento = 0;
        foreach ($venta->valesAplicados as $va) {
            $vale = $va->valeCompra;
            if (!$vale) continue;

            // DESCUENTO en esta venta: misma compra, o próxima compra YA canjeada
            // (genera_vale_futuro=false). El generado (=true) no descuenta aquí.
            $esDescuentoEnVenta = in_array($vale->tipo_promocion, ['DESCUENTO_MISMA_COMPRA', 'DESCUENTO_PROXIMA_COMPRA'], true)
                && !$va->genera_vale_futuro;
            if ($esDescuentoEnVenta) {
                $valor = (float) ($vale->descuento_valor ?? 0);
                if ($valor <= 0) continue;
                // Scopear según la modalidad del vale (producto/categoría/todos).
                $baseScope = $this->baseDescuentoScope($vale, $productos);
                if ($vale->descuento_tipo === 'PORCENTAJE') {
                    $descuento += $baseScope * $valor / 100;
                } elseif ($vale->descuento_tipo === 'MONTO_FIJO') {
                    $descuento += min($valor, $baseScope);
                }
                continue;
            }

            // 2x1 y producto gratis aplicados en esta venta (auto o por código manual):
            // el monto ya fue calculado y persistido al aplicar el vale.
            if (
                ($vale->tipo_promocion === 'DOS_POR_UNO' || $vale->tipo_promocion === 'PRODUCTO_GRATIS')
                && !$va->genera_vale_futuro
            ) {
                $descuento += (float) ($va->descuento_aplicado ?? 0);
            }
        }

        return round($descuento, 2);
    }

    /**
     * Preparar lista de productos REGALADOS por vales de tipo PRODUCTO_GRATIS aplicados.
     * Se muestran como filas adicionales en la tabla del ticket/A4 con subtotal = 0
     * (no suman al total de la venta).
     */
    private function prepararProductosGratis(Venta $venta): array
    {
        $lista = [];
        foreach ($venta->valesAplicados as $va) {
            $vale = $va->valeCompra;
            if (!$vale) continue;
            if ($vale->tipo_promocion !== 'PRODUCTO_GRATIS') continue;

            $productoGratis = $vale->productoGratis;
            if (!$productoGratis) continue;

            $cantidad = (float) ($vale->cantidad_producto_gratis ?? 1);

            $lista[] = [
                'codigo' => $productoGratis->cod_producto ?? '',
                'nombre' => $productoGratis->name . ' (REGALO)',
                'marca' => $productoGratis->marca->name ?? '',
                'unidad' => $productoGratis->unidad_medida->name ?? 'UND',
                'cantidad' => $cantidad,
                'precio' => 0,
                'descuento' => 0,
                'subtotal' => 0,
                'sobrecargo_porcentaje' => 0,
                'sobrecargo_valor' => 0,
                'paquete_id' => null,
                'paquete_nombre' => null,
                'es_gratis' => true,
                'vale_nombre' => $vale->nombre,
            ];
        }
        return $lista;
    }

    /**
     * Preparar lista de vales aplicados con descuento sobre la misma compra
     * (DESCUENTO_MISMA_COMPRA, DOS_POR_UNO, PRODUCTO_GRATIS) para mostrar en
     * el ticket/A4 (nombre + beneficio + monto).
     */
    private function prepararValesDescuentoAplicados(Venta $venta, array $productos): array
    {
        $lista = [];
        foreach ($venta->valesAplicados as $va) {
            $vale = $va->valeCompra;
            if (!$vale) continue;

            $monto = 0;
            $beneficio = '';

            $esDescuentoEnVenta = in_array($vale->tipo_promocion, ['DESCUENTO_MISMA_COMPRA', 'DESCUENTO_PROXIMA_COMPRA'], true)
                && !$va->genera_vale_futuro;
            if ($esDescuentoEnVenta) {
                $valor = (float) ($vale->descuento_valor ?? 0);
                if ($valor <= 0) continue;
                $baseScope = $this->baseDescuentoScope($vale, $productos);
                if ($vale->descuento_tipo === 'PORCENTAJE') {
                    $monto = round($baseScope * $valor / 100, 2);
                    $beneficio = number_format($valor, 0) . '% DSCTO';
                } elseif ($vale->descuento_tipo === 'MONTO_FIJO') {
                    $monto = round(min($valor, $baseScope), 2);
                    $beneficio = 'S/ ' . number_format($valor, 2) . ' DSCTO';
                }
            } elseif (
                ($vale->tipo_promocion === 'DOS_POR_UNO' || $vale->tipo_promocion === 'PRODUCTO_GRATIS')
                && !$va->genera_vale_futuro
            ) {
                $monto = round((float) ($va->descuento_aplicado ?? 0), 2);
                if ($monto <= 0) continue;
                $beneficio = $vale->tipo_promocion === 'DOS_POR_UNO' ? '2x1' : 'PRODUCTO GRATIS';
            } else {
                continue;
            }

            $lista[] = [
                'codigo' => $vale->codigo,
                'nombre' => $vale->nombre,
                'beneficio' => $beneficio,
                'monto' => $monto,
            ];
        }

        return $lista;
    }

    /**
     * Obtener el codigo QR del comprobante electronico si existe.
     */
    private function obtenerCodigoQr(Venta $venta): ?string
    {
        if (!$venta->serie || !$venta->numero) {
            return null;
        }

        $comprobante = ComprobanteElectronico::where('serie', $venta->serie)
            ->where('correlativo', $venta->numero)
            ->first();

        return $comprobante?->codigo_qr;
    }

    /**
     * Resolver la dirección del cliente para el PDF según el tipo seleccionado
     * en la venta (direccion_seleccionada: D1-D4). Las direcciones viven en la
     * tabla normalizada `direcciones_cliente` (el cliente ya no tiene columna
     * plana `direccion`). Si el tipo elegido no existe, cae a D1, y si no, a la
     * primera disponible.
     */
    private function resolverDireccionCliente(Venta $venta): string
    {
        $cliente = $venta->cliente;
        if (!$cliente) return '';

        $tipo = $venta->direccion_seleccionada instanceof \BackedEnum
            ? $venta->direccion_seleccionada->value
            : ($venta->direccion_seleccionada ?? null);
        $tipo = $tipo ?: 'D1';

        $direcciones = $cliente->direcciones ?? collect();

        $seleccionada = $direcciones->firstWhere('tipo', $tipo)
            ?? $direcciones->firstWhere('tipo', 'D1')
            ?? $direcciones->first();

        return (string) ($seleccionada?->direccion ?? '');
    }

    /**
     * Teléfonos del cliente para el PDF: ambos (telefono / celular) separados
     * por " / ", omitiendo los vacíos. Devuelve '' si no hay ninguno.
     */
    private function resolverTelefonosCliente(?\App\Models\Cliente $cliente): string
    {
        if (!$cliente) return '';
        // array_unique: si Cel 1 y Cel 2 tienen el mismo número, no repetirlo.
        $tels = array_unique(array_filter([
            trim((string) ($cliente->telefono ?? '')),
            trim((string) ($cliente->celular ?? '')),
        ]));
        return implode(' / ', $tels);
    }

    /**
     * Preparar las filas de informacion del cliente.
     */
    private function prepararInfoCliente(Venta $venta, string $moneda = 'SOLES'): array
    {
        $cliente = $venta->cliente;
        $clienteNombre = $cliente?->razon_social
            ?: trim(($cliente?->nombres ?? '') . ' ' . ($cliente?->apellidos ?? ''))
            ?: 'CLIENTES VARIOS';

        $fecha = $venta->fecha;

        return [
            [
                'Cliente' => $clienteNombre,
                'F. Emision' => PdfService::formatFecha($fecha),
            ],
            [
                'Direccion' => $this->resolverDireccionCliente($venta),
                'Hora' => PdfService::formatFecha($fecha, 'H:i:s'),
            ],
            [
                // Ambos teléfonos del cliente separados por " / "
                'Telefono' => $this->resolverTelefonosCliente($cliente),
                'Email' => $cliente?->email ?? '',
            ],
            [
                // Placeholder "SN-XXXXXXXX" = cliente sin documento → mostrar "—"
                'RUC / DNI' => str_starts_with((string) ($cliente?->numero_documento ?? ''), 'SN-')
                    ? '—'
                    : ($cliente?->numero_documento ?? ''),
                'Tipo Doc.' => $venta->tipo_documento->value,
            ],
            [
                'Vendedor' => $venta->user->name,
                'Almacen' => $venta->almacen->name,
            ],
            [
                'Forma Pago' => match($venta->forma_de_pago) {
                    \App\Enums\FormaDePago::Contado => 'Contado',
                    \App\Enums\FormaDePago::Credito => 'Crédito',
                    default => $venta->forma_de_pago->value ?? '',
                },
                'Moneda' => $moneda,
            ],
            [
                'Cajero' => $venta->user->name,
                'Estado' => $venta->estado_de_venta->value ?? '',
            ],
            ...($this->getNombreRecomendado($venta) ? [[
                'Recomendado por' => $this->getNombreRecomendado($venta),
                '' => '',
            ]] : []),
        ];
    }

    /**
     * Obtener el nombre del cliente que recomendó la venta.
     */
    private function getNombreRecomendado(Venta $venta): ?string
    {
        $recomendado = $venta->recomendadoPor;
        if (!$recomendado) return null;

        return $recomendado->razon_social
            ?: trim(($recomendado->nombres ?? '') . ' ' . ($recomendado->apellidos ?? ''))
            ?: null;
    }

    /**
     * Obtener el titulo segun el tipo de documento.
     */
    private function getTituloDocumento(string $tipo): string
    {
        return match ($tipo) {
            '01' => 'FACTURA ELECTRONICA',
            '03' => 'BOLETA DE VENTA',
            'nv' => 'NOTA DE VENTA',
            default => strtoupper($tipo),
        };
    }

    /**
     * Formatear el numero de documento (serie-numero).
     */
    private function formatNumeroDocumento(Venta $venta): string
    {
        $serie = $venta->serie ?: 'S/N';
        $numero = str_pad($venta->numero ?? '0', 8, '0', STR_PAD_LEFT);

        return "{$serie}-{$numero}";
    }

    private function getConsultaUrl(): string
    {
        $frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/');

        return "{$frontendUrl}/consulta";
    }

    /**
     * Resolver los estilos del PDF mezclando los del usuario con los defaults.
     * Devuelve valores ya calculados (pt, px) listos para inyectar en CSS inline.
     */
    private function resolverEstilos(array $estilos): array
    {
        $e = array_merge(PlantillaImpresion::DEFAULT_ESTILOS, $estilos);

        $densidad = $e['densidad'] ?? 'normal';
        $padMul = $densidad === 'compacta' ? 0.7 : ($densidad === 'espaciada' ? 1.4 : 1.0);

        $fontPt = (int) ($e['tamano_base'] ?? 8);
        $borderPx = (int) ($e['grosor_borde'] ?? 2);

        return [
            'color_tema'  => $e['color_tema'],
            'color_borde' => $e['color_borde'],
            'color_texto' => $e['color_texto'],
            'fuente'      => $e['fuente'],
            'font_pt'     => $fontPt,
            'font_sm_pt'  => max(6, $fontPt - 1),
            'font_lg_pt'  => $fontPt + 2,
            'border_px'   => $borderPx,
            'border_thin_px' => max(1, (int) round($borderPx / 2)),
            'pad_px'      => (int) round(4 * $padMul),
            'pad_lg_px'   => (int) round(6 * $padMul),
            'densidad'    => $densidad,
        ];
    }

    /**
     * Resolver los estilos por bloque mezclando overrides con defaults globales.
     * Cada bloque recibe valores por defecto (color, font_pt, peso, alineacion).
     */
    private function resolverEstilosBloques(array $est, array $overrides): array
    {
        $fg = $est['fuente'];
        $defaultsPorBloque = [
            'empresa_razon'     => ['color' => $est['color_texto'], 'tamano' => $est['font_lg_pt'], 'peso' => 'bold',   'alineacion' => 'center', 'fuente' => $fg],
            'empresa_direccion' => ['color' => $est['color_texto'], 'tamano' => $est['font_sm_pt'], 'peso' => 'normal', 'alineacion' => 'center', 'fuente' => $fg],
            'caja_ruc'          => ['color' => $est['color_texto'], 'tamano' => $est['font_lg_pt'], 'peso' => 'bold',   'alineacion' => 'center', 'fuente' => $fg],
            'caja_tipo'         => ['color' => $est['color_texto'], 'tamano' => $est['font_lg_pt'] + 1, 'peso' => 'bold', 'alineacion' => 'center', 'fuente' => $fg],
            'caja_numero'       => ['color' => $est['color_texto'], 'tamano' => $est['font_lg_pt'] + 1, 'peso' => 'bold', 'alineacion' => 'center', 'fuente' => $fg],
            'info_label'        => ['color' => $est['color_texto'], 'tamano' => $est['font_sm_pt'], 'peso' => 'bold',   'alineacion' => 'left',   'fuente' => $fg],
            'info_valor'        => ['color' => $est['color_texto'], 'tamano' => $est['font_sm_pt'], 'peso' => 'normal', 'alineacion' => 'left',   'fuente' => $fg],
            'tabla_header'      => ['color' => $est['color_texto'], 'tamano' => $est['font_sm_pt'], 'peso' => 'bold',   'alineacion' => 'center', 'fuente' => $fg],
            'tabla_fila'        => ['color' => $est['color_texto'], 'tamano' => $est['font_sm_pt'], 'peso' => 'normal', 'alineacion' => 'left',   'fuente' => $fg],
            'son'               => ['color' => $est['color_texto'], 'tamano' => $est['font_sm_pt'], 'peso' => 'bold',   'alineacion' => 'left',   'fuente' => $fg],
            'obs_label'         => ['color' => $est['color_texto'], 'tamano' => $est['font_pt'],    'peso' => 'bold',   'alineacion' => 'left',   'fuente' => $fg],
            'obs_valor'         => ['color' => $est['color_texto'], 'tamano' => max(6, $est['font_sm_pt'] - 1), 'peso' => 'normal', 'alineacion' => 'left', 'fuente' => $fg],
            'total_label'       => ['color' => $est['color_texto'], 'tamano' => $est['font_pt'],    'peso' => 'bold',   'alineacion' => 'right',  'fuente' => $fg],
            'total_valor'       => ['color' => $est['color_texto'], 'tamano' => $est['font_pt'],    'peso' => 'normal', 'alineacion' => 'right',  'fuente' => $fg],
            'despedida_footer'  => ['color' => $est['color_texto'], 'tamano' => $est['font_pt'],    'peso' => 'bold',   'alineacion' => 'center', 'fuente' => $fg],
            'consulta_leyenda'  => ['color' => '#666666',           'tamano' => $est['font_sm_pt'], 'peso' => 'normal', 'alineacion' => 'center', 'fuente' => $fg],
            'consulta_url'      => ['color' => '#333333',           'tamano' => $est['font_sm_pt'], 'peso' => 'bold',   'alineacion' => 'center', 'fuente' => $fg],
        ];

        $resultado = [];
        foreach ($defaultsPorBloque as $key => $def) {
            $ov = $overrides[$key] ?? [];
            $resultado[$key] = [
                'color'      => !empty($ov['color']) ? $ov['color'] : $def['color'],
                'tamano'     => !empty($ov['tamano']) ? (int) $ov['tamano'] : $def['tamano'],
                'peso'       => !empty($ov['peso']) ? $ov['peso'] : $def['peso'],
                'alineacion' => !empty($ov['alineacion']) ? $ov['alineacion'] : $def['alineacion'],
                'fuente'     => !empty($ov['fuente']) ? $ov['fuente'] : $def['fuente'],
                'cursiva'    => isset($ov['cursiva']) ? (bool) $ov['cursiva'] : false,
                'subrayado'  => isset($ov['subrayado']) ? (bool) $ov['subrayado'] : false,
            ];
            // Generar string CSS listo para inyectar
            $resultado[$key]['css'] = sprintf(
                'color: %s; font-size: %dpt; font-weight: %s; text-align: %s; font-family: "%s", Arial, sans-serif; font-style: %s; text-decoration: %s;',
                $resultado[$key]['color'],
                $resultado[$key]['tamano'],
                $resultado[$key]['peso'],
                $resultado[$key]['alineacion'],
                $resultado[$key]['fuente'],
                $resultado[$key]['cursiva'] ? 'italic' : 'normal',
                $resultado[$key]['subrayado'] ? 'underline' : 'none'
            );
        }

        return $resultado;
    }

    /**
     * Resolver los logos adicionales (otras empresas) a mostrar en Nota de Venta.
     */
    private function obtenerLogosExtras(array $empresaIds): array
    {
        if (empty($empresaIds)) {
            return [];
        }

        $empresas = Empresa::whereIn('id', $empresaIds)->get(['id', 'razon_social', 'logo']);

        return $empresas->map(function ($e) {
            return [
                'id' => $e->id,
                'razon_social' => $e->razon_social,
                'logo_path' => PdfService::getLogoPath($e->logo),
            ];
        })->all();
    }
}
