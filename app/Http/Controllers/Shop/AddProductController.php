<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\StoreProductRequest;
use App\Models\Brand;
use App\Models\Business;
use App\Models\Category;
use App\Models\Location;
use App\Models\SellingPriceGroup;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\Variation;
use App\Models\VariationTemplate;
use App\Models\Warranty;
use App\Services\ProductService;
use App\Support\CurrentBusiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AddProductController extends Controller
{
    public function __construct(
        private CurrentBusiness $currentBusiness,
        private ProductService $productService,
    ) {}

    /**
     * Render the Add Product form page with all catalog dropdown metadata.
     */
    public function create(Request $request): Response
    {
        $businessId = $this->currentBusiness->id();

        return Inertia::render('Shop/Products/Create', [
            'product'     => null,
            'formOptions' => $this->getFormOptions($businessId),
            'can'         => [
                'create' => $request->user()?->can('shop.products.create') ?? true,
                'edit'   => $request->user()?->can('shop.products.edit') ?? true,
            ],
        ]);
    }

    /**
     * Return Add Product dropdown metadata as JSON for API / Postman testing.
     */
    public function formOptions(): JsonResponse
    {
        $businessId = $this->currentBusiness->id();

        return response()->json([
            'data' => $this->getFormOptions($businessId),
        ]);
    }

    /**
     * Store a newly created product (single, variable, or combo).
     */
    public function store(StoreProductRequest $request): JsonResponse|RedirectResponse
    {
        $businessId = $this->currentBusiness->id();
        $userId = (int) ($request->user()?->id ?? 0);
        $validated = $request->validated();

        $variationImages = [];
        foreach (($validated['variations'] ?? []) as $index => $row) {
            $file = $request->file("variations.{$index}.image");
            $variationImages[(int) $index] = $file instanceof UploadedFile ? $file : null;
        }

        if (empty($variationImages) && $request->hasFile('variation_image')) {
            $file = $request->file('variation_image');
            $variationImages[0] = $file instanceof UploadedFile ? $file : null;
        }

        $result = $this->productService->createOrMergeProduct(
            businessId: $businessId,
            userId: $userId,
            validated: $validated,
            image: $request->file('image') instanceof UploadedFile ? $request->file('image') : null,
            brochure: $request->file('product_brochure') instanceof UploadedFile ? $request->file('product_brochure') : null,
            variationImages: $variationImages,
        );

        $product = $result['product'];
        $created = $result['created'];
        $message = $created
            ? 'Product created successfully.'
            : 'Product already exists with the same type; stock quantity increased.';

        if ($request->wantsJson()) {
            return response()->json([
                'message'           => $message,
                'stock_incremented' => ! $created,
                'data'              => $this->productService->transformProduct($product),
            ], $created ? 201 : 200);
        }

        $saveAction = (string) ($validated['save_action'] ?? 'save');

        if ($saveAction === 'save_and_add_another') {
            return redirect()->route('shop.products.create')
                ->with('success', $message . ' You can add another product.');
        }

        if ($saveAction === 'save_and_opening_stock') {
            return redirect()->route('shop.products.index', ['opening_stock_product_id' => $product->id])
                ->with('success', $message . ' Set opening stock below.');
        }

        return redirect()->route('shop.products.index')
            ->with('success', $message);
    }

    /**
     * Quick-create a Unit directly from the Add Product form (+ button).
     */
    public function quickStoreUnit(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'actual_name'   => ['required', 'string', 'max:255'],
            'short_name'    => ['required', 'string', 'max:20'],
            'allow_decimal' => ['nullable', 'boolean'],
        ]);

        $businessId = $this->currentBusiness->id();
        $actualName = trim($validated['actual_name']);
        $shortName = trim($validated['short_name']);

        $exists = Unit::where('business_id', $businessId)
            ->where(function ($q) use ($actualName, $shortName): void {
                $q->whereRaw('LOWER(TRIM(actual_name)) = ?', [mb_strtolower($actualName)])
                    ->orWhereRaw('LOWER(TRIM(short_name)) = ?', [mb_strtolower($shortName)]);
            })
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'actual_name' => ['A unit with this name or short name already exists.'],
            ]);
        }

        $unit = Unit::create([
            'business_id'   => $businessId,
            'actual_name'   => $actualName,
            'short_name'    => $shortName,
            'allow_decimal' => (bool) ($validated['allow_decimal'] ?? false),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Unit created successfully.',
                'data'    => $unit,
            ], 201);
        }

        return redirect()->back()->with('success', 'Unit created successfully.');
    }

    /**
     * Quick-create a Brand directly from the Add Product form (+ button).
     */
    public function quickStoreBrand(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $businessId = $this->currentBusiness->id();
        $name = trim($validated['name']);

        $exists = Brand::where('business_id', $businessId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['A brand with this name already exists.'],
            ]);
        }

        $brand = Brand::create([
            'business_id' => $businessId,
            'name'        => $name,
            'description' => $validated['description'] ?? null,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Brand created successfully.',
                'data'    => $brand,
            ], 201);
        }

        return redirect()->back()->with('success', 'Brand created successfully.');
    }

    /**
     * Collect all dropdown options needed by the Add / Edit Product form.
     *
     * @return array<string, mixed>
     */
    public function getFormOptions(int $businessId): array
    {
        $business = Business::withoutGlobalScope('business')->find($businessId);

        $categories = Category::where('business_id', $businessId)
            ->where('category_type', 'product')
            ->orderBy('name')
            ->get(['id', 'name', 'short_code', 'parent_id']);

        $comboVariations = Variation::query()
            ->with(['product:id,name,sku,type'])
            ->whereHas('product', function ($q) use ($businessId): void {
                $q->where('business_id', $businessId)
                    ->whereIn('type', ['single', 'variable']);
            })
            ->orderBy('id')
            ->get(['id', 'product_id', 'name', 'sku', 'default_purchase_price', 'default_sell_price'])
            ->map(fn (Variation $v): array => [
                'id'                     => $v->id,
                'product_id'             => $v->product_id,
                'label'                  => trim(($v->product?->name ?? '') . ($v->name !== 'DUMMY' ? ' - ' . $v->name : '') . ' (' . ($v->sku ?? '') . ')'),
                'sku'                    => $v->sku,
                'default_purchase_price' => (float) $v->default_purchase_price,
                'default_sell_price'     => (float) $v->default_sell_price,
            ])
            ->values();

        return [
            'units' => Unit::where('business_id', $businessId)
                ->orderBy('actual_name')
                ->get(['id', 'actual_name', 'short_name', 'allow_decimal']),
            'brands' => Brand::where('business_id', $businessId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'categories' => $categories->whereNull('parent_id')->values(),
            'sub_categories' => $categories->whereNotNull('parent_id')->values(),
            'locations' => Location::where('business_id', $businessId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'location_id']),
            'tax_rates' => TaxRate::where('business_id', $businessId)
                ->orderBy('name')
                ->get(['id', 'name', 'amount', 'is_default']),
            'warranties' => Warranty::where('business_id', $businessId)
                ->orderBy('name')
                ->get(['id', 'name', 'duration', 'duration_type']),
            'variation_templates' => VariationTemplate::where('business_id', $businessId)
                ->with('values:id,variation_template_id,name')
                ->orderBy('name')
                ->get(['id', 'name']),
            'selling_price_groups' => SellingPriceGroup::where('business_id', $businessId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'combo_variations' => $comboVariations,
            'barcode_types' => [
                ['value' => 'C128', 'label' => 'Code 128 (C128)'],
                ['value' => 'C39', 'label' => 'Code 39 (C39)'],
                ['value' => 'EAN13', 'label' => 'EAN-13'],
                ['value' => 'EAN8', 'label' => 'EAN-8'],
                ['value' => 'UPCA', 'label' => 'UPC-A'],
                ['value' => 'UPCE', 'label' => 'UPC-E'],
                ['value' => 'ITF14', 'label' => 'ITF-14'],
            ],
            'default_profit_percent' => $business && $business->default_profit_percent !== null
                ? (float) $business->default_profit_percent
                : 25.00,
        ];
    }
}
