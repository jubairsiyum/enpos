<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VariationGroupPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'variation_id',
        'price_group_id',
        'price_inc_tax',
    ];

    protected $casts = [
        'price_inc_tax' => 'decimal:4',
    ];

    public function variation(): BelongsTo
    {
        return $this->belongsTo(Variation::class);
    }

    public function priceGroup(): BelongsTo
    {
        return $this->belongsTo(SellingPriceGroup::class, 'price_group_id');
    }
}
