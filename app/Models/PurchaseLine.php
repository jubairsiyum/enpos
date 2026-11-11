<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'product_id',
        'variation_id',
        'quantity',
        'quantity_adjusted',
        'quantity_returned',
        'quantity_sold',
        'purchase_price',
        'purchase_price_inc_tax',
        'item_tax',
        'tax_id',
        'sub_total',
        'lot_number',
        'exp_date',
    ];

    protected $casts = [
        'quantity'               => 'decimal:4',
        'quantity_adjusted'      => 'decimal:4',
        'quantity_returned'      => 'decimal:4',
        'quantity_sold'          => 'decimal:4',
        'purchase_price'         => 'decimal:4',
        'purchase_price_inc_tax' => 'decimal:4',
        'item_tax'               => 'decimal:4',
        'sub_total'              => 'decimal:4',
        'exp_date'               => 'date',
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
