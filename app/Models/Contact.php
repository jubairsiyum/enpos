<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use HasFactory, BelongsToBusiness, SoftDeletes;

    protected $fillable = [
        'business_id',
        'type',
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
        'dob',
        'customer_group_id',
        'credit_limit',
        'opening_balance',
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
        'is_default'     => 'boolean',
        'is_exempt_tax'  => 'boolean',
        'credit_limit'   => 'decimal:2',
        'opening_balance'=> 'decimal:2',
        'balance'        => 'decimal:2',
        'total_purchase' => 'decimal:2',
        'total_purchase_paid'          => 'decimal:2',
        'total_purchase_return'        => 'decimal:2',
        'total_purchase_return_paid'   => 'decimal:2',
        'total_sell'                   => 'decimal:2',
        'total_sell_paid'              => 'decimal:2',
        'total_sell_return'            => 'decimal:2',
        'total_sell_return_paid'       => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function customerGroup()
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Auto-generate a contact_id prefix like CO0001 or CN0001.
     */
    public static function generateContactId(int $businessId, string $type = 'supplier'): string
    {
        $prefix = $type === 'customer' ? 'CN' : 'CO';

        $count = static::withoutGlobalScope('business')
            ->where('business_id', $businessId)
            ->count();

        return $prefix . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }
}
