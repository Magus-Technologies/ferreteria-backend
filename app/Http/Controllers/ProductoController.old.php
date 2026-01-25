<?php

namespace App\Http\Controllers;

use App\Contracts\ProductoServiceInterface;
use App\Contracts\ProductoImportServiceInterface;
use App\Contracts\ProductoFileServiceInterface;
use App\Contracts\ProductoValidationServiceInterface;
use App\Contracts\ProductoPriceServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    public function __construct(
        private ProductoServiceInterface $productoService,
        private ProductoImportServiceInterface $importService,
        private ProductoFileServiceInterface $fileService,
        private ProductoValidationServiceInterface $validationService,
        private ProductoPriceServiceInterface $priceService,
    ) {}

    /**
     * Display a listing of the resource.
     *
     * Required permission: PRODUCTO_LISTADO
     */
    public function index(Request $request): JsonResponse
    {
        // Validate required parameters
        $validated = $request->validate([
            "almacen_id" => "required|integer|exists:almacen,id",
            "search" => "nullable|string",
            "estado" => "nullable|boolean",
            "categoria_id" => "nullable|integer|exists:categoria,id",
            "marca_id" => "nullable|integer|exists:marca,id",
            "unidad_medida_id" => "nullable|integer|exists:unidadmedida,id",
            "accion_tecnica" => "nullable|string",
            "ubicacion_id" => "nullable|integer|exists:ubicacion,id",
            "cs_stock" => "nullable|in:con_stock,sin_stock,all",
            "cs_comision" => "nullable|in:con_comision,sin_comision,all",
            "per_page" => "nullable|integer|min:1|max:200",
            "page" => "nullable|integer|min:1",
        ]);

        return $this->productoService->getAllByAlmacen($validated);
    }

    /**
     * Store a newly created resource in storage.
     *
     * Creates complete product with:
     * - Product (tabla producto)
     * - ProductoAlmacen (tabla productoalmacen)
     * - Prices (tabla productoalmacenunidadderivada)
     * - Optional initial stock (8 tables if has stock)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Product fields
            "cod_producto" => "nullable|string|unique:producto",
            "cod_barra" => "nullable|string|unique:producto",
            "name" => "required|string|unique:producto",
            "name_ticket" => "required|string",
            "categoria_id" => "required|exists:categoria,id",
            "marca_id" => "required|exists:marca,id",
            "unidad_medida_id" => "required|exists:unidadmedida,id",
            "accion_tecnica" => "nullable|string",
            "img" => "nullable|string",
            "ficha_tecnica" => "nullable|string",
            "stock_min" => "required|numeric|min:0",
            "stock_max" => "nullable|integer|min:0",
            "unidades_contenidas" => "required|numeric|min:0",
            "estado" => "boolean",
            "permitido" => "nullable|boolean",

            // Context
            "almacen_id" => "required|exists:almacen,id",

            // ProductoAlmacen
            "producto_almacen" => "required|array",
            "producto_almacen.ubicacion_id" => "required|exists:ubicacion,id",

            // Unit derivatives (Prices)
            "unidades_derivadas" => "required|array|min:1",
            "unidades_derivadas.*.unidad_derivada_id" =>
                "required|exists:unidadderivada,id",
            "unidades_derivadas.*.factor" => "required|numeric|min:0",
            "unidades_derivadas.*.precio_publico" => "required|numeric|min:0",
            "unidades_derivadas.*.comision_publico" => "nullable|numeric",
            "unidades_derivadas.*.precio_especial" => "nullable|numeric",
            "unidades_derivadas.*.comision_especial" => "nullable|numeric",
            "unidades_derivadas.*.activador_especial" => "nullable|numeric",
            "unidades_derivadas.*.precio_minimo" => "nullable|numeric",
            "unidades_derivadas.*.comision_minimo" => "nullable|numeric",
            "unidades_derivadas.*.activador_minimo" => "nullable|numeric",
            "unidades_derivadas.*.precio_ultimo" => "nullable|numeric",
            "unidades_derivadas.*.comision_ultimo" => "nullable|numeric",
            "unidades_derivadas.*.activador_ultimo" => "nullable|numeric",
            "unidades_derivadas.*.costo" => "required|numeric|min:0",

            // Purchase (Initial stock)
            "compra" => "nullable|array",
            "compra.lote" => "nullable|string",
            "compra.vencimiento" => "nullable|date",
            "compra.stock_entero" => "nullable|numeric|min:0",
            "compra.stock_fraccion" => "nullable|numeric|min:0",
        ]);

        return $this->productoService->create($validated);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        return $this->productoService->getById($id);
    }

    /**
     * Get price details for a specific product in a warehouse.
     *
     * GET /api/productos/{id}/detalle-precios?almacen_id={id}
     * Required permission: PRODUCTO_LISTADO
     */
    public function detallePrecios(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            "almacen_id" => "required|integer|exists:almacen,id",
        ]);

        return $this->priceService->getProductPriceDetails(
            $id,
            $validated["almacen_id"],
        );
    }

    /**
     * Update the specified resource in storage.
     *
     * Updates complete product:
     * - Product (tabla producto)
     * - ProductoAlmacen (tabla productoalmacen)
     * - Prices (DELETE ALL + CREATE MANY in productoalmacenunidadderivada)
     *
     * NOTE: Does NOT modify stock (compra field is ignored)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            // Product fields
            "cod_producto" =>
                "nullable|string|unique:producto,cod_producto," . $id,
            "cod_barra" => "nullable|string|unique:producto,cod_barra," . $id,
            "name" => "required|string|unique:producto,name," . $id,
            "name_ticket" => "required|string",
            "categoria_id" => "required|exists:categoria,id",
            "marca_id" => "required|exists:marca,id",
            "unidad_medida_id" => "required|exists:unidadmedida,id",
            "accion_tecnica" => "nullable|string",
            "img" => "nullable|string",
            "ficha_tecnica" => "nullable|string",
            "stock_min" => "required|numeric|min:0",
            "stock_max" => "nullable|integer|min:0",
            "unidades_contenidas" => "required|numeric|min:0",
            "estado" => "boolean",
            "permitido" => "nullable|boolean",

            // Context
            "almacen_id" => "required|exists:almacen,id",

            // ProductoAlmacen
            "producto_almacen" => "required|array",
            "producto_almacen.ubicacion_id" => "required|exists:ubicacion,id",

            // Unit derivatives (Prices)
            "unidades_derivadas" => "required|array|min:1",
            "unidades_derivadas.*.unidad_derivada_id" =>
                "required|exists:unidadderivada,id",
            "unidades_derivadas.*.factor" => "required|numeric|min:0",
            "unidades_derivadas.*.precio_publico" => "required|numeric|min:0",
            "unidades_derivadas.*.comision_publico" => "nullable|numeric",
            "unidades_derivadas.*.precio_especial" => "nullable|numeric",
            "unidades_derivadas.*.comision_especial" => "nullable|numeric",
            "unidades_derivadas.*.activador_especial" => "nullable|numeric",
            "unidades_derivadas.*.precio_minimo" => "nullable|numeric",
            "unidades_derivadas.*.comision_minimo" => "nullable|numeric",
            "unidades_derivadas.*.activador_minimo" => "nullable|numeric",
            "unidades_derivadas.*.precio_ultimo" => "nullable|numeric",
            "unidades_derivadas.*.comision_ultimo" => "nullable|numeric",
            "unidades_derivadas.*.activador_ultimo" => "nullable|numeric",
            "unidades_derivadas.*.costo" => "required|numeric|min:0",
        ]);

        return $this->productoService->update($id, $validated);
    }

    /**
     * Remove the specified resource from storage.
     *
     * Deletes product with validations:
     * - Verifies it has no inventory movements
     * - Verifies it has no more than 1 purchase
     * - If has 1 purchase, verifies it's initial stock
     * - Deletes the initial stock purchase
     * - Deletes the product (cascade deletes producto_almacen and prices)
     */
    public function destroy(int $id): JsonResponse
    {
        return $this->productoService->delete($id);
    }

    /**
     * Import products from Excel.
     *
     * POST /api/productos/import
     *
     * Imports products in batches of 50:
     * - Creates producto + producto_almacen
     * - Adjusts cost by dividing by unidades_contenidas
     * - Returns duplicates (products that already exist)
     */
    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            "data" => "required|array",
        ]);

        return $this->importService->importFromExcel($validated["data"]);
    }

    /**
     * Get import progress status
     *
     * GET /api/productos/import-progress/{importId}
     */
    public function importProgress(string $importId): JsonResponse
    {
        return $this->importService->getImportProgress($importId);
    }

    /**
     * Cancel an ongoing import process
     *
     * POST /api/productos/import-cancel/{importId}
     */
    public function cancelImport(string $importId): JsonResponse
    {
        return $this->importService->cancelImport($importId);
    }

    /**
     * Get import results
     *
     * GET /api/productos/import-results/{importId}
     */
    public function importResults(string $importId): JsonResponse
    {
        return $this->importService->getImportResults($importId);
    }

    /**
     * Validate if a product code already exists
     *
     * GET /api/productos/validar-codigo?cod_producto=ABC123&exclude_id=123
     */
    public function validarCodigo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            "cod_producto" => "required|string",
            "exclude_id" => "nullable|integer",
        ]);

        return $this->validationService->validateProductCode(
            $validated["cod_producto"],
            $validated["exclude_id"] ?? null,
        );
    }

    /**
     * Upload files for a product (image and/or technical sheet)
     *
     * POST /api/productos/{id}/upload-files
     */
    public function uploadFiles(Request $request, int $id): JsonResponse
    {
        return $this->fileService->uploadProductFiles($id, $request);
    }

    /**
     * Upload multiple files for multiple products at once
     *
     * POST /api/productos/upload-files-masivo
     *
     * Body:
     * - files: array of files
     * - tipo: 'img' | 'ficha_tecnica'
     *
     * Response:
     * {
     *   "data": {
     *     "uploaded": ["17337", "17338"],
     *     "not_found": ["99999"]
     *   }
     * }
     */
    public function uploadFilesMasivo(Request $request): JsonResponse
    {
        return $this->fileService->uploadMultipleProductFiles($request);
    }
}
