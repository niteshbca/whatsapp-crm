<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function index(): JsonResponse
    {
        $reminders = Reminder::orderBy('scheduled_for')->get();

        return response()->json(['data' => $reminders]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'title' => ['required', 'string', 'max:160'],
            'channel' => ['nullable', 'string', 'in:whatsapp,email'],
            'scheduled_for' => ['required', 'date'],
            'message' => ['required', 'string', 'max:5000'],
            'status' => ['nullable', 'string', 'in:scheduled,sent,failed,cancelled'],
        ]);

        $reminder = Reminder::create([
            'company_id' => $data['company_id'] ?? null,
            'appointment_id' => $data['appointment_id'] ?? null,
            'title' => $data['title'],
            'channel' => $data['channel'] ?? 'whatsapp',
            'scheduled_for' => $data['scheduled_for'],
            'message' => $data['message'],
            'status' => $data['status'] ?? 'scheduled',
        ]);

        return response()->json(['data' => $reminder], 201);
    }

    public function summary(): JsonResponse
    {
        $reminders = Reminder::all();
        $byChannel = [
            'whatsapp' => $reminders->where('channel', 'whatsapp')->count(),
            'email' => $reminders->where('channel', 'email')->count(),
        ];

        return response()->json([
            'data' => [
                'total' => $reminders->count(),
                'upcoming' => $reminders->where('scheduled_for', '>=', now())->count(),
                'by_channel' => $byChannel,
            ],
        ]);
    }

    public function destroy(Reminder $reminder): JsonResponse
    {
        $reminder->delete();

        return response()->json(['ok' => true]);
    }
}
