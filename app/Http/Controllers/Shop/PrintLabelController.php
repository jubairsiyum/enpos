<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\BarcodeSettingRequest;
use App\Http\Requests\Shop\PrintLabelPreviewRequest;
use App\Models\BarcodeSetting;
use App\Models\PrintLabelJob;
use App\Models\Product;
use App\Models\SellingPriceGroup;
use App\Models\Variation;
use App\Support\CurrentBusiness;
use App\Support\LabelPdfCodec;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrintLabelController extends Controller
{
    public function __construct(
        private CurrentBusiness $currentBusiness,
        private LabelPdfCodec $labelPdfCodec,
    ) {}

    /**
     * Display Print Labels options, barcode settings, selling price groups, and recent jobs.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $businessId = (int) $this->currentBusiness->id();
        BarcodeSetting::ensureDefaultsForBusiness($businessId);

        $barcodeSettings = BarcodeSetting::query()
            ->where('business_id', $businessId)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        $sellingPriceGroups = SellingPriceGroup::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $recentJobs = PrintLabelJob::query()
            ->where('business_id', $businessId)
            ->with('barcodeSetting:id,name,sticker_size')
            ->latest('id')
            ->limit(20)
            ->get();

        $payload = [
            'barcode_settings'     => $barcodeSettings,
            'selling_price_groups' => $sellingPriceGroups,
            'default_display'      => PrintLabelJob::DEFAULT_DISPLAY_OPTIONS,
            'recent_jobs'          => $recentJobs,
        ];

        if ($request->wantsJson()) {
            return response()->json(['data' => $payload]);
        }

        return Inertia::render('Shop/Products/PrintLabels/Index', $payload);
    }

    /**
     * Search products and variations by name or SKU to add to the Print Labels table.
     */
    public function search(Request $request): JsonResponse
    {
        $businessId = (int) $this->currentBusiness->id();
        $search = trim((string) $request->input('search', ''));
        $limit = max(1, min(50, (int) $request->input('limit', 20)));

        $products = Product::query()
            ->where('business_id', $businessId)
            ->where('is_inactive', false)
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhereHas('variations', function ($vq) use ($search): void {
                            $vq->where('sku', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        });
                });
            })
            ->with([
                'variations' => fn ($q) => $q->orderBy('id'),
                'variations.groupPrices',
            ])
            ->orderBy('name')
            ->limit($limit)
            ->get();

        $results = [];
        foreach ($products as $product) {
            foreach ($product->variations as $variation) {
                $displayVariation = $variation->name === 'DUMMY' ? 'Default' : $variation->name;
                $results[] = [
                    'product_id'         => $product->id,
                    'variation_id'       => $variation->id,
                    'product_name'       => $product->name,
                    'variation_name'     => $displayVariation,
                    'full_name'          => $variation->name === 'DUMMY'
                        ? $product->name
                        : "{$product->name} - {$displayVariation}",
                    'product_sku'        => $product->sku,
                    'variation_sku'      => $variation->sku ?? $product->sku,
                    'barcode_type'       => $product->barcode_type,
                    'default_sell_price' => (float) $variation->default_sell_price,
                    'sell_price_inc_tax' => (float) $variation->sell_price_inc_tax,
                    'group_prices'       => $variation->groupPrices->map(fn ($gp): array => [
                        'price_group_id' => $gp->price_group_id,
                        'price_inc_tax'  => (float) $gp->price_inc_tax,
                    ])->values()->all(),
                ];
            }
        }

        return response()->json(['data' => $results]);
    }

    /**
     * Validate and generate a Print Labels preview payload + printable HTML.
     */
    public function preview(PrintLabelPreviewRequest $request): JsonResponse
    {
        $resolved = $this->resolveLabelPreviewData($request, 'preview');

        return response()->json([
            'message' => 'Labels preview generated successfully.',
            'data'    => $resolved,
        ]);
    }

    /**
     * Validate and generate a downloadable PDF of the labels.
     */
    public function previewPdf(PrintLabelPreviewRequest $request): StreamedResponse|JsonResponse
    {
        $resolved = $this->resolveLabelPreviewData($request, 'pdf');

        /** @var BarcodeSetting $setting */
        $setting = $resolved['barcode_setting_model'];
        $pdfBinary = $this->labelPdfCodec->buildLabelsPdf(
            $resolved['labels'],
            $resolved['display'],
            $setting
        );

        if ($request->boolean('as_json')) {
            return response()->json([
                'message' => 'Labels PDF generated successfully.',
                'data'    => [
                    'job_id'       => $resolved['job_id'],
                    'total_labels' => $resolved['total_labels'],
                    'pdf_base64'   => base64_encode($pdfBinary),
                ],
            ]);
        }

        return response()->streamDownload(function () use ($pdfBinary): void {
            echo $pdfBinary;
        }, 'labels_preview.pdf', [
            'Content-Type'   => 'application/pdf',
            'Content-Length' => (string) strlen($pdfBinary),
            'Cache-Control'  => 'max-age=0',
        ]);
    }

    /**
     * Create a new barcode setting for the current business.
     */
    public function storeBarcodeSetting(BarcodeSettingRequest $request): JsonResponse
    {
        $businessId = (int) $this->currentBusiness->id();
        BarcodeSetting::ensureDefaultsForBusiness($businessId);
        $validated = $request->validated();

        $setting = DB::transaction(function () use ($businessId, $validated): BarcodeSetting {
            if (! empty($validated['is_default'])) {
                BarcodeSetting::where('business_id', $businessId)->update(['is_default' => false]);
            }

            return BarcodeSetting::create(array_merge($validated, [
                'business_id'  => $businessId,
                'barcode_type' => $validated['barcode_type'] ?? 'C128',
                'is_default'   => (bool) ($validated['is_default'] ?? false),
            ]));
        });

        return response()->json([
            'message' => 'Barcode setting created successfully.',
            'data'    => $setting,
        ], 201);
    }

    /**
     * Update an existing barcode setting.
     */
    public function updateBarcodeSetting(BarcodeSettingRequest $request, int $barcodeSetting): JsonResponse
    {
        $businessId = (int) $this->currentBusiness->id();
        $setting = BarcodeSetting::where('business_id', $businessId)->findOrFail($barcodeSetting);
        $validated = $request->validated();

        DB::transaction(function () use ($setting, $businessId, $validated): void {
            if (! empty($validated['is_default'])) {
                BarcodeSetting::where('business_id', $businessId)
                    ->whereKeyNot($setting->id)
                    ->update(['is_default' => false]);
            }

            $setting->update($validated);
        });

        return response()->json([
            'message' => 'Barcode setting updated successfully.',
            'data'    => $setting->fresh(),
        ]);
    }

    /**
     * Delete a barcode setting.
     */
    public function destroyBarcodeSetting(int $barcodeSetting): JsonResponse
    {
        $businessId = (int) $this->currentBusiness->id();
        $setting = BarcodeSetting::where('business_id', $businessId)->findOrFail($barcodeSetting);

        if (BarcodeSetting::where('business_id', $businessId)->count() <= 1) {
            return response()->json([
                'message' => 'At least one barcode setting must remain.',
            ], 422);
        }

        $wasDefault = $setting->is_default;
        $setting->delete();

        if ($wasDefault) {
            $next = BarcodeSetting::where('business_id', $businessId)->orderBy('id')->first();
            if ($next !== null) {
                $next->update(['is_default' => true]);
            }
        }

        return response()->json([
            'message' => 'Barcode setting deleted successfully.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveLabelPreviewData(PrintLabelPreviewRequest $request, string $outputFormat): array
    {
        $business = $this->currentBusiness->get();
        $businessId = (int) $this->currentBusiness->id();
        BarcodeSetting::ensureDefaultsForBusiness($businessId);

        $validated = $request->validated();
        $display = array_merge(
            PrintLabelJob::DEFAULT_DISPLAY_OPTIONS,
            $validated['display'] ?? []
        );

        $barcodeSetting = null;
        if (! empty($validated['barcode_setting_id'])) {
            $barcodeSetting = BarcodeSetting::where('business_id', $businessId)
                ->findOrFail((int) $validated['barcode_setting_id']);
        } else {
            $barcodeSetting = BarcodeSetting::where('business_id', $businessId)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->firstOrFail();
        }

        $currencySymbol = (string) ($business?->currency_symbol ?? '$');
        $businessName = (string) ($business?->name ?? '');

        $resolvedItems = [];
        $expandedLabels = [];
        $totalLabels = 0;

        foreach ($validated['items'] as $index => $item) {
            $variation = $this->resolveItemVariation($item, $businessId, $index);
            $product = $variation->product;
            $qty = (int) $item['quantity'];
            $packingDate = $item['packing_date'] ?? null;
            $priceGroupId = ! empty($item['selling_price_group_id']) ? (int) $item['selling_price_group_id'] : null;

            $unitPrice = $this->resolveLabelPrice(
                $variation,
                $product,
                $priceGroupId,
                (string) $display['price_type']
            );
            $formattedPrice = $currencySymbol . number_format($unitPrice, 2, '.', '');
            $variationLabel = $variation->name === 'DUMMY' ? '' : $variation->name;
            $sku = (string) ($variation->sku ?: $product->sku ?: '');

            $summary = [
                'product_id'             => $product->id,
                'variation_id'           => $variation->id,
                'product_name'           => $product->name,
                'variation_name'         => $variationLabel,
                'sku'                    => $sku,
                'barcode_type'           => $product->barcode_type,
                'quantity'               => $qty,
                'packing_date'           => $packingDate,
                'selling_price_group_id' => $priceGroupId,
                'unit_price'             => $unitPrice,
                'formatted_price'        => $formattedPrice,
                'business_name'          => $businessName,
            ];

            $resolvedItems[] = $summary;
            $totalLabels += $qty;

            for ($i = 0; $i < $qty; $i++) {
                $expandedLabels[] = [
                    'product_id'      => $product->id,
                    'variation_id'    => $variation->id,
                    'product_name'    => $display['show_product_name'] ? $product->name : null,
                    'variation_name'  => ($display['show_variation'] && $variationLabel !== '') ? $variationLabel : null,
                    'unit_price'      => $display['show_price'] ? $unitPrice : null,
                    'formatted_price' => $display['show_price'] ? $formattedPrice : null,
                    'business_name'   => $display['show_business_name'] ? $businessName : null,
                    'packing_date'    => ($display['show_packing_date'] && $packingDate) ? $packingDate : null,
                    'sku'             => $sku,
                    'barcode_type'    => $product->barcode_type,
                ];
            }
        }

        $job = PrintLabelJob::create([
            'business_id'        => $businessId,
            'created_by'         => $request->user()?->id,
            'barcode_setting_id' => $barcodeSetting->id,
            'items'              => $resolvedItems,
            'display_options'    => $display,
            'total_labels'       => $totalLabels,
            'output_format'      => $outputFormat,
        ]);

        return [
            'job_id'                => $job->id,
            'total_labels'          => $totalLabels,
            'barcode_setting'       => $barcodeSetting->toArray(),
            'barcode_setting_model' => $barcodeSetting,
            'display'               => $display,
            'items'                 => $resolvedItems,
            'labels'                => $expandedLabels,
            'html'                  => $this->renderPreviewHtml($expandedLabels, $display, $barcodeSetting),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveItemVariation(array $item, int $businessId, int $index): Variation
    {
        if (! empty($item['variation_id'])) {
            /** @var Variation|null $variation */
            $variation = Variation::query()
                ->with(['product.tax:id,amount', 'groupPrices'])
                ->whereHas('product', fn ($q) => $q->where('business_id', $businessId)->whereNull('deleted_at'))
                ->find((int) $item['variation_id']);

            if ($variation === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.variation_id" => ['Selected variation does not belong to this business.'],
                ]);
            }

            if (! empty($item['product_id']) && (int) $item['product_id'] !== (int) $variation->product_id) {
                throw ValidationException::withMessages([
                    "items.{$index}.variation_id" => ['Selected variation does not belong to the specified product.'],
                ]);
            }

            return $variation;
        }

        /** @var Product|null $product */
        $product = Product::query()
            ->where('business_id', $businessId)
            ->with(['tax:id,amount', 'variations' => fn ($q) => $q->orderBy('id'), 'variations.groupPrices'])
            ->find((int) $item['product_id']);

        $variation = $product?->variations->first();
        if ($variation === null) {
            throw ValidationException::withMessages([
                "items.{$index}.product_id" => ['Selected product has no variations available for label printing.'],
            ]);
        }

        $variation->setRelation('product', $product);

        return $variation;
    }

    private function resolveLabelPrice(
        Variation $variation,
        Product $product,
        ?int $priceGroupId,
        string $priceType,
    ): float {
        if ($priceGroupId !== null) {
            $groupPrice = $variation->groupPrices->firstWhere('price_group_id', $priceGroupId);
            if ($groupPrice !== null) {
                $groupInc = (float) $groupPrice->price_inc_tax;
                if ($priceType === 'exclusive') {
                    $taxPercent = (float) ($product->tax?->amount ?? 0);
                    $multiplier = 1 + ($taxPercent / 100);

                    return $multiplier > 0 ? round($groupInc / $multiplier, 2) : round($groupInc, 2);
                }

                return round($groupInc, 2);
            }
        }

        $raw = $priceType === 'exclusive'
            ? (float) $variation->default_sell_price
            : (float) $variation->sell_price_inc_tax;

        return round($raw, 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $labels
     * @param  array<string, mixed>  $display
     */
    private function renderPreviewHtml(array $labels, array $display, BarcodeSetting $setting): string
    {
        $cols = max(1, (int) $setting->stickers_in_one_row);
        $cards = '';

        foreach ($labels as $label) {
            $parts = [];
            if (! empty($label['business_name'])) {
                $parts[] = '<div style="font-weight:700;font-size:' . (int) $display['business_name_size'] . 'px;">'
                    . e((string) $label['business_name']) . '</div>';
            }
            if (! empty($label['product_name'])) {
                $parts[] = '<div style="font-size:' . (int) $display['product_name_size'] . 'px;">'
                    . e((string) $label['product_name']) . '</div>';
            }
            if (! empty($label['variation_name'])) {
                $parts[] = '<div style="font-size:' . (int) $display['variation_size'] . 'px;">'
                    . e((string) $label['variation_name']) . '</div>';
            }
            if (! empty($label['formatted_price'])) {
                $parts[] = '<div style="font-weight:600;font-size:' . (int) $display['price_size'] . 'px;">'
                    . e((string) $label['formatted_price']) . '</div>';
            }
            if (! empty($label['packing_date'])) {
                $parts[] = '<div style="font-size:' . (int) $display['packing_date_size'] . 'px;">Packed: '
                    . e((string) $label['packing_date']) . '</div>';
            }
            $parts[] = '<div style="font-family:monospace;font-size:11px;margin-top:4px;">'
                . e((string) $label['sku']) . '</div>';

            $cards .= '<div style="border:1px dashed #cbd5e1;padding:8px;text-align:center;">'
                . implode('', $parts) . '</div>';
        }

        return '<div style="display:grid;grid-template-columns:repeat(' . $cols . ',1fr);gap:8px;">'
            . $cards . '</div>';
    }
}
