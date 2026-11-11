<?php

namespace App\Http\Requests\Shop;

use App\Models\Product;

class UpdateProductRequest extends ProductRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $routeProduct = $this->route('product');
        $ignoreId = $routeProduct instanceof Product ? $routeProduct->id : (is_numeric($routeProduct) ? (int) $routeProduct : null);

        return $this->productRules($ignoreId);
    }
}
