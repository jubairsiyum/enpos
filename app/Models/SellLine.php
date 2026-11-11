<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'product_id',
        'variation_id',
        'quantity',
        'quantity_returned',
        'unit_price',
        'unit_price_inc_tax',
        'unit_price_before_discount',
        'line_discount_amount',
        'line_discount_type',
        'item_tax',
        'tax_id',
        'selling_price_group_id',
        'warranty_id',
        'sub_total',
        'parent_sell_line_id',
        'modifiers',
        'lot_no_line_id',
    ];

    protected $casts = [
        'quantity'                   => 'decimal:4',
        'quantity_returned'          => 'decimal:4',
        'unit_price'                 => 'decimal:4',
        'unit_price_inc_tax'         => 'decimal:4',
        'unit_price_before_discount' => 'decimal:4',
        'line_discount_amount'       => 'decimal:4',
        'item_tax'                   => 'decimal:4',
        'sub_total'                  => 'decimal:4',
        'modifiers'                  => 'array',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(Variation::class);
    }
}
