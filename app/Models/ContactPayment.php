<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactPayment extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'business_id',
        'contact_id',
        'created_by',
        'payment_ref_no',
        'amount',
        'due_applied',
        'advance_added',
        'method',
        'paid_on',
        'note',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'due_applied'   => 'decimal:2',
        'advance_added' => 'decimal:2',
        'paid_on'       => 'date:Y-m-d',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
