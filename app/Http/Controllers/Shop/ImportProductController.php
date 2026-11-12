<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ImportProduct;
use App\Models\Location;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\Variation;
use App\Models\VariationTemplate;
use App\Models\VariationValueTemplate;
use App\Services\ProductService;
use App\Support\CurrentBusiness;
use App\Support\ExcelWorkbookCodec;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportProductController extends Controller
{
    /**
     * Supported barcode type aliases mapped to canonical database codes.
     *
     * @var array<string, string>
     */
    private const BARCODE_TYPE_MAP = [
        'C128'   => 'C128',
        'C39'    => 'C39',
        'EAN-13' => 'EAN13',
        'EAN13'  => 'EAN13',
        'EAN-8'  => 'EAN8',
        'EAN8'   => 'EAN8',
        'UPC-A'  => 'UPCA',
        'UPCA'   => 'UPCA',
        'UPC-E'  => 'UPCE',
        'UPCE'   => 'UPCE',
        'ITF-14' => 'ITF14',
        'ITF14'  => 'ITF14',
    ];

    public function __construct(
        private CurrentBusiness $currentBusiness,
        private ExcelWorkbookCodec $excelCodec,
        private ProductService $productService,
    ) {
    }

    /**
     * Display the import products instructions/upload page or list imported records.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $businessId = $this->currentBusiness->id();

        if ($request->wantsJson()) {
            $perPage = max(1, min(200, (int) $request->input('per_page', 25)));
            $imports = ImportProduct::query()
                ->where('business_id', $businessId)
                ->with('product:id,name,sku,type')
                ->latest('id')
                ->paginate($perPage)
                ->withQueryString();

            return response()->json([
                'data' => $imports->items(),
                'meta' => [
                    'current_page' => $imports->currentPage(),
                    'last_page'    => $imports->lastPage(),
                    'per_page'     => $imports->perPage(),
                    'total'        => $imports->total(),
                ],
            ]);
        }

        return Inertia::render('Shop/Products/ImportProducts/Index', [
            'columns' => ImportProduct::TEMPLATE_COLUMNS,
        ]);
    }

    /**
     * Download the import products template in genuine binary Excel (.xls) format.
     */
    public function downloadTemplate(): StreamedResponse
    {
        $headers = array_map(
            fn (array $col): string => $col['name'],
            ImportProduct::TEMPLATE_COLUMNS
        );
        $binary = $this->excelCodec->buildXls([$headers]);

        return response()->streamDownload(function () use ($binary): void {
            echo $binary;
        }, 'import_products_template.xls', [
            'Content-Type'   => 'application/vnd.ms-excel',
            'Content-Length' => (string) strlen($binary),
            'Cache-Control'  => 'max-age=0',
        ]);
    }

    /**
     * Validate and import products from an uploaded .xls, .xlsx, or .csv file.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['xls', 'xlsx', 'csv', 'txt'], true)) {
            throw ValidationException::withMessages([
                'file' => ['The file must be a .xls, .xlsx, or .csv file.'],
            ]);
        }

        $rows = $this->parseUploadedFile($file, $extension);
        if (empty($rows)) {
            throw ValidationException::withMessages([
                'file' => ['No product rows were found in the uploaded file.'],
            ]);
        }

        $businessId = $this->currentBusiness->id();
        $userId = (int) ($request->user()?->id ?? 0);

        $imported = DB::transaction(function () use ($rows, $businessId, $userId): array {
            $created = [];
            $seenSkus = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // 1-based accounting for header row
                $mapped = $this->mapRowToFields($row);
                $validated = $this->validateAndResolveRow($mapped, $rowNumber, $businessId, $seenSkus);

                if (! empty($validated['sku'])) {
                    $seenSkus[] = mb_strtolower((string) $validated['sku']);
                }

                $result = $this->productService->createOrMergeProduct(
                    businessId: $businessId,
                    userId: $userId,
                    validated: $validated['product_payload'],
                );

                /** @var Product $product */
                $product = $result['product'];

                if (! empty($validated['opening_stock_entries'])) {
                    $this->applyImportedOpeningStock(
                        $product,
                        $businessId,
                        $userId,
                        $validated['opening_stock_entries'],
                        $validated['opening_stock_location_id']
                    );
                }

                $created[] = ImportProduct::create(array_merge($validated['audit_record'], [
                    'business_id' => $businessId,
                    'imported_by' => $userId > 0 ? $userId : null,
                    'product_id'  => $product->id,
                    'sku'         => $product->sku,
                ]));
            }

            return $created;
        });

        $count = count($imported);

        if ($request->wantsJson()) {
            return response()->json([
                'message'        => "{$count} product(s) imported successfully.",
                'imported_count' => $count,
                'data'           => $imported,
            ], 201);
        }

        return redirect()->route('shop.products.import.index')
            ->with('success', "{$count} product(s) imported successfully.");
    }

    /**
     * Parse uploaded spreadsheet/CSV file into raw data rows (excluding template header).
     *
     * @return array<int, array<int, string|null>>
     */
    private function parseUploadedFile(UploadedFile $file, string $extension): array
    {
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false || trim($contents) === '') {
            return [];
        }

        try {
            if ($this->excelCodec->isBinaryXls($contents)) {
                $rows = $this->excelCodec->parseBinaryXls($contents);
            } elseif ($this->excelCodec->isXlsx($contents) || $extension === 'xlsx') {
                $rows = $this->excelCodec->parseXlsx($contents);
            } elseif ($extension === 'xls' && str_contains($contents, '<Workbook')) {
                $rows = $this->parseSpreadsheetMl($contents);
            } elseif ($extension === 'xls' && stripos($contents, '<table') !== false) {
                $rows = $this->parseHtmlTable($contents);
            } else {
                $rows = $this->parseCsvContent($contents);
            }
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'file' => [$e->getMessage()],
            ]);
        }

        if (empty($rows)) {
            return [];
        }

        // Drop header row when it matches the template column header (strip UTF-8 BOM if present)
        $firstCellRaw = ltrim((string) ($rows[0][0] ?? ''), "\xEF\xBB\xBF");
        $firstCell = strtolower(trim($firstCellRaw));
        if ($firstCell === 'product name') {
            array_shift($rows);
        }

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
     * @return array<int, array<int, string|null>>
     */
    private function parseSpreadsheetMl(string $xmlContent): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            throw ValidationException::withMessages([
                'file' => ['Unable to parse the .xls XML file. Please use the provided template.'],
            ]);
        }

        $ssNs = 'urn:schemas-microsoft-com:office:spreadsheet';
        $xml->registerXPathNamespace('ss', $ssNs);
        $rowNodes = $xml->xpath('//ss:Worksheet/ss:Table/ss:Row') ?: $xml->xpath('//Worksheet/Table/Row');

        if (! is_array($rowNodes)) {
            return [];
        }

        $rows = [];
        foreach ($rowNodes as $rowNode) {
            $row = [];
            $colIndex = 0;

            $cells = $rowNode->children($ssNs)->Cell;
            if (count($cells) === 0) {
                $cells = $rowNode->Cell;
            }

            foreach ($cells as $cell) {
                $ssAttrs = $cell->attributes($ssNs);
                if (isset($ssAttrs['Index'])) {
                    $colIndex = ((int) $ssAttrs['Index']) - 1;
                }

                $dataNode = $cell->children($ssNs)->Data ?? $cell->Data;
                $row[$colIndex] = trim((string) $dataNode);
                $colIndex++;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function parseHtmlTable(string $html): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $rows = [];

        foreach ($dom->getElementsByTagName('tr') as $tr) {
            $row = [];
            foreach ($tr->childNodes as $cell) {
                if (in_array(strtolower($cell->nodeName), ['td', 'th'], true)) {
                    $row[] = trim($cell->textContent);
                }
            }
            if (! empty($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function parseCsvContent(string $contents): array
    {
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $contents);
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    /**
     * Map raw 37-column row array to named keys and normalize basic values.
     *
     * @param  array<int, string|null>  $row
     * @return array<string, mixed>
     */
    private function mapRowToFields(array $row): array
    {
        $mapped = [];
        foreach (ImportProduct::TEMPLATE_COLUMNS as $idx => $col) {
            $value = $row[$idx] ?? null;
            $trimmed = $value !== null ? trim((string) $value) : null;
            $mapped[$col['key']] = $trimmed === '' ? null : $trimmed;
        }

        if ($mapped['barcode_type'] !== null) {
            $upper = strtoupper((string) $mapped['barcode_type']);
            $mapped['barcode_type'] = self::BARCODE_TYPE_MAP[$upper] ?? $upper;
        } else {
            $mapped['barcode_type'] = 'C128';
        }

        foreach (['expiry_period_unit', 'selling_price_tax_type', 'product_type'] as $enumKey) {
            if ($mapped[$enumKey] !== null) {
                $mapped[$enumKey] = strtolower((string) $mapped[$enumKey]);
            }
        }

        // Convert Excel serial date if provided in Expiry Date
        if ($mapped['expiry_date'] !== null && preg_match('/^\d+(\.\d+)?$/', (string) $mapped['expiry_date'])) {
            $serial = (float) $mapped['expiry_date'];
            if ($serial >= 1 && $serial < 100000) {
                $mapped['expiry_date'] = gmdate('m-d-Y', (int) round(($serial - 25569) * 86400));
            }
        }

        return $mapped;
    }

    /**
     * Validate a mapped row and resolve foreign keys / variation structures.
     *
     * @param  array<string, mixed>  $mapped
     * @param  array<int, string>    $seenSkus
     * @return array{
     *     sku: ?string,
     *     product_payload: array<string, mixed>,
     *     opening_stock_entries: array<int, array{variation_name: string, quantity: float}>,
     *     opening_stock_location_id: ?int,
     *     audit_record: array<string, mixed>
     * }
     */
    private function validateAndResolveRow(
        array $mapped,
        int $rowNumber,
        int $businessId,
        array $seenSkus,
    ): array {
        $validator = Validator::make($mapped, [
            'product_name'           => ['required', 'string', 'max:255'],
            'brand'                  => ['nullable', 'string', 'max:255'],
            'unit'                   => ['required', 'string', 'max:255'],
            'category'               => ['nullable', 'string', 'max:255'],
            'sub_category'           => ['nullable', 'string', 'max:255'],
            'sku'                    => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'sku')->where('business_id', $businessId)->whereNull('deleted_at'),
            ],
            'barcode_type'           => ['nullable', 'string', Rule::in(['C128', 'C39', 'EAN13', 'EAN8', 'UPCA', 'UPCE', 'ITF14'])],
            'manage_stock'           => ['required', Rule::in(['0', '1', 0, 1])],
            'alert_quantity'         => ['nullable', 'numeric', 'min:0'],
            'expires_in'             => ['nullable', 'integer', 'min:0'],
            'expiry_period_unit'     => ['nullable', Rule::in(['days', 'months'])],
            'applicable_tax'         => ['nullable', 'string', 'max:255'],
            'selling_price_tax_type' => ['required', Rule::in(['inclusive', 'exclusive'])],
            'product_type'           => ['required', Rule::in(['single', 'variable'])],
            'variation_name'         => ['required_if:product_type,variable', 'nullable', 'string', 'max:255'],
            'variation_values'       => ['required_if:product_type,variable', 'nullable', 'string'],
            'variation_skus'         => ['nullable', 'string'],
            'purchase_price_inc_tax' => ['nullable', 'string'],
            'purchase_price_exc_tax' => ['nullable', 'string'],
            'profit_margin'          => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'selling_price'          => ['nullable', 'string'],
            'opening_stock'          => ['nullable', 'string'],
            'opening_stock_location' => ['nullable', 'string', 'max:255'],
            'expiry_date'            => ['nullable', 'string', 'max:30'],
            'enable_sr_no'           => ['nullable', Rule::in(['0', '1', 0, 1])],
            'weight'                 => ['nullable', 'string', 'max:100'],
            'rack'                   => ['nullable', 'string'],
            'row'                    => ['nullable', 'string'],
            'position'               => ['nullable', 'string'],
            'image'                  => ['nullable', 'string', 'max:255'],
            'product_description'    => ['nullable', 'string'],
            'custom_field1'          => ['nullable', 'string'],
            'custom_field2'          => ['nullable', 'string'],
            'custom_field3'          => ['nullable', 'string'],
            'custom_field4'          => ['nullable', 'string'],
            'not_for_selling'        => ['nullable', Rule::in(['0', '1', 0, 1])],
            'product_locations'      => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            $this->failRow($rowNumber, $validator->errors()->first());
        }

        if ($mapped['sku'] !== null && in_array(mb_strtolower((string) $mapped['sku']), $seenSkus, true)) {
            $this->failRow($rowNumber, "Duplicate SKU '{$mapped['sku']}' found in the uploaded file.");
        }

        if ($mapped['purchase_price_inc_tax'] === null && $mapped['purchase_price_exc_tax'] === null) {
            $this->failRow($rowNumber, 'Either Purchase Price (Including Tax) or Purchase Price (Excluding Tax) is required.');
        }

        if ($mapped['expiry_date'] !== null && ! $this->isValidExpiryDate((string) $mapped['expiry_date'])) {
            $this->failRow($rowNumber, 'Expiry Date must be in mm-dd-yyyy format (e.g. 11-25-2018).');
        }

        // Resolve Unit (must exist)
        $unit = $this->resolveUnit($businessId, (string) $mapped['unit'], $rowNumber);

        // Resolve or create Brand
        $brandId = $mapped['brand'] !== null
            ? $this->resolveOrCreateBrand($businessId, (string) $mapped['brand'])
            : null;

        // Resolve or create Category & Sub-category
        if ($mapped['sub_category'] !== null && $mapped['category'] === null) {
            $this->failRow($rowNumber, 'Category is required when Sub category is specified.');
        }

        $categoryId = $mapped['category'] !== null
            ? $this->resolveOrCreateCategory($businessId, (string) $mapped['category'], null)
            : null;

        $subCategoryId = ($mapped['sub_category'] !== null && $categoryId !== null)
            ? $this->resolveOrCreateCategory($businessId, (string) $mapped['sub_category'], $categoryId)
            : null;

        // Resolve Tax Rate
        $taxId = $mapped['applicable_tax'] !== null
            ? $this->resolveTaxRate($businessId, (string) $mapped['applicable_tax'], $rowNumber)
            : null;

        // Resolve Locations
        $locationIds = $this->resolveProductLocations($businessId, $mapped['product_locations'], $rowNumber);
        $openingStockLocationId = $this->resolveOpeningStockLocation(
            $businessId,
            $mapped['opening_stock_location'],
            $locationIds,
            $rowNumber
        );

        $manageStock = ((int) $mapped['manage_stock']) === 1;
        $enableSrNo = ((int) ($mapped['enable_sr_no'] ?? 0)) === 1;
        $notForSelling = ((int) ($mapped['not_for_selling'] ?? 0)) === 1;

        $productType = (string) $mapped['product_type'];
        $variationSetup = $this->buildVariationAndPricingPayload(
            $mapped,
            $productType,
            $businessId,
            $rowNumber
        );

        $customFields = array_filter([
            'custom_field1'      => $mapped['custom_field1'],
            'custom_field2'      => $mapped['custom_field2'],
            'custom_field3'      => $mapped['custom_field3'],
            'custom_field4'      => $mapped['custom_field4'],
            'expires_in'         => $mapped['expires_in'] !== null ? (int) $mapped['expires_in'] : null,
            'expiry_period_unit' => $mapped['expiry_period_unit'],
            'expiry_date'        => $mapped['expiry_date'],
            'rack'               => $mapped['rack'],
            'row'                => $mapped['row'],
            'position'           => $mapped['position'],
        ], fn (mixed $v): bool => $v !== null);

        $productPayload = array_merge([
            'name'                   => $mapped['product_name'],
            'type'                   => $productType,
            'sku'                    => $mapped['sku'],
            'barcode_type'           => $mapped['barcode_type'],
            'unit_id'                => $unit->id,
            'brand_id'               => $brandId,
            'category_id'            => $categoryId,
            'sub_category_id'        => $subCategoryId,
            'tax_id'                 => $taxId,
            'tax_type'               => $mapped['selling_price_tax_type'],
            'enable_stock'           => $manageStock,
            'alert_quantity'         => $manageStock && $mapped['alert_quantity'] !== null ? (float) $mapped['alert_quantity'] : null,
            'profit_percent'         => $mapped['profit_margin'] !== null ? (float) $mapped['profit_margin'] : null,
            'enable_sr_no'           => $enableSrNo,
            'weight'                 => $mapped['weight'],
            'description'            => $mapped['product_description'],
            'not_for_selling'        => $notForSelling,
            'location_ids'           => $locationIds,
            'custom_fields'          => $customFields !== [] ? $customFields : null,
        ], $variationSetup['payload']);

        $auditRecord = [
            'product_name'           => $mapped['product_name'],
            'brand'                  => $mapped['brand'],
            'unit'                   => $mapped['unit'],
            'category'               => $mapped['category'],
            'sub_category'           => $mapped['sub_category'],
            'sku'                    => $mapped['sku'],
            'barcode_type'           => $mapped['barcode_type'],
            'manage_stock'           => $manageStock,
            'alert_quantity'         => $mapped['alert_quantity'] !== null ? (float) $mapped['alert_quantity'] : null,
            'expires_in'             => $mapped['expires_in'] !== null ? (int) $mapped['expires_in'] : null,
            'expiry_period_unit'     => $mapped['expiry_period_unit'],
            'applicable_tax'         => $mapped['applicable_tax'],
            'selling_price_tax_type' => $mapped['selling_price_tax_type'],
            'product_type'           => $productType,
            'variation_name'         => $mapped['variation_name'],
            'variation_values'       => $mapped['variation_values'],
            'variation_skus'         => $mapped['variation_skus'],
            'purchase_price_inc_tax' => $mapped['purchase_price_inc_tax'],
            'purchase_price_exc_tax' => $mapped['purchase_price_exc_tax'],
            'profit_margin'          => $mapped['profit_margin'] !== null ? (float) $mapped['profit_margin'] : null,
            'selling_price'          => $mapped['selling_price'],
            'opening_stock'          => $mapped['opening_stock'],
            'opening_stock_location' => $mapped['opening_stock_location'],
            'expiry_date'            => $mapped['expiry_date'],
            'enable_sr_no'           => $enableSrNo,
            'weight'                 => $mapped['weight'],
            'rack'                   => $mapped['rack'],
            'row'                    => $mapped['row'],
            'position'               => $mapped['position'],
            'image'                  => $mapped['image'],
            'product_description'    => $mapped['product_description'],
            'custom_field1'          => $mapped['custom_field1'],
            'custom_field2'          => $mapped['custom_field2'],
            'custom_field3'          => $mapped['custom_field3'],
            'custom_field4'          => $mapped['custom_field4'],
            'not_for_selling'        => $notForSelling,
            'product_locations'      => $mapped['product_locations'],
        ];

        return [
            'sku'                       => $mapped['sku'],
            'product_payload'           => $productPayload,
            'opening_stock_entries'     => $manageStock ? $variationSetup['opening_stock_entries'] : [],
            'opening_stock_location_id' => $openingStockLocationId,
            'audit_record'              => $auditRecord,
        ];
    }

    /**
     * Build pricing, variations, and opening stock entries for single or variable products.
     *
     * @param  array<string, mixed>  $mapped
     * @return array{
     *     payload: array<string, mixed>,
     *     opening_stock_entries: array<int, array{variation_name: string, quantity: float}>
     * }
     */
    private function buildVariationAndPricingPayload(
        array $mapped,
        string $productType,
        int $businessId,
        int $rowNumber,
    ): array {
        if ($productType === 'single') {
            $inc = $this->parseSingleNumeric($mapped['purchase_price_inc_tax'], 'Purchase Price (Including Tax)', $rowNumber);
            $exc = $this->parseSingleNumeric($mapped['purchase_price_exc_tax'], 'Purchase Price (Excluding Tax)', $rowNumber);
            $sell = $this->parseSingleNumeric($mapped['selling_price'], 'Selling Price', $rowNumber);
            $qty = $this->parseSingleNumeric($mapped['opening_stock'], 'Opening Stock', $rowNumber);

            $payload = [];
            if ($exc !== null) {
                $payload['default_purchase_price'] = $exc;
            }
            if ($inc !== null) {
                $payload['dpp_inc_tax'] = $inc;
            }
            if ($sell !== null) {
                if ($mapped['selling_price_tax_type'] === 'inclusive') {
                    $payload['sell_price_inc_tax'] = $sell;
                } else {
                    $payload['default_sell_price'] = $sell;
                }
            }

            $openingEntries = [];
            if ($qty !== null && $qty > 0) {
                $openingEntries[] = [
                    'variation_name' => 'DUMMY',
                    'quantity'       => $qty,
                ];
            }

            return [
                'payload'               => $payload,
                'opening_stock_entries' => $openingEntries,
            ];
        }

        // Variable product
        $values = $this->splitPipeValues((string) $mapped['variation_values']);
        if ($values === []) {
            $this->failRow($rowNumber, 'Variation Values must contain at least one value.');
        }

        $template = VariationTemplate::firstOrCreate(
            ['business_id' => $businessId, 'name' => trim((string) $mapped['variation_name'])],
        );

        $skus = $mapped['variation_skus'] !== null ? $this->splitPipeValues((string) $mapped['variation_skus'], false) : [];
        $incList = $mapped['purchase_price_inc_tax'] !== null ? $this->splitPipeValues((string) $mapped['purchase_price_inc_tax'], false) : [];
        $excList = $mapped['purchase_price_exc_tax'] !== null ? $this->splitPipeValues((string) $mapped['purchase_price_exc_tax'], false) : [];
        $sellList = $mapped['selling_price'] !== null ? $this->splitPipeValues((string) $mapped['selling_price'], false) : [];
        $stockList = $mapped['opening_stock'] !== null ? $this->splitPipeValues((string) $mapped['opening_stock'], false) : [];

        $variations = [];
        $openingEntries = [];

        foreach ($values as $idx => $valName) {
            $valueTemplate = VariationValueTemplate::firstOrCreate([
                'variation_template_id' => $template->id,
                'name'                  => $valName,
            ]);

            $incVal = $this->pickVariationNumeric($incList, $idx, 'Purchase Price (Including Tax)', $rowNumber);
            $excVal = $this->pickVariationNumeric($excList, $idx, 'Purchase Price (Excluding Tax)', $rowNumber);

            if ($incVal === null && $excVal === null) {
                $this->failRow($rowNumber, "Missing purchase price for variation '{$valName}'.");
            }

            $sellVal = $this->pickVariationNumeric($sellList, $idx, 'Selling Price', $rowNumber);
            $qtyVal = $this->pickVariationNumeric($stockList, $idx, 'Opening Stock', $rowNumber);

            $rowPayload = [
                'name'                  => $valName,
                'sku'                   => isset($skus[$idx]) && $skus[$idx] !== '' ? $skus[$idx] : null,
                'variation_template_id' => $template->id,
                'variation_value_id'    => $valueTemplate->id,
            ];

            if ($excVal !== null) {
                $rowPayload['default_purchase_price'] = $excVal;
            }
            if ($incVal !== null) {
                $rowPayload['dpp_inc_tax'] = $incVal;
            }
            if ($mapped['profit_margin'] !== null) {
                $rowPayload['profit_percent'] = (float) $mapped['profit_margin'];
            }
            if ($sellVal !== null) {
                if ($mapped['selling_price_tax_type'] === 'inclusive') {
                    $rowPayload['sell_price_inc_tax'] = $sellVal;
                } else {
                    $rowPayload['default_sell_price'] = $sellVal;
                }
            }

            $variations[] = $rowPayload;

            if ($qtyVal !== null && $qtyVal > 0) {
                $openingEntries[] = [
                    'variation_name' => $valName,
                    'quantity'       => $qtyVal,
                ];
            }
        }

        return [
            'payload'               => ['variations' => $variations],
            'opening_stock_entries' => $openingEntries,
        ];
    }

    /**
     * Apply imported opening stock specifically to the resolved opening stock location.
     *
     * @param  array<int, array{variation_name: string, quantity: float}>  $entries
     */
    private function applyImportedOpeningStock(
        Product $product,
        int $businessId,
        int $userId,
        array $entries,
        ?int $openingStockLocationId,
    ): void {
        if ($openingStockLocationId === null) {
            return;
        }

        $product->load('variations');
        $stockRows = [];

        foreach ($entries as $entry) {
            $targetName = mb_strtolower(trim($entry['variation_name']));
            $variation = $product->variations->first(
                fn (Variation $v): bool => mb_strtolower(trim((string) $v->name)) === $targetName
            );

            if (! $variation) {
                continue;
            }

            $stockRows[] = [
                'variation_id'         => $variation->id,
                'location_id'          => $openingStockLocationId,
                'quantity'             => round($entry['quantity'], 4),
                'unit_cost_before_tax' => (float) $variation->default_purchase_price,
            ];
        }

        if ($stockRows !== []) {
            $this->productService->saveOpeningStock($product, $businessId, $userId, $stockRows);
        }
    }

    private function resolveUnit(int $businessId, string $unitName, int $rowNumber): Unit
    {
        $needle = mb_strtolower(trim($unitName));
        $unit = Unit::where('business_id', $businessId)
            ->where(function ($q) use ($needle): void {
                $q->whereRaw('LOWER(TRIM(actual_name)) = ?', [$needle])
                    ->orWhereRaw('LOWER(TRIM(short_name)) = ?', [$needle]);
            })
            ->first();

        if (! $unit) {
            $this->failRow($rowNumber, "Unit '{$unitName}' not found.");
        }

        return $unit;
    }

    private function resolveOrCreateBrand(int $businessId, string $brandName): int
    {
        $trimmed = trim($brandName);
        $existing = Brand::where('business_id', $businessId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($trimmed)])
            ->first();

        if ($existing) {
            return $existing->id;
        }

        return Brand::create([
            'business_id' => $businessId,
            'name'        => $trimmed,
        ])->id;
    }

    private function resolveOrCreateCategory(int $businessId, string $name, ?int $parentId): int
    {
        $trimmed = trim($name);
        $query = Category::where('business_id', $businessId)
            ->where('category_type', 'product')
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($trimmed)]);

        if ($parentId === null) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $parentId);
        }

        $existing = $query->first();
        if ($existing) {
            return $existing->id;
        }

        return Category::create([
            'business_id'   => $businessId,
            'name'          => $trimmed,
            'parent_id'     => $parentId,
            'category_type' => 'product',
        ])->id;
    }

    private function resolveTaxRate(int $businessId, string $taxName, int $rowNumber): int
    {
        $needle = mb_strtolower(trim($taxName));
        $tax = TaxRate::where('business_id', $businessId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [$needle])
            ->first();

        if (! $tax) {
            $this->failRow($rowNumber, "Applicable Tax '{$taxName}' not found.");
        }

        return $tax->id;
    }

    /**
     * @return array<int, int>
     */
    private function resolveProductLocations(int $businessId, ?string $rawLocations, int $rowNumber): array
    {
        if ($rawLocations === null || trim($rawLocations) === '') {
            $firstId = Location::where('business_id', $businessId)->orderBy('id')->value('id');

            return $firstId ? [(int) $firstId] : [];
        }

        $names = array_values(array_filter(array_map('trim', explode(',', $rawLocations))));
        $locationIds = [];

        foreach ($names as $name) {
            $location = Location::where('business_id', $businessId)
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])
                ->first();

            if (! $location) {
                $this->failRow($rowNumber, "Product location '{$name}' not found.");
            }

            $locationIds[] = $location->id;
        }

        return array_values(array_unique($locationIds));
    }

    /**
     * @param  array<int, int>  $productLocationIds
     */
    private function resolveOpeningStockLocation(
        int $businessId,
        ?string $rawLocation,
        array $productLocationIds,
        int $rowNumber,
    ): ?int {
        if ($rawLocation === null || trim($rawLocation) === '') {
            if ($productLocationIds !== []) {
                return $productLocationIds[0];
            }

            $firstId = Location::where('business_id', $businessId)->orderBy('id')->value('id');

            return $firstId ? (int) $firstId : null;
        }

        $location = Location::where('business_id', $businessId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($rawLocation))])
            ->first();

        if (! $location) {
            $this->failRow($rowNumber, "Opening stock location '{$rawLocation}' not found.");
        }

        return $location->id;
    }

    private function parseSingleNumeric(?string $value, string $label, int $rowNumber): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (! is_numeric($value) || (float) $value < 0) {
            $this->failRow($rowNumber, "{$label} must be a non-negative number.");
        }

        return (float) $value;
    }

    /**
     * @param  array<int, string>  $list
     */
    private function pickVariationNumeric(array $list, int $index, string $label, int $rowNumber): ?float
    {
        if ($list === []) {
            return null;
        }

        $raw = $list[$index] ?? (count($list) === 1 ? $list[0] : null);
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        if (! is_numeric($raw) || (float) $raw < 0) {
            $this->failRow($rowNumber, "{$label} must contain non-negative numbers.");
        }

        return (float) $raw;
    }

    /**
     * @return array<int, string>
     */
    private function splitPipeValues(string $raw, bool $filterEmpty = true): array
    {
        $parts = array_map('trim', explode('|', $raw));

        if ($filterEmpty) {
            return array_values(array_filter($parts, fn (string $v): bool => $v !== ''));
        }

        return $parts;
    }

    private function isValidExpiryDate(string $value): bool
    {
        $dt = \DateTimeImmutable::createFromFormat('m-d-Y', $value);
        if ($dt !== false && $dt->format('m-d-Y') === $value) {
            return true;
        }

        $iso = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $iso !== false && $iso->format('Y-m-d') === $value;
    }

    private function failRow(int $rowNumber, string $message): never
    {
        throw ValidationException::withMessages([
            'file' => ["Row {$rowNumber}: {$message}"],
        ]);
    }
}
