<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Services\RecipientParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ContactController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Contact::query();

        if ($search = trim((string) $request->query('search'))) {
            $query->where('number', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%");
        }

        $contacts = $query->orderByDesc('id')->paginate($request->integer('limit', 30));

        return response()->json($contacts);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'number' => ['required', 'string', 'max:32'],
        ]);

        $number = RecipientParser::normalizeNumber($data['number']);

        if ($number === null) {
            throw ValidationException::withMessages(['number' => 'Invalid phone number']);
        }

        $contact = Contact::updateOrCreate(
            ['number' => $number],
            ['name' => $data['name'] ?? null],
        );

        return response()->json($contact, 201);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'numbers' => ['nullable', 'string', 'max:100000'],
            'csv' => ['nullable', 'file'],
        ]);

        $decoded = json_decode((string) $request->input('numbers', '[]'), true);
        $numbers = is_array($decoded) ? $decoded : [];

        $recipients = [];

        foreach ($numbers as $value) {
            $number = RecipientParser::normalizeNumber((string) $value);

            if ($number !== null) {
                $recipients[] = ['number' => $number, 'name' => null];
            }
        }

        /** @var UploadedFile|null $csv */
        $csv = $request->file('csv');

        if ($csv !== null) {
            $recipients = array_merge($recipients, RecipientParser::parseCsv($csv->getRealPath()));
        }

        $created = 0;
        $existing = 0;

        foreach ($recipients as $recipient) {
            $exists = Contact::where('number', $recipient['number'])->exists();

            Contact::updateOrCreate(
                ['number' => $recipient['number']],
                ['name' => $recipient['name']],
            );

            $exists ? $existing++ : $created++;
        }

        return response()->json([
            'ok' => true,
            'created' => $created,
            'existing' => $existing,
            'total' => count($recipients),
        ]);
    }

    public function destroy(Contact $contact): JsonResponse
    {
        $contact->delete();

        return response()->json(['ok' => true]);
    }
}