<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * @group Authentication
 *
 * Endpoints for user registration, login, logout, and retrieving the authenticated user.
 */
class AuthController extends Controller
{
    /**
     * Register
     *
     * Create a new user account and receive an API token.
     *
     * @unauthenticated
     *
     * @bodyParam name string required The full name of the user. Example: Jean Dupont
     * @bodyParam email string required The email address. Must be unique. Example: jean@example.com
     * @bodyParam password string required The password (min 8 characters). Example: password123
     * @bodyParam password_confirmation string required Must match the password field. Example: password123
     * @bodyParam phone string The user's phone number. Example: +33612345678
     *
     * @response 201 {
     *   "user": {
     *     "id": 1,
     *     "name": "Jean Dupont",
     *     "email": "jean@example.com",
     *     "phone": "+33612345678",
     *     "created_at": "2026-01-15T10:00:00.000000Z",
     *     "updated_at": "2026-01-15T10:00:00.000000Z"
     *   },
     *   "token": "1|abc123def456..."
     * }
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "email": ["The email has already been taken."]
     *   }
     * }
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
        ]);

        $user->assignRole('client');

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    /**
     * Login
     *
     * Authenticate an existing user and receive an API token.
     *
     * @unauthenticated
     *
     * @bodyParam email string required The user's email address. Example: jean@example.com
     * @bodyParam password string required The user's password. Example: password123
     *
     * @response 200 {
     *   "user": {
     *     "id": 1,
     *     "name": "Jean Dupont",
     *     "email": "jean@example.com",
     *     "phone": "+33612345678",
     *     "created_at": "2026-01-15T10:00:00.000000Z",
     *     "updated_at": "2026-01-15T10:00:00.000000Z"
     *   },
     *   "token": "2|xyz789ghi012..."
     * }
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "email": ["Les identifiants sont incorrects."]
     *   }
     * }
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants sont incorrects.'],
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * Logout
     *
     * Revoke the current API token for the authenticated user.
     *
     * @authenticated
     *
     * @response 200 {
     *   "message": "Déconnexion réussie."
     * }
     * @response 401 {
     *   "message": "Unauthenticated."
     * }
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnexion réussie.']);
    }

    /**
     * Get Authenticated User
     *
     * Retrieve the currently authenticated user's profile with their roles.
     *
     * @authenticated
     *
     * @response 200 {
     *   "id": 1,
     *   "name": "Jean Dupont",
     *   "email": "jean@example.com",
     *   "phone": "+33612345678",
     *   "created_at": "2026-01-15T10:00:00.000000Z",
     *   "updated_at": "2026-01-15T10:00:00.000000Z",
     *   "roles": [
     *     {
     *       "id": 1,
     *       "name": "client",
     *       "guard_name": "web"
     *     }
     *   ]
     * }
     * @response 401 {
     *   "message": "Unauthenticated."
     * }
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json($request->user()->load('roles'));
    }
}
