<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthTokenController extends Controller
{
    /** POST /api/login : only security auditors may obtain a checkpoint token. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->hasRole(Role::SecurityAuditor)) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match an auditor account.']);
        }

        $token = $user->createToken($data['device_name'] ?? 'checkpoint-device', ['checkpoint:verify']);

        return response()->json([
            'token' => $token->plainTextToken,
            'abilities' => ['checkpoint:verify'],
        ]);
    }

    /** DELETE /api/logout : revoke the current token. */
    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Token revoked.']);
    }
}
