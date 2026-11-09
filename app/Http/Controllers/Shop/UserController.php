<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\User;
use App\Support\CurrentBusiness;
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
    public function __construct(private CurrentBusiness $currentBusiness) {}

    public function index(Request $request): Response|JsonResponse
    {
        $businessId = $this->currentBusiness->id();

        $query = User::query()
            ->where('business_id', $businessId)
            ->where('is_cmmsn_agent', false)
            ->with(['roles:id,name'])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('is_active'), function ($q) use ($request) {
                $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
            })
            ->latest('id');

        $perPage = (int) $request->input('per_page', 25);
        $users = $query->paginate($perPage)->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $users->items(),
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'last_page'    => $users->lastPage(),
                    'per_page'     => $users->perPage(),
                    'total'        => $users->total(),
                ],
            ]);
        }

        $roles = Role::where(function ($q) use ($businessId) {
            $q->where('business_id', $businessId)
                ->orWhere(function ($sub) {
                    $sub->whereNull('business_id')
                        ->whereIn('name', ['owner', 'admin', 'cashier']);
                });
        })->get(['id', 'name']);

        $locations = Location::where('business_id', $businessId)
            ->where('is_active', true)
            ->get(['id', 'name', 'location_id']);

        return Inertia::render('Shop/Users/Index', [
            'users' => $users->through(fn (User $u) => [
                'id'         => $u->id,
                'username'   => $u->username ?? '-',
                'name'       => $u->name,
                'role'       => $u->roles->pluck('name')->first() ?? $u->type,
                'email'      => $u->email,
                'is_active'  => $u->is_active,
                'created_at' => $u->created_at?->format('Y-m-d'),
            ]),
            'filters' => $request->only(['search', 'is_active', 'per_page']),
            'roles' => $roles,
            'locations' => $locations,
            'can' => [
                'create' => $request->user()?->can('shop.users.create') ?? true,
                'edit'   => $request->user()?->can('shop.users.edit') ?? true,
                'delete' => $request->user()?->can('shop.users.delete') ?? true,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $businessId = $this->currentBusiness->id();

        $roles = Role::where(function ($q) use ($businessId) {
            $q->where('business_id', $businessId)
                ->orWhere(function ($sub) {
                    $sub->whereNull('business_id')
                        ->whereIn('name', ['owner', 'admin', 'cashier']);
                });
        })->get(['id', 'name']);

        $locations = Location::where('business_id', $businessId)
            ->where('is_active', true)
            ->get(['id', 'name', 'location_id']);

        return Inertia::render('Shop/Users/Create', [
            'roles'     => $roles,
            'locations' => $locations,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $businessId = $this->currentBusiness->id();

        $validated = $request->validate([
            'prefix'              => ['nullable', 'string', 'max:20'],
            'first_name'          => ['required', 'string', 'max:100'],
            'last_name'           => ['nullable', 'string', 'max:100'],
            'email'               => ['required', 'email', 'max:255', 'unique:users,email'],
            'username'            => ['nullable', 'string', 'max:100', 'unique:users,username'],
            'password'            => ['required', 'string', 'min:6', 'confirmed'],
            'role'                => ['nullable', 'string'],
            'is_active'           => ['boolean'],
            'allow_login'         => ['boolean'],
            'access_locations'    => ['nullable', 'array'],
            'commission_rate'     => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_sales_discount'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'selected_contacts'   => ['nullable', 'array'],
            'dob'                 => ['nullable', 'date'],
            'gender'              => ['nullable', 'string', 'max:20'],
            'marital_status'      => ['nullable', 'string', 'max:30'],
            'blood_group'         => ['nullable', 'string', 'max:10'],
            'phone'               => ['nullable', 'string', 'max:30'],
            'alt_number'          => ['nullable', 'string', 'max:30'],
            'family_number'       => ['nullable', 'string', 'max:30'],
            'fb_link'             => ['nullable', 'url', 'max:255'],
            'twitter_link'        => ['nullable', 'url', 'max:255'],
            'social_media_1'      => ['nullable', 'string', 'max:255'],
            'social_media_2'      => ['nullable', 'string', 'max:255'],
            'custom_field_1'      => ['nullable', 'string', 'max:255'],
            'custom_field_2'      => ['nullable', 'string', 'max:255'],
            'custom_field_3'      => ['nullable', 'string', 'max:255'],
            'custom_field_4'      => ['nullable', 'string', 'max:255'],
            'guardian_name'       => ['nullable', 'string', 'max:255'],
            'id_proof_name'       => ['nullable', 'string', 'max:255'],
            'id_proof_number'     => ['nullable', 'string', 'max:255'],
            'permanent_address'   => ['nullable', 'string'],
            'current_address'     => ['nullable', 'string'],
            'bank_details'        => ['nullable', 'array'],
            'hrm_details'         => ['nullable', 'array'],
        ]);

        $name = trim(($validated['prefix'] ?? '') . ' ' . $validated['first_name'] . ' ' . ($validated['last_name'] ?? ''));

        $user = User::create([
            'business_id'        => $businessId,
            'type'               => 'staff',
            'name'               => $name,
            'first_name'         => $validated['first_name'],
            'last_name'          => $validated['last_name'] ?? null,
            'prefix'             => $validated['prefix'] ?? null,
            'email'              => $validated['email'],
            'username'           => $validated['username'] ?? null,
            'password'           => Hash::make($validated['password']),
            'is_active'          => $validated['is_active'] ?? true,
            'allow_login'        => $validated['allow_login'] ?? true,
            'is_cmmsn_agent'     => false,
            'access_locations'   => $validated['access_locations'] ?? ['all'],
            'commission_rate'    => $validated['commission_rate'] ?? null,
            'max_sales_discount' => $validated['max_sales_discount'] ?? null,
            'selected_contacts'  => $validated['selected_contacts'] ?? null,
            'dob'                => $validated['dob'] ?? null,
            'gender'             => $validated['gender'] ?? null,
            'marital_status'     => $validated['marital_status'] ?? null,
            'blood_group'        => $validated['blood_group'] ?? null,
            'phone'              => $validated['phone'] ?? null,
            'alt_number'         => $validated['alt_number'] ?? null,
            'family_number'      => $validated['family_number'] ?? null,
            'fb_link'            => $validated['fb_link'] ?? null,
            'twitter_link'       => $validated['twitter_link'] ?? null,
            'social_media_1'     => $validated['social_media_1'] ?? null,
            'social_media_2'     => $validated['social_media_2'] ?? null,
            'custom_field_1'     => $validated['custom_field_1'] ?? null,
            'custom_field_2'     => $validated['custom_field_2'] ?? null,
            'custom_field_3'     => $validated['custom_field_3'] ?? null,
            'custom_field_4'     => $validated['custom_field_4'] ?? null,
            'guardian_name'      => $validated['guardian_name'] ?? null,
            'id_proof_name'      => $validated['id_proof_name'] ?? null,
            'id_proof_number'    => $validated['id_proof_number'] ?? null,
            'permanent_address'  => $validated['permanent_address'] ?? null,
            'current_address'    => $validated['current_address'] ?? null,
            'bank_details'       => $validated['bank_details'] ?? null,
            'hrm_details'        => $validated['hrm_details'] ?? null,
        ]);

        if (! empty($validated['role'])) {
            setPermissionsTeamId($businessId);
            $user->assignRole($validated['role']);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'User created successfully.',
                'data'    => $user->load('roles'),
            ], 201);
        }

        return redirect()->route('shop.users.index')->with('success', 'User created successfully.');
    }

    public function show(Request $request, User $user): Response|JsonResponse
    {
        $this->authorizeBusinessUser($user);

        $user->load(['roles:id,name']);

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $user,
            ]);
        }

        return Inertia::render('Shop/Users/Show', [
            'user' => $user,
        ]);
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorizeBusinessUser($user);

        $businessId = $this->currentBusiness->id();

        $roles = Role::where(function ($q) use ($businessId) {
            $q->where('business_id', $businessId)
                ->orWhere(function ($sub) {
                    $sub->whereNull('business_id')
                        ->whereIn('name', ['owner', 'admin', 'cashier']);
                });
        })->get(['id', 'name']);

        $locations = Location::where('business_id', $businessId)
            ->where('is_active', true)
            ->get(['id', 'name', 'location_id']);

        return Inertia::render('Shop/Users/Edit', [
            'user'      => array_merge($user->toArray(), [
                'role' => $user->roles->pluck('name')->first() ?? '',
            ]),
            'roles'     => $roles,
            'locations' => $locations,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $this->authorizeBusinessUser($user);
        $businessId = $this->currentBusiness->id();

        $validated = $request->validate([
            'prefix'              => ['nullable', 'string', 'max:20'],
            'first_name'          => ['required', 'string', 'max:100'],
            'last_name'           => ['nullable', 'string', 'max:100'],
            'email'               => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'username'            => ['nullable', 'string', 'max:100', Rule::unique('users', 'username')->ignore($user->id)],
            'password'            => ['nullable', 'string', 'min:6', 'confirmed'],
            'role'                => ['nullable', 'string'],
            'is_active'           => ['boolean'],
            'allow_login'         => ['boolean'],
            'access_locations'    => ['nullable', 'array'],
            'commission_rate'     => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_sales_discount'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'selected_contacts'   => ['nullable', 'array'],
            'dob'                 => ['nullable', 'date'],
            'gender'              => ['nullable', 'string', 'max:20'],
            'marital_status'      => ['nullable', 'string', 'max:30'],
            'blood_group'         => ['nullable', 'string', 'max:10'],
            'phone'               => ['nullable', 'string', 'max:30'],
            'alt_number'          => ['nullable', 'string', 'max:30'],
            'family_number'       => ['nullable', 'string', 'max:30'],
            'fb_link'             => ['nullable', 'url', 'max:255'],
            'twitter_link'        => ['nullable', 'url', 'max:255'],
            'social_media_1'      => ['nullable', 'string', 'max:255'],
            'social_media_2'      => ['nullable', 'string', 'max:255'],
            'custom_field_1'      => ['nullable', 'string', 'max:255'],
            'custom_field_2'      => ['nullable', 'string', 'max:255'],
            'custom_field_3'      => ['nullable', 'string', 'max:255'],
            'custom_field_4'      => ['nullable', 'string', 'max:255'],
            'guardian_name'       => ['nullable', 'string', 'max:255'],
            'id_proof_name'       => ['nullable', 'string', 'max:255'],
            'id_proof_number'     => ['nullable', 'string', 'max:255'],
            'permanent_address'   => ['nullable', 'string'],
            'current_address'     => ['nullable', 'string'],
            'bank_details'        => ['nullable', 'array'],
            'hrm_details'         => ['nullable', 'array'],
        ]);

        $name = trim(($validated['prefix'] ?? '') . ' ' . $validated['first_name'] . ' ' . ($validated['last_name'] ?? ''));

        $updateData = [
            'name'               => $name,
            'first_name'         => $validated['first_name'],
            'last_name'          => $validated['last_name'] ?? null,
            'prefix'             => $validated['prefix'] ?? null,
            'email'              => $validated['email'],
            'username'           => $validated['username'] ?? null,
            'is_active'          => $validated['is_active'] ?? $user->is_active,
            'allow_login'        => $validated['allow_login'] ?? $user->allow_login,
            'access_locations'   => $validated['access_locations'] ?? $user->access_locations,
            'commission_rate'    => $validated['commission_rate'] ?? $user->commission_rate,
            'max_sales_discount' => $validated['max_sales_discount'] ?? $user->max_sales_discount,
            'selected_contacts'  => $validated['selected_contacts'] ?? $user->selected_contacts,
            'dob'                => $validated['dob'] ?? $user->dob,
            'gender'             => $validated['gender'] ?? $user->gender,
            'marital_status'     => $validated['marital_status'] ?? $user->marital_status,
            'blood_group'        => $validated['blood_group'] ?? $user->blood_group,
            'phone'              => $validated['phone'] ?? $user->phone,
            'alt_number'         => $validated['alt_number'] ?? $user->alt_number,
            'family_number'      => $validated['family_number'] ?? $user->family_number,
            'fb_link'            => $validated['fb_link'] ?? $user->fb_link,
            'twitter_link'       => $validated['twitter_link'] ?? $user->twitter_link,
            'social_media_1'     => $validated['social_media_1'] ?? $user->social_media_1,
            'social_media_2'     => $validated['social_media_2'] ?? $user->social_media_2,
            'custom_field_1'     => $validated['custom_field_1'] ?? $user->custom_field_1,
            'custom_field_2'     => $validated['custom_field_2'] ?? $user->custom_field_2,
            'custom_field_3'     => $validated['custom_field_3'] ?? $user->custom_field_3,
            'custom_field_4'     => $validated['custom_field_4'] ?? $user->custom_field_4,
            'guardian_name'      => $validated['guardian_name'] ?? $user->guardian_name,
            'id_proof_name'      => $validated['id_proof_name'] ?? $user->id_proof_name,
            'id_proof_number'    => $validated['id_proof_number'] ?? $user->id_proof_number,
            'permanent_address'  => $validated['permanent_address'] ?? $user->permanent_address,
            'current_address'    => $validated['current_address'] ?? $user->current_address,
            'bank_details'       => $validated['bank_details'] ?? $user->bank_details,
            'hrm_details'        => $validated['hrm_details'] ?? $user->hrm_details,
        ];

        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        if (array_key_exists('role', $validated)) {
            setPermissionsTeamId($businessId);
            $user->syncRoles($validated['role'] ? [$validated['role']] : []);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'User updated successfully.',
                'data'    => $user->fresh('roles'),
            ]);
        }

        return redirect()->route('shop.users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $this->authorizeBusinessUser($user);

        if ($user->id === $request->user()?->id) {
            abort(400, 'You cannot delete yourself.');
        }

        if ($user->type === 'owner') {
            abort(400, 'Shop owner cannot be deleted.');
        }

        $user->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'User deleted successfully.',
            ]);
        }

        return redirect()->route('shop.users.index')->with('success', 'User deleted successfully.');
    }

    private function authorizeBusinessUser(User $user): void
    {
        if (auth()->user()?->isSuperAdmin()) {
            return;
        }

        if ((int) $user->business_id !== (int) $this->currentBusiness->id()) {
            abort(404, 'User not found for this shop.');
        }
    }
}
