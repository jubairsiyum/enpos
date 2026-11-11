<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportContact extends Model
{
    use HasFactory, BelongsToBusiness;

    /**
     * Exact 27 columns for Import Contacts template & validation.
     *
     * @var array<int, array{number: int, key: string, name: string, requirement: string, instruction: string}>
     */
    public const TEMPLATE_COLUMNS = [
        ['number' => 1,  'key' => 'contact_type',     'name' => 'Contact type',             'requirement' => '(Required)',                                      'instruction' => "Available Options:\n1 = Customer,\n2 = Supplier,\n3 = Both"],
        ['number' => 2,  'key' => 'prefix',           'name' => 'Prefix',                   'requirement' => '(Optional)',                                      'instruction' => ''],
        ['number' => 3,  'key' => 'first_name',       'name' => 'First Name',               'requirement' => '(Required)',                                      'instruction' => ''],
        ['number' => 4,  'key' => 'middle_name',      'name' => 'Middle name',              'requirement' => '(Optional)',                                      'instruction' => ''],
        ['number' => 5,  'key' => 'last_name',        'name' => 'Last Name',                'requirement' => '(Optional)',                                      'instruction' => ''],
        ['number' => 6,  'key' => 'business_name',    'name' => 'Business Name',            'requirement' => '(Required if contact type is supplier or both)',   'instruction' => ''],
        ['number' => 7,  'key' => 'contact_id',       'name' => 'Contact ID',               'requirement' => '(Optional)',                                      'instruction' => 'Leave blank to auto generate Contact ID'],
        ['number' => 8,  'key' => 'tax_number',       'name' => 'Tax number',               'requirement' => '(Optional)',                                      'instruction' => ''],
        ['number' => 9,  'key' => 'opening_balance',  'name' => 'Opening Balance',          'requirement' => '(Optional)',                                      'instruction' => ''],
        ['number' => 10, 'key' => 'pay_term',         'name' => 'Pay term',                 'requirement' => '(Required if contact type is supplier or both)',   'instruction' => ''],
        ['number' => 11, 'key' => 'pay_term_period',  'name' => 'Pay term period',          'requirement' => '(Required if contact type is supplier or both)',   'instruction' => 'Available Options: days and months'],
        ['number' => 12, 'key' => 'credit_limit',     'name' => 'Credit Limit',             'requirement' => '(Optional)',                                      'instruction' => ''],
        ['number' => 13, 'key' => 'email',            'name' => 'Email',                    'requirement' => '(Optional)',                                      'instruction' => ''],
        ['number' => 14, 'key' => 'mobile',           'name' => 'Mobile',                   'requirement' => '(Required)',                                      'instruction' => ''],
        ['number' => 15, 'key' => 'alternate_number', 'name' => 'Alternate contact number', 'requirement' => '(Optional)',                                      'instruction' => ''],
        ['number' => 16, 'key' => 'landline',         'name' => 'Landline',                 'requirement' => '(Optional)',                                      'instruction' => ''],
        ['number' => 17, 'key' => 'city',             'name' => 'City',                     'requirement' => '(Optional)',                                      'instruction' => ''],
        ['number' => 18, 'key' => 'state',            'name' => 'State',                    'requirement' => '(Optional)',                                      'instruction' => ''],
        ['number' => 19, 'key' => 'country',          'name' => 'Country',                  'requirement' => '(Optional)',                                      'instruction' => ''],
        ['number' => 20, 'key' => 'address_line_1',   'name' => 'Address line 1',           'requirement' => '(Optional)',                                      'instruction' => ''],
        ['number' => 21, 'key' => 'address_line_2',   'name' => 'Address line 2',           'requirement' => '(Optional)',                                      'instruction' => ''],
        ['number' => 22, 'key' => 'zip_code',         'name' => 'Zip Code',                 'requirement' => '(Optional)',                                      'instruction' => ''],
        ['number' => 23, 'key' => 'dob',              'name' => 'Date of birth',            'requirement' => '(Optional)',                                      'instruction' => 'Format Y-m-d (2026-11-11)'],
        ['number' => 24, 'key' => 'custom_field1',    'name' => 'Custom Field 1',           'requirement' => '(Optional)',                                      'instruction' => ''],
        ['number' => 25, 'key' => 'custom_field2',    'name' => 'Custom Field 2',           'requirement' => '(Optional)',                                      'instruction' => ''],
        ['number' => 26, 'key' => 'custom_field3',    'name' => 'Custom Field 3',           'requirement' => '(Optional)',                                      'instruction' => ''],
        ['number' => 27, 'key' => 'custom_field4',    'name' => 'Custom Field 4',           'requirement' => '(Optional)',                                      'instruction' => ''],
    ];

    protected $fillable = [
        'business_id',
        'imported_by',
        'contact_record_id',
        'contact_type',
        'prefix',
        'first_name',
        'middle_name',
        'last_name',
        'business_name',
        'contact_id',
        'tax_number',
        'opening_balance',
        'pay_term',
        'pay_term_period',
        'credit_limit',
        'email',
        'mobile',
        'alternate_number',
        'landline',
        'city',
        'state',
        'country',
        'address_line_1',
        'address_line_2',
        'zip_code',
        'dob',
        'custom_field1',
        'custom_field2',
        'custom_field3',
        'custom_field4',
    ];

    protected $casts = [
        'contact_type'    => 'integer',
        'opening_balance' => 'decimal:2',
        'pay_term'        => 'integer',
        'credit_limit'    => 'decimal:2',
        'dob'             => 'date:Y-m-d',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_record_id');
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public static function resolveTypeName(int $contactType): string
    {
        return match ($contactType) {
            1       => 'customer',
            2       => 'supplier',
            3       => 'both',
            default => throw new \InvalidArgumentException("Invalid contact type: {$contactType}"),
        };
    }
}
