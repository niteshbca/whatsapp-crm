<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function index(): JsonResponse
    {
        $appointments = Appointment::orderBy('scheduled_at')->get();

        return response()->json(['data' => $appointments]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'title' => ['required', 'string', 'max:160'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'scheduled_at' => ['required', 'date'],
            'status' => ['nullable', 'string', 'in:scheduled,confirmed,completed,cancelled'],
            'notes' => ['nullable', 'string'],
        ]);

        $appointment = Appointment::create([
            'company_id' => $data['company_id'] ?? null,
            'lead_id' => $data['lead_id'] ?? null,
            'title' => $data['title'],
            'contact_name' => $data['contact_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'scheduled_at' => $data['scheduled_at'],
            'status' => $data['status'] ?? 'scheduled',
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json(['data' => $appointment], 201);
    }

    public function update(Request $request, Appointment $appointment): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'scheduled_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:scheduled,confirmed,completed,cancelled'],
            'notes' => ['nullable', 'string'],
        ]);

        $appointment->update($data);

        return response()->json(['data' => $appointment->fresh()]);
    }

    public function summary(): JsonResponse
    {
        $appointments = Appointment::all();
        $byStatus = [
            'scheduled' => $appointments->where('status', 'scheduled')->count(),
            'confirmed' => $appointments->where('status', 'confirmed')->count(),
            'completed' => $appointments->where('status', 'completed')->count(),
            'cancelled' => $appointments->where('status', 'cancelled')->count(),
        ];

        return response()->json([
            'data' => [
                'total' => $appointments->count(),
                'upcoming' => $appointments->where('scheduled_at', '>=', now())->count(),
                'by_status' => $byStatus,
            ],
        ]);
    }

    public function destroy(Appointment $appointment): JsonResponse
    {
        $appointment->delete();

        return response()->json(['ok' => true]);
    }
}
