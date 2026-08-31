<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;

        $users = User::query()
            ->where('company_id', $companyId)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'data' => $users,
            'role' => $request->user()->role,
            'company_id' => $companyId,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'string', 'in:admin,manager,employee,viewer'],
            'status' => ['nullable', 'string', 'in:active,inactive,pending'],
            'permissions' => ['nullable', 'array'],
        ]);

        $user = User::create([
            'company_id' => $data['company_id'],
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'status' => $data['status'] ?? 'active',
            'permissions' => $data['permissions'] ?? [],
        ]);

        return response()->json([
            'data' => $user,
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'role' => ['nullable', 'string', 'in:admin,manager,employee,viewer'],
            'status' => ['nullable', 'string', 'in:active,inactive,pending'],
            'permissions' => ['nullable', 'array'],
        ]);

        $user->fill([
            'role' => $data['role'] ?? $user->role,
            'status' => $data['status'] ?? $user->status,
            'permissions' => $data['permissions'] ?? $user->permissions ?? [],
        ]);

        $user->save();

        return response()->json([
            'data' => $user->fresh(),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json([
            'ok' => true,
            'deleted_user_id' => $user->id,
        ]);
    }
}
