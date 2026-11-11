<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, BelongsToBusiness, SoftDeletes;

    protected $fillable = [
        'business_id',
        'created_by',
        'name',
        'type',
        'sku',
        'unit_id',
        'sub_unit_id',
        'sub_unit_multiplier',
        'category_id',
        'sub_category_id',
        'brand_id',
        'tax_id',
        'tax_type',
        'default_purchase_price',
        'default_sell_price',
        'profit_percent',
        'alert_quantity',
        'barcode_type',
        'enable_stock',
        'not_for_selling',
        'is_inactive',
        'enable_sr_no',
        'weight',
        'preparation_time_in_minutes',
        'woocommerce_disable_sync',
        'description',
        'image',
        'product_brochure',
        'warranty_id',
        'custom_fields',
    ];

    protected $casts = [
        'default_purchase_price'      => 'decimal:4',
        'default_sell_price'          => 'decimal:4',
        'profit_percent'              => 'decimal:2',
        'alert_quantity'              => 'decimal:2',
        'enable_stock'                => 'boolean',
        'not_for_selling'             => 'boolean',
        'is_inactive'                 => 'boolean',
        'enable_sr_no'                => 'boolean',
        'woocommerce_disable_sync'    => 'boolean',
        'preparation_time_in_minutes' => 'integer',
        'sub_unit_multiplier'         => 'integer',
        'custom_fields'               => 'array',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function subUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'sub_unit_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'sub_category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'tax_id');
    }

    public function warranty(): BelongsTo
    {
        return $this->belongsTo(Warranty::class, 'warranty_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'product_locations')->withTimestamps();
    }

    public function variations(): HasMany
    {
        return $this->hasMany(Variation::class);
    }

    public function variationLocationDetails(): HasMany
    {
        return $this->hasMany(VariationLocationDetail::class);
    }

    /**
     * Auto-generate a unique SKU like AS0001 for a business.
     */
    public static function generateSku(int $businessId, string $prefix = 'AS'): string
    {
        $maxSequence = static::withoutGlobalScope('business')
            ->withTrashed()
            ->where('business_id', $businessId)
            ->count();

        do {
            $maxSequence++;
            $candidate = $prefix . str_pad((string) $maxSequence, 4, '0', STR_PAD_LEFT);
            $exists = static::withoutGlobalScope('business')
                ->withTrashed()
                ->where('business_id', $businessId)
                ->where('sku', $candidate)
                ->exists();
        } while ($exists);

        return $candidate;
    }
}
