<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiKeyController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => ApiKey::with('company')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:120'],
            'permissions' => ['nullable', 'array'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $key = ApiKey::create([
            'company_id' => $data['company_id'],
            'name' => $data['name'],
            'key' => Str::random(32),
            'permissions' => $data['permissions'] ?? ['send_messages'],
            'status' => $data['status'] ?? 'active',
        ]);

        return response()->json(['data' => $key], 201);
    }
}
