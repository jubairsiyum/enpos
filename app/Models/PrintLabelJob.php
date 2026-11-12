<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintLabelJob extends Model
{
    use HasFactory, BelongsToBusiness;

    /**
     * Default Print Labels visibility and font size settings.
     *
     * @var array<string, mixed>
     */
    public const DEFAULT_DISPLAY_OPTIONS = [
        'show_product_name'  => true,
        'product_name_size'  => 15,
        'show_variation'     => true,
        'variation_size'     => 17,
        'show_price'         => true,
        'price_size'         => 17,
        'price_type'         => 'inclusive',
        'show_business_name' => true,
        'business_name_size' => 20,
        'show_packing_date'  => true,
        'packing_date_size'  => 12,
    ];

    protected $fillable = [
        'business_id',
        'created_by',
        'barcode_setting_id',
        'items',
        'display_options',
        'total_labels',
        'output_format',
    ];

    protected $casts = [
        'items'           => 'array',
        'display_options' => 'array',
        'total_labels'    => 'integer',
    ];

    public function barcodeSetting(): BelongsTo
    {
        return $this->belongsTo(BarcodeSetting::class, 'barcode_setting_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
