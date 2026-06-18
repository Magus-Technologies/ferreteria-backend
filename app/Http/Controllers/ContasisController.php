<?php

namespace App\Http\Controllers;

use App\Models\ComprobanteElectronico;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Genera reportes en formato CONTASIS para importación al libro
 * de ventas y compras del sistema contable CONTASIS (Perú).
 */
class ContasisController extends Controller
{
    /**
     * Registro de Ventas — fuente: comprobantes_electronicos (FA/BO/NC/ND).
     * Solo incluye documentos electrónicos emitidos al cliente.
     */
    public function ventas(Request $request): JsonResponse
    {
        $request->validate([
            'desde'      => 'required|date',
            'hasta'      => 'required|date',
            'almacen_id' => 'sometimes|integer',
        ]);

        $query = ComprobanteElectronico::query()
            ->whereBetween('fecha_emision', [$request->desde, $request->hasta])
            ->whereNull('deleted_at')
            ->with(['comprobanteReferencia:id,tipo_comprobante,serie,correlativo'])
            ->select([
                'id', 'venta_id', 'tipo_comprobante', 'serie', 'correlativo',
                'fecha_emision', 'fecha_vencimiento',
                'cliente_tipo_documento', 'cliente_numero_documento', 'cliente_razon_social',
                'operacion_gravada', 'operacion_inafecta', 'total_igv', 'importe_total',
                'tipo_cambio', 'moneda',
                'comprobante_referencia_id', 'tipo_doc_referencia', 'numero_doc_referencia',
                'observaciones',
            ])
            ->orderBy('fecha_emision')
            ->orderBy('tipo_comprobante')
            ->orderBy('serie')
            ->orderBy('correlativo');

        if ($request->filled('almacen_id')) {
            $query->whereHas('venta', fn ($q) => $q->where('almacen_id', $request->almacen_id));
        }

        $items = $query->get()->map(function ($ce) {
            $ref = $ce->comprobanteReferencia;

            // Parsear numero_doc_referencia (ej. "B001-00017908") cuando no hay FK
            $rawRef    = $ce->numero_doc_referencia ?? '';
            $refSerie  = $ref?->serie;
            $refNum    = $ref?->correlativo;
            if (!$refSerie && str_contains($rawRef, '-')) {
                [$refSerie, $refNumStr] = explode('-', $rawRef, 2);
                $refNum = (int) ltrim($refNumStr, '0') ?: null;
            }

            $ccodenti = match ($ce->cliente_tipo_documento) {
                '6'     => 6,
                '1'     => 1,
                '4'     => 4,
                '7'     => 7,
                default => 0,
            };

            return [
                'ffechadoc' => $ce->fecha_emision?->format('d/m/Y') ?? '',
                'ffechaven' => $ce->fecha_vencimiento?->format('d/m/Y'),
                'ccoddoc'   => $ce->tipo_comprobante,
                'cserie'    => $ce->serie,
                'cnumero'   => $ce->correlativo,
                'ccodenti'  => $ccodenti,
                'ccodruc'   => $ce->cliente_numero_documento ?? '',
                'crazsoc'   => $ce->cliente_razon_social ?? '',
                'nbase1'    => round((float) $ce->operacion_gravada, 2),
                'nigv1'     => round((float) $ce->total_igv, 2),
                'ntots'     => round((float) $ce->importe_total, 2),
                'ntc'       => round((float) ($ce->tipo_cambio ?? 1), 4),
                'crefdoc'   => $ce->tipo_doc_referencia,
                'crefser'   => $refSerie,
                'crefnum'   => $refNum,
                'cglosa'    => $ce->observaciones,
            ];
        });

        return response()->json(['data' => $items, 'total' => $items->count()]);
    }

    /**
     * Registro de Compras — fuente: tabla compra + productos.
     * Total calculado como sum(costo * cantidad * factor + flete),
     * excluyendo bonificaciones. Compras anuladas excluidas.
     */
    public function compras(Request $request): JsonResponse
    {
        $request->validate([
            'desde'      => 'required|date',
            'hasta'      => 'required|date',
            'almacen_id' => 'sometimes|integer',
        ]);

        $query = DB::table('compra as c')
            ->select([
                'c.id',
                'c.tipo_documento',
                'c.serie',
                'c.numero',
                'c.fecha',
                'c.tipo_de_cambio',
                'c.percepcion',
                'p.ruc',
                'p.razon_social',
                DB::raw('SUM(CASE WHEN udic.bonificacion = 0 THEN pac.costo * udic.cantidad * udic.factor ELSE 0 END + udic.flete) as total'),
            ])
            ->leftJoin('proveedor as p', 'p.id', '=', 'c.proveedor_id')
            ->leftJoin('productoalmacencompra as pac', 'pac.compra_id', '=', 'c.id')
            ->leftJoin('unidadderivadainmutablecompra as udic', 'udic.producto_almacen_compra_id', '=', 'pac.id')
            ->whereBetween('c.fecha', [
                $request->desde . ' 00:00:00',
                $request->hasta . ' 23:59:59',
            ])
            ->where('c.estado_de_compra', '!=', 'an')
            ->groupBy(
                'c.id', 'c.tipo_documento', 'c.serie', 'c.numero',
                'c.fecha', 'c.tipo_de_cambio', 'c.percepcion',
                'p.ruc', 'p.razon_social'
            )
            ->orderBy('c.fecha')
            ->orderBy('c.tipo_documento')
            ->orderBy('c.serie')
            ->orderBy('c.numero');

        if ($request->filled('almacen_id')) {
            $query->where('c.almacen_id', $request->almacen_id);
        }

        $items = $query->get()->map(function ($row) {
            $total = round((float) ($row->total ?? 0), 2);
            $perc  = round((float) ($row->percepcion ?? 0), 2);
            $tc    = round((float) ($row->tipo_de_cambio ?? 1), 4);

            // Facturas (01) y boletas de empresas (03): gravadas con 18% IGV.
            // Otros tipos: inafectos.
            $esGravada = in_array($row->tipo_documento, ['01', '03']);
            if ($esGravada) {
                $nbase1 = round($total / 1.18, 2);
                $nigv1  = round($total - $nbase1, 2);
                $nina   = 0.0;
            } else {
                $nbase1 = 0.0;
                $nigv1  = 0.0;
                $nina   = $total;
            }

            $ruc      = (string) ($row->ruc ?? '');
            $ccodenti = strlen($ruc) === 11 ? 6 : (strlen($ruc) === 8 ? 1 : 0);

            return [
                'ffechadoc'  => $row->fecha ? Carbon::parse($row->fecha)->format('d/m/Y') : '',
                'ccoddoc'    => $row->tipo_documento ?? '',
                'cserie'     => $row->serie ?? '',
                'cnumero'    => $row->numero ?? '',
                'ccodenti'   => $ccodenti,
                'ccodruc'    => $ruc,
                'crazsoc'    => $row->razon_social ?? '',
                'nbase1'     => $nbase1,
                'nigv1'      => $nigv1,
                'nina'       => $nina,
                'ntots'      => round($total + $perc, 2),
                'ntc'        => $tc,
                'percepcion' => $perc,
            ];
        });

        return response()->json(['data' => $items, 'total' => $items->count()]);
    }
}
