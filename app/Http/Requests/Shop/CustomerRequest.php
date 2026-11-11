<?php

namespace App\Http\Requests\Shop;

use App\Support\CurrentBusiness;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize payload before validation (e.g., strip empty contact person slots).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('contact_persons') && is_array($this->input('contact_persons'))) {
            $filtered = collect($this->input('contact_persons'))
                ->filter(function (mixed $person): bool {
                    if (! is_array($person)) {
                        return false;
                    }

                    $nonEmptyFields = collect($person)
                        ->except(['allow_login', 'id'])
                        ->filter(fn (mixed $v): bool => $v !== null && $v !== '');

                    return $nonEmptyFields->isNotEmpty() || ! empty($person['allow_login']);
                })
                ->values()
                ->all();

            $this->merge(['contact_persons' => $filtered]);
        }

        if ($this->has('pay_term_type') && $this->input('pay_term_type') === '') {
            $this->merge(['pay_term_type' => null]);
        }

        if ($this->has('credit_limit') && $this->input('credit_limit') === '') {
            $this->merge(['credit_limit' => null]);
        }

        if ($this->has('customer_group_id') && $this->input('customer_group_id') === '') {
            $this->merge(['customer_group_id' => null]);
        }

        if ($this->has('assigned_to')) {
            $assigned = $this->input('assigned_to');
            if ($assigned === null || $assigned === '') {
                $this->merge(['assigned_to' => []]);
            } elseif (is_array($assigned)) {
                $cleaned = collect($assigned)
                    ->filter(fn (mixed $id): bool => $id !== null && $id !== '')
                    ->values()
                    ->all();
                $this->merge(['assigned_to' => $cleaned]);
            }
        }
    }

    /**
     * Shared customer validation rules.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function customerRules(?int $ignoreContactId = null): array
    {
        $businessId = app(CurrentBusiness::class)->id();

        $contactIdRule = Rule::unique('contacts', 'contact_id')
            ->where('business_id', $businessId);

        if ($ignoreContactId !== null) {
            $contactIdRule->ignore($ignoreContactId);
        }

        $rules = [
            'type'                    => ['nullable', Rule::in(['customer', 'supplier', 'both'])],
            'entity_type'             => ['nullable', Rule::in(['individual', 'business'])],
            'contact_id'              => ['nullable', 'string', 'max:50', $contactIdRule],
            'customer_group_id'       => ['nullable', 'integer', Rule::exists('customer_groups', 'id')->where('business_id', $businessId)],
            'name'                    => ['nullable', 'string', 'max:255'],
            'supplier_business_name'  => ['nullable', 'string', 'max:255'],
            'prefix'                  => ['nullable', 'string', 'max:20'],
            'first_name'              => ['nullable', 'string', 'max:255'],
            'middle_name'             => ['nullable', 'string', 'max:255'],
            'last_name'               => ['nullable', 'string', 'max:255'],
            'dob'                     => ['nullable', 'string', 'max:50'],
            'mobile'                  => $ignoreContactId === null
                ? ['required', 'string', 'max:20']
                : ['sometimes', 'required', 'string', 'max:20'],
            'alternate_number'        => ['nullable', 'string', 'max:20'],
            'landline'                => ['nullable', 'string', 'max:20'],
            'email'                   => ['nullable', 'email', 'max:255'],
            'assigned_to'             => ['nullable', 'array'],
            'assigned_to.*'           => ['integer', Rule::exists('users', 'id')->where('business_id', $businessId)],
            'tax_number'              => ['nullable', 'string', 'max:100'],
            'opening_balance'         => ['nullable', 'numeric', 'min:0'],
            'balance'                 => ['nullable', 'numeric', 'min:0'],
            'pay_term_number'         => ['nullable', 'integer', 'min:1', 'max:65535'],
            'pay_term_type'           => ['nullable', Rule::in(['days', 'months'])],
            'credit_limit'            => ['nullable', 'numeric', 'min:0'],
            'address_line_1'          => ['nullable', 'string', 'max:500'],
            'address_line_2'          => ['nullable', 'string', 'max:500'],
            'city'                    => ['nullable', 'string', 'max:100'],
            'state'                   => ['nullable', 'string', 'max:100'],
            'country'                 => ['nullable', 'string', 'max:100'],
            'zip_code'                => ['nullable', 'string', 'max:20'],
            'landmark'                => ['nullable', 'string', 'max:255'],
            'street_name'             => ['nullable', 'string', 'max:255'],
            'building_number'         => ['nullable', 'string', 'max:255'],
            'additional_number'       => ['nullable', 'string', 'max:255'],
            'shipping_address'        => ['nullable', 'string'],
            'contact_persons'         => ['nullable', 'array', 'max:10'],
            'contact_persons.*.id'                          => ['nullable', 'integer'],
            'contact_persons.*.prefix'                      => ['nullable', 'string', 'max:20'],
            'contact_persons.*.first_name'                  => ['required', 'string', 'max:255'],
            'contact_persons.*.last_name'                   => ['nullable', 'string', 'max:255'],
            'contact_persons.*.email'                       => ['nullable', 'email', 'max:255'],
            'contact_persons.*.mobile'                      => ['nullable', 'string', 'max:30'],
            'contact_persons.*.alternate_number'            => ['nullable', 'string', 'max:30'],
            'contact_persons.*.family_number'               => ['nullable', 'string', 'max:30'],
            'contact_persons.*.department'                  => ['nullable', 'string', 'max:255'],
            'contact_persons.*.designation'                 => ['nullable', 'string', 'max:255'],
            'contact_persons.*.sales_commission_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'contact_persons.*.allow_login'                 => ['nullable', 'boolean'],
        ];

        for ($i = 1; $i <= 10; $i++) {
            $rules["custom_field{$i}"] = ['nullable', 'string'];
        }

        return $rules;
    }
}
