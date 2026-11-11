<?php

namespace App\Http\Requests\Shop;

use App\Models\Product;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveOpeningStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $businessId = app(CurrentBusiness::class)->id();
        $routeProduct = $this->route('product');
        $productId = $routeProduct instanceof Product ? $routeProduct->id : (int) $routeProduct;

        return [
            'stocks'                     => ['required', 'array', 'min:1'],
            'stocks.*.variation_id'      => [
                'required',
                'integer',
                Rule::exists('variations', 'id')->where('product_id', $productId),
            ],
            'stocks.*.location_id'       => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where('business_id', $businessId),
            ],
            'stocks.*.quantity'          => ['required', 'numeric', 'min:0', 'max:999999999.9999'],
            'stocks.*.unit_cost_before_tax' => ['nullable', 'numeric', 'min:0', 'max:9999999999.9999'],
            'stocks.*.lot_number'        => ['nullable', 'string', 'max:100'],
            'stocks.*.exp_date'          => ['nullable', 'date_format:Y-m-d'],
            'transaction_date'           => ['nullable', 'date_format:Y-m-d'],
            'additional_notes'           => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Prevent duplicate variation+location entries within a single opening stock submission.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $stocks = $this->input('stocks');
            if (! is_array($stocks)) {
                return;
            }

            $seenPairs = [];
            foreach ($stocks as $index => $row) {
                if (! is_array($row) || ! isset($row['variation_id'], $row['location_id'])) {
                    continue;
                }

                $pairKey = ((int) $row['variation_id']) . ':' . ((int) $row['location_id']);
                if (in_array($pairKey, $seenPairs, true)) {
                    $validator->errors()->add(
                        "stocks.{$index}.variation_id",
                        'Duplicate variation and location entry in opening stock payload.'
                    );
                }
                $seenPairs[] = $pairKey;
            }
        });
    }
}
