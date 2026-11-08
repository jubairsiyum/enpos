<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    /**
     * User types:
     *  - super_admin  : platform owner, all powers
     *  - admin        : platform staff, limited platform powers
     *  - owner        : shop owner
     *  - staff        : shop staff (permissions via spatie roles)
     */

    protected $fillable = [
        'name',
        'email',
        'password',
        'type',          // super_admin | admin | owner | staff
        'business_id',   // NULL for platform users
        'is_active',
        'phone',
        'avatar',
        'language',
        'theme',
        'dark_mode',
        'commission_type',   // fixed | percentage | NULL
        'commission_rate',
        'max_sales_discount',
        'allow_login',
        'selected_contacts',  // JSON: pinned contacts in POS
        'crm_contact_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'is_active'         => 'boolean',
        'dark_mode'         => 'boolean',
        'allow_login'       => 'boolean',
        'selected_contacts' => 'array',
        'commission_rate'   => 'decimal:4',
        'max_sales_discount'=> 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Spatie Permission team key
    |--------------------------------------------------------------------------
    */

    // Tells Spatie which column to use as the team foreign key
    public function getPermissionTeamId(): ?int
    {
        return $this->business_id;
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function ownedBusiness()
    {
        return $this->hasOne(Business::class, 'owner_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isSuperAdmin(): bool
    {
        return $this->type === 'super_admin';
    }

    public function isPlatformAdmin(): bool
    {
        return in_array($this->type, ['super_admin', 'admin']);
    }

    public function isShopUser(): bool
    {
        return in_array($this->type, ['owner', 'staff']);
    }

    public function isPlatformUser(): bool
    {
        return is_null($this->business_id);
    }
}
