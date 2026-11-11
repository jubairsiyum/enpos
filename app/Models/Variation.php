<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Variation extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'default_purchase_price',
        'dpp_inc_tax',
        'profit_percent',
        'default_sell_price',
        'sell_price_inc_tax',
        'variation_value_id',
        'variation_template_id',
        'image',
    ];

    protected $casts = [
        'default_purchase_price' => 'decimal:4',
        'dpp_inc_tax'            => 'decimal:4',
        'profit_percent'         => 'decimal:2',
        'default_sell_price'     => 'decimal:4',
        'sell_price_inc_tax'     => 'decimal:4',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function locationDetails(): HasMany
    {
        return $this->hasMany(VariationLocationDetail::class);
    }

    public function groupPrices(): HasMany
    {
        return $this->hasMany(VariationGroupPrice::class);
    }

    public function comboComponents(): HasMany
    {
        return $this->hasMany(ComboProductComponent::class, 'variation_id');
    }

    public function variationTemplate(): BelongsTo
    {
        return $this->belongsTo(VariationTemplate::class, 'variation_template_id');
    }

    public function variationValueTemplate(): BelongsTo
    {
        return $this->belongsTo(VariationValueTemplate::class, 'variation_value_id');
    }
}
