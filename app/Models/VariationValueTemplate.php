<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VariationValueTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'variation_template_id',
        'name',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(VariationTemplate::class, 'variation_template_id');
    }

    public function variations(): HasMany
    {
        return $this->hasMany(Variation::class, 'variation_value_id');
    }
}
