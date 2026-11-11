<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComboProductComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'variation_id',
        'component_variation_id',
        'qty',
    ];

    protected $casts = [
        'qty' => 'decimal:4',
    ];

    public function variation(): BelongsTo
    {
        return $this->belongsTo(Variation::class, 'variation_id');
    }

    public function componentVariation(): BelongsTo
    {
        return $this->belongsTo(Variation::class, 'component_variation_id');
    }
}
