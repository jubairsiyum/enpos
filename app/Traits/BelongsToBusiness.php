<?php

namespace App\Traits;

use App\Support\CurrentBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToBusiness
{
    /**
     * Boot the trait: register the global scope and auto-fill business_id.
     */
    protected static function bootBelongsToBusiness(): void
    {
        // Auto-fill business_id on create
        static::creating(function (Model $model) {
            if (empty($model->business_id)) {
                $business = app(CurrentBusiness::class)->get();
                if ($business) {
                    $model->business_id = $business->id;
                }
            }
        });

        // Global scope: always filter by the current business
        static::addGlobalScope('business', function (Builder $builder) {
            $business = app(CurrentBusiness::class)->get();
            if ($business) {
                $builder->where($builder->getModel()->getTable() . '.business_id', $business->id);
            }
        });
    }

    /**
     * Relationship: each record belongs to a Business.
     */
    public function business()
    {
        return $this->belongsTo(\App\Models\Business::class);
    }

    /**
     * Temporarily bypass the global scope when needed (e.g., platform admin).
     */
    public static function withoutBusinessScope(): Builder
    {
        return static::withoutGlobalScope('business');
    }
}

