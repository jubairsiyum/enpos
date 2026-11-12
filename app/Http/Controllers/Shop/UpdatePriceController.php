<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\ImportProductPriceRequest;
use App\Models\Product;
use App\Models\ProductPriceUpdate;
use App\Models\SellingPriceGroup;
use App\Models\Variation;
use App\Models\VariationGroupPrice;
use App\Support\CurrentBusiness;
use App\Support\ExcelWorkbookCodec;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UpdatePriceController extends Controller
{
    public function __construct(
        private CurrentBusiness $currentBusiness,
        private ExcelWorkbookCodec $excelCodec,
    ) {}

    /**
     * Display Update Price instructions, expected export columns, and update history.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $businessId = (int) $this->currentBusiness->id();
        $priceGroups = $this->activePriceGroups($businessId);
        $expectedHeaders = $this->buildExpectedHeaders($priceGroups);

        $perPage = max(1, min(200, (int) $request->input('per_page', 25)));
        $history = ProductPriceUpdate::query()
            ->where('business_id', $businessId)
            ->with('updater:id,name')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        $payload = [
            'instructions'          => ProductPriceUpdate::INSTRUCTIONS,
            'expected_headers'      => $expectedHeaders,
            'selling_price_groups'  => $priceGroups->map(fn (SellingPriceGroup $g): array => [
                'id'   => $g->id,
                'name' => $g->name,
            ])->values()->all(),
            'history' => [
                'data' => $history->items(),
                'meta' => [
                    'current_page' => $history->currentPage(),
                    'last_page'    => $history->lastPage(),
                    'per_page'     => $history->perPage(),
                    'total'        => $history->total(),
                ],
            ],
        ];

        if ($request->wantsJson()) {
            return response()->json(['data' => $payload]);
        }

        return Inertia::render('Shop/Products/UpdatePrice/Index', $payload);
    }

    /**
     * Export all product variation prices and selling price group prices as .xls or .csv.
     */
    public function export(Request $request): StreamedResponse
    {
        $businessId = (int) $this->currentBusiness->id();
        $priceGroups = $this->activePriceGroups($businessId);
        $headers = $this->buildExpectedHeaders($priceGroups);

        $products = Product::query()
            ->where('business_id', $businessId)
            ->with([
                'variations' => fn ($q) => $q->orderBy('id'),
                'variations.groupPrices',
            ])
            ->orderBy('name')
            ->get();

        $rows = [$headers];
        foreach ($products as $product) {
            foreach ($product->variations as $variation) {
                $row = [
                    $product->name,
                    (string) ($product->sku ?? ''),
                    $variation->name,
                    (string) ($variation->sku ?? ''),
                    number_format((float) $variation->sell_price_inc_tax, 2, '.', ''),
                ];

                foreach ($priceGroups as $group) {
                    $groupPrice = $variation->groupPrices->firstWhere('price_group_id', $group->id);
                    $row[] = $groupPrice !== null
                        ? number_format((float) $groupPrice->price_inc_tax, 2, '.', '')
                        : '';
                }

                $rows[] = $row;
            }
        }

        $format = strtolower((string) $request->query('format', 'xls'));
        if ($format === 'csv') {
            $csv = $this->excelCodec->buildCsv($rows);

            return response()->streamDownload(function () use ($csv): void {
                echo $csv;
            }, 'product_prices.csv', [
                'Content-Type'   => 'text/csv; charset=UTF-8',
                'Content-Length' => (string) strlen($csv),
                'Cache-Control'  => 'max-age=0',
            ]);
        }

        $binary = $this->excelCodec->buildXls($rows, 'ProductPrices');

        return response()->streamDownload(function () use ($binary): void {
            echo $binary;
        }, 'product_prices.xls', [
            'Content-Type'   => 'application/vnd.ms-excel',
            'Content-Length' => (string) strlen($binary),
            'Cache-Control'  => 'max-age=0',
        ]);
    }

    /**
     * Validate and import updated product prices & selling price groups from an uploaded file.
     */
    public function import(ImportProductPriceRequest $request): JsonResponse|RedirectResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['xls', 'xlsx', 'csv', 'txt'], true)) {
            throw ValidationException::withMessages([
                'file' => ['The file must be a .xls, .xlsx, or .csv file.'],
            ]);
        }

        $contents = file_get_contents($file->getRealPath());
        if ($contents === false || trim($contents) === '') {
            throw ValidationException::withMessages([
                'file' => ['The uploaded file is empty.'],
            ]);
        }

        try {
            $rawRows = $this->excelCodec->parseUploadedRows($contents, $extension);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'file' => [$e->getMessage()],
            ]);
        }

        $rows = $this->filterNonEmptyRows($rawRows);
        if (count($rows) < 2) {
            throw ValidationException::withMessages([
                'file' => ['The file must contain the header row and at least one product row.'],
            ]);
        }

        $businessId = (int) $this->currentBusiness->id();
        $userId = $request->user()?->id;
        $priceGroups = $this->activePriceGroups($businessId);
        $expectedHeaders = $this->buildExpectedHeaders($priceGroups);

        $headerRow = array_shift($rows);
        $this->assertValidHeaders($headerRow, $expectedHeaders);

        $updatedLogs = DB::transaction(function () use ($rows, $businessId, $userId, $priceGroups): array {
            $logs = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;
                $logs[] = $this->processImportRow($row, $rowNumber, $businessId, $userId, $priceGroups);
            }

            return $logs;
        });

        $count = count($updatedLogs);
        $message = "{$count} product price(s) updated successfully.";

        if ($request->wantsJson()) {
            return response()->json([
                'message'       => $message,
                'updated_count' => $count,
                'data'          => $updatedLogs,
            ]);
        }

        return redirect()->route('shop.products.update-price.index')
            ->with('success', $message);
    }

    /**
     * @return Collection<int, SellingPriceGroup>
     */
    private function activePriceGroups(int $businessId): Collection
    {
        return SellingPriceGroup::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'name']);
    }

    /**
     * @param  Collection<int, SellingPriceGroup>  $priceGroups
     * @return array<int, string>
     */
    private function buildExpectedHeaders(Collection $priceGroups): array
    {
        return array_merge(
            ProductPriceUpdate::BASE_HEADERS,
            $priceGroups->pluck('name')->all()
        );
    }

    /**
     * @param  array<int, array<int, string|null>>  $rows
     * @return array<int, array<int, string|null>>
     */
    private function filterNonEmptyRows(array $rows): array
    {
        return array_values(array_filter($rows, function (array $row): bool {
            foreach ($row as $cell) {
                if ($cell !== null && trim((string) $cell) !== '') {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @param  array<int, string|null>  $headerRow
     * @param  array<int, string>  $expectedHeaders
     */
    private function assertValidHeaders(array $headerRow, array $expectedHeaders): void
    {
        $normalizedActual = array_map(function (mixed $cell, int $idx): string {
            $text = (string) ($cell ?? '');
            if ($idx === 0) {
                $text = ltrim($text, "\xEF\xBB\xBF");
            }

            return mb_strtolower(trim($text));
        }, $headerRow, array_keys($headerRow));

        $normalizedExpected = array_map(
            fn (string $h): string => mb_strtolower(trim($h)),
            $expectedHeaders
        );

        if (count($normalizedActual) < count($normalizedExpected)) {
            throw ValidationException::withMessages([
                'file' => ['Headers do not match the exported format. Do not change any product name, sku & headers.'],
            ]);
        }

        foreach ($normalizedExpected as $colIndex => $expected) {
            if (($normalizedActual[$colIndex] ?? '') !== $expected) {
                throw ValidationException::withMessages([
                    'file' => ["Header mismatch at column " . ($colIndex + 1) . ": expected '{$expectedHeaders[$colIndex]}'. Do not change any product name, sku & headers."],
                ]);
            }
        }
    }

    /**
     * @param  array<int, string|null>  $row
     * @param  Collection<int, SellingPriceGroup>  $priceGroups
     */
    private function processImportRow(
        array $row,
        int $rowNumber,
        int $businessId,
        ?int $userId,
        Collection $priceGroups,
    ): ProductPriceUpdate {
        $productName = trim((string) ($row[0] ?? ''));
        $productSku = trim((string) ($row[1] ?? ''));
        $variationName = trim((string) ($row[2] ?? ''));
        $variationSku = trim((string) ($row[3] ?? ''));
        $rawSellPriceIncTax = trim((string) ($row[4] ?? ''));

        if ($productName === '' || $productSku === '') {
            throw ValidationException::withMessages([
                'file' => ["Row {$rowNumber}: Product name and SKU are required and must not be changed."],
            ]);
        }

        /** @var Product|null $product */
        $product = Product::query()
            ->where('business_id', $businessId)
            ->where('sku', $productSku)
            ->with(['tax:id,amount', 'variations.groupPrices'])
            ->first();

        if ($product === null || mb_strtolower(trim($product->name)) !== mb_strtolower($productName)) {
            throw ValidationException::withMessages([
                'file' => ["Row {$rowNumber}: Product '{$productName}' with SKU '{$productSku}' was not found. Do not change any product name, sku & headers."],
            ]);
        }

        $variation = $this->resolveVariationForRow($product, $variationName, $variationSku, $rowNumber);

        if ($rawSellPriceIncTax === '' || ! is_numeric($rawSellPriceIncTax) || (float) $rawSellPriceIncTax < 0) {
            throw ValidationException::withMessages([
                'file' => ["Row {$rowNumber}: Selling Price (Inc. Tax) must be a valid non-negative number."],
            ]);
        }

        $newSellInc = round((float) $rawSellPriceIncTax, 4);
        $taxPercent = (float) ($product->tax?->amount ?? 0);
        $multiplier = 1 + ($taxPercent / 100);
        $newSellExc = $multiplier > 0 ? round($newSellInc / $multiplier, 4) : $newSellInc;

        $oldSellExc = (float) $variation->default_sell_price;
        $oldSellInc = (float) $variation->sell_price_inc_tax;

        $newProfitPercent = (float) $variation->profit_percent;
        $purchaseExc = (float) $variation->default_purchase_price;
        if ($purchaseExc > 0) {
            $newProfitPercent = round((($newSellExc - $purchaseExc) / $purchaseExc) * 100, 2);
        }

        $variation->update([
            'default_sell_price' => $newSellExc,
            'sell_price_inc_tax' => $newSellInc,
            'profit_percent'     => $newProfitPercent,
        ]);

        if ($product->type === 'single') {
            $product->update([
                'default_sell_price' => $newSellExc,
                'profit_percent'     => $newProfitPercent,
            ]);
        }

        $groupSnapshot = [];
        foreach ($priceGroups->values() as $idx => $group) {
            $colIndex = 5 + $idx;
            $rawGroupPrice = trim((string) ($row[$colIndex] ?? ''));
            $existingGroupPrice = $variation->groupPrices->firstWhere('price_group_id', $group->id);
            $oldGroupVal = $existingGroupPrice !== null ? (float) $existingGroupPrice->price_inc_tax : null;

            if ($rawGroupPrice === '') {
                continue;
            }

            if (! is_numeric($rawGroupPrice) || (float) $rawGroupPrice < 0) {
                throw ValidationException::withMessages([
                    'file' => ["Row {$rowNumber}: Price for selling price group '{$group->name}' must be a valid non-negative number."],
                ]);
            }

            $newGroupVal = round((float) $rawGroupPrice, 4);
            VariationGroupPrice::updateOrCreate(
                [
                    'variation_id'   => $variation->id,
                    'price_group_id' => $group->id,
                ],
                [
                    'price_inc_tax' => $newGroupVal,
                ]
            );

            $groupSnapshot[] = [
                'price_group_id'   => $group->id,
                'price_group_name' => $group->name,
                'old_price'        => $oldGroupVal,
                'new_price'        => $newGroupVal,
            ];
        }

        return ProductPriceUpdate::create([
            'business_id'            => $businessId,
            'updated_by'             => $userId,
            'product_id'             => $product->id,
            'variation_id'           => $variation->id,
            'product_name'           => $product->name,
            'sku'                    => (string) $product->sku,
            'variation_name'         => $variation->name,
            'variation_sku'          => $variation->sku,
            'old_sell_price'         => $oldSellExc,
            'new_sell_price'         => $newSellExc,
            'old_sell_price_inc_tax' => $oldSellInc,
            'new_sell_price_inc_tax' => $newSellInc,
            'group_prices_snapshot'  => $groupSnapshot,
            'source'                 => 'import',
        ]);
    }

    private function resolveVariationForRow(
        Product $product,
        string $variationName,
        string $variationSku,
        int $rowNumber,
    ): Variation {
        $matched = $product->variations->first(function (Variation $v) use ($variationName, $variationSku): bool {
            if ($variationSku !== '' && (string) $v->sku !== $variationSku) {
                return false;
            }

            $normalizedRowName = mb_strtolower($variationName);
            $normalizedVarName = mb_strtolower(trim($v->name));

            if ($normalizedVarName === 'dummy' && in_array($normalizedRowName, ['', 'dummy', 'default'], true)) {
                return true;
            }

            return $normalizedVarName === $normalizedRowName;
        });

        if ($matched === null) {
            throw ValidationException::withMessages([
                'file' => ["Row {$rowNumber}: Variation '{$variationName}' (SKU: '{$variationSku}') does not belong to product '{$product->name}'. Do not change any product name, sku & headers."],
            ]);
        }

        return $matched;
    }
}
