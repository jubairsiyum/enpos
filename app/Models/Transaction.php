<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, BelongsToBusiness, SoftDeletes;

    protected $fillable = [
        'business_id',
        'type',
        'status',
        'payment_status',
        'invoice_no',
        'location_id',
        'contact_id',
        'expense_category_id',
        'expense_sub_category_id',
        'cash_register_id',
        'created_by',
        'selling_price_group_id',
        'transaction_date',
        'total_before_tax',
        'tax_amount',
        'tax_id',
        'discount_type_value',
        'discount_type',
        'total_amount',
        'amount_paid',
        'change_return',
        'shipping_charges',
        'shipping_address_id',
        'shipping_cost',
        'packing_charge',
        'transfer_parent_id',
        'is_suspend',
        'is_hold',
        'is_quotation',
        'is_recurring',
        'recur_interval',
        'recur_interval_type',
        'recur_repetitions',
        'recur_parent_id',
        'additional_notes',
        'staff_note',
        'exchange_rate',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'total_before_tax' => 'decimal:2',
        'tax_amount'       => 'decimal:2',
        'total_amount'     => 'decimal:2',
        'amount_paid'      => 'decimal:2',
        'exchange_rate'    => 'array',
        'is_suspend'       => 'boolean',
        'is_hold'          => 'boolean',
        'is_quotation'     => 'boolean',
        'is_recurring'     => 'boolean',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function purchaseLines(): HasMany
    {
        return $this->hasMany(PurchaseLine::class);
    }

    public function sellLines(): HasMany
    {
        return $this->hasMany(SellLine::class);
    }
}
