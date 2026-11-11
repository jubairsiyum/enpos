<?php

namespace App\Http\Requests\Shop;

use App\Models\Product;
use App\Support\CurrentBusiness;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
}
