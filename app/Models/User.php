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
        'username',
        'prefix',
        'first_name',
        'last_name',
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
        'is_cmmsn_agent',
        'access_locations',
        'allow_login',
        'selected_contacts',  // JSON: pinned contacts in POS
        'crm_contact_id',
        'dob',
        'gender',
        'marital_status',
        'blood_group',
        'alt_number',
        'family_number',
        'fb_link',
        'twitter_link',
        'social_media_1',
        'social_media_2',
        'custom_field_1',
        'custom_field_2',
        'custom_field_3',
        'custom_field_4',
        'guardian_name',
        'id_proof_name',
        'id_proof_number',
        'permanent_address',
        'current_address',
        'bank_details',
        'hrm_details',
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
        'is_cmmsn_agent'    => 'boolean',
        'access_locations'  => 'array',
        'selected_contacts' => 'array',
        'bank_details'      => 'array',
        'hrm_details'       => 'array',
        'dob'               => 'date',
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

    public function scopeForBusiness($query, int $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    public function scopeCommissionAgents($query)
    {
        return $query->where('is_cmmsn_agent', true);
    }
}
