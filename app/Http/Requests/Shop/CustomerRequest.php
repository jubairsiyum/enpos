<?php

namespace App\Http\Requests\Shop;

use App\Support\CurrentBusiness;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        $updates = [];

        foreach (['name', 'supplier_business_name', 'prefix', 'first_name', 'middle_name', 'last_name', 'mobile', 'alternate_number', 'landline', 'contact_id', 'tax_number'] as $trimField) {
            if ($this->has($trimField) && is_string($this->input($trimField))) {
                $trimmed = trim($this->input($trimField));
                $updates[$trimField] = $trimmed === '' ? null : $trimmed;
            }
        }

        if ($this->has('email') && is_string($this->input('email'))) {
            $trimmedEmail = mb_strtolower(trim($this->input('email')));
            $updates['email'] = $trimmedEmail === '' ? null : $trimmedEmail;
        }

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
                ->map(function (array $person): array {
                    foreach (['prefix', 'first_name', 'last_name', 'mobile', 'alternate_number', 'family_number'] as $field) {
                        if (isset($person[$field]) && is_string($person[$field])) {
                            $trimmed = trim($person[$field]);
                            $person[$field] = $trimmed === '' ? null : $trimmed;
                        }
                    }
                    if (isset($person['email']) && is_string($person['email'])) {
                        $trimmedEmail = mb_strtolower(trim($person['email']));
                        $person['email'] = $trimmedEmail === '' ? null : $trimmedEmail;
                    }

                    return $person;
                })
                ->values()
                ->all();

            $updates['contact_persons'] = $filtered;
        }

        if ($this->has('pay_term_type') && $this->input('pay_term_type') === '') {
            $updates['pay_term_type'] = null;
        }

        if ($this->has('credit_limit') && $this->input('credit_limit') === '') {
            $updates['credit_limit'] = null;
        }

        if ($this->has('customer_group_id') && $this->input('customer_group_id') === '') {
            $updates['customer_group_id'] = null;
        }

        if ($this->has('assigned_to')) {
            $assigned = $this->input('assigned_to');
            if ($assigned === null || $assigned === '') {
                $updates['assigned_to'] = [];
            } elseif (is_array($assigned)) {
                $updates['assigned_to'] = collect($assigned)
                    ->filter(fn (mixed $id): bool => $id !== null && $id !== '')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->unique()
                    ->values()
                    ->all();
            }
        }

        if ($updates !== []) {
            $this->merge($updates);
        }
    }

    /**
     * Prevent duplicate contact person emails/mobiles within the same submission.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $persons = $this->input('contact_persons');
            if (! is_array($persons)) {
                return;
            }

            $seenEmails = [];
            foreach ($persons as $index => $person) {
                if (! is_array($person) || empty($person['email'])) {
                    continue;
                }
                $emailLower = mb_strtolower(trim((string) $person['email']));
                if (in_array($emailLower, $seenEmails, true)) {
                    $validator->errors()->add(
                        "contact_persons.{$index}.email",
                        'Duplicate contact person email in the same request.'
                    );
                }
                $seenEmails[] = $emailLower;
            }
        });
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
            ->where('business_id', $businessId)
            ->whereNull('deleted_at');

        $mobileUniqueRule = Rule::unique('contacts', 'mobile')
            ->where('business_id', $businessId)
            ->whereNull('deleted_at');

        $emailUniqueRule = Rule::unique('contacts', 'email')
            ->where('business_id', $businessId)
            ->whereNull('deleted_at');

        if ($ignoreContactId !== null) {
            $contactIdRule->ignore($ignoreContactId);
            $mobileUniqueRule->ignore($ignoreContactId);
            $emailUniqueRule->ignore($ignoreContactId);
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
                ? ['required', 'string', 'max:20', $mobileUniqueRule]
                : ['sometimes', 'required', 'string', 'max:20', $mobileUniqueRule],
            'alternate_number'        => ['nullable', 'string', 'max:20'],
            'landline'                => ['nullable', 'string', 'max:20'],
            'email'                   => ['nullable', 'email', 'max:255', $emailUniqueRule],
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
