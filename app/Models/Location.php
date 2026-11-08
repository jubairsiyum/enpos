<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
    use HasFactory, SoftDeletes, BelongsToBusiness;

    protected $fillable = [
        'business_id',
        'name',
        'location_id',   // human-readable short ID
        'landmark',
        'city',
        'state',
        'country',
        'zip_code',
        'invoice_scheme_id',
        'invoice_layout_id',
        'selling_price_group_id',
        'print_receipt_on_invoice',
        'receipt_printer_type',   // browser | printer
        'printer_id',
        'mobile_number',
        'alternate_number',
        'email',
        'featured_products',      // JSON
        'is_active',
        'default_payment_accounts', // JSON
        'custom_field1',
        'custom_field2',
        'custom_field3',
        'custom_field4',
    ];

    protected $casts = [
        'featured_products'         => 'array',
        'default_payment_accounts'  => 'array',
        'is_active'                 => 'boolean',
        'print_receipt_on_invoice'  => 'boolean',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}

