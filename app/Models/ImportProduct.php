<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportProduct extends Model
{
    use HasFactory, BelongsToBusiness;

    /**
     * Exact 37 columns for Import Products template & validation.
     *
     * @var array<int, array{number: int, key: string, name: string, requirement: string, instruction: string}>
     */
    public const TEMPLATE_COLUMNS = [
        [
            'number'      => 1,
            'key'         => 'product_name',
            'name'        => 'Product Name',
            'requirement' => '(Required)',
            'instruction' => 'Name of the product',
        ],
        [
            'number'      => 2,
            'key'         => 'brand',
            'name'        => 'Brand',
            'requirement' => '(Optional)',
            'instruction' => "Name of the brand\n(If not found new brand with the given name will be created)",
        ],
        [
            'number'      => 3,
            'key'         => 'unit',
            'name'        => 'Unit',
            'requirement' => '(Required)',
            'instruction' => 'Name of the unit',
        ],
        [
            'number'      => 4,
            'key'         => 'category',
            'name'        => 'Category',
            'requirement' => '(Optional)',
            'instruction' => "Name of the category\n(If not found new category with the given name will be created)",
        ],
        [
            'number'      => 5,
            'key'         => 'sub_category',
            'name'        => 'Sub category',
            'requirement' => '(Optional)',
            'instruction' => "Name of the sub-category\n(If not found new sub-category with the given name under the parent Category will be created)",
        ],
        [
            'number'      => 6,
            'key'         => 'sku',
            'name'        => 'SKU',
            'requirement' => '(Optional)',
            'instruction' => 'Product SKU. If blank an SKU will be automatically generated',
        ],
        [
            'number'      => 7,
            'key'         => 'barcode_type',
            'name'        => 'Barcode Type',
            'requirement' => '(Optional, Default: C128)',
            'instruction' => "Barcode Type for the product.\nCurrently supported: C128, C39, EAN-13, EAN-8, UPC-A, UPC-E, ITF-14",
        ],
        [
            'number'      => 8,
            'key'         => 'manage_stock',
            'name'        => 'Manage Stock?',
            'requirement' => '(Required)',
            'instruction' => "Enable or disable stock management\n1 = Yes\n0 = No",
        ],
        [
            'number'      => 9,
            'key'         => 'alert_quantity',
            'name'        => 'Alert quantity',
            'requirement' => '(Optional)',
            'instruction' => 'Alert quantity',
        ],
        [
            'number'      => 10,
            'key'         => 'expires_in',
            'name'        => 'Expires in',
            'requirement' => '(Optional)',
            'instruction' => 'Product expiry period (Only in numbers)',
        ],
        [
            'number'      => 11,
            'key'         => 'expiry_period_unit',
            'name'        => 'Expiry Period Unit',
            'requirement' => '(Optional)',
            'instruction' => "Unit for the expiry period\nAvailable Options: days, months",
        ],
        [
            'number'      => 12,
            'key'         => 'applicable_tax',
            'name'        => 'Applicable Tax',
            'requirement' => '(Optional)',
            'instruction' => "Name of the Tax Rate\nIf purchase Price (Excluding Tax) is not same as\nPurchase Price (Including Tax) then you must supply the tax rate name.",
        ],
        [
            'number'      => 13,
            'key'         => 'selling_price_tax_type',
            'name'        => 'Selling Price Tax Type',
            'requirement' => '(Required)',
            'instruction' => "Selling Price Tax Type\nAvailable Options: inclusive, exclusive",
        ],
        [
            'number'      => 14,
            'key'         => 'product_type',
            'name'        => 'Product Type',
            'requirement' => '(Required)',
            'instruction' => "Product Type\nAvailable Options: single, variable",
        ],
        [
            'number'      => 15,
            'key'         => 'variation_name',
            'name'        => 'Variation Name',
            'requirement' => '(Required if product type is variable)',
            'instruction' => 'Name of the variation (Ex: Size, Color etc )',
        ],
        [
            'number'      => 16,
            'key'         => 'variation_values',
            'name'        => 'Variation Values',
            'requirement' => '(Required if product type is variable)',
            'instruction' => "Values for the variation separated with '|'\n(Ex: Red|Blue|Green)",
        ],
        [
            'number'      => 17,
            'key'         => 'variation_skus',
            'name'        => 'Variation SKUs',
            'requirement' => '(Optional)',
            'instruction' => "SKUs of each variations separated by '|' if product type is variable",
        ],
        [
            'number'      => 18,
            'key'         => 'purchase_price_inc_tax',
            'name'        => 'Purchase Price (Including Tax)',
            'requirement' => '(Required if Purchase Price Excluding Tax is not given)',
            'instruction' => "Purchase Price (Including Tax) (Only in numbers)\nFor variable products '|' separated values with\nthe same order as Variation Values\n(Ex: 84|85|88)",
        ],
        [
            'number'      => 19,
            'key'         => 'purchase_price_exc_tax',
            'name'        => 'Purchase Price (Excluding Tax)',
            'requirement' => '(Required if Purchase Price Including Tax is not given)',
            'instruction' => "Purchase Price (Excluding Tax) (Only in numbers)\nFor variable products '|' separated values with\nthe same order as Variation Values\n(Ex: 84|85|88)",
        ],
        [
            'number'      => 20,
            'key'         => 'profit_margin',
            'name'        => 'Profit Margin %',
            'requirement' => '(Optional)',
            'instruction' => "Profit Margin (Only in numbers)\nIf blank default profit margin for the\nbusiness will be used",
        ],
        [
            'number'      => 21,
            'key'         => 'selling_price',
            'name'        => 'Selling Price',
            'requirement' => '(Optional)',
            'instruction' => "Selling Price (Only in numbers)\nIf blank selling price will be calculated\nwith the given Purchase Price\nand Applicable Tax",
        ],
        [
            'number'      => 22,
            'key'         => 'opening_stock',
            'name'        => 'Opening Stock',
            'requirement' => '(Optional)',
            'instruction' => "Opening Stock (Only in numbers)\nFor variable products separate stock quantities with '|'\n(Ex: 100|150|200)",
        ],
        [
            'number'      => 23,
            'key'         => 'opening_stock_location',
            'name'        => 'Opening stock location',
            'requirement' => '(Optional)',
            'instruction' => "Name of the business location\nIf blank first business location will be used",
        ],
        [
            'number'      => 24,
            'key'         => 'expiry_date',
            'name'        => 'Expiry Date',
            'requirement' => '(Optional)',
            'instruction' => "Stock Expiry Date\nFormat: mm-dd-yyyy; Ex: 11-25-2018",
        ],
        [
            'number'      => 25,
            'key'         => 'enable_sr_no',
            'name'        => 'Enable Product description, IMEI or Serial Number',
            'requirement' => '(Optional, Default: 0)',
            'instruction' => "1 = Yes\n0 = No",
        ],
        [
            'number'      => 26,
            'key'         => 'weight',
            'name'        => 'Weight',
            'requirement' => '(Optional)',
            'instruction' => 'Optional',
        ],
        [
            'number'      => 27,
            'key'         => 'rack',
            'name'        => 'Rack',
            'requirement' => '(Optional)',
            'instruction' => "Rack details seperated by '|' for different business locations serially.\n(Ex: R1|R5|R12)",
        ],
        [
            'number'      => 28,
            'key'         => 'row',
            'name'        => 'Row',
            'requirement' => '(Optional)',
            'instruction' => "Row details seperated by '|' for different business locations serially.\n(Ex: ROW1|ROW2|ROW3)",
        ],
        [
            'number'      => 29,
            'key'         => 'position',
            'name'        => 'Position',
            'requirement' => '(Optional)',
            'instruction' => "Position details seperated by '|' for different business locations serially.\n(Ex: POS1|POS2|POS3)",
        ],
        [
            'number'      => 30,
            'key'         => 'image',
            'name'        => 'Image',
            'requirement' => '(Optional)',
            'instruction' => "Image name with extension.\n(Image name must be uploaded to the server public/uploads/img )\nOr URL of the image",
        ],
        [
            'number'      => 31,
            'key'         => 'product_description',
            'name'        => 'Product Description',
            'requirement' => '(Optional)',
            'instruction' => '',
        ],
        [
            'number'      => 32,
            'key'         => 'custom_field1',
            'name'        => 'Custom Field1',
            'requirement' => '(Optional)',
            'instruction' => '',
        ],
        [
            'number'      => 33,
            'key'         => 'custom_field2',
            'name'        => 'Custom Field2',
            'requirement' => '(Optional)',
            'instruction' => '',
        ],
        [
            'number'      => 34,
            'key'         => 'custom_field3',
            'name'        => 'Custom Field3',
            'requirement' => '(Optional)',
            'instruction' => '',
        ],
        [
            'number'      => 35,
            'key'         => 'custom_field4',
            'name'        => 'Custom Field4',
            'requirement' => '(Optional)',
            'instruction' => '',
        ],
        [
            'number'      => 36,
            'key'         => 'not_for_selling',
            'name'        => 'Not for selling',
            'requirement' => '(Optional)',
            'instruction' => "1 = Yes\n0 = No",
        ],
        [
            'number'      => 37,
            'key'         => 'product_locations',
            'name'        => 'Product locations',
            'requirement' => '(Optional)',
            'instruction' => 'Comma separated string of business location names where product will be available',
        ],
    ];

    protected $fillable = [
        'business_id',
        'imported_by',
        'product_id',
        'product_name',
        'brand',
        'unit',
        'category',
        'sub_category',
        'sku',
        'barcode_type',
        'manage_stock',
        'alert_quantity',
        'expires_in',
        'expiry_period_unit',
        'applicable_tax',
        'selling_price_tax_type',
        'product_type',
        'variation_name',
        'variation_values',
        'variation_skus',
        'purchase_price_inc_tax',
        'purchase_price_exc_tax',
        'profit_margin',
        'selling_price',
        'opening_stock',
        'opening_stock_location',
        'expiry_date',
        'enable_sr_no',
        'weight',
        'rack',
        'row',
        'position',
        'image',
        'product_description',
        'custom_field1',
        'custom_field2',
        'custom_field3',
        'custom_field4',
        'not_for_selling',
        'product_locations',
    ];

    protected $casts = [
        'manage_stock'    => 'boolean',
        'alert_quantity'  => 'decimal:2',
        'expires_in'      => 'integer',
        'profit_margin'   => 'decimal:2',
        'enable_sr_no'    => 'boolean',
        'not_for_selling' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
