<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'business_id',
        'actual_name',
        'short_name',
        'allow_decimal',
    ];

    protected $casts = [
        'allow_decimal' => 'boolean',
    ];
}
