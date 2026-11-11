<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ImportContact;
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

class ImportContactController extends Controller
{
    public function __construct(
        private CurrentBusiness $currentBusiness,
        private ExcelWorkbookCodec $excelCodec,
    ) {}

    /**
     * Display the import contacts instructions/upload page or list imported records.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $businessId = $this->currentBusiness->id();

        if ($request->wantsJson()) {
            $perPage = max(1, min(200, (int) $request->input('per_page', 25)));
            $imports = ImportContact::query()
                ->where('business_id', $businessId)
                ->with('contact:id,contact_id,name,type')
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

        return Inertia::render('Shop/Contacts/ImportContacts/Index', [
            'columns' => ImportContact::TEMPLATE_COLUMNS,
        ]);
    }

    /**
     * Download the import contacts template in genuine binary Excel (.xls) format.
     */
    public function downloadTemplate(): StreamedResponse
    {
        $headers = array_map(fn (array $col): string => $col['name'], ImportContact::TEMPLATE_COLUMNS);
        $binary = $this->excelCodec->buildXls([$headers]);

        return response()->streamDownload(function () use ($binary): void {
            echo $binary;
        }, 'import_contacts_template.xls', [
            'Content-Type'   => 'application/vnd.ms-excel',
            'Content-Length' => (string) strlen($binary),
            'Cache-Control'  => 'max-age=0',
        ]);
    }

    /**
     * Validate and import contacts from an uploaded .xls, .xlsx, or .csv file.
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
                'file' => ['No contact rows were found in the uploaded file.'],
            ]);
        }

        $businessId = $this->currentBusiness->id();
        $userId = $request->user()?->id;

        $imported = DB::transaction(function () use ($rows, $businessId, $userId): array {
            $created = [];
            $seenContactIds = [];
            $seenMobiles = [];
            $seenEmails = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // 1-based accounting for header row
                $mapped = $this->mapRowToFields($row);
                $validated = $this->validateRow($mapped, $rowNumber, $businessId, $seenContactIds, $seenMobiles, $seenEmails);

                $contactId = $validated['contact_id'] ?: Contact::generateContactId($businessId, 'CO');
                $seenContactIds[] = $contactId;
                $seenMobiles[] = $validated['mobile'];
                if (! empty($validated['email'])) {
                    $seenEmails[] = mb_strtolower((string) $validated['email']);
                }

                $type = ImportContact::resolveTypeName((int) $validated['contact_type']);
                $fullName = trim(implode(' ', array_filter([
                    $validated['prefix'] ?? null,
                    $validated['first_name'],
                    $validated['middle_name'] ?? null,
                    $validated['last_name'] ?? null,
                ])));

                $contact = Contact::create([
                    'business_id'            => $businessId,
                    'created_by'             => $userId,
                    'type'                   => $type,
                    'contact_id'             => $contactId,
                    'entity_type'            => (! empty($validated['business_name']) && in_array((int) $validated['contact_type'], [2, 3], true))
                        ? 'business'
                        : 'individual',
                    'name'                   => $fullName !== '' ? $fullName : ($validated['business_name'] ?? $validated['mobile']),
                    'supplier_business_name' => $validated['business_name'] ?? null,
                    'prefix'                 => $validated['prefix'] ?? null,
                    'first_name'             => $validated['first_name'],
                    'middle_name'            => $validated['middle_name'] ?? null,
                    'last_name'              => $validated['last_name'] ?? null,
                    'tax_number'             => $validated['tax_number'] ?? null,
                    'opening_balance'        => $validated['opening_balance'] ?? 0,
                    'pay_term_number'        => $validated['pay_term'] ?? null,
                    'pay_term_type'          => $validated['pay_term_period'] ?? null,
                    'credit_limit'           => $validated['credit_limit'] ?? null,
                    'email'                  => $validated['email'] ?? null,
                    'mobile'                 => $validated['mobile'],
                    'alternate_number'       => $validated['alternate_number'] ?? null,
                    'landline'               => $validated['landline'] ?? null,
                    'city'                   => $validated['city'] ?? null,
                    'state'                  => $validated['state'] ?? null,
                    'country'                => $validated['country'] ?? null,
                    'address_line_1'         => $validated['address_line_1'] ?? null,
                    'address_line_2'         => $validated['address_line_2'] ?? null,
                    'zip_code'               => $validated['zip_code'] ?? null,
                    'dob'                    => $validated['dob'] ?? null,
                    'custom_field1'          => $validated['custom_field1'] ?? null,
                    'custom_field2'          => $validated['custom_field2'] ?? null,
                    'custom_field3'          => $validated['custom_field3'] ?? null,
                    'custom_field4'          => $validated['custom_field4'] ?? null,
                ]);

                $created[] = ImportContact::create(array_merge($validated, [
                    'business_id'       => $businessId,
                    'imported_by'       => $userId,
                    'contact_record_id' => $contact->id,
                    'contact_id'        => $contactId,
                ]));
            }

            return $created;
        });

        $count = count($imported);

        if ($request->wantsJson()) {
            return response()->json([
                'message'        => "{$count} contact(s) imported successfully.",
                'imported_count' => $count,
                'data'           => $imported,
            ], 201);
        }

        return redirect()->route('shop.import-contacts.index')
            ->with('success', "{$count} contact(s) imported successfully.");
    }

    /*
    |--------------------------------------------------------------------------
    | Private helpers
    |--------------------------------------------------------------------------
    */

    /**
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
        if ($firstCell === 'contact type') {
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
     * @param  array<int, string|null>  $row
     * @return array<string, mixed>
     */
    private function mapRowToFields(array $row): array
    {
        $mapped = [];
        foreach (ImportContact::TEMPLATE_COLUMNS as $idx => $col) {
            $value = $row[$idx] ?? null;
            $trimmed = $value !== null ? trim((string) $value) : null;
            $mapped[$col['key']] = $trimmed === '' ? null : $trimmed;
        }

        if ($mapped['pay_term_period'] !== null) {
            $mapped['pay_term_period'] = strtolower((string) $mapped['pay_term_period']);
        }
        if ($mapped['email'] !== null) {
            $mapped['email'] = mb_strtolower((string) $mapped['email']);
        }

        // Microsoft Excel stores unquoted dates as serial numbers (days since 1899-12-30)
        if ($mapped['dob'] !== null && preg_match('/^\d+(\.\d+)?$/', (string) $mapped['dob'])) {
            $serial = (float) $mapped['dob'];
            if ($serial >= 1 && $serial < 100000) {
                $mapped['dob'] = gmdate('Y-m-d', (int) round(($serial - 25569) * 86400));
            }
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<int, string>    $seenContactIds
     * @param  array<int, string>    $seenMobiles
     * @param  array<int, string>    $seenEmails
     * @return array<string, mixed>
     */
    private function validateRow(
        array $mapped,
        int $rowNumber,
        int $businessId,
        array $seenContactIds,
        array $seenMobiles,
        array $seenEmails,
    ): array {
        $isSupplierOrBoth = in_array((int) ($mapped['contact_type'] ?? 0), [2, 3], true);

        $validator = Validator::make($mapped, [
            'contact_type'     => ['required', 'integer', Rule::in([1, 2, 3])],
            'prefix'           => ['nullable', 'string', 'max:20'],
            'first_name'       => ['required', 'string', 'max:255'],
            'middle_name'      => ['nullable', 'string', 'max:255'],
            'last_name'        => ['nullable', 'string', 'max:255'],
            'business_name'    => [Rule::requiredIf($isSupplierOrBoth), 'nullable', 'string', 'max:255'],
            'contact_id'       => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('contacts', 'contact_id')->where('business_id', $businessId)->whereNull('deleted_at'),
                Rule::notIn($seenContactIds),
            ],
            'tax_number'       => ['nullable', 'string', 'max:100'],
            'opening_balance'  => ['nullable', 'numeric', 'min:0'],
            'pay_term'         => [Rule::requiredIf($isSupplierOrBoth), 'nullable', 'integer', 'min:1', 'max:65535'],
            'pay_term_period'  => [Rule::requiredIf($isSupplierOrBoth), 'nullable', Rule::in(['days', 'months'])],
            'credit_limit'     => ['nullable', 'numeric', 'min:0'],
            'email'            => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('contacts', 'email')->where('business_id', $businessId)->whereNull('deleted_at'),
                Rule::notIn($seenEmails),
            ],
            'mobile'           => [
                'required',
                'string',
                'max:20',
                Rule::unique('contacts', 'mobile')->where('business_id', $businessId)->whereNull('deleted_at'),
                Rule::notIn($seenMobiles),
            ],
            'alternate_number' => ['nullable', 'string', 'max:20'],
            'landline'         => ['nullable', 'string', 'max:20'],
            'city'             => ['nullable', 'string', 'max:100'],
            'state'            => ['nullable', 'string', 'max:100'],
            'country'          => ['nullable', 'string', 'max:100'],
            'address_line_1'   => ['nullable', 'string', 'max:500'],
            'address_line_2'   => ['nullable', 'string', 'max:500'],
            'zip_code'         => ['nullable', 'string', 'max:20'],
            'dob'              => ['nullable', 'date_format:Y-m-d'],
            'custom_field1'    => ['nullable', 'string'],
            'custom_field2'    => ['nullable', 'string'],
            'custom_field3'    => ['nullable', 'string'],
            'custom_field4'    => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            $firstError = $validator->errors()->first();
            throw ValidationException::withMessages([
                'file' => ["Row {$rowNumber}: {$firstError}"],
            ]);
        }

        return $validator->validated();
    }
}
