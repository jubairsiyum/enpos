<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\User;
use App\Support\CurrentBusiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
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

        $roles = [
            ['id' => 'super_admin', 'name' => 'super_admin', 'label' => 'Superadmin'],
            ['id' => 'admin',       'name' => 'admin',       'label' => 'Admin'],
            ['id' => 'owner',       'name' => 'owner',       'label' => 'Shop Owners'],
        ];

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

        $roles = [
            ['id' => 'super_admin', 'name' => 'super_admin', 'label' => 'Superadmin'],
            ['id' => 'admin',       'name' => 'admin',       'label' => 'Admin'],
            ['id' => 'owner',       'name' => 'owner',       'label' => 'Shop Owners'],
        ];

        $locations = Location::where('business_id', $businessId)
            ->where('is_active', true)
            ->get(['id', 'name', 'location_id']);

        $departments = DB::table('hrm_departments')
            ->where('business_id', $businessId)
            ->get(['id', 'name']);

        $designations = DB::table('hrm_designations')
            ->where('business_id', $businessId)
            ->get(['id', 'name']);

        $contacts = DB::table('contacts')
            ->where('business_id', $businessId)
            ->get(['id', 'name']);

        return Inertia::render('Shop/Users/Create', [
            'roles'        => $roles,
            'locations'    => $locations,
            'departments'  => $departments,
            'designations' => $designations,
            'contacts'     => $contacts,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $businessId = $this->currentBusiness->id();
        $validated = $this->validateUserPayload($request, $businessId);

        $name = trim(($validated['prefix'] ?? '') . ' ' . $validated['first_name'] . ' ' . ($validated['last_name'] ?? ''));

        $password = ! empty($validated['password'])
            ? Hash::make($validated['password'])
            : Hash::make(\Illuminate\Support\Str::random(32));

        $accessLocations = (! empty($validated['access_locations']) && ! in_array('all', $validated['access_locations']))
            ? $validated['access_locations']
            : ['all'];

        $role = $validated['role'] ?? null;
        $type = match ($role) {
            'super_admin' => 'super_admin',
            'admin'       => 'admin',
            'owner'       => 'owner',
            default       => 'staff',
        };

        $user = User::create([
            'business_id'        => $businessId,
            'type'               => $type,
            'name'               => $name,
            'first_name'         => $validated['first_name'],
            'last_name'          => $validated['last_name'] ?? null,
            'prefix'             => $validated['prefix'] ?? null,
            'email'              => $validated['email'],
            'username'           => $validated['username'] ?? null,
            'password'           => $password,
            'is_active'          => $validated['is_active'] ?? true,
            'enable_service_staff_pin' => (bool) ($validated['enable_service_staff_pin'] ?? false),
            'staff_pin'          => $validated['staff_pin'] ?? null,
            'allow_login'        => $validated['allow_login'] ?? true,
            'is_cmmsn_agent'     => false,
            'access_locations'   => $accessLocations,
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

        if (! empty($role)) {
            setPermissionsTeamId($businessId);
            $user->assignRole($role);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'User created successfully.',
                'data'    => $user->load('roles'),
            ], 201);
        }

        return redirect()->back()->with('success', "User '{$name}' created and saved to database successfully.");
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

        $roles = [
            ['id' => 'super_admin', 'name' => 'super_admin', 'label' => 'Superadmin'],
            ['id' => 'admin',       'name' => 'admin',       'label' => 'Admin'],
            ['id' => 'owner',       'name' => 'owner',       'label' => 'Shop Owners'],
        ];

        $locations = Location::where('business_id', $businessId)
            ->where('is_active', true)
            ->get(['id', 'name', 'location_id']);

        $departments = DB::table('hrm_departments')
            ->where('business_id', $businessId)
            ->get(['id', 'name']);

        $designations = DB::table('hrm_designations')
            ->where('business_id', $businessId)
            ->get(['id', 'name']);

        $contacts = DB::table('contacts')
            ->where('business_id', $businessId)
            ->get(['id', 'name']);

        return Inertia::render('Shop/Users/Edit', [
            'user'         => array_merge($user->toArray(), [
                'role' => $user->roles->pluck('name')->first() ?? '',
            ]),
            'roles'        => $roles,
            'locations'    => $locations,
            'departments'  => $departments,
            'designations' => $designations,
            'contacts'     => $contacts,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $this->authorizeBusinessUser($user);
        $businessId = $this->currentBusiness->id();
        $validated = $this->validateUserPayload($request, $businessId, $user);

        $name = trim(($validated['prefix'] ?? '') . ' ' . $validated['first_name'] . ' ' . ($validated['last_name'] ?? ''));

        $updateData = [
            'name'               => $name,
            'first_name'         => $validated['first_name'],
            'last_name'          => $validated['last_name'] ?? null,
            'prefix'             => $validated['prefix'] ?? null,
            'email'              => $validated['email'],
            'username'           => $validated['username'] ?? null,
            'is_active'          => $validated['is_active'] ?? $user->is_active,
            'enable_service_staff_pin' => array_key_exists('enable_service_staff_pin', $validated)
                ? (bool) $validated['enable_service_staff_pin']
                : $user->enable_service_staff_pin,
            'staff_pin'          => $validated['staff_pin'] ?? $user->staff_pin,
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

        if (array_key_exists('role', $validated)) {
            $role = $validated['role'];
            $type = match ($role) {
                'super_admin' => 'super_admin',
                'admin'       => 'admin',
                'owner'       => 'owner',
                default       => $user->type,
            };
            $updateData['type'] = $type;
        }

        $user->update($updateData);

        if (array_key_exists('role', $validated)) {
            $role = $validated['role'];
            setPermissionsTeamId($businessId);
            $user->syncRoles($role ? [$role] : []);
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

    /**
     * Validate and normalize user creation/update payloads with anti-redundancy checks.
     *
     * @return array<string, mixed>
     */
    private function validateUserPayload(Request $request, int $businessId, ?User $existingUser = null): array
    {
        $normalized = [];

        if ($request->has('email') && is_string($request->input('email'))) {
            $normalized['email'] = mb_strtolower(trim($request->input('email')));
        }
        if ($request->has('username') && is_string($request->input('username'))) {
            $trimmedUsername = mb_strtolower(trim($request->input('username')));
            $normalized['username'] = $trimmedUsername === '' ? null : $trimmedUsername;
        }
        foreach (['prefix', 'first_name', 'last_name', 'phone', 'alt_number', 'family_number', 'staff_pin'] as $field) {
            if ($request->has($field) && is_string($request->input($field))) {
                $trimmed = trim($request->input($field));
                $normalized[$field] = $trimmed === '' ? null : $trimmed;
            }
        }
        if ($request->has('access_locations') && is_array($request->input('access_locations'))) {
            $normalized['access_locations'] = array_values(array_unique(
                array_filter($request->input('access_locations'), fn (mixed $v): bool => $v !== null && $v !== '')
            ));
        }
        if ($request->has('selected_contacts') && is_array($request->input('selected_contacts'))) {
            $normalized['selected_contacts'] = array_values(array_unique(
                array_filter($request->input('selected_contacts'), fn (mixed $v): bool => $v !== null && $v !== '')
            ));
        }

        if ($normalized !== []) {
            $request->merge($normalized);
        }

        $emailRule = Rule::unique('users', 'email');
        $usernameRule = Rule::unique('users', 'username');
        $phoneRule = Rule::unique('users', 'phone')
            ->where('business_id', $businessId)
            ->whereNull('deleted_at');
        $pinRule = Rule::unique('users', 'staff_pin')
            ->where('business_id', $businessId)
            ->whereNull('deleted_at');

        if ($existingUser !== null) {
            $emailRule->ignore($existingUser->id);
            $usernameRule->ignore($existingUser->id);
            $phoneRule->ignore($existingUser->id);
            $pinRule->ignore($existingUser->id);
        }

        $passwordRules = $existingUser === null
            ? [
                Rule::requiredIf(fn () => filter_var($request->input('allow_login', true), FILTER_VALIDATE_BOOLEAN)),
                'nullable',
                'string',
                'min:6',
                'confirmed',
            ]
            : ['nullable', 'string', 'min:6', 'confirmed'];

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'prefix'                   => ['nullable', 'string', 'max:20'],
            'first_name'               => ['required', 'string', 'max:100'],
            'last_name'                => ['nullable', 'string', 'max:100'],
            'email'                    => ['required', 'email', 'max:255', $emailRule],
            'username'                 => ['nullable', 'string', 'max:100', $usernameRule],
            'password'                 => $passwordRules,
            'role'                     => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) use ($businessId): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    if (in_array($value, ['super_admin', 'admin', 'owner', 'cashier'], true)) {
                        return;
                    }
                    $customExists = Role::where('business_id', $businessId)->where('name', (string) $value)->exists();
                    if (! $customExists) {
                        $fail('The selected role is invalid for this business.');
                    }
                },
            ],
            'is_active'                => ['boolean'],
            'enable_service_staff_pin' => ['nullable', 'boolean'],
            'staff_pin'                => ['nullable', 'string', 'max:30', $pinRule],
            'allow_login'              => ['boolean'],
            'access_locations'         => ['nullable', 'array'],
            'access_locations.*'       => [
                function (string $attribute, mixed $value, \Closure $fail) use ($businessId): void {
                    if ($value === 'all') {
                        return;
                    }
                    if (! is_numeric($value) || ! Location::where('business_id', $businessId)->whereKey((int) $value)->exists()) {
                        $fail('The selected location is invalid for this business.');
                    }
                },
            ],
            'commission_rate'          => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_sales_discount'       => ['nullable', 'numeric', 'min:0', 'max:100'],
            'selected_contacts'        => ['nullable', 'array'],
            'selected_contacts.*'      => ['integer', Rule::exists('contacts', 'id')->where('business_id', $businessId)],
            'dob'                      => ['nullable', 'date'],
            'gender'                   => ['nullable', 'string', 'max:20'],
            'marital_status'           => ['nullable', 'string', 'max:30'],
            'blood_group'              => ['nullable', 'string', 'max:10'],
            'phone'                    => ['nullable', 'string', 'max:30', $phoneRule],
            'alt_number'               => ['nullable', 'string', 'max:30'],
            'family_number'            => ['nullable', 'string', 'max:30'],
            'fb_link'                  => ['nullable', 'url', 'max:255'],
            'twitter_link'             => ['nullable', 'url', 'max:255'],
            'social_media_1'           => ['nullable', 'string', 'max:255'],
            'social_media_2'           => ['nullable', 'string', 'max:255'],
            'custom_field_1'           => ['nullable', 'string', 'max:255'],
            'custom_field_2'           => ['nullable', 'string', 'max:255'],
            'custom_field_3'           => ['nullable', 'string', 'max:255'],
            'custom_field_4'           => ['nullable', 'string', 'max:255'],
            'guardian_name'            => ['nullable', 'string', 'max:255'],
            'id_proof_name'            => ['nullable', 'string', 'max:255'],
            'id_proof_number'          => ['nullable', 'string', 'max:255'],
            'permanent_address'        => ['nullable', 'string'],
            'current_address'          => ['nullable', 'string'],
            'bank_details'             => ['nullable', 'array'],
            'hrm_details'              => ['nullable', 'array'],
        ]);

        $validator->after(function (Validator $validator) use ($request): void {
            if ($request->boolean('enable_service_staff_pin') && ! $request->filled('staff_pin')) {
                $validator->errors()->add('staff_pin', 'Staff PIN is required when service staff PIN is enabled.');
            }
        });

        return $validator->validate();
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
