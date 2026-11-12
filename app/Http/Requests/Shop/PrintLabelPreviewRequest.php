<?php

namespace App\Http\Requests\Shop;

use App\Support\CurrentBusiness;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrintLabelPreviewRequest extends FormRequest
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

        return [
            'items'                          => ['required', 'array', 'min:1'],
            'items.*.product_id'             => [
                'required_without:items.*.variation_id',
                'nullable',
                'integer',
                Rule::exists('products', 'id')->where('business_id', $businessId)->whereNull('deleted_at'),
            ],
            'items.*.variation_id'           => [
                'required_without:items.*.product_id',
                'nullable',
                'integer',
                Rule::exists('variations', 'id'),
            ],
            'items.*.quantity'               => ['required', 'integer', 'min:1', 'max:5000'],
            'items.*.packing_date'           => ['nullable', 'date'],
            'items.*.selling_price_group_id' => [
                'nullable',
                'integer',
                Rule::exists('selling_price_groups', 'id')->where('business_id', $businessId),
            ],
            'barcode_setting_id'             => [
                'nullable',
                'integer',
                Rule::exists('barcode_settings', 'id')->where('business_id', $businessId),
            ],
            'display'                        => ['nullable', 'array'],
            'display.show_product_name'      => ['nullable', 'boolean'],
            'display.product_name_size'      => ['nullable', 'integer', 'min:6', 'max:72'],
            'display.show_variation'         => ['nullable', 'boolean'],
            'display.variation_size'         => ['nullable', 'integer', 'min:6', 'max:72'],
            'display.show_price'             => ['nullable', 'boolean'],
            'display.price_size'             => ['nullable', 'integer', 'min:6', 'max:72'],
            'display.price_type'             => ['nullable', 'string', Rule::in(['inclusive', 'exclusive'])],
            'display.show_business_name'     => ['nullable', 'boolean'],
            'display.business_name_size'     => ['nullable', 'integer', 'min:6', 'max:72'],
            'display.show_packing_date'      => ['nullable', 'boolean'],
            'display.packing_date_size'      => ['nullable', 'integer', 'min:6', 'max:72'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required'          => 'Please add at least one product to print labels.',
            'items.min'               => 'Please add at least one product to print labels.',
            'items.*.quantity.min'    => 'Number of labels must be at least 1.',
            'items.*.quantity.max'    => 'Number of labels cannot exceed 5000 per item.',
            'display.price_type.in'   => 'Show Price must be either inclusive or exclusive.',
        ];
    }
}
