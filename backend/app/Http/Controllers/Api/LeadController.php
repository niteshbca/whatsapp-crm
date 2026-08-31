<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Lead::query();

        if ($stage = $request->query('stage')) {
            $query->where('stage', $stage);
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $leads = $query->orderByDesc('id')->get();

        return response()->json(['data' => $leads]);
    }

    public function stats(): JsonResponse
    {
        $leads = Lead::all();
        $byStage = [
            'new' => $leads->where('stage', 'new')->count(),
            'contacted' => $leads->where('stage', 'contacted')->count(),
            'qualified' => $leads->where('stage', 'qualified')->count(),
            'proposal' => $leads->where('stage', 'proposal')->count(),
            'won' => $leads->where('stage', 'won')->count(),
            'lost' => $leads->where('stage', 'lost')->count(),
        ];

        return response()->json([
            'data' => [
                'total' => $leads->count(),
                'by_stage' => $byStage,
                'new_today' => $leads->where('created_at', '>=', now()->startOfDay())->count(),
                'total_value' => $leads->sum('value'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'source' => ['nullable', 'string', 'max:80'],
            'stage' => ['nullable', 'string', 'in:new,contacted,qualified,proposal,won,lost'],
            'notes' => ['nullable', 'string'],
            'value' => ['nullable', 'numeric'],
            'owner_name' => ['nullable', 'string', 'max:120'],
        ]);

        $lead = Lead::create([
            'company_id' => $data['company_id'] ?? null,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'source' => $data['source'] ?? 'manual',
            'stage' => $data['stage'] ?? 'new',
            'notes' => $data['notes'] ?? null,
            'value' => $data['value'] ?? 0,
            'owner_name' => $data['owner_name'] ?? null,
        ]);

        return response()->json(['data' => $lead], 201);
    }

    public function update(Request $request, Lead $lead): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'source' => ['nullable', 'string', 'max:80'],
            'stage' => ['nullable', 'string', 'in:new,contacted,qualified,proposal,won,lost'],
            'notes' => ['nullable', 'string'],
            'value' => ['nullable', 'numeric'],
            'owner_name' => ['nullable', 'string', 'max:120'],
        ]);

        $lead->update($data);

        return response()->json(['data' => $lead->fresh()]);
    }

    public function destroy(Lead $lead): JsonResponse
    {
        $lead->delete();

        return response()->json(['ok' => true]);
    }
}
