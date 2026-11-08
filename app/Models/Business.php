<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'email',
        'phone',
        'country',
        'state',
        'city',
        'zip',
        'address',
        'currency_id',
        'currency_symbol',
        'currency_position',
        'default_sales_tax',
        'default_profit_percent',
        'owner_id',
        'fy_start_month',
        'accounting_method',
        'default_unit_id',
        'logo',
        'custom_domain',
        'status',
        'enabled_modules',
        'pos_settings',
        'invoice_settings',
    ];

    protected $casts = [
        'enabled_modules'  => 'array',
        'pos_settings'     => 'array',
        'invoice_settings' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->latest();
    }

    public function locations()
    {
        return $this->hasMany(Location::class);
    }

    public function settings()
    {
        return $this->hasMany(BusinessSetting::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function hasModule(string $module): bool
    {
        $core = config('modules.core', []);
        if (in_array($module, $core)) {
            return true;
        }

        return in_array($module, $this->enabled_modules ?? []);
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        $setting = $this->settings->firstWhere('key', $key);
        return $setting ? $setting->value : $default;
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeSuspended($query)
    {
        return $query->where('status', 'suspended');
    }
}

