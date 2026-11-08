<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'billing_cycle',   // monthly | yearly | lifetime
        'trial_days',
        'max_locations',
        'max_users',
        'max_products',
        'modules',         // JSON array of allowed optional modules
        'is_active',
        'is_featured',
        'sort_order',
    ];

    protected $casts = [
        'modules'     => 'array',
        'price'       => 'decimal:2',
        'is_active'   => 'boolean',
        'is_featured' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function allowsModule(string $module): bool
    {
        $core = config('modules.core', []);
        if (in_array($module, $core)) {
            return true;
        }

        return in_array($module, $this->modules ?? []);
    }
}

