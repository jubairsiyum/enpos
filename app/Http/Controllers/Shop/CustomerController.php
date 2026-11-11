<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\StoreCustomerRequest;
use App\Http\Requests\Shop\UpdateCustomerRequest;
use App\Models\Contact;
use App\Models\CustomerGroup;
use App\Models\User;
use App\Support\CurrentBusiness;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(private CurrentBusiness $currentBusiness) {}

    /**
     * Display a listing of customers with filters, pagination, and totals.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $businessId = $this->currentBusiness->id();

        $query = $this->buildFilteredQuery($request);

        $totalsRow = (clone $query)
            ->reorder()
            ->selectRaw('COALESCE(SUM(total_sell - total_sell_paid), 0) as total_sell_due')
            ->selectRaw('COALESCE(SUM(total_sell_return - total_sell_return_paid), 0) as total_sell_return_due')
            ->first();

        $perPage = max(1, min(200, (int) $request->input('per_page', 25)));
        $customers = $query
            ->with(['customerGroup:id,name,amount', 'assignedUsers:id,name', 'contactPersons'])
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        $transformed = $customers->through(fn (Contact $c): array => $this->transformCustomer($c));

        $totals = [
            'total_sell_due'        => (float) ($totalsRow?->total_sell_due ?? 0),
            'total_sell_return_due' => (float) ($totalsRow?->total_sell_return_due ?? 0),
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'data'   => $transformed->items(),
                'meta'   => [
                    'current_page' => $customers->currentPage(),
                    'last_page'    => $customers->lastPage(),
                    'per_page'     => $customers->perPage(),
                    'total'        => $customers->total(),
                ],
                'totals' => $totals,
            ]);
        }

        $customerGroups = CustomerGroup::where('business_id', $businessId)
            ->orderBy('name')
            ->get(['id', 'name', 'amount']);

        $users = User::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Shop/Contacts/Customers/Index', [
            'customers'      => $transformed,
            'totals'         => $totals,
            'customerGroups' => $customerGroups,
            'users'          => $users,
            'filters'        => $request->only([
                'search',
                'per_page',
                'customer_group_id',
                'assigned_to',
                'has_sell_due',
                'has_sell_return_due',
                'has_advance_balance',
                'has_opening_balance',
                'no_sell_since',
                'only_trashed',
            ]),
            'can'            => [
                'create' => true,
                'edit'   => true,
                'delete' => true,
            ],
        ]);
    }

    /**
     * Store a newly created customer along with assigned users and contact persons.
     */
    public function store(StoreCustomerRequest $request): JsonResponse|RedirectResponse
    {
        $businessId = $this->currentBusiness->id();
        $validated = $request->validated();

        $customer = DB::transaction(function () use ($validated, $businessId, $request): Contact {
            $contactData = Arr::except($validated, ['assigned_to', 'contact_persons']);
            $contactData['business_id'] = $businessId;
            $contactData['created_by'] = $request->user()?->id;
            $contactData['type'] = $contactData['type'] ?? 'customer';
            $contactData['entity_type'] = $contactData['entity_type'] ?? 'individual';
            $contactData['opening_balance'] = $contactData['opening_balance'] ?? 0;

            if (empty($contactData['contact_id'])) {
                $contactData['contact_id'] = Contact::generateContactId($businessId, 'CO');
            }

            $contactData['name'] = $this->resolveDisplayName($contactData);

            $contact = Contact::create($contactData);

            if (array_key_exists('assigned_to', $validated)) {
                $contact->assignedUsers()->sync($validated['assigned_to'] ?? []);
            }

            if (! empty($validated['contact_persons'])) {
                $this->createContactPersons($contact, $businessId, $validated['contact_persons']);
            }

            return $contact->fresh(['customerGroup', 'assignedUsers', 'contactPersons']);
        });

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Customer created successfully.',
                'data'    => $this->transformCustomer($customer),
            ], 201);
        }

        return redirect()->route('shop.customers.index')
            ->with('success', 'Customer created successfully.');
    }

    /**
     * Display the specified customer.
     */
    public function show(Request $request, Contact $customer): JsonResponse|Response
    {
        $this->authorizeCustomer($customer);

        $customer->load(['customerGroup', 'assignedUsers', 'contactPersons']);

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $this->transformCustomer($customer),
            ]);
        }

        return Inertia::render('Shop/Contacts/Customers/Index', [
            'customer' => $this->transformCustomer($customer),
        ]);
    }

    /**
     * Update the specified customer.
     */
    public function update(UpdateCustomerRequest $request, Contact $customer): JsonResponse|RedirectResponse
    {
        $this->authorizeCustomer($customer);

        $businessId = $this->currentBusiness->id();
        $validated = $request->validated();

        $updated = DB::transaction(function () use ($validated, $customer, $businessId): Contact {
            $contactData = Arr::except($validated, ['assigned_to', 'contact_persons']);

            if (
                empty($contactData['name']) &&
                (
                    array_key_exists('first_name', $contactData) ||
                    array_key_exists('last_name', $contactData) ||
                    array_key_exists('prefix', $contactData) ||
                    array_key_exists('supplier_business_name', $contactData)
                )
            ) {
                $merged = array_merge($customer->toArray(), $contactData);
                unset($merged['name']);
                $contactData['name'] = $this->resolveDisplayName($merged);
            }

            $customer->update($contactData);

            if (array_key_exists('assigned_to', $validated)) {
                $customer->assignedUsers()->sync($validated['assigned_to'] ?? []);
            }

            if (array_key_exists('contact_persons', $validated)) {
                $this->syncContactPersons($customer, $businessId, $validated['contact_persons'] ?? []);
            }

            return $customer->fresh(['customerGroup', 'assignedUsers', 'contactPersons']);
        });

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Customer updated successfully.',
                'data'    => $this->transformCustomer($updated),
            ]);
        }

        return redirect()->route('shop.customers.index')
            ->with('success', 'Customer updated successfully.');
    }

    /**
     * Soft-delete the specified customer.
     */
    public function destroy(Request $request, Contact $customer): JsonResponse|RedirectResponse
    {
        $this->authorizeCustomer($customer);

        if ($customer->is_default) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Default Walk-In Customer cannot be deleted.',
                ], 422);
            }

            return redirect()->route('shop.customers.index')
                ->with('error', 'Default Walk-In Customer cannot be deleted.');
        }

        $customer->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Customer deleted successfully.']);
        }

        return redirect()->route('shop.customers.index')
            ->with('success', 'Customer deleted successfully.');
    }

    /**
     * Restore a soft-deleted customer.
     */
    public function restore(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        $businessId = $this->currentBusiness->id();

        $customer = Contact::onlyTrashed()
            ->customers()
            ->where('business_id', $businessId)
            ->findOrFail($id);

        $customer->restore();
        $customer->load(['customerGroup', 'assignedUsers', 'contactPersons']);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Customer restored successfully.',
                'data'    => $this->transformCustomer($customer),
            ]);
        }

        return redirect()->route('shop.customers.index')
            ->with('success', 'Customer restored successfully.');
    }

    /**
     * Permanently delete a customer.
     */
    public function forceDelete(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        $businessId = $this->currentBusiness->id();

        $customer = Contact::withTrashed()
            ->customers()
            ->where('business_id', $businessId)
            ->findOrFail($id);

        $customer->forceDelete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Customer permanently deleted.']);
        }

        return redirect()->route('shop.customers.index')
            ->with('success', 'Customer permanently deleted.');
    }

    /*
    |--------------------------------------------------------------------------
    | Private helpers
    |--------------------------------------------------------------------------
    */

    private function buildFilteredQuery(Request $request): Builder
    {
        return Contact::query()
            ->customers()
            ->when($request->boolean('only_trashed'), function (Builder $q): void {
                $q->onlyTrashed();
            })
            ->when(! $request->boolean('only_trashed') && $request->boolean('with_trashed'), function (Builder $q): void {
                $q->withTrashed();
            })
            ->when($request->input('search'), function (Builder $q, string $search): void {
                $q->where(function (Builder $sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('contact_id', 'like', "%{$search}%")
                        ->orWhere('supplier_business_name', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('tax_number', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('customer_group_id'), function (Builder $q) use ($request): void {
                $q->where('customer_group_id', $request->input('customer_group_id'));
            })
            ->when($request->filled('assigned_to'), function (Builder $q) use ($request): void {
                $assignedId = $request->input('assigned_to');
                $q->whereHas('assignedUsers', fn (Builder $u) => $u->where('users.id', $assignedId));
            })
            ->when($request->boolean('has_sell_due'), function (Builder $q): void {
                $q->whereRaw('(total_sell - total_sell_paid) > 0');
            })
            ->when($request->boolean('has_sell_return_due'), function (Builder $q): void {
                $q->whereRaw('(total_sell_return - total_sell_return_paid) > 0');
            })
            ->when($request->boolean('has_advance_balance'), function (Builder $q): void {
                $q->where('balance', '>', 0);
            })
            ->when($request->boolean('has_opening_balance'), function (Builder $q): void {
                $q->where('opening_balance', '>', 0);
            })
            ->when($request->input('no_sell_since'), function (Builder $q, string $period): void {
                $cutoff = match ($period) {
                    '1_month'  => Carbon::now()->subMonth(),
                    '3_months' => Carbon::now()->subMonths(3),
                    '6_months' => Carbon::now()->subMonths(6),
                    '1_year'   => Carbon::now()->subYear(),
                    default    => null,
                };

                if ($cutoff !== null) {
                    $q->whereNotExists(function ($sub) use ($cutoff): void {
                        $sub->select(DB::raw(1))
                            ->from('transactions')
                            ->whereColumn('transactions.contact_id', 'contacts.id')
                            ->where('transactions.type', 'sell')
                            ->where('transactions.transaction_date', '>=', $cutoff->toDateString())
                            ->whereNull('transactions.deleted_at');
                    });
                }
            });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveDisplayName(array $data): string
    {
        if (! empty($data['name'])) {
            return trim((string) $data['name']);
        }

        $personName = trim(implode(' ', array_filter([
            $data['prefix'] ?? null,
            $data['first_name'] ?? null,
            $data['middle_name'] ?? null,
            $data['last_name'] ?? null,
        ])));

        if ($personName !== '') {
            return $personName;
        }

        if (! empty($data['supplier_business_name'])) {
            return trim((string) $data['supplier_business_name']);
        }

        return (string) ($data['mobile'] ?? 'Customer');
    }

    /**
     * @param  array<int, array<string, mixed>>  $persons
     */
    private function createContactPersons(Contact $contact, int $businessId, array $persons): void
    {
        foreach ($persons as $person) {
            $contact->contactPersons()->create([
                'business_id'                 => $businessId,
                'prefix'                      => $person['prefix'] ?? null,
                'first_name'                  => $person['first_name'],
                'last_name'                   => $person['last_name'] ?? null,
                'email'                       => $person['email'] ?? null,
                'mobile'                      => $person['mobile'] ?? null,
                'alternate_number'            => $person['alternate_number'] ?? null,
                'family_number'               => $person['family_number'] ?? null,
                'department'                  => $person['department'] ?? null,
                'designation'                 => $person['designation'] ?? null,
                'sales_commission_percentage' => $person['sales_commission_percentage'] ?? null,
                'allow_login'                 => (bool) ($person['allow_login'] ?? false),
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $persons
     */
    private function syncContactPersons(Contact $contact, int $businessId, array $persons): void
    {
        $keepIds = [];

        foreach ($persons as $personData) {
            $attributes = [
                'business_id'                 => $businessId,
                'prefix'                      => $personData['prefix'] ?? null,
                'first_name'                  => $personData['first_name'],
                'last_name'                   => $personData['last_name'] ?? null,
                'email'                       => $personData['email'] ?? null,
                'mobile'                      => $personData['mobile'] ?? null,
                'alternate_number'            => $personData['alternate_number'] ?? null,
                'family_number'               => $personData['family_number'] ?? null,
                'department'                  => $personData['department'] ?? null,
                'designation'                 => $personData['designation'] ?? null,
                'sales_commission_percentage' => $personData['sales_commission_percentage'] ?? null,
                'allow_login'                 => (bool) ($personData['allow_login'] ?? false),
            ];

            if (! empty($personData['id'])) {
                $existing = $contact->contactPersons()->whereKey($personData['id'])->first();
                if ($existing) {
                    $existing->update($attributes);
                    $keepIds[] = $existing->id;
                    continue;
                }
            }

            $created = $contact->contactPersons()->create($attributes);
            $keepIds[] = $created->id;
        }

        $contact->contactPersons()->whereNotIn('id', $keepIds)->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function transformCustomer(Contact $contact): array
    {
        $payTermDisplay = $contact->pay_term_number
            ? trim($contact->pay_term_number . ' ' . ucfirst($contact->pay_term_type ?? 'days'))
            : '';

        return [
            'id'                     => $contact->id,
            'contact_id'             => $contact->contact_id ?: ('CO' . str_pad((string) $contact->id, 4, '0', STR_PAD_LEFT)),
            'type'                   => $contact->type,
            'entity_type'            => $contact->entity_type ?? 'individual',
            'contact_status'         => $contact->contact_status ?: 'active',
            'supplier_business_name' => $contact->supplier_business_name,
            'name'                   => $contact->name,
            'prefix'                 => $contact->prefix,
            'first_name'             => $contact->first_name,
            'middle_name'            => $contact->middle_name,
            'last_name'              => $contact->last_name,
            'dob'                    => $contact->dob,
            'email'                  => $contact->email,
            'mobile'                 => $contact->mobile,
            'landline'               => $contact->landline,
            'alternate_number'       => $contact->alternate_number,
            'tax_number'             => $contact->tax_number,
            'credit_limit'           => $contact->credit_limit !== null ? (float) $contact->credit_limit : null,
            'pay_term_number'        => $contact->pay_term_number,
            'pay_term_type'          => $contact->pay_term_type,
            'pay_term'               => $payTermDisplay,
            'opening_balance'        => (float) ($contact->opening_balance ?? 0),
            'advance_balance'        => (float) ($contact->balance ?? 0),
            'created_at'             => $contact->created_at?->format('d/m/Y'),
            'customer_group_id'      => $contact->customer_group_id,
            'customer_group_name'    => $contact->customerGroup?->name,
            'address'                => implode(', ', array_filter([
                $contact->address_line_1,
                $contact->address_line_2,
                $contact->city,
                $contact->state,
                $contact->country,
                $contact->zip_code,
            ])),
            'address_line_1'         => $contact->address_line_1,
            'address_line_2'         => $contact->address_line_2,
            'city'                   => $contact->city,
            'state'                  => $contact->state,
            'country'                => $contact->country,
            'zip_code'               => $contact->zip_code,
            'landmark'               => $contact->landmark,
            'street_name'            => $contact->street_name,
            'building_number'        => $contact->building_number,
            'additional_number'      => $contact->additional_number,
            'shipping_address'       => $contact->shipping_address,
            'total_sell_due'         => (float) ($contact->total_sell - $contact->total_sell_paid),
            'total_sell_return_due'  => (float) ($contact->total_sell_return - $contact->total_sell_return_paid),
            'custom_field1'          => $contact->custom_field1,
            'custom_field2'          => $contact->custom_field2,
            'custom_field3'          => $contact->custom_field3,
            'custom_field4'          => $contact->custom_field4,
            'custom_field5'          => $contact->custom_field5,
            'custom_field6'          => $contact->custom_field6,
            'custom_field7'          => $contact->custom_field7,
            'custom_field8'          => $contact->custom_field8,
            'custom_field9'          => $contact->custom_field9,
            'custom_field10'         => $contact->custom_field10,
            'assigned_to'            => $contact->assignedUsers->pluck('id')->values()->all(),
            'assigned_users'         => $contact->assignedUsers->map(fn (User $u): array => [
                'id'   => $u->id,
                'name' => $u->name,
            ])->values()->all(),
            'contact_persons'        => $contact->contactPersons->map(fn ($p): array => [
                'id'                          => $p->id,
                'prefix'                      => $p->prefix,
                'first_name'                  => $p->first_name,
                'last_name'                   => $p->last_name,
                'email'                       => $p->email,
                'mobile'                      => $p->mobile,
                'alternate_number'            => $p->alternate_number,
                'family_number'               => $p->family_number,
                'department'                  => $p->department,
                'designation'                 => $p->designation,
                'sales_commission_percentage' => $p->sales_commission_percentage !== null ? (float) $p->sales_commission_percentage : null,
                'allow_login'                 => (bool) $p->allow_login,
            ])->values()->all(),
            'is_default'             => (bool) $contact->is_default,
            'deleted_at'             => $contact->deleted_at?->toISOString(),
        ];
    }

    private function authorizeCustomer(Contact $customer): void
    {
        $isSameBusiness = (int) $customer->business_id === (int) $this->currentBusiness->id();
        $isCustomerType = in_array($customer->type, ['customer', 'both'], true);

        if (! $isSameBusiness || ! $isCustomerType) {
            abort(404, 'Customer not found for this business.');
        }
    }
}
