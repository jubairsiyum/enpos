<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\BulkProductActionRequest;
use App\Http\Requests\Shop\SaveOpeningStockRequest;
use App\Http\Requests\Shop\StoreProductRequest;
use App\Http\Requests\Shop\UpdateProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\PurchaseLine;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\Variation;
use App\Models\VariationLocationDetail;
use App\Services\ProductService;
use App\Support\CurrentBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        private CurrentBusiness $currentBusiness,
        private ProductService $productService,
    ) {}

    /**
     * Display a paginated listing of products (List Products & Stock Report).
     */
    public function index(Request $request): Response|JsonResponse
    {
        $businessId = $this->currentBusiness->id();
        $query = $this->buildFilteredQuery($request);

        $perPage = max(1, min(200, (int) $request->input('per_page', 25)));
        $products = $query
            ->with([
                'unit:id,actual_name,short_name',
                'category:id,name',
                'subCategory:id,name',
                'brand:id,name',
                'tax:id,name,amount',
                'warranty:id,name',
                'locations:id,name',
                'variations.locationDetails',
                'variations.comboComponents.componentVariation.product:id,name',
            ])
            ->paginate($perPage)
            ->withQueryString();

        $transformed = $products->through(fn (Product $p): array => $this->productService->transformProduct($p));

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $transformed->items(),
                'meta' => [
                    'current_page' => $products->currentPage(),
                    'last_page'    => $products->lastPage(),
                    'per_page'     => $products->perPage(),
                    'total'        => $products->total(),
                ],
            ]);
        }

        $stockReport = $this->buildStockReportRows($businessId, $request);

        return Inertia::render('Shop/Products/Index', [
            'products'    => $transformed,
            'stockReport' => $stockReport,
            'filterOptions' => [
                'categories' => Category::where('business_id', $businessId)
                    ->where('category_type', 'product')
                    ->whereNull('parent_id')
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'brands' => Brand::where('business_id', $businessId)->orderBy('name')->get(['id', 'name']),
                'units' => Unit::where('business_id', $businessId)->orderBy('actual_name')->get(['id', 'actual_name', 'short_name']),
                'tax_rates' => TaxRate::where('business_id', $businessId)->orderBy('name')->get(['id', 'name', 'amount']),
                'locations' => Location::where('business_id', $businessId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            ],
            'filters' => $request->only([
                'search',
                'per_page',
                'type',
                'category_id',
                'brand_id',
                'unit_id',
                'tax_id',
                'location_id',
                'is_inactive',
                'not_for_selling',
                'woocommerce_enabled',
                'only_trashed',
                'sort_by',
                'sort_dir',
                'opening_stock_product_id',
            ]),
            'can' => [
                'create'              => $request->user()?->can('shop.products.create') ?? true,
                'edit'                => $request->user()?->can('shop.products.edit') ?? true,
                'delete'              => $request->user()?->can('shop.products.delete') ?? true,
                'add_opening_stock'   => $request->user()?->can('shop.products.add_opening_stock') ?? true,
                'view_purchase_price' => $request->user()?->can('shop.products.view_purchase_price') ?? true,
            ],
        ]);
    }

    /**
     * Store a newly created product via the shared AddProductController handler.
     */
    public function store(StoreProductRequest $request, AddProductController $addProductController): JsonResponse|RedirectResponse
    {
        return $addProductController->store($request);
    }

    /**
     * Display the specified product with variations and location stock.
     */
    public function show(Request $request, Product $product): JsonResponse|Response
    {
        $this->authorizeProduct($product);

        $product->load([
            'unit',
            'subUnit',
            'category',
            'subCategory',
            'brand',
            'tax',
            'warranty',
            'locations',
            'variations.locationDetails.location:id,name',
            'variations.comboComponents.componentVariation.product:id,name',
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $this->productService->transformProduct($product),
            ]);
        }

        return Inertia::render('Shop/Products/Index', [
            'selectedProduct' => $this->productService->transformProduct($product),
        ]);
    }

    /**
     * Render the Add/Edit Product form pre-populated for editing.
     */
    public function edit(Request $request, Product $product, AddProductController $addProductController): Response
    {
        $this->authorizeProduct($product);

        $businessId = $this->currentBusiness->id();
        $product->load([
            'unit',
            'subUnit',
            'category',
            'subCategory',
            'brand',
            'tax',
            'warranty',
            'locations',
            'variations.locationDetails',
            'variations.comboComponents.componentVariation.product:id,name',
        ]);

        return Inertia::render('Shop/Products/Create', [
            'product'     => $this->productService->transformProduct($product),
            'formOptions' => $addProductController->getFormOptions($businessId),
            'can'         => [
                'create' => $request->user()?->can('shop.products.create') ?? true,
                'edit'   => $request->user()?->can('shop.products.edit') ?? true,
            ],
        ]);
    }

    /**
     * Update the specified product.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse|RedirectResponse
    {
        $this->authorizeProduct($product);

        $businessId = $this->currentBusiness->id();
        $validated = $request->validated();

        $variationImages = [];
        foreach (($validated['variations'] ?? []) as $index => $row) {
            $file = $request->file("variations.{$index}.image");
            $variationImages[(int) $index] = $file instanceof UploadedFile ? $file : null;
        }

        $updated = $this->productService->updateProduct(
            product: $product,
            businessId: $businessId,
            validated: $validated,
            image: $request->file('image') instanceof UploadedFile ? $request->file('image') : null,
            brochure: $request->file('product_brochure') instanceof UploadedFile ? $request->file('product_brochure') : null,
            variationImages: $variationImages,
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Product updated successfully.',
                'data'    => $this->productService->transformProduct($updated),
            ]);
        }

        return redirect()->route('shop.products.index')
            ->with('success', 'Product updated successfully.');
    }

    /**
     * Soft-delete the specified product.
     */
    public function destroy(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        $this->authorizeProduct($product);

        $product->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Product deleted successfully.']);
        }

        return redirect()->route('shop.products.index')
            ->with('success', 'Product deleted successfully.');
    }

    /**
     * Restore a soft-deleted product.
     */
    public function restore(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        $businessId = $this->currentBusiness->id();

        $product = Product::withoutGlobalScope('business')
            ->onlyTrashed()
            ->where('business_id', $businessId)
            ->findOrFail($id);

        $product->restore();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Product restored successfully.',
                'data'    => $this->productService->transformProduct($product->fresh([
                    'unit', 'category', 'subCategory', 'brand', 'tax', 'locations', 'variations.locationDetails',
                ])),
            ]);
        }

        return redirect()->route('shop.products.index')
            ->with('success', 'Product restored successfully.');
    }

    /**
     * Permanently delete a product and its stored media files.
     */
    public function forceDelete(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        $businessId = $this->currentBusiness->id();

        $product = Product::withoutGlobalScope('business')
            ->withTrashed()
            ->where('business_id', $businessId)
            ->findOrFail($id);

        $this->productService->forceDeleteProduct($product);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Product permanently deleted.']);
        }

        return redirect()->route('shop.products.index')
            ->with('success', 'Product permanently deleted.');
    }

    /**
     * Duplicate an existing product.
     */
    public function duplicate(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        $this->authorizeProduct($product);

        $businessId = $this->currentBusiness->id();
        $userId = (int) ($request->user()?->id ?? 0);
        $copy = $this->productService->duplicateProduct($product, $businessId, $userId);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Product duplicated successfully.',
                'data'    => $this->productService->transformProduct($copy),
            ], 201);
        }

        return redirect()->route('shop.products.index')
            ->with('success', 'Product duplicated successfully.');
    }

    /**
     * Retrieve opening stock matrix (variations x locations) for a product.
     */
    public function openingStock(Request $request, Product $product): JsonResponse
    {
        $this->authorizeProduct($product);
        $businessId = $this->currentBusiness->id();

        $product->load(['unit:id,actual_name,short_name', 'locations:id,name', 'variations']);
        $locations = $product->locations->isNotEmpty()
            ? $product->locations
            : Location::where('business_id', $businessId)->where('is_active', true)->get(['id', 'name']);

        $existingPurchaseLines = PurchaseLine::query()
            ->with('transaction:id,location_id,type')
            ->where('product_id', $product->id)
            ->whereHas('transaction', fn ($q) => $q->where('type', 'opening_stock'))
            ->get();

        $stockDetails = VariationLocationDetail::where('product_id', $product->id)->get();

        $rows = [];
        foreach ($locations as $location) {
            foreach ($product->variations as $variation) {
                $openingLine = $existingPurchaseLines->first(function (PurchaseLine $line) use ($location, $variation): bool {
                    return (int) $line->variation_id === (int) $variation->id
                        && (int) $line->transaction?->location_id === (int) $location->id;
                });
                $detail = $stockDetails->first(function (VariationLocationDetail $d) use ($location, $variation): bool {
                    return (int) $d->variation_id === (int) $variation->id
                        && (int) $d->location_id === (int) $location->id;
                });

                $rows[] = [
                    'variation_id'         => $variation->id,
                    'variation_name'       => $variation->name === 'DUMMY' ? 'Default' : $variation->name,
                    'sku'                  => $variation->sku,
                    'location_id'          => $location->id,
                    'location_name'        => $location->name,
                    'quantity'             => $openingLine ? (float) $openingLine->quantity : 0.0,
                    'current_stock'        => $detail ? (float) $detail->qty_available : 0.0,
                    'unit_cost_before_tax' => $openingLine
                        ? (float) $openingLine->purchase_price
                        : (float) $variation->default_purchase_price,
                    'lot_number'           => $openingLine?->lot_number,
                    'exp_date'             => $openingLine?->exp_date?->format('Y-m-d'),
                ];
            }
        }

        return response()->json([
            'data' => [
                'product_id'   => $product->id,
                'product_name' => $product->name,
                'sku'          => $product->sku,
                'unit'         => $product->unit?->short_name ?? '',
                'rows'         => $rows,
            ],
        ]);
    }

    /**
     * Save or update opening stock quantities for a product.
     */
    public function saveOpeningStock(SaveOpeningStockRequest $request, Product $product): JsonResponse|RedirectResponse
    {
        $this->authorizeProduct($product);

        $validated = $request->validated();
        $result = $this->productService->saveOpeningStock(
            product: $product,
            businessId: $this->currentBusiness->id(),
            userId: (int) ($request->user()?->id ?? 0),
            stocks: $validated['stocks'],
            transactionDate: $validated['transaction_date'] ?? null,
            additionalNotes: $validated['additional_notes'] ?? null,
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Opening stock saved successfully.',
                'data'    => $result,
            ]);
        }

        return redirect()->back()->with('success', 'Opening stock saved successfully.');
    }

    /**
     * Retrieve chronological stock movement history for a product.
     */
    public function stockHistory(Request $request, Product $product): JsonResponse
    {
        $this->authorizeProduct($product);

        $locationId = $request->filled('location_id') ? (int) $request->input('location_id') : null;

        return response()->json([
            'data' => $this->productService->getStockHistory($product, $locationId),
        ]);
    }

    /**
     * Return printable barcode label metadata for a product's variations.
     */
    public function labels(Product $product): JsonResponse
    {
        $this->authorizeProduct($product);

        $product->load(['brand:id,name', 'variations:id,product_id,name,sku,default_sell_price,sell_price_inc_tax']);

        return response()->json([
            'data' => [
                'product_id'   => $product->id,
                'product_name' => $product->name,
                'sku'          => $product->sku,
                'barcode_type' => $product->barcode_type,
                'brand_name'   => $product->brand?->name,
                'variations'   => $product->variations->map(fn (Variation $v): array => [
                    'variation_id'       => $v->id,
                    'variation_name'     => $v->name === 'DUMMY' ? 'Default' : $v->name,
                    'sku'                => $v->sku,
                    'default_sell_price' => (float) $v->default_sell_price,
                    'sell_price_inc_tax' => (float) $v->sell_price_inc_tax,
                ])->values()->all(),
            ],
        ]);
    }

    /**
     * Return the Stock Report dataset (per product/variation/location valuation).
     */
    public function stockReport(Request $request): JsonResponse
    {
        $businessId = $this->currentBusiness->id();
        $rows = $this->buildStockReportRows($businessId, $request);

        return response()->json([
            'data' => $rows,
        ]);
    }

    /**
     * Bulk soft-delete selected products.
     */
    public function bulkDestroy(BulkProductActionRequest $request): JsonResponse|RedirectResponse
    {
        $businessId = $this->currentBusiness->id();
        $ids = $request->validated('product_ids');

        $count = Product::where('business_id', $businessId)
            ->whereIn('id', $ids)
            ->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "{$count} product(s) deleted successfully.",
                'data'    => ['affected_count' => $count],
            ]);
        }

        return redirect()->back()->with('success', "{$count} product(s) deleted successfully.");
    }

    /**
     * Bulk add or remove selected products from business locations.
     */
    public function bulkLocation(BulkProductActionRequest $request): JsonResponse|RedirectResponse
    {
        $businessId = $this->currentBusiness->id();
        $validated = $request->validated();
        $action = $validated['action'] ?? 'add';

        $locationIds = $validated['location_ids'] ?? [];
        if (! empty($validated['location_id'])) {
            $locationIds[] = (int) $validated['location_id'];
        }
        $locationIds = array_values(array_unique(array_map('intval', $locationIds)));

        if (empty($locationIds)) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Please select at least one location.'], 422);
            }

            return redirect()->back()->with('error', 'Please select at least one location.');
        }

        $products = Product::where('business_id', $businessId)
            ->whereIn('id', $validated['product_ids'])
            ->with('variations:id,product_id')
            ->get();

        DB::transaction(function () use ($products, $locationIds, $action): void {
            foreach ($products as $product) {
                if ($action === 'remove') {
                    $product->locations()->detach($locationIds);
                } else {
                    $product->locations()->syncWithoutDetaching($locationIds);
                    foreach ($product->variations as $variation) {
                        foreach ($locationIds as $locationId) {
                            VariationLocationDetail::firstOrCreate(
                                ['variation_id' => $variation->id, 'location_id' => $locationId],
                                ['product_id' => $product->id, 'qty_available' => 0]
                            );
                        }
                    }
                }
            }
        });

        $verb = $action === 'remove' ? 'removed from' : 'added to';
        $message = "{$products->count()} product(s) {$verb} selected location(s).";

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'data'    => ['affected_count' => $products->count()],
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Bulk activate or deactivate selected products.
     */
    public function bulkToggleStatus(BulkProductActionRequest $request): JsonResponse|RedirectResponse
    {
        $businessId = $this->currentBusiness->id();
        $validated = $request->validated();
        $isInactive = (bool) ($validated['is_inactive'] ?? true);

        $count = Product::where('business_id', $businessId)
            ->whereIn('id', $validated['product_ids'])
            ->update(['is_inactive' => $isInactive]);

        $label = $isInactive ? 'deactivated' : 'activated';
        $message = "{$count} product(s) {$label} successfully.";

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'data'    => ['affected_count' => $count, 'is_inactive' => $isInactive],
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Bulk enable/mark selected products for WooCommerce Sync.
     */
    public function bulkWooCommerceSync(BulkProductActionRequest $request): JsonResponse|RedirectResponse
    {
        $businessId = $this->currentBusiness->id();
        $ids = $request->validated('product_ids');

        $count = Product::where('business_id', $businessId)
            ->whereIn('id', $ids)
            ->update(['woocommerce_disable_sync' => false]);

        $message = "{$count} product(s) queued for WooCommerce sync.";

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'data'    => ['affected_count' => $count],
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Build the filtered and sorted product query.
     *
     * @return Builder<Product>
     */
    private function buildFilteredQuery(Request $request): Builder
    {
        $businessId = $this->currentBusiness->id();

        $query = Product::query()
            ->where('products.business_id', $businessId)
            ->withSum('variationLocationDetails as total_stock', 'qty_available');

        if ($request->boolean('only_trashed')) {
            $query->onlyTrashed();
        } elseif ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function (Builder $q) use ($search): void {
                $q->where('products.name', 'like', "%{$search}%")
                    ->orWhere('products.sku', 'like', "%{$search}%")
                    ->orWhereHas('variations', fn (Builder $v) => $v->where('sku', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('type')) {
            $query->where('products.type', (string) $request->input('type'));
        }

        if ($request->filled('category_id')) {
            $catId = (int) $request->input('category_id');
            $query->where(function (Builder $q) use ($catId): void {
                $q->where('products.category_id', $catId)
                    ->orWhere('products.sub_category_id', $catId);
            });
        }

        if ($request->filled('brand_id')) {
            $query->where('products.brand_id', (int) $request->input('brand_id'));
        }

        if ($request->filled('unit_id')) {
            $query->where('products.unit_id', (int) $request->input('unit_id'));
        }

        if ($request->filled('tax_id')) {
            $query->where('products.tax_id', (int) $request->input('tax_id'));
        }

        if ($request->filled('location_id')) {
            $locId = (int) $request->input('location_id');
            $query->whereHas('locations', fn (Builder $q) => $q->where('locations.id', $locId));
        }

        if ($request->has('is_inactive') && $request->input('is_inactive') !== '' && $request->input('is_inactive') !== null) {
            $query->where('products.is_inactive', $request->boolean('is_inactive'));
        }

        if ($request->boolean('not_for_selling')) {
            $query->where('products.not_for_selling', true);
        }

        if ($request->boolean('woocommerce_enabled')) {
            $query->where('products.woocommerce_disable_sync', false);
        }

        $sortBy = (string) $request->input('sort_by', 'id');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        return match ($sortBy) {
            'name'                   => $query->orderBy('products.name', $sortDir),
            'default_purchase_price' => $query->orderBy('products.default_purchase_price', $sortDir),
            'default_sell_price'     => $query->orderBy('products.default_sell_price', $sortDir),
            'current_stock'          => $query->orderBy('total_stock', $sortDir),
            'type'                   => $query->orderBy('products.type', $sortDir),
            'sku'                    => $query->orderBy('products.sku', $sortDir),
            default                  => $query->orderBy('products.id', $sortDir),
        };
    }

    /**
     * Build location-wise stock report rows for the Stock Report tab and API.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildStockReportRows(int $businessId, Request $request): array
    {
        $query = VariationLocationDetail::query()
            ->with([
                'product:id,business_id,name,sku,type,category_id,unit_id',
                'product.category:id,name',
                'product.unit:id,short_name',
                'variation:id,product_id,name,sku,default_purchase_price,default_sell_price,sell_price_inc_tax',
                'location:id,name',
            ])
            ->whereHas('product', function (Builder $q) use ($businessId, $request): void {
                $q->where('business_id', $businessId);
                if ($request->filled('search')) {
                    $s = trim((string) $request->input('search'));
                    $q->where(fn (Builder $sub) => $sub->where('name', 'like', "%{$s}%")->orWhere('sku', 'like', "%{$s}%"));
                }
                if ($request->filled('category_id')) {
                    $q->where('category_id', (int) $request->input('category_id'));
                }
                if ($request->filled('brand_id')) {
                    $q->where('brand_id', (int) $request->input('brand_id'));
                }
            });

        if ($request->filled('location_id')) {
            $query->where('location_id', (int) $request->input('location_id'));
        }

        return $query->limit(200)->get()->map(function (VariationLocationDetail $detail): array {
            $qty = (float) $detail->qty_available;
            $purchasePrice = (float) ($detail->variation?->default_purchase_price ?? 0);
            $sellPrice = (float) ($detail->variation?->default_sell_price ?? 0);
            $stockValuePurchase = round($qty * $purchasePrice, 2);
            $stockValueSale = round($qty * $sellPrice, 2);

            return [
                'product_id'              => $detail->product_id,
                'variation_id'            => $detail->variation_id,
                'sku'                     => $detail->variation?->sku ?? $detail->product?->sku,
                'product_name'            => $detail->product?->name ?? '',
                'variation_name'          => $detail->variation?->name === 'DUMMY' ? '' : ($detail->variation?->name ?? ''),
                'category_name'           => $detail->product?->category?->name ?? '-',
                'location_name'           => $detail->location?->name ?? '-',
                'unit_short_name'         => $detail->product?->unit?->short_name ?? '',
                'unit_selling_price'      => $sellPrice,
                'current_stock'           => $qty,
                'stock_value_by_purchase' => $stockValuePurchase,
                'stock_value_by_sale'     => $stockValueSale,
                'potential_profit'        => round($stockValueSale - $stockValuePurchase, 2),
            ];
        })->values()->all();
    }

    private function authorizeProduct(Product $product): void
    {
        if ((int) $product->business_id !== $this->currentBusiness->id()) {
            abort(404);
        }
    }
}
