<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\SellingPriceGroup;
use App\Support\CurrentBusiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerGroupController extends Controller
{
    public function __construct(private CurrentBusiness $currentBusiness) {}

    /**
     * Display a listing of customer groups.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $businessId = $this->currentBusiness->id();
        $perPage = max(1, min(200, (int) $request->input('per_page', 25)));

        $groups = CustomerGroup::query()
            ->where('business_id', $businessId)
            ->with('sellingPriceGroup:id,name')
            ->when($request->input('search'), function ($q, string $search): void {
                $q->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $transformed = $groups->through(fn (CustomerGroup $g): array => $this->transformGroup($g));

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $transformed->items(),
                'meta' => [
                    'current_page' => $groups->currentPage(),
                    'last_page'    => $groups->lastPage(),
                    'per_page'     => $groups->perPage(),
                    'total'        => $groups->total(),
                ],
            ]);
        }

        $sellingPriceGroups = SellingPriceGroup::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Shop/Contacts/CustomerGroups/Index', [
            'customerGroups'     => $transformed,
            'sellingPriceGroups' => $sellingPriceGroups,
            'filters'            => $request->only(['search', 'per_page']),
            'can'                => [
                'create' => true,
                'edit'   => true,
                'delete' => true,
            ],
        ]);
    }

    /**
     * Store a newly created customer group.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $businessId = $this->currentBusiness->id();
        $payload = $this->validateAndNormalize($request, $businessId, false);

        if (! empty($payload['is_default'])) {
            CustomerGroup::where('business_id', $businessId)->update(['is_default' => false]);
        }

        $group = CustomerGroup::create(array_merge($payload, [
            'business_id' => $businessId,
        ]))->load('sellingPriceGroup:id,name');

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Customer group created successfully.',
                'data'    => $this->transformGroup($group),
            ], 201);
        }

        return redirect()->route('shop.customer-groups.index')
            ->with('success', 'Customer group created successfully.');
    }

    /**
     * Display the specified customer group.
     */
    public function show(Request $request, CustomerGroup $customerGroup): JsonResponse|Response
    {
        $this->authorizeGroup($customerGroup);
        $customerGroup->load('sellingPriceGroup:id,name');

        if ($request->wantsJson()) {
            return response()->json(['data' => $this->transformGroup($customerGroup)]);
        }

        return Inertia::render('Shop/Contacts/CustomerGroups/Index', [
            'customerGroup' => $this->transformGroup($customerGroup),
        ]);
    }

    /**
     * Update the specified customer group.
     */
    public function update(Request $request, CustomerGroup $customerGroup): JsonResponse|RedirectResponse
    {
        $this->authorizeGroup($customerGroup);
        $businessId = $this->currentBusiness->id();

        $payload = $this->validateAndNormalize($request, $businessId, true, $customerGroup);

        if (! empty($payload['is_default'])) {
            CustomerGroup::where('business_id', $businessId)
                ->whereKeyNot($customerGroup->id)
                ->update(['is_default' => false]);
        }

        $customerGroup->update($payload);
        $customerGroup->load('sellingPriceGroup:id,name');

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Customer group updated successfully.',
                'data'    => $this->transformGroup($customerGroup),
            ]);
        }

        return redirect()->route('shop.customer-groups.index')
            ->with('success', 'Customer group updated successfully.');
    }

    /**
     * Remove the specified customer group.
     */
    public function destroy(Request $request, CustomerGroup $customerGroup): JsonResponse|RedirectResponse
    {
        $this->authorizeGroup($customerGroup);

        $customerGroup->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Customer group deleted successfully.']);
        }

        return redirect()->route('shop.customer-groups.index')
            ->with('success', 'Customer group deleted successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Private helpers
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, mixed>
     */
    private function validateAndNormalize(
        Request $request,
        int $businessId,
        bool $isUpdate,
        ?CustomerGroup $existing = null
    ): array {
        if ($request->has('name') && is_string($request->input('name'))) {
            $request->merge(['name' => trim($request->input('name'))]);
        }

        $validated = $request->validate([
            'name'                   => [$isUpdate ? 'sometimes' : 'required', 'required', 'string', 'max:255'],
            'price_calculation_type' => ['nullable', Rule::in(['percentage', 'selling_price_group'])],
            'amount'                 => ['nullable', 'numeric', 'min:-100', 'max:999.99'],
            'selling_price_group_id' => [
                'nullable',
                'integer',
                Rule::exists('selling_price_groups', 'id')->where('business_id', $businessId),
            ],
            'is_default'             => ['nullable', 'boolean'],
        ]);

        if (isset($validated['name'])) {
            $duplicateQuery = CustomerGroup::where('business_id', $businessId)
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($validated['name'])]);

            if ($existing !== null) {
                $duplicateQuery->whereKeyNot($existing->id);
            }

            if ($duplicateQuery->exists()) {
                throw ValidationException::withMessages([
                    'name' => ['A customer group with this name already exists.'],
                ]);
            }
        }

        $calculationType = $validated['price_calculation_type']
            ?? $existing?->price_calculation_type
            ?? 'percentage';

        $validated['price_calculation_type'] = $calculationType;

        if ($calculationType === 'selling_price_group') {
            $validated['amount'] = null;
            if (! array_key_exists('selling_price_group_id', $validated)) {
                $validated['selling_price_group_id'] = $existing?->selling_price_group_id;
            }
            if (empty($validated['selling_price_group_id'])) {
                throw ValidationException::withMessages([
                    'selling_price_group_id' => ['Please select a selling price group when using selling price group calculation.'],
                ]);
            }
        } else {
            $validated['selling_price_group_id'] = null;
            if (! array_key_exists('amount', $validated) && ! $isUpdate) {
                $validated['amount'] = 0;
            }
        }

        if (! $isUpdate) {
            $validated['is_default'] = (bool) ($validated['is_default'] ?? false);
        }

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function transformGroup(CustomerGroup $group): array
    {
        return [
            'id'                       => $group->id,
            'name'                     => $group->name,
            'price_calculation_type'   => $group->price_calculation_type ?? 'percentage',
            'amount'                   => $group->amount !== null ? (string) $group->amount : null,
            'selling_price_group_id'   => $group->selling_price_group_id,
            'selling_price_group_name' => $group->sellingPriceGroup?->name,
            'is_default'               => (bool) $group->is_default,
            'created_at'               => $group->created_at?->format('d/m/Y'),
        ];
    }

    private function authorizeGroup(CustomerGroup $group): void
    {
        if ((int) $group->business_id !== (int) $this->currentBusiness->id()) {
            abort(404, 'Customer group not found for this business.');
        }
    }
}
