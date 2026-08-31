<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MessageTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        $templates = MessageTemplate::orderByDesc('id')->get();

        return response()->json(['data' => $templates]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:120'],
            'content' => ['required', 'string', 'max:50000'],
            'category' => ['nullable', 'string', 'max:80'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $template = MessageTemplate::create($data);

        return response()->json(['data' => $template], 201);
    }

    public function update(Request $request, MessageTemplate $template): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:120'],
            'content' => ['required', 'string', 'max:50000'],
            'category' => ['nullable', 'string', 'max:80'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $template->update($data);

        return response()->json(['data' => $template->fresh()]);
    }

    public function destroy(MessageTemplate $template): JsonResponse
    {
        $template->delete();

        return response()->json(['ok' => true]);
    }
}
