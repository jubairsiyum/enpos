<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CurrentBusiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SalesCommissionAgentController extends Controller
{
    public function __construct(private CurrentBusiness $currentBusiness) {}

    public function index(Request $request): Response|JsonResponse
    {
        $businessId = $this->currentBusiness->id();

        $query = User::query()
            ->where('business_id', $businessId)
            ->where('is_cmmsn_agent', true)
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->latest('id');

        $agents = $query->paginate((int) $request->input('per_page', 25))->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $agents->items(),
                'meta' => [
                    'current_page' => $agents->currentPage(),
                    'last_page'    => $agents->lastPage(),
                    'per_page'     => $agents->perPage(),
                    'total'        => $agents->total(),
                ],
            ]);
        }

        return Inertia::render('Shop/SalesCommissionAgents/Index', [
            'agents' => $agents->through(fn (User $a) => [
                'id'              => $a->id,
                'name'            => $a->name,
                'email'           => $a->email,
                'phone'           => $a->phone ?? '-',
                'address'         => $a->permanent_address ?? $a->current_address ?? '-',
                'commission_rate' => $a->commission_rate ? $a->commission_rate . '%' : '0%',
                'is_active'       => $a->is_active,
            ]),
            'filters' => $request->only(['search', 'per_page']),
            'can' => [
                'create' => $request->user()?->can('shop.users.create') ?? true,
                'edit'   => $request->user()?->can('shop.users.edit') ?? true,
                'delete' => $request->user()?->can('shop.users.delete') ?? true,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $businessId = $this->currentBusiness->id();

        $validated = $request->validate([
            'prefix'            => ['nullable', 'string', 'max:20'],
            'first_name'        => ['required', 'string', 'max:100'],
            'last_name'         => ['nullable', 'string', 'max:100'],
            'email'             => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone'             => ['nullable', 'string', 'max:30'],
            'permanent_address' => ['nullable', 'string'],
            'commission_rate'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active'         => ['boolean'],
        ]);

        $name = trim(($validated['prefix'] ?? '') . ' ' . $validated['first_name'] . ' ' . ($validated['last_name'] ?? ''));
        $email = ! empty($validated['email']) ? $validated['email'] : ('agent_' . time() . '_' . rand(100, 999) . '@agent.local');

        $agent = User::create([
            'business_id'       => $businessId,
            'type'              => 'staff',
            'name'              => $name,
            'first_name'        => $validated['first_name'],
            'last_name'         => $validated['last_name'] ?? null,
            'prefix'            => $validated['prefix'] ?? null,
            'email'             => $email,
            'phone'             => $validated['phone'] ?? null,
            'permanent_address' => $validated['permanent_address'] ?? null,
            'commission_rate'   => $validated['commission_rate'] ?? 0,
            'is_cmmsn_agent'    => true,
            'allow_login'       => false,
            'is_active'         => $validated['is_active'] ?? true,
            'password'          => Hash::make('secret123'),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Sales Commission Agent created successfully.',
                'data'    => $agent,
            ], 201);
        }

        return redirect()->route('shop.sales-commission-agents.index')->with('success', 'Sales commission agent created.');
    }

    public function update(Request $request, User $salesCommissionAgent): RedirectResponse|JsonResponse
    {
        $this->authorizeBusinessAgent($salesCommissionAgent);

        $validated = $request->validate([
            'prefix'            => ['nullable', 'string', 'max:20'],
            'first_name'        => ['required', 'string', 'max:100'],
            'last_name'         => ['nullable', 'string', 'max:100'],
            'email'             => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($salesCommissionAgent->id)],
            'phone'             => ['nullable', 'string', 'max:30'],
            'permanent_address' => ['nullable', 'string'],
            'commission_rate'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active'         => ['boolean'],
        ]);

        $name = trim(($validated['prefix'] ?? '') . ' ' . $validated['first_name'] . ' ' . ($validated['last_name'] ?? ''));
        $email = ! empty($validated['email']) ? $validated['email'] : $salesCommissionAgent->email;

        $salesCommissionAgent->update([
            'name'              => $name,
            'first_name'        => $validated['first_name'],
            'last_name'         => $validated['last_name'] ?? null,
            'prefix'            => $validated['prefix'] ?? null,
            'email'             => $email,
            'phone'             => $validated['phone'] ?? null,
            'permanent_address' => $validated['permanent_address'] ?? null,
            'commission_rate'   => $validated['commission_rate'] ?? 0,
            'is_active'         => $validated['is_active'] ?? $salesCommissionAgent->is_active,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Sales Commission Agent updated successfully.',
                'data'    => $salesCommissionAgent,
            ]);
        }

        return redirect()->route('shop.sales-commission-agents.index')->with('success', 'Sales commission agent updated.');
    }

    public function destroy(Request $request, User $salesCommissionAgent): RedirectResponse|JsonResponse
    {
        $this->authorizeBusinessAgent($salesCommissionAgent);

        $salesCommissionAgent->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Sales Commission Agent deleted successfully.']);
        }

        return redirect()->route('shop.sales-commission-agents.index')->with('success', 'Sales commission agent deleted.');
    }

    private function authorizeBusinessAgent(User $user): void
    {
        if ((int) $user->business_id !== (int) $this->currentBusiness->id() || ! $user->is_cmmsn_agent) {
            abort(404, 'Commission agent not found for this shop.');
        }
    }
}
