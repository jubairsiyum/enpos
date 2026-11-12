<?php

namespace App\Http\Requests\Shop;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BarcodeSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name') && is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');
        $req = $isUpdate ? 'sometimes' : 'required';

        return [
            'name'                         => [$req, 'required', 'string', 'max:255'],
            'barcode_type'                 => ['nullable', 'string', Rule::in(['C128', 'C39', 'EAN13', 'EAN8', 'UPCA', 'UPCE', 'ITF14'])],
            'sticker_size'                 => ['nullable', 'string', 'max:100'],
            'sticker_width'                => [$req, 'required', 'numeric', 'min:0.1', 'max:500'],
            'sticker_height'               => [$req, 'required', 'numeric', 'min:0.1', 'max:500'],
            'border_top'                   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'border_bottom'                => ['nullable', 'numeric', 'min:0', 'max:100'],
            'border_left'                  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'border_right'                 => ['nullable', 'numeric', 'min:0', 'max:100'],
            'stickers_in_one_row'          => [$req, 'required', 'integer', 'min:1', 'max:20'],
            'is_default'                   => ['nullable', 'boolean'],
            'settings'                     => ['nullable', 'array'],
            'settings.unit'                => ['nullable', 'string', Rule::in(['in', 'mm'])],
            'settings.is_continuous'       => ['nullable', 'boolean'],
            'settings.sheet_width'         => ['nullable', 'numeric', 'min:0.1', 'max:1000'],
            'settings.sheet_height'        => ['nullable', 'numeric', 'min:0.1', 'max:1000'],
            'settings.stickers_per_sheet'  => ['nullable', 'integer', 'min:1', 'max:500'],
            'settings.row_distance'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'settings.col_distance'        => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
