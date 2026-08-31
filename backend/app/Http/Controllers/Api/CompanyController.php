<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Company;
use App\Models\WhatsAppAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CompanyController extends Controller
{
    public function __construct(private readonly \App\Services\WhatsAppService $whatsapp)
    {
    }

    public function index(): JsonResponse
    {
        $companies = Company::with(['whatsappAccounts', 'apiKeys'])->get();

        return response()->json(['data' => $companies]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'description' => ['nullable', 'string'],
        ]);

        $company = Company::create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'status' => $data['status'] ?? 'active',
            'description' => $data['description'] ?? null,
        ]);

        return response()->json(['data' => $company], 201);
    }

    public function show(Company $company): JsonResponse
    {
        $company->load(['whatsappAccounts', 'apiKeys']);

        return response()->json(['data' => $company]);
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'description' => ['nullable', 'string'],
        ]);

        $company->update([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'status' => $data['status'] ?? $company->status,
            'description' => $data['description'] ?? $company->description,
        ]);

        return response()->json(['data' => $company->fresh()]);
    }

    public function destroy(Company $company): JsonResponse
    {
        $company->delete();

        return response()->json(['ok' => true]);
    }

    public function accounts(Company $company): JsonResponse
    {
        return response()->json([
            'data' => $company->whatsappAccounts()->orderBy('id')->get(),
        ]);
    }

    public function connectNumber(Request $request, Company $company): JsonResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'session_name' => ['nullable', 'string', 'max:120'],
            'phone_number' => ['nullable', 'string', 'max:32'],
        ]);

        $status = $this->whatsapp->status($company->id);
        $phoneNumber = trim((string) ($data['phone_number'] ?? '')) ?: (string) ($status['phone'] ?? '');
        $sessionName = trim((string) ($data['session_name'] ?? '')) ?: trim((string) $data['label']);

        $account = WhatsAppAccount::updateOrCreate(
            [
                'company_id' => $company->id,
                'session_name' => $sessionName,
            ],
            [
                'label' => $data['label'],
                'phone_number' => $phoneNumber !== '' ? $phoneNumber : null,
                'status' => ($status['connected'] ?? false) ? 'connected' : 'disconnected',
                'last_connected_at' => ($status['connected'] ?? false) ? now() : null,
                'error_message' => $status['error'] ?? null,
            ]
        );

        if ($phoneNumber !== '' && $account->phone_number === null) {
            $account->forceFill(['phone_number' => $phoneNumber])->save();
        }

        return response()->json(['data' => $account], 201);
    }

    public function logoutNumber(Company $company): JsonResponse
    {
        $this->whatsapp->logout($company->id);

        $company->whatsappAccounts()->update([
            'status' => 'disconnected',
            'phone_number' => null,
            'last_connected_at' => null,
            'error_message' => 'Logged out by company owner',
        ]);

        return response()->json(['ok' => true]);
    }
}
