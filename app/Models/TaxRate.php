<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'business_id',
        'name',
        'amount',
        'is_tax_group',
        'sub_taxes',
        'is_default',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'is_tax_group' => 'boolean',
        'sub_taxes'    => 'array',
        'is_default'   => 'boolean',
    ];
}
