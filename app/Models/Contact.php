<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use HasFactory, BelongsToBusiness, SoftDeletes;

    protected $fillable = [
        'business_id',
        'created_by',
        'type',
        'contact_id',
        'entity_type',
        'contact_status',
        'name',
        'supplier_business_name',
        'prefix',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'mobile',
        'landline',
        'alternate_number',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'country',
        'zip_code',
        'landmark',
        'street_name',
        'building_number',
        'additional_number',
        'dob',
        'customer_group_id',
        'credit_limit',
        'opening_balance',
        'pay_term_number',
        'pay_term_type',
        'balance',
        'total_purchase',
        'total_purchase_paid',
        'total_purchase_return',
        'total_purchase_return_paid',
        'total_sell',
        'total_sell_paid',
        'total_sell_return',
        'total_sell_return_paid',
        'is_default',
        'is_exempt_tax',
        'tax_number',
        'position',
        'website',
        'shipping_address',
        'shipping_city',
        'shipping_state',
        'shipping_country',
        'shipping_zip_code',
        'custom_field1',
        'custom_field2',
        'custom_field3',
        'custom_field4',
        'custom_field5',
        'custom_field6',
        'custom_field7',
        'custom_field8',
        'custom_field9',
        'custom_field10',
    ];

    protected $casts = [
        'is_default'                 => 'boolean',
        'is_exempt_tax'              => 'boolean',
        'credit_limit'               => 'decimal:2',
        'opening_balance'            => 'decimal:2',
        'pay_term_number'            => 'integer',
        'balance'                    => 'decimal:2',
        'total_purchase'             => 'decimal:2',
        'total_purchase_paid'        => 'decimal:2',
        'total_purchase_return'      => 'decimal:2',
        'total_purchase_return_paid' => 'decimal:2',
        'total_sell'                 => 'decimal:2',
        'total_sell_paid'            => 'decimal:2',
        'total_sell_return'          => 'decimal:2',
        'total_sell_return_paid'     => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function contactPersons(): HasMany
    {
        return $this->hasMany(ContactPerson::class);
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'contact_user')->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ContactPayment::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ContactDocument::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeCustomers(Builder $query): Builder
    {
        return $query->whereIn('type', ['customer', 'both']);
    }

    public function scopeSuppliers(Builder $query): Builder
    {
        return $query->whereIn('type', ['supplier', 'both']);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Auto-generate a unique contact_id like CO0001 for a business.
     */
    public static function generateContactId(int $businessId, string $prefix = 'CO'): string
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
                ->where('contact_id', $candidate)
                ->exists();
        } while ($exists);

        return $candidate;
    }
}
