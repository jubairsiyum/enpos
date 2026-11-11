<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactPerson extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $table = 'contact_persons';

    protected $fillable = [
        'business_id',
        'contact_id',
        'prefix',
        'first_name',
        'last_name',
        'email',
        'mobile',
        'alternate_number',
        'family_number',
        'department',
        'designation',
        'sales_commission_percentage',
        'allow_login',
        'user_id',
    ];

    protected $casts = [
        'sales_commission_percentage' => 'decimal:2',
        'allow_login'                 => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->prefix,
            $this->first_name,
            $this->last_name,
        ])));
    }
}
