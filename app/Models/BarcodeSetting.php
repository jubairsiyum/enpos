<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BarcodeSetting extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'business_id',
        'name',
        'barcode_type',
        'sticker_size',
        'sticker_height',
        'sticker_width',
        'border_top',
        'border_bottom',
        'border_left',
        'border_right',
        'stickers_in_one_row',
        'is_default',
        'settings',
    ];

    protected $casts = [
        'sticker_height'      => 'decimal:2',
        'sticker_width'       => 'decimal:2',
        'border_top'          => 'decimal:2',
        'border_bottom'       => 'decimal:2',
        'border_left'         => 'decimal:2',
        'border_right'        => 'decimal:2',
        'stickers_in_one_row' => 'integer',
        'is_default'          => 'boolean',
        'settings'            => 'array',
    ];

    public function printLabelJobs(): HasMany
    {
        return $this->hasMany(PrintLabelJob::class, 'barcode_setting_id');
    }

    /**
     * Default barcode label sheet and continuous-roll presets.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function defaultPresets(): array
    {
        return [
            [
                'name'                => '20 Labels per Sheet, Sheet Size: 8.5" x 11", Label Size: 4" x 1", Labels per sheet: 20',
                'barcode_type'        => 'C128',
                'sticker_size'        => '4" x 1"',
                'sticker_width'       => 4.00,
                'sticker_height'      => 1.00,
                'border_top'          => 0.50,
                'border_bottom'       => 0.50,
                'border_left'         => 0.19,
                'border_right'        => 0.19,
                'stickers_in_one_row' => 2,
                'is_default'          => true,
                'settings'            => [
                    'unit'               => 'in',
                    'is_continuous'      => false,
                    'sheet_width'        => 8.5,
                    'sheet_height'       => 11.0,
                    'stickers_per_sheet' => 20,
                    'row_distance'       => 0.0,
                    'col_distance'       => 0.125,
                ],
            ],
            [
                'name'                => '30 Labels per sheet, Sheet Size: 8.5" x 11", Label Size: 2.625" x 1", Labels per sheet: 30',
                'barcode_type'        => 'C128',
                'sticker_size'        => '2.625" x 1"',
                'sticker_width'       => 2.63,
                'sticker_height'      => 1.00,
                'border_top'          => 0.50,
                'border_bottom'       => 0.50,
                'border_left'         => 0.19,
                'border_right'        => 0.19,
                'stickers_in_one_row' => 3,
                'is_default'          => false,
                'settings'            => [
                    'unit'               => 'in',
                    'is_continuous'      => false,
                    'sheet_width'        => 8.5,
                    'sheet_height'       => 11.0,
                    'stickers_per_sheet' => 30,
                    'row_distance'       => 0.0,
                    'col_distance'       => 0.125,
                ],
            ],
            [
                'name'                => '32 Labels per sheet, Sheet Size: 8.5" x 11", Label Size: 2" x 1.25", Labels per sheet: 32',
                'barcode_type'        => 'C128',
                'sticker_size'        => '2" x 1.25"',
                'sticker_width'       => 2.00,
                'sticker_height'      => 1.25,
                'border_top'          => 0.50,
                'border_bottom'       => 0.50,
                'border_left'         => 0.25,
                'border_right'        => 0.25,
                'stickers_in_one_row' => 4,
                'is_default'          => false,
                'settings'            => [
                    'unit'               => 'in',
                    'is_continuous'      => false,
                    'sheet_width'        => 8.5,
                    'sheet_height'       => 11.0,
                    'stickers_per_sheet' => 32,
                    'row_distance'       => 0.0,
                    'col_distance'       => 0.0,
                ],
            ],
            [
                'name'                => '40 Labels per sheet, Sheet Size: 8.5" x 11", Label Size: 2" x 1", Labels per sheet: 40',
                'barcode_type'        => 'C128',
                'sticker_size'        => '2" x 1"',
                'sticker_width'       => 2.00,
                'sticker_height'      => 1.00,
                'border_top'          => 0.50,
                'border_bottom'       => 0.50,
                'border_left'         => 0.25,
                'border_right'        => 0.25,
                'stickers_in_one_row' => 4,
                'is_default'          => false,
                'settings'            => [
                    'unit'               => 'in',
                    'is_continuous'      => false,
                    'sheet_width'        => 8.5,
                    'sheet_height'       => 11.0,
                    'stickers_per_sheet' => 40,
                    'row_distance'       => 0.0,
                    'col_distance'       => 0.0,
                ],
            ],
            [
                'name'                => '50 Labels per sheet, Sheet Size: 8.5" x 11", Label Size: 1.5" x 1", Labels per sheet: 50',
                'barcode_type'        => 'C128',
                'sticker_size'        => '1.5" x 1"',
                'sticker_width'       => 1.50,
                'sticker_height'      => 1.00,
                'border_top'          => 0.50,
                'border_bottom'       => 0.50,
                'border_left'         => 0.50,
                'border_right'        => 0.50,
                'stickers_in_one_row' => 5,
                'is_default'          => false,
                'settings'            => [
                    'unit'               => 'in',
                    'is_continuous'      => false,
                    'sheet_width'        => 8.5,
                    'sheet_height'       => 11.0,
                    'stickers_per_sheet' => 50,
                    'row_distance'       => 0.0,
                    'col_distance'       => 0.0,
                ],
            ],
            [
                'name'                => 'Continuous Rolls - 31.75mm x 25.4mm, Label Size: 31.75mm x 25.4mm, Gap: 3.18mm',
                'barcode_type'        => 'C128',
                'sticker_size'        => '31.75mm x 25.4mm',
                'sticker_width'       => 31.75,
                'sticker_height'      => 25.40,
                'border_top'          => 0.00,
                'border_bottom'       => 0.00,
                'border_left'         => 0.00,
                'border_right'        => 0.00,
                'stickers_in_one_row' => 1,
                'is_default'          => false,
                'settings'            => [
                    'unit'               => 'mm',
                    'is_continuous'      => true,
                    'sheet_width'        => 31.75,
                    'sheet_height'       => 25.40,
                    'stickers_per_sheet' => 1,
                    'row_distance'       => 3.18,
                    'col_distance'       => 0.0,
                ],
            ],
        ];
    }

    /**
     * Ensure default barcode presets exist for the given business.
     */
    public static function ensureDefaultsForBusiness(int $businessId): void
    {
        if (static::where('business_id', $businessId)->exists()) {
            return;
        }

        foreach (static::defaultPresets() as $preset) {
            static::create(array_merge($preset, ['business_id' => $businessId]));
        }
    }
}
