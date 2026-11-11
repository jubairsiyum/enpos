<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BusinessController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $businesses = Business::withoutGlobalScope('business')
            ->with(['owner', 'activeSubscription.plan'])
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%$s%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20);

        return response()->json($businesses);
    }

    public function store(Request $request): JsonResponse
    {
        $normalized = [];
        if ($request->has('name') && is_string($request->input('name'))) {
            $normalized['name'] = trim($request->input('name'));
        }
        if ($request->has('owner_email') && is_string($request->input('owner_email'))) {
            $normalized['owner_email'] = mb_strtolower(trim($request->input('owner_email')));
        }
        if ($request->has('email') && is_string($request->input('email'))) {
            $normalized['email'] = mb_strtolower(trim($request->input('email')));
        }
        if ($normalized !== []) {
            $request->merge($normalized);
        }

        $derivedSlug = $request->filled('slug')
            ? trim((string) $request->input('slug'))
            : Str::slug((string) $request->input('name'));

        $request->merge(['slug' => $derivedSlug]);

        $validated = $request->validate([
            'name'           => 'required|string|max:255|unique:businesses,name',
            'slug'           => 'required|string|unique:businesses,slug|regex:/^[a-z0-9\-]+$/i',
            'email'          => 'nullable|email',
            'phone'          => 'nullable|string|max:20',
            'plan_id'        => 'required|exists:plans,id',
            'owner_name'     => 'required|string|max:255',
            'owner_email'    => 'required|email|unique:users,email',
            'owner_password' => 'required|string|min:8',
        ]);

        return DB::transaction(function () use ($validated) {
            $plan = Plan::findOrFail($validated['plan_id']);

            // 1. Create the business
            $business = Business::withoutGlobalScope('business')->create([
                'name'   => $validated['name'],
                'slug'   => $validated['slug'] ?? Str::slug($validated['name']),
                'email'  => $validated['email'] ?? null,
                'phone'  => $validated['phone'] ?? null,
                'status' => 'active',
            ]);

            // 2. Create the owner user
            $owner = User::create([
                'name'        => $validated['owner_name'],
                'email'       => $validated['owner_email'],
                'password'    => Hash::make($validated['owner_password']),
                'type'        => 'owner',
                'business_id' => $business->id,
                'is_active'   => true,
            ]);

            $business->update(['owner_id' => $owner->id]);

            // 3. Create trial subscription
            Subscription::create([
                'business_id'   => $business->id,
                'plan_id'       => $plan->id,
                'status'        => $plan->trial_days > 0 ? 'trial' : 'active',
                'billing_cycle' => $plan->billing_cycle,
                'amount'        => $plan->price,
                'trial_ends_at' => $plan->trial_days > 0 ? now()->addDays($plan->trial_days) : null,
                'starts_at'     => now(),
                'ends_at'       => now()->addMonth(),
            ]);

            // 4. Create default location
            $business->locations()->create([
                'name'      => $business->name . ' - Main',
                'is_active' => true,
            ]);

            return response()->json([
                'message'  => 'Shop created successfully.',
                'business' => $business->load('owner', 'activeSubscription.plan'),
            ], 201);
        });
    }

    public function show(Business $business): JsonResponse
    {
        return response()->json(
            $business->load('owner', 'activeSubscription.plan', 'locations')
        );
    }

    public function update(Request $request, Business $business): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'email'         => 'nullable|email',
            'phone'         => 'nullable|string|max:20',
            'custom_domain' => 'nullable|string|unique:businesses,custom_domain,' . $business->id,
            'status'        => 'sometimes|in:active,suspended,pending,cancelled',
        ]);

        $business->update($validated);

        return response()->json(['message' => 'Business updated.', 'business' => $business]);
    }

    public function destroy(Business $business): JsonResponse
    {
        $business->delete();

        return response()->json(['message' => 'Business deleted.']);
    }

    public function suspend(Business $business): JsonResponse
    {
        $business->update(['status' => 'suspended']);

        return response()->json(['message' => 'Business suspended.']);
    }

    public function activate(Business $business): JsonResponse
    {
        $business->update(['status' => 'active']);

        return response()->json(['message' => 'Business activated.']);
    }

    public function impersonate(Request $request, Business $business): JsonResponse
    {
        $this->authorize('platform.businesses.impersonate');

        $owner = $business->owner;

        if (! $owner) {
            return response()->json(['message' => 'No owner found for this business.'], 422);
        }

        $token = $owner->createToken('impersonate')->plainTextToken;

        return response()->json([
            'message' => 'Impersonation token issued.',
            'token'   => $token,
            'user'    => [
                'id'          => $owner->id,
                'name'        => $owner->name,
                'email'       => $owner->email,
                'type'        => $owner->type,
                'business_id' => $owner->business_id,
            ],
        ]);
    }
}

