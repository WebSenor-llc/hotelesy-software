<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/auth/login
     * Sanctum token-based; returns bearer token for API + a Sanctum cookie
     * for SPA usage.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6'],
            'tenant_slug' => ['nullable', 'string', 'max:100'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        // Tenant scoping: a user is unique per (tenant, email).
        $userQuery = User::query()->where('email', $data['email']);
        if (! empty($data['tenant_slug'])) {
            $userQuery->whereHas('tenant', fn($q) => $q->where('slug', $data['tenant_slug']));
        }

        /** @var User|null $user */
        $user = $userQuery->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if (! $user->tenant->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['Your hotel subscription is not active. Contact support.'],
            ]);
        }

        $token = $user->createToken(
            $data['device_name'] ?? 'web',
            expiresAt: now()->addDays(30)
        )->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->load('tenant', 'roles'),
        ]);
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->load('tenant', 'roles', 'permissions'),
        ]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
