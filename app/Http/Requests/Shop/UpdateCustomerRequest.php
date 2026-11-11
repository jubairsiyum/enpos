<?php

namespace App\Http\Requests\Shop;

use App\Models\Contact;

class UpdateCustomerRequest extends CustomerRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $customer = $this->route('customer');
        $ignoreId = $customer instanceof Contact ? $customer->id : (is_numeric($customer) ? (int) $customer : null);

        return $this->customerRules($ignoreId);
    }
}
