<?php

namespace App\Services;

use App\Models\Business;
use App\Models\ComboProductComponent;
use App\Models\Location;
use App\Models\Product;
use App\Models\PurchaseLine;
use App\Models\SellLine;
use App\Models\TaxRate;
use App\Models\Transaction;
use App\Models\Variation;
use App\Models\VariationLocationDetail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductService
{
    /**
     * Create a new product with locations, variations, combo components, and uploaded media.
     *
     * @param  array<string, mixed>  $validated
     * @param  array<int, UploadedFile|null>  $variationImages
     */
    public function createProduct(
        int $businessId,
        int $userId,
        array $validated,
        ?UploadedFile $image = null,
        ?UploadedFile $brochure = null,
        array $variationImages = [],
    ): Product {
        return DB::transaction(function () use ($businessId, $userId, $validated, $image, $brochure, $variationImages): Product {
            $type = $validated['type'] ?? 'single';
            $sku = ! empty($validated['sku'])
                ? (string) $validated['sku']
                : Product::generateSku($businessId, 'AS');

            $taxPercent = $this->resolveTaxPercent($businessId, $validated['tax_id'] ?? null);
            $defaultProfitPercent = $this->resolveDefaultProfitPercent($businessId, $validated['profit_percent'] ?? null);

            $imagePath = $image ? $image->store('products/images', 'public') : null;
            $brochurePath = $brochure ? $brochure->store('products/brochures', 'public') : null;

            $pricing = $this->computePricing(
                purchaseExc: $validated['default_purchase_price'] ?? null,
                purchaseInc: $validated['dpp_inc_tax'] ?? null,
                profitPercent: $defaultProfitPercent,
                sellExc: $validated['default_sell_price'] ?? null,
                sellInc: $validated['sell_price_inc_tax'] ?? null,
                taxPercent: $taxPercent,
            );

            $product = Product::create([
                'business_id'                 => $businessId,
                'created_by'                  => $userId,
                'name'                        => $validated['name'],
                'type'                        => $type,
                'sku'                         => $sku,
                'barcode_type'                => $validated['barcode_type'] ?? 'C128',
                'unit_id'                     => $validated['unit_id'],
                'sub_unit_id'                 => $validated['sub_unit_id'] ?? null,
                'sub_unit_multiplier'         => $validated['sub_unit_multiplier'] ?? null,
                'brand_id'                    => $validated['brand_id'] ?? null,
                'category_id'                 => $validated['category_id'] ?? null,
                'sub_category_id'             => $validated['sub_category_id'] ?? null,
                'warranty_id'                 => $validated['warranty_id'] ?? null,
                'tax_id'                      => $validated['tax_id'] ?? null,
                'tax_type'                    => $validated['tax_type'] ?? 'exclusive',
                'enable_stock'                => $validated['enable_stock'] ?? true,
                'alert_quantity'              => ($validated['enable_stock'] ?? true) ? ($validated['alert_quantity'] ?? null) : null,
                'not_for_selling'             => $validated['not_for_selling'] ?? false,
                'is_inactive'                 => $validated['is_inactive'] ?? false,
                'enable_sr_no'                => $validated['enable_sr_no'] ?? false,
                'weight'                      => $validated['weight'] ?? null,
                'preparation_time_in_minutes' => $validated['preparation_time_in_minutes'] ?? null,
                'woocommerce_disable_sync'    => $validated['woocommerce_disable_sync'] ?? false,
                'description'                 => $validated['description'] ?? null,
                'image'                       => $imagePath,
                'product_brochure'            => $brochurePath,
                'default_purchase_price'      => $pricing['default_purchase_price'],
                'default_sell_price'          => $pricing['default_sell_price'],
                'profit_percent'              => $pricing['profit_percent'],
                'custom_fields'               => $validated['custom_fields'] ?? null,
            ]);

            $locationIds = $this->resolveLocationIds($businessId, $validated['location_ids'] ?? null);
            $product->locations()->sync($locationIds);

            $this->syncVariationsOnCreate($product, $type, $validated, $pricing, $taxPercent, $defaultProfitPercent, $locationIds, $variationImages);

            return $product->fresh([
                'unit',
                'subUnit',
                'category',
                'subCategory',
                'brand',
                'tax',
                'warranty',
                'locations',
                'variations.locationDetails',
                'variations.comboComponents.componentVariation.product',
            ]);
        });
    }

    /**
     * Update an existing product and sync its locations, variations, and combo components.
     *
     * @param  array<string, mixed>  $validated
     * @param  array<int, UploadedFile|null>  $variationImages
     */
    public function updateProduct(
        Product $product,
        int $businessId,
        array $validated,
        ?UploadedFile $image = null,
        ?UploadedFile $brochure = null,
        array $variationImages = [],
    ): Product {
        return DB::transaction(function () use ($product, $businessId, $validated, $image, $brochure, $variationImages): Product {
            $type = $validated['type'] ?? $product->type;
            $taxId = array_key_exists('tax_id', $validated) ? $validated['tax_id'] : $product->tax_id;
            $taxPercent = $this->resolveTaxPercent($businessId, $taxId);
            $defaultProfitPercent = $this->resolveDefaultProfitPercent(
                $businessId,
                $validated['profit_percent'] ?? (float) $product->profit_percent
            );

            $updateData = Arr::only($validated, [
                'name',
                'sku',
                'barcode_type',
                'type',
                'unit_id',
                'sub_unit_id',
                'sub_unit_multiplier',
                'brand_id',
                'category_id',
                'sub_category_id',
                'warranty_id',
                'tax_id',
                'tax_type',
                'enable_stock',
                'alert_quantity',
                'not_for_selling',
                'is_inactive',
                'enable_sr_no',
                'weight',
                'preparation_time_in_minutes',
                'woocommerce_disable_sync',
                'description',
                'custom_fields',
            ]);

            if (array_key_exists('sku', $updateData) && empty($updateData['sku'])) {
                $updateData['sku'] = Product::generateSku($businessId, 'AS');
            }

            if ($image) {
                if ($product->image) {
                    Storage::disk('public')->delete($product->image);
                }
                $updateData['image'] = $image->store('products/images', 'public');
            }

            if ($brochure) {
                if ($product->product_brochure) {
                    Storage::disk('public')->delete($product->product_brochure);
                }
                $updateData['product_brochure'] = $brochure->store('products/brochures', 'public');
            }

            $pricing = $this->computePricing(
                purchaseExc: $validated['default_purchase_price'] ?? (float) $product->default_purchase_price,
                purchaseInc: $validated['dpp_inc_tax'] ?? null,
                profitPercent: $defaultProfitPercent,
                sellExc: $validated['default_sell_price'] ?? (array_key_exists('default_purchase_price', $validated) || array_key_exists('profit_percent', $validated) ? null : (float) $product->default_sell_price),
                sellInc: $validated['sell_price_inc_tax'] ?? null,
                taxPercent: $taxPercent,
            );

            if ($type !== 'variable') {
                $updateData['default_purchase_price'] = $pricing['default_purchase_price'];
                $updateData['default_sell_price'] = $pricing['default_sell_price'];
                $updateData['profit_percent'] = $pricing['profit_percent'];
            }

            $product->update($updateData);

            if (array_key_exists('location_ids', $validated)) {
                $locationIds = $validated['location_ids'] ?? [];
                $product->locations()->sync($locationIds);
            } else {
                $locationIds = $product->locations()->pluck('locations.id')->all();
            }

            $this->syncVariationsOnUpdate($product, $type, $validated, $pricing, $taxPercent, $defaultProfitPercent, $locationIds, $variationImages);

            return $product->fresh([
                'unit',
                'subUnit',
                'category',
                'subCategory',
                'brand',
                'tax',
                'warranty',
                'locations',
                'variations.locationDetails',
                'variations.comboComponents.componentVariation.product',
            ]);
        });
    }

    /**
     * Duplicate an existing product with a new SKU, locations, variations, and combo components.
     */
    public function duplicateProduct(Product $source, int $businessId, int $userId): Product
    {
        return DB::transaction(function () use ($source, $businessId, $userId): Product {
            $source->load(['locations', 'variations.comboComponents']);

            $newSku = Product::generateSku($businessId, 'AS');
            $copy = $source->replicate(['sku', 'created_by']);
            $copy->name = $source->name . ' (Copy)';
            $copy->sku = $newSku;
            $copy->created_by = $userId;
            $copy->save();

            $locationIds = $source->locations->pluck('id')->all();
            $copy->locations()->sync($locationIds);

            foreach ($source->variations as $index => $variation) {
                $subSku = $source->type === 'variable'
                    ? $newSku . '-' . ($index + 1)
                    : $newSku;

                $newVariation = $copy->variations()->create([
                    'name'                   => $variation->name,
                    'sku'                    => $subSku,
                    'default_purchase_price' => $variation->default_purchase_price,
                    'dpp_inc_tax'            => $variation->dpp_inc_tax,
                    'profit_percent'         => $variation->profit_percent,
                    'default_sell_price'     => $variation->default_sell_price,
                    'sell_price_inc_tax'     => $variation->sell_price_inc_tax,
                    'variation_value_id'     => $variation->variation_value_id,
                    'variation_template_id'  => $variation->variation_template_id,
                    'image'                  => $variation->image,
                ]);

                foreach ($locationIds as $locationId) {
                    VariationLocationDetail::create([
                        'variation_id'  => $newVariation->id,
                        'location_id'   => $locationId,
                        'product_id'    => $copy->id,
                        'qty_available' => 0,
                    ]);
                }

                foreach ($variation->comboComponents as $component) {
                    ComboProductComponent::create([
                        'variation_id'           => $newVariation->id,
                        'component_variation_id' => $component->component_variation_id,
                        'qty'                    => $component->qty,
                    ]);
                }
            }

            return $copy->fresh([
                'unit',
                'subUnit',
                'category',
                'subCategory',
                'brand',
                'tax',
                'warranty',
                'locations',
                'variations.locationDetails',
                'variations.comboComponents.componentVariation.product',
            ]);
        });
    }

    /**
     * Save or update opening stock per variation & location, recording an opening_stock transaction.
     *
     * @param  array<int, array<string, mixed>>  $stocks
     * @return array<string, mixed>
     */
    public function saveOpeningStock(
        Product $product,
        int $businessId,
        int $userId,
        array $stocks,
        ?string $transactionDate = null,
        ?string $additionalNotes = null,
    ): array {
        return DB::transaction(function () use ($product, $businessId, $userId, $stocks, $transactionDate, $additionalNotes): array {
            $date = $transactionDate ?: now()->toDateString();
            $product->load(['tax', 'variations']);
            $taxPercent = $product->tax ? (float) $product->tax->amount : 0.0;

            $groupedByLocation = collect($stocks)->groupBy('location_id');
            $locationIdsToEnsure = $groupedByLocation->keys()->map(fn ($id): int => (int) $id)->all();
            $product->locations()->syncWithoutDetaching($locationIdsToEnsure);

            $updatedEntries = [];

            foreach ($groupedByLocation as $locationId => $rows) {
                $locationId = (int) $locationId;

                $transaction = Transaction::firstOrCreate(
                    [
                        'business_id' => $businessId,
                        'type'        => 'opening_stock',
                        'location_id' => $locationId,
                        'invoice_no'  => 'OS-' . $product->id . '-' . $locationId,
                    ],
                    [
                        'status'           => 'received',
                        'payment_status'   => 'paid',
                        'created_by'       => $userId,
                        'transaction_date' => $date,
                        'additional_notes' => $additionalNotes,
                    ]
                );

                $locationTotal = 0.0;

                foreach ($rows as $row) {
                    $variationId = (int) $row['variation_id'];
                    $newQty = round((float) $row['quantity'], 4);
                    $variation = $product->variations->firstWhere('id', $variationId);

                    $unitCostExc = isset($row['unit_cost_before_tax'])
                        ? round((float) $row['unit_cost_before_tax'], 4)
                        : round((float) ($variation?->default_purchase_price ?? $product->default_purchase_price), 4);

                    $unitCostInc = round($unitCostExc * (1 + $taxPercent / 100), 4);
                    $itemTax = round($unitCostInc - $unitCostExc, 4);
                    $subTotal = round($unitCostInc * $newQty, 4);
                    $locationTotal += $subTotal;

                    $existingLine = PurchaseLine::where('transaction_id', $transaction->id)
                        ->where('product_id', $product->id)
                        ->where('variation_id', $variationId)
                        ->first();

                    $previousOpeningQty = $existingLine ? (float) $existingLine->quantity : 0.0;
                    $delta = round($newQty - $previousOpeningQty, 4);

                    if ($existingLine) {
                        $existingLine->update([
                            'quantity'               => $newQty,
                            'purchase_price'         => $unitCostExc,
                            'purchase_price_inc_tax' => $unitCostInc,
                            'item_tax'               => $itemTax,
                            'tax_id'                 => $product->tax_id,
                            'sub_total'              => $subTotal,
                            'lot_number'             => $row['lot_number'] ?? $existingLine->lot_number,
                            'exp_date'               => $row['exp_date'] ?? $existingLine->exp_date,
                        ]);
                    } else {
                        PurchaseLine::create([
                            'transaction_id'         => $transaction->id,
                            'product_id'             => $product->id,
                            'variation_id'           => $variationId,
                            'quantity'               => $newQty,
                            'purchase_price'         => $unitCostExc,
                            'purchase_price_inc_tax' => $unitCostInc,
                            'item_tax'               => $itemTax,
                            'tax_id'                 => $product->tax_id,
                            'sub_total'              => $subTotal,
                            'lot_number'             => $row['lot_number'] ?? null,
                            'exp_date'               => $row['exp_date'] ?? null,
                        ]);
                    }

                    $stockDetail = VariationLocationDetail::firstOrNew([
                        'variation_id' => $variationId,
                        'location_id'  => $locationId,
                    ]);
                    $stockDetail->product_id = $product->id;
                    $stockDetail->qty_available = round(((float) ($stockDetail->qty_available ?? 0)) + $delta, 4);
                    $stockDetail->save();

                    $updatedEntries[] = [
                        'variation_id'  => $variationId,
                        'location_id'   => $locationId,
                        'quantity'      => $newQty,
                        'qty_available' => (float) $stockDetail->qty_available,
                    ];
                }

                $transaction->update([
                    'transaction_date' => $date,
                    'total_before_tax' => round($locationTotal, 2),
                    'total_amount'     => round($locationTotal, 2),
                    'amount_paid'      => round($locationTotal, 2),
                    'additional_notes' => $additionalNotes ?? $transaction->additional_notes,
                ]);
            }

            return [
                'product_id' => $product->id,
                'entries'    => $updatedEntries,
                'total_stock' => (float) VariationLocationDetail::where('product_id', $product->id)->sum('qty_available'),
            ];
        });
    }

    /**
     * Build chronological stock movement history for a product.
     *
     * @return array<string, mixed>
     */
    public function getStockHistory(Product $product, ?int $locationId = null): array
    {
        $product->load(['unit:id,actual_name,short_name', 'variations:id,product_id,name,sku']);

        $purchaseRows = PurchaseLine::query()
            ->with(['transaction.location:id,name', 'variation:id,name,sku'])
            ->where('product_id', $product->id)
            ->whereHas('transaction', function ($q) use ($locationId): void {
                if ($locationId !== null) {
                    $q->where('location_id', $locationId);
                }
            })
            ->get()
            ->map(function (PurchaseLine $line): array {
                $tx = $line->transaction;
                $isReturn = $tx?->type === 'purchase_return';
                $qtyChange = $isReturn ? -(float) $line->quantity : (float) $line->quantity;

                return [
                    'date'           => $tx?->transaction_date?->format('Y-m-d') ?? $line->created_at?->format('Y-m-d'),
                    'timestamp'      => $line->created_at?->toIso8601String(),
                    'type'           => $tx?->type ?? 'purchase',
                    'type_label'     => match ($tx?->type) {
                        'opening_stock'     => 'Opening Stock',
                        'purchase_return'   => 'Purchase Return',
                        'purchase_transfer' => 'Stock Transfer (In)',
                        default             => 'Purchase',
                    },
                    'reference_no'   => $tx?->invoice_no ?? ('TX-' . $tx?->id),
                    'location_id'    => $tx?->location_id,
                    'location_name'  => $tx?->location?->name ?? '-',
                    'variation_name' => $line->variation?->name === 'DUMMY' ? 'Default' : ($line->variation?->name ?? 'Default'),
                    'quantity_change' => $qtyChange,
                ];
            });

        $sellRows = SellLine::query()
            ->with(['transaction.location:id,name', 'variation:id,name,sku'])
            ->where('product_id', $product->id)
            ->whereHas('transaction', function ($q) use ($locationId): void {
                if ($locationId !== null) {
                    $q->where('location_id', $locationId);
                }
            })
            ->get()
            ->map(function (SellLine $line): array {
                $tx = $line->transaction;
                $isReturn = $tx?->type === 'sell_return';
                $qtyChange = $isReturn ? (float) $line->quantity : -(float) $line->quantity;

                return [
                    'date'           => $tx?->transaction_date?->format('Y-m-d') ?? $line->created_at?->format('Y-m-d'),
                    'timestamp'      => $line->created_at?->toIso8601String(),
                    'type'           => $tx?->type ?? 'sell',
                    'type_label'     => match ($tx?->type) {
                        'sell_return'      => 'Sell Return',
                        'sell_transfer'    => 'Stock Transfer (Out)',
                        'stock_adjustment' => 'Stock Adjustment',
                        default            => 'Sell',
                    },
                    'reference_no'   => $tx?->invoice_no ?? ('TX-' . $tx?->id),
                    'location_id'    => $tx?->location_id,
                    'location_name'  => $tx?->location?->name ?? '-',
                    'variation_name' => $line->variation?->name === 'DUMMY' ? 'Default' : ($line->variation?->name ?? 'Default'),
                    'quantity_change' => $qtyChange,
                ];
            });

        $merged = $purchaseRows->concat($sellRows)
            ->sortBy('timestamp')
            ->values();

        $runningStock = 0.0;
        $entries = $merged->map(function (array $entry) use (&$runningStock): array {
            $runningStock = round($runningStock + $entry['quantity_change'], 4);
            $entry['new_quantity'] = $runningStock;

            return $entry;
        })->reverse()->values()->all();

        $currentStockQuery = VariationLocationDetail::where('product_id', $product->id);
        if ($locationId !== null) {
            $currentStockQuery->where('location_id', $locationId);
        }

        return [
            'product_id'    => $product->id,
            'product_name'  => $product->name,
            'sku'           => $product->sku,
            'unit'          => $product->unit?->short_name ?? '',
            'current_stock' => (float) $currentStockQuery->sum('qty_available'),
            'entries'       => $entries,
        ];
    }

    /**
     * Transform a Product Eloquent model into the canonical API & Inertia shape.
     *
     * @return array<string, mixed>
     */
    public function transformProduct(Product $product): array
    {
        $variations = $product->relationLoaded('variations') ? $product->variations : collect();
        $locationDetails = $product->relationLoaded('variationLocationDetails')
            ? $product->variationLocationDetails
            : $variations->flatMap(fn (Variation $v) => $v->relationLoaded('locationDetails') ? $v->locationDetails : collect());

        $currentStock = (float) $locationDetails->sum('qty_available');

        $purchasePrices = $variations->isNotEmpty()
            ? $variations->pluck('default_purchase_price')->map(fn ($p): float => (float) $p)
            : collect([(float) $product->default_purchase_price]);

        $sellPrices = $variations->isNotEmpty()
            ? $variations->pluck('default_sell_price')->map(fn ($p): float => (float) $p)
            : collect([(float) $product->default_sell_price]);

        $sellPricesIncTax = $variations->isNotEmpty()
            ? $variations->pluck('sell_price_inc_tax')->map(fn ($p): float => (float) $p)
            : collect([(float) $product->default_sell_price]);

        $minPurchase = (float) $purchasePrices->min();
        $maxPurchase = (float) $purchasePrices->max();
        $minSell = (float) $sellPrices->min();
        $maxSell = (float) $sellPrices->max();

        $categoryDisplay = $product->category?->name ?? '';
        if ($product->subCategory) {
            $categoryDisplay = trim($categoryDisplay . ' -- ' . $product->subCategory->name, ' -');
        }

        $locationNames = $product->relationLoaded('locations')
            ? $product->locations->pluck('name')->values()->all()
            : [];

        $firstVariation = $variations->first();

        return [
            'id'                          => $product->id,
            'business_id'                 => $product->business_id,
            'name'                        => $product->name,
            'type'                        => $product->type,
            'type_label'                  => ucfirst($product->type),
            'sku'                         => $product->sku,
            'barcode_type'                => $product->barcode_type,
            'unit_id'                     => $product->unit_id,
            'unit_name'                   => $product->unit?->actual_name ?? '',
            'unit_short_name'             => $product->unit?->short_name ?? '',
            'sub_unit_id'                 => $product->sub_unit_id,
            'sub_unit_multiplier'         => $product->sub_unit_multiplier,
            'category_id'                 => $product->category_id,
            'sub_category_id'             => $product->sub_category_id,
            'category_name'               => $product->category?->name,
            'sub_category_name'           => $product->subCategory?->name,
            'category_display'            => $categoryDisplay !== '' ? $categoryDisplay : null,
            'brand_id'                    => $product->brand_id,
            'brand_name'                  => $product->brand?->name,
            'tax_id'                      => $product->tax_id,
            'tax_name'                    => $product->tax?->name,
            'tax_amount'                  => $product->tax ? (float) $product->tax->amount : 0.0,
            'tax_type'                    => $product->tax_type,
            'warranty_id'                 => $product->warranty_id,
            'warranty_name'               => $product->warranty?->name,
            'enable_stock'                => (bool) $product->enable_stock,
            'alert_quantity'              => $product->alert_quantity !== null ? (float) $product->alert_quantity : null,
            'not_for_selling'             => (bool) $product->not_for_selling,
            'is_inactive'                 => (bool) $product->is_inactive,
            'enable_sr_no'                => (bool) $product->enable_sr_no,
            'weight'                      => $product->weight,
            'preparation_time_in_minutes' => $product->preparation_time_in_minutes,
            'woocommerce_disable_sync'    => (bool) $product->woocommerce_disable_sync,
            'description'                 => $product->description,
            'image'                       => $product->image,
            'image_url'                   => $product->image ? Storage::disk('public')->url($product->image) : null,
            'product_brochure'            => $product->product_brochure,
            'brochure_url'                => $product->product_brochure ? Storage::disk('public')->url($product->product_brochure) : null,
            'default_purchase_price'      => (float) $product->default_purchase_price,
            'dpp_inc_tax'                 => $firstVariation ? (float) $firstVariation->dpp_inc_tax : (float) $product->default_purchase_price,
            'profit_percent'              => (float) $product->profit_percent,
            'default_sell_price'          => (float) $product->default_sell_price,
            'sell_price_inc_tax'          => $firstVariation ? (float) $firstVariation->sell_price_inc_tax : (float) $product->default_sell_price,
            'min_purchase_price'          => $minPurchase,
            'max_purchase_price'          => $maxPurchase,
            'min_sell_price'              => $minSell,
            'max_sell_price'              => $maxSell,
            'min_sell_price_inc_tax'      => (float) $sellPricesIncTax->min(),
            'max_sell_price_inc_tax'      => (float) $sellPricesIncTax->max(),
            'current_stock'               => $currentStock,
            'location_ids'                => $product->relationLoaded('locations') ? $product->locations->pluck('id')->values()->all() : [],
            'location_names'              => $locationNames,
            'locations'                   => $product->relationLoaded('locations')
                ? $product->locations->map(fn (Location $loc): array => ['id' => $loc->id, 'name' => $loc->name])->values()->all()
                : [],
            'variations'                  => $variations->map(fn (Variation $v): array => [
                'id'                     => $v->id,
                'name'                   => $v->name,
                'sku'                    => $v->sku,
                'default_purchase_price' => (float) $v->default_purchase_price,
                'dpp_inc_tax'            => (float) $v->dpp_inc_tax,
                'profit_percent'         => (float) $v->profit_percent,
                'default_sell_price'     => (float) $v->default_sell_price,
                'sell_price_inc_tax'     => (float) $v->sell_price_inc_tax,
                'variation_template_id'  => $v->variation_template_id,
                'variation_value_id'     => $v->variation_value_id,
                'image'                  => $v->image,
                'image_url'              => $v->image ? Storage::disk('public')->url($v->image) : null,
                'stock'                  => $v->relationLoaded('locationDetails') ? (float) $v->locationDetails->sum('qty_available') : 0.0,
                'combo_components'       => $v->relationLoaded('comboComponents')
                    ? $v->comboComponents->map(fn (ComboProductComponent $c): array => [
                        'id'                     => $c->id,
                        'component_variation_id' => $c->component_variation_id,
                        'qty'                    => (float) $c->qty,
                        'component_name'         => $c->componentVariation?->product?->name,
                        'variation_name'         => $c->componentVariation?->name,
                        'sku'                    => $c->componentVariation?->sku,
                        'default_purchase_price' => (float) ($c->componentVariation?->default_purchase_price ?? 0),
                    ])->values()->all()
                    : [],
            ])->values()->all(),
            'custom_fields'               => $product->custom_fields,
            'deleted_at'                  => $product->deleted_at?->toIso8601String(),
            'created_at'                  => $product->created_at?->format('Y-m-d'),
        ];
    }

    /**
     * Compute consistent tax-exclusive, tax-inclusive, and margin values.
     *
     * @return array{default_purchase_price: float, dpp_inc_tax: float, profit_percent: float, default_sell_price: float, sell_price_inc_tax: float}
     */
    public function computePricing(
        mixed $purchaseExc,
        mixed $purchaseInc,
        float $profitPercent,
        mixed $sellExc,
        mixed $sellInc,
        float $taxPercent,
    ): array {
        $multiplier = 1 + ($taxPercent / 100);

        if ($purchaseExc !== null) {
            $pExc = round((float) $purchaseExc, 4);
            $pInc = $purchaseInc !== null ? round((float) $purchaseInc, 4) : round($pExc * $multiplier, 4);
        } elseif ($purchaseInc !== null) {
            $pInc = round((float) $purchaseInc, 4);
            $pExc = $multiplier > 0 ? round($pInc / $multiplier, 4) : $pInc;
        } else {
            $pExc = 0.0;
            $pInc = 0.0;
        }

        if ($sellExc !== null) {
            $sExc = round((float) $sellExc, 4);
            $sInc = $sellInc !== null ? round((float) $sellInc, 4) : round($sExc * $multiplier, 4);
            if ($pExc > 0) {
                $profitPercent = round((($sExc - $pExc) / $pExc) * 100, 2);
            }
        } elseif ($sellInc !== null) {
            $sInc = round((float) $sellInc, 4);
            $sExc = $multiplier > 0 ? round($sInc / $multiplier, 4) : $sInc;
            if ($pExc > 0) {
                $profitPercent = round((($sExc - $pExc) / $pExc) * 100, 2);
            }
        } else {
            $sExc = round($pExc * (1 + ($profitPercent / 100)), 4);
            $sInc = round($sExc * $multiplier, 4);
        }

        return [
            'default_purchase_price' => $pExc,
            'dpp_inc_tax'            => $pInc,
            'profit_percent'         => round($profitPercent, 2),
            'default_sell_price'     => $sExc,
            'sell_price_inc_tax'     => $sInc,
        ];
    }

    private function resolveTaxPercent(int $businessId, mixed $taxId): float
    {
        if (empty($taxId)) {
            return 0.0;
        }

        $tax = TaxRate::where('business_id', $businessId)->find((int) $taxId);

        return $tax ? (float) $tax->amount : 0.0;
    }

    private function resolveDefaultProfitPercent(int $businessId, mixed $explicitProfit): float
    {
        if ($explicitProfit !== null) {
            return (float) $explicitProfit;
        }

        $business = Business::withoutGlobalScope('business')->find($businessId);

        return $business && $business->default_profit_percent !== null
            ? (float) $business->default_profit_percent
            : 25.00;
    }

    /**
     * @param  array<int, int>|null  $requestedLocationIds
     * @return array<int, int>
     */
    private function resolveLocationIds(int $businessId, ?array $requestedLocationIds): array
    {
        if (is_array($requestedLocationIds) && count($requestedLocationIds) > 0) {
            return $requestedLocationIds;
        }

        return Location::where('business_id', $businessId)
            ->where('is_active', true)
            ->pluck('id')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array{default_purchase_price: float, dpp_inc_tax: float, profit_percent: float, default_sell_price: float, sell_price_inc_tax: float}  $pricing
     * @param  array<int, int>  $locationIds
     * @param  array<int, UploadedFile|null>  $variationImages
     */
    private function syncVariationsOnCreate(
        Product $product,
        string $type,
        array $validated,
        array $pricing,
        float $taxPercent,
        float $defaultProfitPercent,
        array $locationIds,
        array $variationImages,
    ): void {
        if ($type === 'variable' && ! empty($validated['variations'])) {
            $minPurchase = null;
            $minSell = null;

            foreach ($validated['variations'] as $index => $row) {
                $rowProfit = isset($row['profit_percent']) ? (float) $row['profit_percent'] : $defaultProfitPercent;
                $rowPricing = $this->computePricing(
                    purchaseExc: $row['default_purchase_price'] ?? 0,
                    purchaseInc: $row['dpp_inc_tax'] ?? null,
                    profitPercent: $rowProfit,
                    sellExc: $row['default_sell_price'] ?? null,
                    sellInc: $row['sell_price_inc_tax'] ?? null,
                    taxPercent: $taxPercent,
                );

                $varImg = $variationImages[$index] ?? null;
                $varImgPath = $varImg ? $varImg->store('products/variations', 'public') : null;

                $variation = $product->variations()->create([
                    'name'                   => $row['name'],
                    'sku'                    => ! empty($row['sku']) ? $row['sku'] : $product->sku . '-' . ($index + 1),
                    'default_purchase_price' => $rowPricing['default_purchase_price'],
                    'dpp_inc_tax'            => $rowPricing['dpp_inc_tax'],
                    'profit_percent'         => $rowPricing['profit_percent'],
                    'default_sell_price'     => $rowPricing['default_sell_price'],
                    'sell_price_inc_tax'     => $rowPricing['sell_price_inc_tax'],
                    'variation_template_id'  => $row['variation_template_id'] ?? null,
                    'variation_value_id'     => $row['variation_value_id'] ?? null,
                    'image'                  => $varImgPath,
                ]);

                $this->ensureVariationLocationRows($product->id, $variation->id, $locationIds);

                $minPurchase = $minPurchase === null ? $rowPricing['default_purchase_price'] : min($minPurchase, $rowPricing['default_purchase_price']);
                $minSell = $minSell === null ? $rowPricing['default_sell_price'] : min($minSell, $rowPricing['default_sell_price']);
            }

            $product->update([
                'default_purchase_price' => $minPurchase ?? 0,
                'default_sell_price'     => $minSell ?? 0,
                'profit_percent'         => $defaultProfitPercent,
            ]);

            return;
        }

        $varImg = $variationImages[0] ?? null;
        $varImgPath = $varImg ? $varImg->store('products/variations', 'public') : null;

        $variation = $product->variations()->create([
            'name'                   => 'DUMMY',
            'sku'                    => $product->sku,
            'default_purchase_price' => $pricing['default_purchase_price'],
            'dpp_inc_tax'            => $pricing['dpp_inc_tax'],
            'profit_percent'         => $pricing['profit_percent'],
            'default_sell_price'     => $pricing['default_sell_price'],
            'sell_price_inc_tax'     => $pricing['sell_price_inc_tax'],
            'image'                  => $varImgPath,
        ]);

        $this->ensureVariationLocationRows($product->id, $variation->id, $locationIds);

        if ($type === 'combo' && ! empty($validated['combo_components'])) {
            foreach ($validated['combo_components'] as $component) {
                ComboProductComponent::create([
                    'variation_id'           => $variation->id,
                    'component_variation_id' => (int) $component['component_variation_id'],
                    'qty'                    => (float) $component['qty'],
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array{default_purchase_price: float, dpp_inc_tax: float, profit_percent: float, default_sell_price: float, sell_price_inc_tax: float}  $pricing
     * @param  array<int, int>  $locationIds
     * @param  array<int, UploadedFile|null>  $variationImages
     */
    private function syncVariationsOnUpdate(
        Product $product,
        string $type,
        array $validated,
        array $pricing,
        float $taxPercent,
        float $defaultProfitPercent,
        array $locationIds,
        array $variationImages,
    ): void {
        if ($type === 'variable' && array_key_exists('variations', $validated)) {
            $keptIds = [];
            $minPurchase = null;
            $minSell = null;

            foreach ($validated['variations'] ?? [] as $index => $row) {
                $rowProfit = isset($row['profit_percent']) ? (float) $row['profit_percent'] : $defaultProfitPercent;
                $rowPricing = $this->computePricing(
                    purchaseExc: $row['default_purchase_price'] ?? 0,
                    purchaseInc: $row['dpp_inc_tax'] ?? null,
                    profitPercent: $rowProfit,
                    sellExc: $row['default_sell_price'] ?? null,
                    sellInc: $row['sell_price_inc_tax'] ?? null,
                    taxPercent: $taxPercent,
                );

                $existing = ! empty($row['id'])
                    ? $product->variations()->whereKey((int) $row['id'])->first()
                    : null;

                $varImg = $variationImages[$index] ?? null;
                $varImgPath = $varImg
                    ? $varImg->store('products/variations', 'public')
                    : ($existing?->image);

                $payload = [
                    'name'                   => $row['name'],
                    'sku'                    => ! empty($row['sku']) ? $row['sku'] : $product->sku . '-' . ($index + 1),
                    'default_purchase_price' => $rowPricing['default_purchase_price'],
                    'dpp_inc_tax'            => $rowPricing['dpp_inc_tax'],
                    'profit_percent'         => $rowPricing['profit_percent'],
                    'default_sell_price'     => $rowPricing['default_sell_price'],
                    'sell_price_inc_tax'     => $rowPricing['sell_price_inc_tax'],
                    'variation_template_id'  => $row['variation_template_id'] ?? null,
                    'variation_value_id'     => $row['variation_value_id'] ?? null,
                    'image'                  => $varImgPath,
                ];

                if ($existing) {
                    $existing->update($payload);
                    $variation = $existing;
                } else {
                    $variation = $product->variations()->create($payload);
                }

                $keptIds[] = $variation->id;
                $this->ensureVariationLocationRows($product->id, $variation->id, $locationIds);

                $minPurchase = $minPurchase === null ? $rowPricing['default_purchase_price'] : min($minPurchase, $rowPricing['default_purchase_price']);
                $minSell = $minSell === null ? $rowPricing['default_sell_price'] : min($minSell, $rowPricing['default_sell_price']);
            }

            $product->variations()->whereNotIn('id', $keptIds)->delete();
            $product->update([
                'default_purchase_price' => $minPurchase ?? 0,
                'default_sell_price'     => $minSell ?? 0,
            ]);

            return;
        }

        if ($type !== 'variable') {
            $variation = $product->variations()->first();
            $varImg = $variationImages[0] ?? null;
            $varImgPath = $varImg ? $varImg->store('products/variations', 'public') : ($variation?->image);

            $payload = [
                'name'                   => 'DUMMY',
                'sku'                    => $product->sku,
                'default_purchase_price' => $pricing['default_purchase_price'],
                'dpp_inc_tax'            => $pricing['dpp_inc_tax'],
                'profit_percent'         => $pricing['profit_percent'],
                'default_sell_price'     => $pricing['default_sell_price'],
                'sell_price_inc_tax'     => $pricing['sell_price_inc_tax'],
                'image'                  => $varImgPath,
            ];

            if ($variation) {
                $variation->update($payload);
            } else {
                $variation = $product->variations()->create($payload);
            }

            $this->ensureVariationLocationRows($product->id, $variation->id, $locationIds);

            if ($type === 'combo' && array_key_exists('combo_components', $validated)) {
                ComboProductComponent::where('variation_id', $variation->id)->delete();
                foreach ($validated['combo_components'] ?? [] as $component) {
                    ComboProductComponent::create([
                        'variation_id'           => $variation->id,
                        'component_variation_id' => (int) $component['component_variation_id'],
                        'qty'                    => (float) $component['qty'],
                    ]);
                }
            }
        }
    }

    /**
     * Permanently delete a product, cleaning up its opening-stock lines or blocking if referenced by transactions.
     *
     * @throws ValidationException
     */
    public function forceDeleteProduct(Product $product): void
    {
        $mediaPaths = array_filter([
            $product->image,
            $product->product_brochure,
        ]);

        DB::transaction(function () use ($product, &$mediaPaths): void {
            $variations = $product->variations()->get(['id', 'image']);
            $variationIds = $variations->pluck('id')->all();

            foreach ($variations as $variation) {
                if ($variation->image) {
                    $mediaPaths[] = $variation->image;
                }
            }

            $hasSellLines = SellLine::query()
                ->where(function ($q) use ($product, $variationIds): void {
                    $q->where('product_id', $product->id);
                    if ($variationIds !== []) {
                        $q->orWhereIn('variation_id', $variationIds);
                    }
                })
                ->exists();

            $purchaseLinesQuery = PurchaseLine::query()
                ->where(function ($q) use ($product, $variationIds): void {
                    $q->where('product_id', $product->id);
                    if ($variationIds !== []) {
                        $q->orWhereIn('variation_id', $variationIds);
                    }
                });

            $hasNonOpeningPurchases = (clone $purchaseLinesQuery)
                ->whereDoesntHave('transaction', function ($q): void {
                    $q->withoutGlobalScope('business')
                        ->withTrashed()
                        ->where('type', 'opening_stock');
                })
                ->exists();

            if ($hasSellLines || $hasNonOpeningPurchases) {
                throw ValidationException::withMessages([
                    'product' => ['Cannot permanently delete a product that has existing purchase or sales transactions.'],
                ]);
            }

            $openingPurchaseLines = (clone $purchaseLinesQuery)->get(['id', 'transaction_id']);

            if ($openingPurchaseLines->isNotEmpty()) {
                $openingTxIds = $openingPurchaseLines->pluck('transaction_id')->unique()->values()->all();

                PurchaseLine::whereIn('id', $openingPurchaseLines->pluck('id')->all())->delete();

                foreach ($openingTxIds as $txId) {
                    $tx = Transaction::withoutGlobalScope('business')
                        ->withTrashed()
                        ->where('id', $txId)
                        ->where('type', 'opening_stock')
                        ->first();

                    if ($tx && ! $tx->purchaseLines()->exists() && ! $tx->sellLines()->exists()) {
                        $tx->forceDelete();
                    }
                }
            }

            if ($variationIds !== []) {
                ComboProductComponent::whereIn('component_variation_id', $variationIds)->delete();
            }

            $product->forceDelete();
        });

        foreach (array_unique($mediaPaths) as $path) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * @param  array<int, int>  $locationIds
     */
    private function ensureVariationLocationRows(int $productId, int $variationId, array $locationIds): void
    {
        foreach ($locationIds as $locationId) {
            VariationLocationDetail::firstOrCreate(
                [
                    'variation_id' => $variationId,
                    'location_id'  => $locationId,
                ],
                [
                    'product_id'    => $productId,
                    'qty_available' => 0,
                ]
            );
        }
    }
}
