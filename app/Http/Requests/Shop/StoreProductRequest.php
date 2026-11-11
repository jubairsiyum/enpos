<?php

namespace App\Http\Requests\Shop;

class StoreProductRequest extends ProductRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->productRules();
    }
}
