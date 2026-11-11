<?php

namespace App\Http\Requests\Shop;

use App\Models\Category;
use App\Models\Product;
use App\Models\Variation;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize empty strings, JSON-encoded formdata arrays, and boolean flags before validation.
     */
    protected function prepareForValidation(): void
    {
        $nullableFields = [
            'sku',
            'unit_id',
            'sub_unit_id',
            'sub_unit_multiplier',
            'brand_id',
            'category_id',
            'sub_category_id',
            'tax_id',
            'warranty_id',
            'alert_quantity',
            'weight',
            'preparation_time_in_minutes',
            'description',
            'default_purchase_price',
            'dpp_inc_tax',
            'profit_percent',
            'default_sell_price',
            'sell_price_inc_tax',
            'quantity',
            'opening_stock',
        ];

        $updates = [];

        foreach (['name', 'sku', 'weight'] as $trimField) {
            if ($this->has($trimField) && is_string($this->input($trimField))) {
                $updates[$trimField] = trim($this->input($trimField));
            }
        }

        foreach ($nullableFields as $field) {
            $current = array_key_exists($field, $updates) ? $updates[$field] : $this->input($field);
            if ($this->has($field) && $current === '') {
                $updates[$field] = null;
            }
        }

        foreach (['enable_stock', 'not_for_selling', 'is_inactive', 'enable_sr_no', 'woocommerce_disable_sync'] as $boolField) {
            if ($this->has($boolField)) {
                $raw = $this->input($boolField);
                if (is_string($raw)) {
                    $parsed = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($parsed !== null) {
                        $updates[$boolField] = $parsed;
                    }
                }
            }
        }

        foreach (['location_ids', 'variations', 'combo_components', 'custom_fields'] as $arrayField) {
            if ($this->has($arrayField)) {
                $value = $this->input($arrayField);
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $value = $decoded;
                    } elseif ($value === '') {
                        $value = [];
                    }
                }

                if ($arrayField === 'location_ids' && is_array($value)) {
                    $value = collect($value)
                        ->filter(fn (mixed $id): bool => $id !== null && $id !== '')
                        ->map(fn (mixed $id): int => (int) $id)
                        ->unique()
                        ->values()
                        ->all();
                }

                if ($arrayField === 'variations' && is_array($value)) {
                    $value = collect($value)->map(function (mixed $row): mixed {
                        if (! is_array($row)) {
                            return $row;
                        }
                        if (isset($row['name']) && is_string($row['name'])) {
                            $row['name'] = trim($row['name']);
                        }
                        if (array_key_exists('sku', $row) && is_string($row['sku'])) {
                            $trimmedSku = trim($row['sku']);
                            $row['sku'] = $trimmedSku === '' ? null : $trimmedSku;
                        }

                        return $row;
                    })->all();
                }

                $updates[$arrayField] = $value;
            }
        }

        if (! empty($updates)) {
            $this->merge($updates);
        }
    }

    /**
     * Configure cross-field business rules to prevent redundant or inconsistent product structures.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $businessId = app(CurrentBusiness::class)->id();
            $routeProduct = $this->route('product');
            $ignoreProductId = $routeProduct instanceof Product
                ? $routeProduct->id
                : (is_numeric($routeProduct) ? (int) $routeProduct : null);

            $unitId = $this->input('unit_id');
            $subUnitId = $this->input('sub_unit_id');
            if ($unitId !== null && $subUnitId !== null && (int) $unitId === (int) $subUnitId) {
                $validator->errors()->add('sub_unit_id', 'Sub-unit must be different from the primary unit.');
            }

            $categoryId = $this->input('category_id');
            $subCategoryId = $this->input('sub_category_id');
            if ($subCategoryId !== null) {
                $subCategory = Category::where('business_id', $businessId)
                    ->where('category_type', 'product')
                    ->find((int) $subCategoryId);

                if (! $subCategory || $subCategory->parent_id === null) {
                    $validator->errors()->add('sub_category_id', 'Selected sub-category must belong to a parent category.');
                } elseif ($categoryId !== null && (int) $subCategory->parent_id !== (int) $categoryId) {
                    $validator->errors()->add('sub_category_id', 'Selected sub-category does not belong to the chosen category.');
                }
            }

            if ($ignoreProductId !== null && $this->filled('name')) {
                $nameLower = mb_strtolower(trim((string) $this->input('name')));
                $duplicateName = Product::where('business_id', $businessId)
                    ->whereKeyNot($ignoreProductId)
                    ->whereRaw('LOWER(TRIM(name)) = ?', [$nameLower])
                    ->exists();

                if ($duplicateName) {
                    $validator->errors()->add('name', 'Another product with this name already exists.');
                }
            }

            $type = $this->input('type') ?? ($routeProduct instanceof Product ? $routeProduct->type : 'single');
            $variations = $this->input('variations');

            if ($type === 'variable' && $ignoreProductId === null && (empty($variations) || ! is_array($variations))) {
                $validator->errors()->add('variations', 'At least one variation is required for a variable product.');
            }

            if (is_array($variations) && $variations !== []) {
                $seenVarNames = [];
                $seenVarSkus = [];

                foreach ($variations as $index => $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $varName = mb_strtolower(trim((string) ($row['name'] ?? '')));
                    if ($varName !== '') {
                        if (in_array($varName, $seenVarNames, true)) {
                            $validator->errors()->add("variations.{$index}.name", 'Duplicate variation names are not allowed on the same product.');
                        }
                        $seenVarNames[] = $varName;
                    }

                    $varSku = trim((string) ($row['sku'] ?? ''));
                    if ($varSku !== '') {
                        $varSkuLower = mb_strtolower($varSku);
                        if (in_array($varSkuLower, $seenVarSkus, true)) {
                            $validator->errors()->add("variations.{$index}.sku", 'Duplicate variation SKUs are not allowed on the same product.');
                        }
                        $seenVarSkus[] = $varSkuLower;

                        $skuConflictQuery = Variation::query()
                            ->whereRaw('LOWER(TRIM(sku)) = ?', [$varSkuLower])
                            ->whereHas('product', fn ($q) => $q->where('business_id', $businessId)->whereNull('deleted_at'));

                        if ($ignoreProductId !== null) {
                            $skuConflictQuery->where('product_id', '!=', $ignoreProductId);
                        }

                        if ($skuConflictQuery->exists()) {
                            $validator->errors()->add("variations.{$index}.sku", "Variation SKU '{$varSku}' is already used by another product.");
                        }
                    }
                }
            }

            $comboComponents = $this->input('combo_components');
            if ($type === 'combo' && $ignoreProductId === null && (empty($comboComponents) || ! is_array($comboComponents))) {
                $validator->errors()->add('combo_components', 'At least one component product is required for a combo product.');
            }

            if (is_array($comboComponents) && $comboComponents !== []) {
                $ownVariationIds = $ignoreProductId !== null
                    ? Variation::where('product_id', $ignoreProductId)->pluck('id')->all()
                    : [];
                $seenComponents = [];

                foreach ($comboComponents as $index => $comp) {
                    if (! is_array($comp) || ! isset($comp['component_variation_id'])) {
                        continue;
                    }
                    $compId = (int) $comp['component_variation_id'];
                    if (in_array($compId, $seenComponents, true)) {
                        $validator->errors()->add("combo_components.{$index}.component_variation_id", 'Duplicate component variations are not allowed in the same combo.');
                    }
                    if (in_array($compId, $ownVariationIds, true)) {
                        $validator->errors()->add("combo_components.{$index}.component_variation_id", 'A combo product cannot contain itself as a component.');
                    }
                    $seenComponents[] = $compId;
                }
            }
        });
    }

    /**
     * Shared validation rules for product creation and updates.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function productRules(?int $ignoreProductId = null): array
    {
        $businessId = app(CurrentBusiness::class)->id();

        $skuRules = ['nullable', 'string', 'max:100'];
        if ($ignoreProductId !== null) {
            $skuRules[] = Rule::unique('products', 'sku')
                ->where('business_id', $businessId)
                ->whereNull('deleted_at')
                ->ignore($ignoreProductId);
        }

        return [
            'name' => $ignoreProductId === null
                ? ['required', 'string', 'max:255']
                : ['sometimes', 'required', 'string', 'max:255'],
            'sku'          => $skuRules,
            'barcode_type' => ['nullable', 'string', Rule::in(['C128', 'C39', 'EAN13', 'EAN8', 'UPCA', 'UPCE', 'ITF14'])],
            'type'         => ['nullable', 'string', Rule::in(['single', 'variable', 'combo'])],
            'unit_id'      => $ignoreProductId === null
                ? ['required', 'integer', Rule::exists('units', 'id')->where('business_id', $businessId)]
                : ['sometimes', 'required', 'integer', Rule::exists('units', 'id')->where('business_id', $businessId)],
            'sub_unit_id'         => ['nullable', 'integer', Rule::exists('units', 'id')->where('business_id', $businessId)],
            'sub_unit_multiplier' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'brand_id'            => ['nullable', 'integer', Rule::exists('brands', 'id')->where('business_id', $businessId)],
            'category_id'         => ['nullable', 'integer', Rule::exists('categories', 'id')->where('business_id', $businessId)],
            'sub_category_id'     => ['nullable', 'integer', Rule::exists('categories', 'id')->where('business_id', $businessId)],
            'warranty_id'         => ['nullable', 'integer', Rule::exists('warranties', 'id')->where('business_id', $businessId)],
            'location_ids'        => ['nullable', 'array'],
            'location_ids.*'      => ['integer', Rule::exists('locations', 'id')->where('business_id', $businessId)],
            'enable_stock'        => ['nullable', 'boolean'],
            'alert_quantity'      => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'quantity'            => ['nullable', 'numeric', 'min:0', 'max:999999999.9999'],
            'opening_stock'       => ['nullable', 'numeric', 'min:0', 'max:999999999.9999'],
            'description'         => ['nullable', 'string'],
            'image'               => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'product_brochure'    => ['nullable', 'file', 'mimes:pdf,csv,zip,doc,docx,jpeg,jpg,png', 'max:5120'],
            'enable_sr_no'        => ['nullable', 'boolean'],
            'not_for_selling'     => ['nullable', 'boolean'],
            'is_inactive'         => ['nullable', 'boolean'],
            'weight'              => ['nullable', 'string', 'max:100'],
            'preparation_time_in_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'woocommerce_disable_sync'    => ['nullable', 'boolean'],
            'tax_id'              => ['nullable', 'integer', Rule::exists('tax_rates', 'id')->where('business_id', $businessId)],
            'tax_type'            => ['nullable', 'string', Rule::in(['exclusive', 'inclusive'])],
            'default_purchase_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.9999'],
            'dpp_inc_tax'            => ['nullable', 'numeric', 'min:0', 'max:9999999999.9999'],
            'profit_percent'         => ['nullable', 'numeric', 'min:-100', 'max:9999.99'],
            'default_sell_price'     => ['nullable', 'numeric', 'min:0', 'max:9999999999.9999'],
            'sell_price_inc_tax'     => ['nullable', 'numeric', 'min:0', 'max:9999999999.9999'],
            'custom_fields'          => ['nullable', 'array'],
            'save_action'            => ['nullable', 'string', Rule::in(['save', 'save_and_add_another', 'save_and_opening_stock'])],

            // Variable product variations
            'variations'                          => ['nullable', 'array', 'min:1'],
            'variations.*.id'                     => ['nullable', 'integer'],
            'variations.*.name'                   => ['required_with:variations', 'string', 'max:255'],
            'variations.*.sku'                    => ['nullable', 'string', 'max:100'],
            'variations.*.default_purchase_price' => ['required_with:variations', 'numeric', 'min:0'],
            'variations.*.dpp_inc_tax'            => ['nullable', 'numeric', 'min:0'],
            'variations.*.profit_percent'         => ['nullable', 'numeric', 'min:-100', 'max:9999.99'],
            'variations.*.default_sell_price'     => ['nullable', 'numeric', 'min:0'],
            'variations.*.sell_price_inc_tax'     => ['nullable', 'numeric', 'min:0'],
            'variations.*.variation_template_id'  => ['nullable', 'integer', Rule::exists('variation_templates', 'id')->where('business_id', $businessId)],
            'variations.*.variation_value_id'     => ['nullable', 'integer', 'exists:variation_value_templates,id'],
            'variations.*.quantity'               => ['nullable', 'numeric', 'min:0', 'max:999999999.9999'],
            'variations.*.opening_stock'          => ['nullable', 'numeric', 'min:0', 'max:999999999.9999'],
            'variations.*.image'                  => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:5120'],

            // Combo product components
            'combo_components'                           => ['nullable', 'array', 'min:1'],
            'combo_components.*.component_variation_id'  => [
                'required_with:combo_components',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) use ($businessId): void {
                    $belongsToBusiness = Variation::query()
                        ->whereKey((int) $value)
                        ->whereHas('product', fn ($q) => $q->where('business_id', $businessId))
                        ->exists();

                    if (! $belongsToBusiness) {
                        $fail('The selected component variation is invalid for this business.');
                    }
                },
            ],
            'combo_components.*.qty' => ['required_with:combo_components', 'numeric', 'min:0.0001'],
        ];
    }
}
