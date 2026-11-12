<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPriceUpdate extends Model
{
    use HasFactory, BelongsToBusiness;

    /**
     * Exact instructions displayed on the Update Price page.
     *
     * @var array<int, string>
     */
    public const INSTRUCTIONS = [
        'Export product prices by clicking on above button',
        'Make changes in product price including tax & selling price groups.',
        'Do not change any product name, sku & headers',
        'After making changes import the file',
    ];

    /**
     * Base column headers before dynamic Selling Price Group columns.
     *
     * @var array<int, string>
     */
    public const BASE_HEADERS = [
        'Product',
        'SKU',
        'Variation',
        'Variation SKU',
        'Selling Price (Inc. Tax)',
    ];

    protected $fillable = [
        'business_id',
        'updated_by',
        'product_id',
        'variation_id',
        'product_name',
        'sku',
        'variation_name',
        'variation_sku',
        'old_sell_price',
        'new_sell_price',
        'old_sell_price_inc_tax',
        'new_sell_price_inc_tax',
        'group_prices_snapshot',
        'source',
    ];

    protected $casts = [
        'old_sell_price'         => 'decimal:4',
        'new_sell_price'         => 'decimal:4',
        'old_sell_price_inc_tax' => 'decimal:4',
        'new_sell_price_inc_tax' => 'decimal:4',
        'group_prices_snapshot'  => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(Variation::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
