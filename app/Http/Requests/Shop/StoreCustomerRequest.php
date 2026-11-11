<?php

namespace App\Http\Requests\Shop;

class StoreCustomerRequest extends CustomerRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->customerRules();
    }
}
