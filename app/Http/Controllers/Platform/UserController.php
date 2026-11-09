<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $search = $request->input('search');

        // 1. Users list
        $usersQuery = User::query()
            ->with(['roles:id,name', 'business:id,name'])
            ->where('is_cmmsn_agent', false)
            ->when($search, function ($q, $s) {
                $q->where(function ($sub) use ($s) {
                    $sub->where('name', 'like', "%{$s}%")
                        ->orWhere('username', 'like', "%{$s}%")
                        ->orWhere('email', 'like', "%{$s}%");
                });
            })
            ->latest('id');

        $users = $usersQuery->paginate(25)->through(fn (User $u) => [
            'id'            => $u->id,
            'username'      => $u->username ?? '-',
            'name'          => $u->name,
            'email'         => $u->email,
            'type'          => $u->type,
            'role'          => $u->roles->pluck('name')->first() ?? $u->type,
            'business_name' => $u->business?->name ?? 'Platform (SuperAdmin)',
            'is_active'     => $u->is_active,
            'phone'         => $u->phone ?? '-',
            'created_at'    => $u->created_at?->format('Y-m-d'),
        ]);

        // 2. Roles list
        $roles = Role::with('permissions:id,name')
            ->withCount('permissions')
            ->latest('id')
            ->get()
            ->map(fn (Role $r) => [
                'id'                => $r->id,
                'name'              => $r->name,
                'is_system'         => is_null($r->business_id) || in_array($r->name, ['super_admin', 'admin', 'owner', 'cashier']),
                'permissions_count' => $r->permissions_count,
                'permissions'       => $r->permissions->pluck('name')->toArray(),
            ]);

        // 3. Sales Commission Agents
        $agents = User::where('is_cmmsn_agent', true)
            ->with('business:id,name')
            ->latest('id')
            ->get()
            ->map(fn (User $a) => [
                'id'              => $a->id,
                'name'            => $a->name,
                'email'           => $a->email,
                'phone'           => $a->phone ?? '-',
                'address'         => $a->permanent_address ?? $a->current_address ?? '-',
                'commission_rate' => $a->commission_rate ? $a->commission_rate . '%' : '0%',
                'business_name'   => $a->business?->name ?? 'Primary Shop',
                'is_active'       => $a->is_active,
            ]);

        // 4. Permission groups for modal / role creation
        $permissionGroups = config('shop_permissions.groups', []);

        $businesses = Business::withoutGlobalScope('business')
            ->get(['id', 'name']);

        if ($request->wantsJson()) {
            return response()->json([
                'users'             => $users,
                'roles'             => $roles,
                'agents'            => $agents,
                'permission_groups' => $permissionGroups,
            ]);
        }

        return Inertia::render('Platform/Users/Index', [
            'users'             => $users,
            'roles'             => $roles,
            'agents'            => $agents,
            'businesses'        => $businesses,
            'permission_groups' => $permissionGroups,
            'permission_layout' => config('shop_permissions.layout', []),
            'filters'           => $request->only(['search', 'tab']),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'first_name'      => ['required', 'string', 'max:100'],
            'last_name'       => ['nullable', 'string', 'max:100'],
            'email'           => ['required', 'email', 'max:255', 'unique:users,email'],
            'username'        => ['nullable', 'string', 'max:100', 'unique:users,username'],
            'password'        => ['required', 'string', 'min:6'],
            'type'            => ['required', 'string', 'in:super_admin,admin,owner,staff'],
            'business_id'     => ['nullable', 'exists:businesses,id'],
            'role'            => ['nullable', 'string'],
            'is_active'       => ['boolean'],
            'phone'           => ['nullable', 'string', 'max:30'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $name = trim($validated['first_name'] . ' ' . ($validated['last_name'] ?? ''));

        $user = User::create([
            'business_id'     => $validated['business_id'] ?? null,
            'type'            => $validated['type'],
            'name'            => $name,
            'first_name'      => $validated['first_name'],
            'last_name'       => $validated['last_name'] ?? null,
            'email'           => $validated['email'],
            'username'        => $validated['username'] ?? null,
            'password'        => Hash::make($validated['password']),
            'is_active'       => $validated['is_active'] ?? true,
            'allow_login'     => true,
            'phone'           => $validated['phone'] ?? null,
            'commission_rate' => $validated['commission_rate'] ?? null,
        ]);

        if (! empty($validated['role'])) {
            if ($user->business_id) {
                setPermissionsTeamId($user->business_id);
            }
            $user->assignRole($validated['role']);
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => 'User created successfully.', 'data' => $user], 201);
        }

        return back()->with('success', 'User created successfully.');
    }

    public function update(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['nullable', 'string', 'max:100'],
            'email'      => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'username'   => ['nullable', 'string', 'max:100', Rule::unique('users', 'username')->ignore($user->id)],
            'password'   => ['nullable', 'string', 'min:6'],
            'type'       => ['required', 'string', 'in:super_admin,admin,owner,staff'],
            'is_active'  => ['boolean'],
            'phone'      => ['nullable', 'string', 'max:30'],
        ]);

        $name = trim($validated['first_name'] . ' ' . ($validated['last_name'] ?? ''));

        $updateData = [
            'name'       => $name,
            'first_name' => $validated['first_name'],
            'last_name'  => $validated['last_name'] ?? null,
            'email'      => $validated['email'],
            'username'   => $validated['username'] ?? null,
            'type'       => $validated['type'],
            'is_active'  => $validated['is_active'] ?? $user->is_active,
            'phone'      => $validated['phone'] ?? $user->phone,
        ];

        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'User updated successfully.', 'data' => $user]);
        }

        return back()->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse|JsonResponse
    {
        if ($user->id === auth()->id()) {
            abort(400, 'You cannot delete yourself.');
        }

        $user->delete();

        return back()->with('success', 'User deleted successfully.');
    }
}
