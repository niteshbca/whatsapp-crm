<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppController extends Controller
{
    public function __construct(private readonly WhatsAppService $whatsapp)
    {
    }

    public function status(Request $request): JsonResponse
    {
        $companyId = $request->query('company_id');

        return response()->json($this->whatsapp->status($companyId ? (int) $companyId : null));
    }

    public function connect(Request $request): JsonResponse
    {
        $companyId = $request->input('company_id');
        $sessionName = $request->input('session_name');

        $result = $this->whatsapp->connect($companyId ? (int) $companyId : null, $sessionName);

        if (isset($result['error'])) {
            return response()->json(['ok' => false, 'error' => $result['error']], 503);
        }

        return response()->json(['ok' => true] + $result);
    }

    public function logout(Request $request): JsonResponse
    {
        $companyId = $request->input('company_id');
        $this->whatsapp->logout($companyId ? (int) $companyId : null);

        return response()->json(['ok' => true]);
    }

    public function testSend(Request $request): JsonResponse
    {
        $data = $request->validate([
            'to' => ['required', 'string', 'max:32'],
            'message' => ['required', 'string', 'max:5000'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
        ]);

        $to = \App\Services\RecipientParser::normalizeNumber($data['to']);

        if ($to === null) {
            return response()->json(['ok' => false, 'error' => 'Invalid phone number'], 422);
        }

        $result = $this->whatsapp->send($to, $data['message'], null, $data['company_id'] ?? null);

        return response()->json($result, (bool) ($result['ok'] ?? false) ? 200 : 409);
    }
}