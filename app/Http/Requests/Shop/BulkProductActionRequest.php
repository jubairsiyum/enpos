<?php

namespace App\Http\Requests\Shop;

use App\Support\CurrentBusiness;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkProductActionRequest extends FormRequest
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
            'product_ids'   => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', Rule::exists('products', 'id')->where('business_id', $businessId)],
            'location_id'   => ['nullable', 'integer', Rule::exists('locations', 'id')->where('business_id', $businessId)],
            'location_ids'  => ['nullable', 'array'],
            'location_ids.*' => ['integer', Rule::exists('locations', 'id')->where('business_id', $businessId)],
            'action'        => ['nullable', 'string', Rule::in(['add', 'remove'])],
            'is_inactive'   => ['nullable', 'boolean'],
        ];
    }
}
