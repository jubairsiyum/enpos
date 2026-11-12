<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\StoreVariationTemplateRequest;
use App\Http\Requests\Shop\UpdateVariationTemplateRequest;
use App\Models\Variation;
use App\Models\VariationTemplate;
use App\Models\VariationValueTemplate;
use App\Support\CurrentBusiness;
use App\Support\ExcelWorkbookCodec;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VariationController extends Controller
{
    public function __construct(
        private CurrentBusiness $currentBusiness,
        private ExcelWorkbookCodec $excelCodec,
    ) {}

    /**
     * Display a paginated listing of product variation templates and their values.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $businessId = (int) $this->currentBusiness->id();
        $perPage = max(1, min(200, (int) $request->input('per_page', 25)));
        $sortBy = in_array($request->input('sort_by'), ['id', 'name'], true)
            ? (string) $request->input('sort_by')
            : 'id';
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $variations = VariationTemplate::query()
            ->where('business_id', $businessId)
            ->with(['values' => fn ($q) => $q->orderBy('id')])
            ->withCount('variations')
            ->when($request->filled('search'), function ($q) use ($request): void {
                $search = trim((string) $request->input('search'));
                $q->where(function ($sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhereHas('values', fn ($vq) => $vq->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();

        $transformed = $variations->through(fn (VariationTemplate $template): array => $this->transformTemplate($template));

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $transformed->items(),
                'meta' => [
                    'current_page' => $variations->currentPage(),
                    'last_page'    => $variations->lastPage(),
                    'per_page'     => $variations->perPage(),
                    'total'        => $variations->total(),
                ],
            ]);
        }

        return Inertia::render('Shop/Products/Variations/Index', [
            'variations' => $transformed,
            'filters'    => $request->only(['search', 'per_page', 'sort_by', 'sort_dir']),
            'can'        => [
                'create' => $request->user()?->can('shop.products.create') ?? true,
                'edit'   => $request->user()?->can('shop.products.edit') ?? true,
                'delete' => $request->user()?->can('shop.products.delete') ?? true,
            ],
        ]);
    }

    /**
     * Export variations table to CSV or binary Excel (.xls).
     */
    public function export(Request $request): StreamedResponse
    {
        $businessId = (int) $this->currentBusiness->id();
        $templates = VariationTemplate::query()
            ->where('business_id', $businessId)
            ->with(['values' => fn ($q) => $q->orderBy('id')])
            ->orderBy('id')
            ->get();

        $rows = [['Variations', 'Values']];
        foreach ($templates as $template) {
            $rows[] = [
                $template->name,
                $template->values->pluck('name')->implode(', '),
            ];
        }

        $format = strtolower((string) $request->query('format', 'csv'));
        if ($format === 'xls') {
            $binary = $this->excelCodec->buildXls($rows, 'Variations');

            return response()->streamDownload(function () use ($binary): void {
                echo $binary;
            }, 'variations.xls', [
                'Content-Type'   => 'application/vnd.ms-excel',
                'Content-Length' => (string) strlen($binary),
                'Cache-Control'  => 'max-age=0',
            ]);
        }

        $csv = $this->excelCodec->buildCsv($rows);

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, 'variations.csv', [
            'Content-Type'   => 'text/csv; charset=UTF-8',
            'Content-Length' => (string) strlen($csv),
            'Cache-Control'  => 'max-age=0',
        ]);
    }

    /**
     * Store a newly created variation template with its values (Add Variation form).
     */
    public function store(StoreVariationTemplateRequest $request): JsonResponse|RedirectResponse
    {
        $businessId = (int) $this->currentBusiness->id();
        $validated = $request->validated();
        $name = trim((string) $validated['name']);

        $this->assertUniqueName($businessId, $name);
        $normalizedValues = $this->normalizeValueEntries($validated['values']);

        $template = DB::transaction(function () use ($businessId, $name, $normalizedValues): VariationTemplate {
            $template = VariationTemplate::create([
                'business_id' => $businessId,
                'name'        => $name,
            ]);

            foreach ($normalizedValues as $entry) {
                $template->values()->create(['name' => $entry['name']]);
            }

            return $template->load(['values' => fn ($q) => $q->orderBy('id')])->loadCount('variations');
        });

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Variation created successfully.',
                'data'    => $this->transformTemplate($template),
            ], 201);
        }

        return redirect()->route('shop.variations.index')
            ->with('success', 'Variation created successfully.');
    }

    /**
     * Display a single variation template with its values.
     */
    public function show(Request $request, int $variation): JsonResponse|Response
    {
        $template = $this->findTemplateOrFail($variation);
        $template->load(['values' => fn ($q) => $q->orderBy('id')])->loadCount('variations');

        if ($request->wantsJson()) {
            return response()->json(['data' => $this->transformTemplate($template)]);
        }

        return Inertia::render('Shop/Products/Variations/Index', [
            'variation' => $this->transformTemplate($template),
        ]);
    }

    /**
     * Update an existing variation template and sync its values.
     */
    public function update(UpdateVariationTemplateRequest $request, int $variation): JsonResponse|RedirectResponse
    {
        $template = $this->findTemplateOrFail($variation);
        $businessId = (int) $this->currentBusiness->id();
        $validated = $request->validated();

        if (isset($validated['name'])) {
            $name = trim((string) $validated['name']);
            $this->assertUniqueName($businessId, $name, $template->id);
        } else {
            $name = $template->name;
        }

        $entries = isset($validated['values'])
            ? $this->normalizeValueEntries($validated['values'])
            : null;

        DB::transaction(function () use ($template, $name, $entries): void {
            $template->update(['name' => $name]);

            if ($entries === null) {
                return;
            }

            $existingValues = $template->values()->get();
            $keepIds = [];

            foreach ($entries as $entry) {
                $matched = null;
                if ($entry['id'] !== null) {
                    $matched = $existingValues->firstWhere('id', $entry['id']);
                }
                if ($matched === null) {
                    $matched = $existingValues->first(
                        fn (VariationValueTemplate $v): bool => mb_strtolower(trim($v->name)) === mb_strtolower($entry['name'])
                    );
                }

                if ($matched !== null) {
                    if ($matched->name !== $entry['name']) {
                        $matched->update(['name' => $entry['name']]);
                    }
                    $keepIds[] = $matched->id;
                } else {
                    $created = $template->values()->create(['name' => $entry['name']]);
                    $keepIds[] = $created->id;
                }
            }

            $toDelete = $existingValues->whereNotIn('id', $keepIds);
            foreach ($toDelete as $removedValue) {
                if (Variation::where('variation_value_id', $removedValue->id)->exists()) {
                    throw ValidationException::withMessages([
                        'values' => ["Variation value '{$removedValue->name}' cannot be removed because it is used by existing products."],
                    ]);
                }
                $removedValue->delete();
            }
        });

        $template->load(['values' => fn ($q) => $q->orderBy('id')])->loadCount('variations');

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Variation updated successfully.',
                'data'    => $this->transformTemplate($template),
            ]);
        }

        return redirect()->route('shop.variations.index')
            ->with('success', 'Variation updated successfully.');
    }

    /**
     * Delete a variation template when it is not used by any product variation.
     */
    public function destroy(Request $request, int $variation): JsonResponse|RedirectResponse
    {
        $template = $this->findTemplateOrFail($variation);

        $valueIds = $template->values()->pluck('id')->all();
        $isInUse = Variation::query()
            ->where('variation_template_id', $template->id)
            ->when(! empty($valueIds), fn ($q) => $q->orWhereIn('variation_value_id', $valueIds))
            ->exists();

        if ($isInUse) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'This variation cannot be deleted because it is used by existing products.',
                ], 422);
            }

            return redirect()->back()
                ->with('error', 'This variation cannot be deleted because it is used by existing products.');
        }

        DB::transaction(function () use ($template): void {
            $template->values()->delete();
            $template->delete();
        });

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Variation deleted successfully.',
            ]);
        }

        return redirect()->route('shop.variations.index')
            ->with('success', 'Variation deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function transformTemplate(VariationTemplate $template): array
    {
        $values = $template->values->map(fn (VariationValueTemplate $v): array => [
            'id'   => $v->id,
            'name' => $v->name,
        ])->values()->all();

        $variationsCount = (int) ($template->variations_count ?? 0);

        return [
            'id'               => $template->id,
            'name'             => $template->name,
            'values'           => $values,
            'values_text'      => $template->values->pluck('name')->implode(', '),
            'variations_count' => $variationsCount,
            'can_delete'       => $variationsCount === 0,
            'created_at'       => $template->created_at?->toISOString(),
            'updated_at'       => $template->updated_at?->toISOString(),
        ];
    }

    private function assertUniqueName(int $businessId, string $name, ?int $ignoreId = null): void
    {
        $query = VariationTemplate::query()
            ->where('business_id', $businessId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)]);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'name' => ['A variation with this name already exists.'],
            ]);
        }
    }

    /**
     * @param  array<int, mixed>  $rawValues
     * @return array<int, array{id: ?int, name: string}>
     */
    private function normalizeValueEntries(array $rawValues): array
    {
        $normalized = [];
        $seen = [];

        foreach ($rawValues as $index => $item) {
            $id = null;
            if (is_array($item)) {
                $id = isset($item['id']) && is_numeric($item['id']) ? (int) $item['id'] : null;
                $name = trim((string) ($item['name'] ?? ''));
            } elseif (is_string($item) || is_numeric($item)) {
                $name = trim((string) $item);
            } else {
                $name = '';
            }

            if ($name === '') {
                throw ValidationException::withMessages([
                    "values.{$index}" => ['Variation value cannot be empty.'],
                ]);
            }

            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                throw ValidationException::withMessages([
                    "values.{$index}" => ["Duplicate variation value '{$name}' is not allowed."],
                ]);
            }

            $seen[$key] = true;
            $normalized[] = ['id' => $id, 'name' => $name];
        }

        if (empty($normalized)) {
            throw ValidationException::withMessages([
                'values' => ['At least one variation value is required.'],
            ]);
        }

        return $normalized;
    }

    private function findTemplateOrFail(int $id): VariationTemplate
    {
        return VariationTemplate::query()
            ->where('business_id', (int) $this->currentBusiness->id())
            ->findOrFail($id);
    }
}
