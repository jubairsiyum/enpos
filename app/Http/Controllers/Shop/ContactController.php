<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Support\CurrentBusiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function __construct(private CurrentBusiness $currentBusiness) {}

    /**
     * Display a listing of contacts filtered by type.
     * Supports ?type=supplier|customer|both query param.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $businessId = $this->currentBusiness->id();
        $type       = $request->input('type', 'supplier');

        $query = Contact::query()
            ->when($request->boolean('only_trashed'), function ($q) {
                $q->onlyTrashed();
            })
            ->when(! $request->boolean('only_trashed') && $request->boolean('with_trashed'), function ($q) {
                $q->withTrashed();
            })
            ->when($type !== 'all', function ($q) use ($type) {
                $q->where(function ($sub) use ($type) {
                    $sub->where('type', $type)
                        ->orWhere('type', 'both');
                });
            })
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('supplier_business_name', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest('id');

        $perPage  = (int) $request->input('per_page', 25);
        $contacts = $query->paginate($perPage)->withQueryString();

        $transform = fn (Contact $c) => [
            'id'                        => $c->id,
            'contact_id'                => $c->contact_id ?: ('CO' . str_pad((string) $c->id, 4, '0', STR_PAD_LEFT)),
            'name'                      => $c->name,
            'supplier_business_name'    => $c->supplier_business_name,
            'prefix'                    => $c->prefix,
            'first_name'                => $c->first_name,
            'last_name'                 => $c->last_name,
            'email'                     => $c->email,
            'mobile'                    => $c->mobile,
            'landline'                  => $c->landline,
            'alternate_number'          => $c->alternate_number,
            'type'                      => $c->type,
            'entity_type'               => $c->entity_type ?? 'individual',
            'contact_status'            => $c->contact_status ?: 'active',
            'tax_number'                => $c->tax_number,
            'credit_limit'              => $c->credit_limit !== null ? (float) $c->credit_limit : null,
            'pay_term_number'           => $c->pay_term_number,
            'pay_term_type'             => $c->pay_term_type,
            'pay_term'                  => $c->pay_term_number ? trim($c->pay_term_number . ' ' . ucfirst($c->pay_term_type ?? 'days')) : '',
            'opening_balance'           => (float) ($c->opening_balance ?? 0),
            'advance_balance'           => (float) ($c->balance ?? 0),
            'address_line_1'            => $c->address_line_1,
            'address_line_2'            => $c->address_line_2,
            'city'                      => $c->city,
            'state'                     => $c->state,
            'country'                   => $c->country,
            'zip_code'                  => $c->zip_code,
            'address'                   => implode(', ', array_filter([
                $c->address_line_1,
                $c->city,
                $c->state,
                $c->country,
            ])),
            'total_purchase_due'        => (float) ($c->total_purchase - $c->total_purchase_paid),
            'total_purchase_return_due' => (float) ($c->total_purchase_return - $c->total_purchase_return_paid),
            'total_sell_due'            => (float) ($c->total_sell - $c->total_sell_paid),
            'total_sell_return_due'     => (float) ($c->total_sell_return - $c->total_sell_return_paid),
            'custom_field1'             => $c->custom_field1,
            'custom_field2'             => $c->custom_field2,
            'custom_field3'             => $c->custom_field3,
            'custom_field4'             => $c->custom_field4,
            'created_at'                => $c->created_at?->format('d/m/Y'),
            'deleted_at'                => $c->deleted_at?->toISOString(),
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $contacts->through($transform)->items(),
                'meta' => [
                    'current_page' => $contacts->currentPage(),
                    'last_page'    => $contacts->lastPage(),
                    'per_page'     => $contacts->perPage(),
                    'total'        => $contacts->total(),
                ],
                'totals' => [
                    'total_purchase_due'        => Contact::where(fn ($q) => $q->where('type', $type)->orWhere('type', 'both'))
                        ->selectRaw('SUM(total_purchase - total_purchase_paid) as due')
                        ->value('due') ?? 0,
                    'total_purchase_return_due' => Contact::where(fn ($q) => $q->where('type', $type)->orWhere('type', 'both'))
                        ->selectRaw('SUM(total_purchase_return - total_purchase_return_paid) as due')
                        ->value('due') ?? 0,
                ],
            ]);
        }

        return Inertia::render('Shop/Contacts/Suppliers/Index', [
            'contacts' => $contacts->through($transform),
            'filters'  => $request->only(['search', 'per_page', 'type']),
            'can'      => [
                'create' => true,
                'edit'   => true,
                'delete' => true,
            ],
        ]);
    }

    /**
     * Store a new contact (supplier / customer / both).
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $businessId = $this->currentBusiness->id();

        $validated = $request->validate([
            'type'                   => ['required', Rule::in(['supplier', 'customer', 'both'])],
            'name'                   => ['nullable', 'string', 'max:255'],
            'supplier_business_name' => ['nullable', 'string', 'max:255'],
            'prefix'                 => ['nullable', 'string', 'max:10'],
            'first_name'             => ['nullable', 'string', 'max:255'],
            'last_name'              => ['nullable', 'string', 'max:255'],
            'email'                  => ['nullable', 'email', 'max:255'],
            'mobile'                 => ['nullable', 'string', 'max:20'],
            'landline'               => ['nullable', 'string', 'max:20'],
            'alternate_number'       => ['nullable', 'string', 'max:20'],
            'tax_number'             => ['nullable', 'string', 'max:100'],
            'opening_balance'        => ['nullable', 'numeric', 'min:0'],
            'pay_term'               => ['nullable', 'integer', 'min:1'],
            'address_line_1'         => ['nullable', 'string', 'max:500'],
            'address_line_2'         => ['nullable', 'string', 'max:500'],
            'city'                   => ['nullable', 'string', 'max:100'],
            'state'                  => ['nullable', 'string', 'max:100'],
            'country'                => ['nullable', 'string', 'max:100'],
            'zip_code'               => ['nullable', 'string', 'max:20'],
            'shipping_address'       => ['nullable', 'string'],
            'custom_field1'          => ['nullable', 'string'],
            'custom_field2'          => ['nullable', 'string'],
            'custom_field3'          => ['nullable', 'string'],
            'custom_field4'          => ['nullable', 'string'],
            'custom_field5'          => ['nullable', 'string'],
            'custom_field6'          => ['nullable', 'string'],
            'custom_field7'          => ['nullable', 'string'],
            'custom_field8'          => ['nullable', 'string'],
            'custom_field9'          => ['nullable', 'string'],
            'custom_field10'         => ['nullable', 'string'],
        ]);

        // Compose the name from first/last if not provided directly
        if (empty($validated['name'])) {
            $validated['name'] = trim(
                ($validated['prefix'] ? $validated['prefix'] . ' ' : '') .
                ($validated['first_name'] ?? '') . ' ' .
                ($validated['last_name'] ?? '')
            );
        }

        $contact = Contact::create(array_merge($validated, [
            'business_id' => $businessId,
        ]));

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Contact created successfully.',
                'data'    => $contact->fresh(),
            ], 201);
        }

        return redirect()->route('shop.contacts.index', ['type' => $contact->type])
            ->with('success', 'Contact created successfully.');
    }

    /**
     * Display the specified contact.
     */
    public function show(Request $request, Contact $contact): JsonResponse|Response
    {
        $this->authorizeContact($contact);

        if ($request->wantsJson()) {
            return response()->json(['data' => $contact]);
        }

        return Inertia::render('Shop/Contacts/Suppliers/Show', [
            'contact' => $contact,
        ]);
    }

    /**
     * Update the specified contact.
     */
    public function update(Request $request, Contact $contact): JsonResponse|RedirectResponse
    {
        $this->authorizeContact($contact);

        $validated = $request->validate([
            'type'                   => ['sometimes', Rule::in(['supplier', 'customer', 'both'])],
            'name'                   => ['nullable', 'string', 'max:255'],
            'supplier_business_name' => ['nullable', 'string', 'max:255'],
            'prefix'                 => ['nullable', 'string', 'max:10'],
            'first_name'             => ['nullable', 'string', 'max:255'],
            'last_name'              => ['nullable', 'string', 'max:255'],
            'email'                  => ['nullable', 'email', 'max:255'],
            'mobile'                 => ['nullable', 'string', 'max:20'],
            'landline'               => ['nullable', 'string', 'max:20'],
            'alternate_number'       => ['nullable', 'string', 'max:20'],
            'tax_number'             => ['nullable', 'string', 'max:100'],
            'opening_balance'        => ['nullable', 'numeric', 'min:0'],
            'address_line_1'         => ['nullable', 'string', 'max:500'],
            'address_line_2'         => ['nullable', 'string', 'max:500'],
            'city'                   => ['nullable', 'string', 'max:100'],
            'state'                  => ['nullable', 'string', 'max:100'],
            'country'                => ['nullable', 'string', 'max:100'],
            'zip_code'               => ['nullable', 'string', 'max:20'],
            'shipping_address'       => ['nullable', 'string'],
            'custom_field1'          => ['nullable', 'string'],
            'custom_field2'          => ['nullable', 'string'],
            'custom_field3'          => ['nullable', 'string'],
            'custom_field4'          => ['nullable', 'string'],
            'custom_field5'          => ['nullable', 'string'],
            'custom_field6'          => ['nullable', 'string'],
            'custom_field7'          => ['nullable', 'string'],
            'custom_field8'          => ['nullable', 'string'],
            'custom_field9'          => ['nullable', 'string'],
            'custom_field10'         => ['nullable', 'string'],
        ]);

        if (isset($validated['first_name']) || isset($validated['last_name'])) {
            if (empty($validated['name'])) {
                $validated['name'] = trim(
                    ($validated['prefix'] ?? $contact->prefix ? ($validated['prefix'] ?? $contact->prefix) . ' ' : '') .
                    ($validated['first_name'] ?? $contact->first_name ?? '') . ' ' .
                    ($validated['last_name'] ?? $contact->last_name ?? '')
                );
            }
        }

        $contact->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Contact updated successfully.',
                'data'    => $contact->fresh(),
            ]);
        }

        return redirect()->route('shop.contacts.index', ['type' => $contact->type])
            ->with('success', 'Contact updated successfully.');
    }

    /**
     * Remove the specified contact.
     */
    public function destroy(Request $request, Contact $contact): JsonResponse|RedirectResponse
    {
        $this->authorizeContact($contact);

        $contact->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Contact deleted successfully.']);
        }

        return redirect()->route('shop.contacts.index')
            ->with('success', 'Contact deleted successfully.');
    }

    /**
     * Restore the specified soft-deleted contact.
     */
    public function restore(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        $businessId = $this->currentBusiness->id();

        $contact = Contact::onlyTrashed()
            ->where('business_id', $businessId)
            ->findOrFail($id);

        $contact->restore();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Contact restored successfully.',
                'data'    => $contact->fresh(),
            ]);
        }

        return redirect()->route('shop.contacts.index', ['type' => $contact->type])
            ->with('success', 'Contact restored successfully.');
    }

    /**
     * Permanently remove the specified contact from storage.
     */
    public function forceDelete(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        $businessId = $this->currentBusiness->id();

        $contact = Contact::withTrashed()
            ->where('business_id', $businessId)
            ->findOrFail($id);

        $contact->forceDelete();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Contact permanently deleted.',
            ]);
        }

        return redirect()->route('shop.contacts.index')
            ->with('success', 'Contact permanently deleted.');
    }

    /*
    |--------------------------------------------------------------------------
    | Private helpers
    |--------------------------------------------------------------------------
    */

    private function authorizeContact(Contact $contact): void
    {
        if ((int) $contact->business_id !== (int) $this->currentBusiness->id()) {
            abort(404, 'Contact not found for this business.');
        }
    }
}
