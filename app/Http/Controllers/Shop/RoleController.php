<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Support\CurrentBusiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(private CurrentBusiness $currentBusiness) {}

    public function index(Request $request): Response|JsonResponse
    {
        $businessId = $this->currentBusiness->id();

        $query = Role::query()
            ->where(function ($q) use ($businessId) {
                $q->where('business_id', $businessId)
                    ->orWhere(function ($sub) {
                        $sub->whereNull('business_id')
                            ->whereIn('name', ['owner', 'admin', 'cashier']);
                    });
            })
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->withCount('permissions')
            ->latest('id');

        $roles = $query->paginate((int) $request->input('per_page', 25))->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $roles->items(),
                'meta' => [
                    'current_page' => $roles->currentPage(),
                    'last_page'    => $roles->lastPage(),
                    'per_page'     => $roles->perPage(),
                    'total'        => $roles->total(),
                ],
            ]);
        }

        return Inertia::render('Shop/Roles/Index', [
            'roles' => $roles->through(fn (Role $r) => [
                'id'                => $r->id,
                'name'              => $r->name,
                'is_system'         => is_null($r->business_id) || in_array($r->name, ['owner', 'admin', 'cashier']),
                'permissions_count' => $r->permissions_count,
            ]),
            'filters' => $request->only(['search', 'per_page']),
            'can' => [
                'create' => $request->user()?->can('shop.roles.create') ?? true,
                'edit'   => $request->user()?->can('shop.roles.edit') ?? true,
                'delete' => $request->user()?->can('shop.roles.delete') ?? true,
            ],
        ]);
    }

    public function permissions(): JsonResponse
    {
        return response()->json([
            'data' => config('shop_permissions.groups', []),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Shop/Roles/Create', [
            'permission_groups' => config('shop_permissions.groups', []),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $businessId = $this->currentBusiness->id();

        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $exists = Role::where('name', $validated['name'])
            ->where('business_id', $businessId)
            ->exists();

        if ($exists) {
            abort(422, 'Role with this name already exists for your business.');
        }

        setPermissionsTeamId($businessId);

        $role = Role::create([
            'name'        => $validated['name'],
            'guard_name'  => 'web',
            'business_id' => $businessId,
        ]);

        if (! empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Role created successfully.',
                'data'    => $role->load('permissions'),
            ], 201);
        }

        return redirect()->route('shop.roles.index')->with('success', 'Role created successfully.');
    }

    public function show(Request $request, Role $role): Response|JsonResponse
    {
        $this->authorizeBusinessRole($role);

        $role->load('permissions:id,name');

        if ($request->wantsJson()) {
            return response()->json(['data' => $role]);
        }

        return Inertia::render('Shop/Roles/Show', [
            'role' => $role,
        ]);
    }

    public function edit(Role $role): Response
    {
        $this->authorizeBusinessRole($role);

        return Inertia::render('Shop/Roles/Edit', [
            'role' => [
                'id'          => $role->id,
                'name'        => $role->name,
                'is_system'   => is_null($role->business_id),
                'permissions' => $role->permissions->pluck('name')->toArray(),
            ],
            'permission_groups' => config('shop_permissions.groups', []),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse|JsonResponse
    {
        $this->authorizeBusinessRole($role);
        $businessId = $this->currentBusiness->id();

        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        if (! is_null($role->business_id)) {
            $role->update(['name' => $validated['name']]);
        }

        setPermissionsTeamId($businessId);
        $role->syncPermissions($validated['permissions'] ?? []);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Role updated successfully.',
                'data'    => $role->fresh('permissions'),
            ]);
        }

        return redirect()->route('shop.roles.index')->with('success', 'Role updated successfully.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse|JsonResponse
    {
        $this->authorizeBusinessRole($role);

        if (is_null($role->business_id) || in_array($role->name, ['owner', 'admin', 'cashier'])) {
            abort(400, 'System default roles cannot be deleted.');
        }

        $role->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Role deleted successfully.',
            ]);
        }

        return redirect()->route('shop.roles.index')->with('success', 'Role deleted successfully.');
    }

    private function authorizeBusinessRole(Role $role): void
    {
        if (auth()->user()?->isSuperAdmin()) {
            return;
        }

        $businessId = $this->currentBusiness->id();

        if (! is_null($role->business_id) && (int) $role->business_id !== (int) $businessId) {
            abort(404, 'Role not found for this shop.');
        }
    }
}
