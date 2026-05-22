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

        // Calcular sobrecargo total de todos los métodos de pago
        $sobrecargoTotal = $venta->despliegueDePagoVentas->sum('sobrecargo_aplicado') ?? 0;
        
        $productos = $this->prepararProductos($venta, $sobrecargoTotal);
        $calculos = $this->calcularTotales($productos, $sobrecargoTotal);

        if ($formato === 'ticket') {
            return $this->generarTicket($venta, $empresa, $productos, $calculos, $sinVales);
        }

        $codigoQr = $this->obtenerCodigoQr($venta);
        $consultaUrl = $this->getConsultaUrl();

        $plantilla = PlantillaImpresion::obtenerParaConFormato((int) $empresa->id, 'venta', 'A4');
        $esNotaVenta = ($venta->tipo_documento->value ?? '') === 'nv';
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
            'codigoQr' => $codigoQr,
            'consultaUrl' => $consultaUrl,
            'filas' => $this->prepararInfoCliente($venta),
            'son' => PdfService::numeroALetras($calculos['total']),
            'observaciones' => $observaciones,
            'plantilla' => $plantilla,
            'logosExtras' => $logosExtras,
            'est' => $est,
            'msg' => $msg,
            'bloques' => $bloques,
            'font_face_css' => $fontFaceCss,
        ];

        $filename = "{$venta->tipo_documento->value}-{$venta->serie}-{$venta->numero}.pdf";

        return PdfService::render('pdf.venta', $data, $filename);
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
            $tipoLabel = match ($valeCompra?->tipo_promocion) {
                'descuento_porcentaje' => 'DESCUENTO %',
                'descuento_fijo' => 'DESCUENTO FIJO',
                'producto_gratis' => 'PRODUCTO GRATIS',
                default => strtoupper($valeCompra?->tipo_promocion ?? ''),
            };
            $beneficio = match ($valeCompra?->descuento_tipo) {
                '%' => ($valeCompra?->descuento_valor ?? 0) . '% DESCUENTO',
                default => 'S/ ' . number_format($valeCompra?->descuento_valor ?? 0, 2) . ' DESCUENTO',
            };

            $vales[] = [
                'tipo_label' => $tipoLabel,
                'nombre' => $valeCompra?->nombre ?? '',
                'beneficio' => $beneficio,
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

        $data = [
            'empresa' => $empresa,
            'logoPath' => PdfService::getLogoPath($empresa->logo),
            'vale' => $vale,
            'numeroDocumento' => $this->formatNumeroDocumento($venta),
            'fechaEmision' => PdfService::formatFecha($venta->fecha),
            'qrBase64' => $qrBase64,
            'barcodeBase64' => $barcodeBase64,
        ];

        $filename = "VALE-{$vale['codigo']}.pdf";

        return PdfService::render(
            'pdf.vale-generado-ticket',
            $data,
            $filename,
            'portrait',
            [0, 0, 226.77, 841.89],
        );
    }

    private function generarTicket($venta, $empresa, array $productos, array $calculos, bool $sinVales = false): Response
    {
        $cliente = $venta->cliente;
        $clienteNombre = $cliente?->razon_social
            ?: trim(($cliente?->nombres ?? '') . ' ' . ($cliente?->apellidos ?? ''))
            ?: 'CLIENTES VARIOS';

        $formaPago = $venta->forma_de_pago->value ?? '';
        $esCredito = stripos($formaPago, 'Credito') !== false || stripos($formaPago, 'Crédito') !== false;

        // Metodos de pago
        $metodosPago = [];
        foreach ($venta->despliegueDePagoVentas as $dp) {
            $metodosPago[] = [
                'nombre' => $dp->despliegueDePago->name ?? '',
                'monto' => (float) $dp->monto,
                'sobrecargo_aplicado' => (float) ($dp->sobrecargo_aplicado ?? 0),
            ];
        }

        // Vales aplicados
        $vales = [];
        foreach ($venta->valesAplicados as $va) {
            if (!$va->genera_vale_futuro || !$va->codigo_vale_generado) {
                continue;
            }
            $valeCompra = $va->valeCompra;
            $tipoLabel = match ($valeCompra?->tipo_promocion) {
                'descuento_porcentaje' => 'DESCUENTO %',
                'descuento_fijo' => 'DESCUENTO FIJO',
                'producto_gratis' => 'PRODUCTO GRATIS',
                default => strtoupper($valeCompra?->tipo_promocion ?? ''),
            };
            $beneficio = match ($valeCompra?->descuento_tipo) {
                '%' => ($valeCompra?->descuento_valor ?? 0) . '% DESCUENTO',
                default => 'S/ ' . number_format($valeCompra?->descuento_valor ?? 0, 2) . ' DESCUENTO',
            };

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
            'clienteDocumento' => $cliente?->numero_documento ?? '99999999',
            'clienteDireccion' => $cliente?->direccion ?? '',
            'metodosPago' => $metodosPago,
            'productos' => $productos,
            'calculos' => $calculos,
            'son' => PdfService::numeroALetras($calculos['total']),
            'observaciones' => $venta->descripcion ?: ($msg['observaciones_default'] ?? '- NINGUNA'),
            'vales' => $sinVales ? [] : $vales,
            'consultaUrl' => $this->getConsultaUrl(),
            'plantilla' => $plantilla,
            'est' => $est,
            'msg' => $msg,
            'bloques' => $bloques,
            'font_face_css' => $fontFaceCss,
        ];

        $filename = "TICKET-{$venta->serie}-{$venta->numero}.pdf";

        return PdfService::render(
            'pdf.venta-ticket',
            $data,
            $filename,
            'portrait',
            [0, 0, 226.77, 841.89],
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
            'recomendadoPor',
            'almacen',
            'productosPorAlmacen.productoAlmacen.producto.marca',
            'productosPorAlmacen.productoAlmacen.producto.unidadMedida',
            'productosPorAlmacen.unidadesDerivadas.unidadDerivadaInmutable',
            'despliegueDePagoVentas.despliegueDePago',
            'valesAplicados.valeCompra',
        ])->findOrFail($ventaId);
    }

    /**
     * Preparar la lista de productos para la tabla.
     * Los productos se ordenan para que los que pertenecen a un paquete
     * aparezcan agrupados bajo el nombre del paquete.
     */
    private function prepararProductos(Venta $venta, float $sobrecargoTotal = 0): array
    {
        $productos = [];
        $subtotalTotal = 0;

        // Primera pasada: calcular subtotal total
        foreach ($venta->productosPorAlmacen as $pa) {
            foreach ($pa->unidadesDerivadas as $ud) {
                $cantidad = (float) $ud->cantidad;
                $precio = (float) $ud->precio;
                $descuento = (float) ($ud->descuento ?? 0);
                $subtotal = $cantidad * $precio;
                $subtotalTotal += $subtotal - $descuento;
            }
        }

        // Segunda pasada: preparar productos con sobrecargo distribuido proporcionalmente
        foreach ($venta->productosPorAlmacen as $pa) {
            $producto = $pa->productoAlmacen->producto;

            foreach ($pa->unidadesDerivadas as $ud) {
                $cantidad = (float) $ud->cantidad;
                $precio = (float) $ud->precio;
                $descuento = (float) ($ud->descuento ?? 0);
                $subtotal = $cantidad * $precio;
                $neto = $subtotal - $descuento;

                // Distribuir sobrecargo proporcionalmente
                $sobrecargoProducto = $subtotalTotal > 0 ? ($neto / $subtotalTotal) * $sobrecargoTotal : 0;
                $sobrecargoProductoPorcentaje = $neto > 0 ? ($sobrecargoProducto / $neto) * 100 : 0;
                
                // Precio unitario con sobrecargo incluido
                $precioConSobrecargo = $cantidad > 0 ? $precio + ($sobrecargoProducto / $cantidad) : $precio;
                $subtotalConSobrecargo = $subtotal + $sobrecargoProducto;

                $productos[] = [
                    'codigo' => $producto->cod_producto ?? '',
                    'nombre' => $producto->name,
                    'marca' => $producto->marca->name ?? '',
                    'unidad' => $ud->unidadDerivadaInmutable->name ?? '',
                    'cantidad' => $cantidad,
                    'precio' => $precioConSobrecargo,
                    'descuento' => $descuento,
                    'subtotal' => $subtotalConSobrecargo,
                    'sobrecargo_porcentaje' => $sobrecargoProductoPorcentaje,
                    'sobrecargo_valor' => $sobrecargoProducto,
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

        return $productos;
    }

    /**
     * Calcular subtotal, IGV y total.
     */
    private function calcularTotales(array $productos, float $sobrecargoTotal = 0): array
    {
        $subtotal = array_sum(array_column($productos, 'subtotal'));
        $totalDescuento = array_sum(array_column($productos, 'descuento'));
        
        // El precio ya incluye IGV y el sobrecargo ya está en el subtotal
        // No agregar sobrecargo nuevamente
        $subtotalConSobrecargo = $subtotal - $totalDescuento;
        
        // Extraer el IGV del total (dividir por 1.18 para obtener base, luego calcular IGV)
        $base = $subtotalConSobrecargo / 1.18;
        $igv = $subtotalConSobrecargo - $base;
        
        // El total es el subtotal con sobrecargo (ya tiene IGV incluido)
        $total = $subtotalConSobrecargo;

        return [
            'subtotal' => $base,
            'igv' => $igv,
            'total' => $total,
            'total_descuento' => $totalDescuento,
        ];
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
     * Preparar las filas de informacion del cliente.
     */
    private function prepararInfoCliente(Venta $venta): array
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
                'Direccion' => $cliente?->direccion ?? '',
                'Hora' => PdfService::formatFecha($fecha, 'H:i:s'),
            ],
            [
                'RUC / DNI' => $cliente?->numero_documento ?? '',
                'Tipo Doc.' => $venta->tipo_documento->value,
            ],
            [
                'Vendedor' => $venta->user->name,
                'Almacen' => $venta->almacen->name,
            ],
            [
                'Forma Pago' => $venta->forma_de_pago->value ?? '',
                'Moneda' => 'SOLES',
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
