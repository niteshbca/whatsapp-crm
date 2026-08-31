<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\Contact;
use App\Services\RecipientParser;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CampaignController extends Controller
{
    public function __construct(private readonly WhatsAppService $whatsapp)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json(
            Campaign::orderByDesc('id')->paginate(30)
        );
    }

    public function analytics(): JsonResponse
    {
        $campaigns = Campaign::query()->get();
        $messages = CampaignMessage::query();

        $totalRecipients = $campaigns->sum('total');
        $totalSuccess = $campaigns->sum('success');
        $totalFailed = $campaigns->sum('failed');
        $totalCampaigns = $campaigns->count();

        $statusBreakdown = [
            'draft' => $campaigns->where('status', Campaign::STATUS_DRAFT)->count(),
            'running' => $campaigns->where('status', Campaign::STATUS_RUNNING)->count(),
            'paused' => $campaigns->where('status', Campaign::STATUS_PAUSED)->count(),
            'completed' => $campaigns->where('status', Campaign::STATUS_COMPLETED)->count(),
            'stopped' => $campaigns->where('status', Campaign::STATUS_STOPPED)->count(),
            'failed' => $campaigns->where('status', Campaign::STATUS_FAILED)->count(),
        ];

        $successRate = $totalRecipients > 0 ? round(($totalSuccess / $totalRecipients) * 100, 2) : 0;

        return response()->json([
            'data' => [
                'total_campaigns' => $totalCampaigns,
                'total_recipients' => $totalRecipients,
                'total_success' => $totalSuccess,
                'total_failed' => $totalFailed,
                'success_rate' => (int) $successRate,
                'status_breakdown' => $statusBreakdown,
                'recent_message_volume' => $messages->selectRaw('count(*) as total, date(created_at) as day')
                    ->groupBy('day')
                    ->orderBy('day', 'desc')
                    ->limit(7)
                    ->get(),
            ],
        ]);
    }

    public function show(Campaign $campaign, Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));

        $query = $campaign->messages();

        if ($search !== '') {
            $query->where(fn ($q) => $q
                ->where('number', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%"));
        }

        $status = trim((string) $request->query('status'));

        if ($status !== '') {
            $query->where('status', $status);
        }

        $messages = $query->paginate($request->integer('limit', 40));

        $counts = $campaign->messages()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $counts = array_merge([
            CampaignMessage::STATUS_PENDING => 0,
            CampaignMessage::STATUS_SENDING => 0,
            CampaignMessage::STATUS_SENT => 0,
            CampaignMessage::STATUS_FAILED => 0,
            CampaignMessage::STATUS_CANCELLED => 0,
        ], $counts);

        return response()->json([
            'campaign' => $campaign,
            'counts' => $counts,
            'messages' => $messages,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'whatsapp_account_id' => ['nullable', 'integer', 'exists:whatsapp_accounts,id'],
            'sender_number' => ['nullable', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:50000'],
            'recipients' => ['nullable', 'string', 'max:500000'],
            'csv' => ['nullable', 'file'],
            'media' => ['nullable', 'array', 'max:10'],
            'media.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx'],
            'delay_min' => ['nullable', 'integer', 'min:1', 'max:120'],
            'delay_max' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);

        $recipients = $this->mergeRecipients($data, $request->file('csv'));

        if (count($recipients) === 0) {
            throw ValidationException::withMessages(['recipients' => 'No valid phone numbers provided']);
        }

        if (count($recipients) > 5000) {
            throw ValidationException::withMessages(['recipients' => 'Maximum 5000 recipients per campaign']);
        }

        $campaign = new Campaign([
            'company_id' => $data['company_id'] ?? null,
            'whatsapp_account_id' => $data['whatsapp_account_id'] ?? null,
            'sender_number' => $data['sender_number'] ?? null,
            'name' => $data['name'],
            'message' => $data['message'],
            'status' => Campaign::STATUS_DRAFT,
            'pending' => count($recipients),
            'total' => count($recipients),
            'delay_min' => $data['delay_min'] ?? 2,
            'delay_max' => $data['delay_max'] ?? 5,
        ]);

        $mediaFiles = $request->file('media', []);
        if ($mediaFiles instanceof UploadedFile) $mediaFiles = [$mediaFiles];
        $mediaPaths = [];
        $mediaNames = [];
        foreach ($mediaFiles as $media) {
            if (! $media instanceof UploadedFile) continue;
            $mediaPaths[] = $media->store('campaigns', 'media');
            $mediaNames[] = $media->getClientOriginalName();
        }
        if ($mediaPaths !== []) {
            $campaign->media_path = $mediaPaths[0];
            $campaign->media_name = implode(', ', $mediaNames);
            $campaign->media_paths = $mediaPaths;
        }

        $campaign->save();

        foreach ($recipients as $recipient) {
            $contact = Contact::updateOrCreate(
                ['number' => $recipient['number']],
                ['name' => $recipient['name']],
            );

            $campaign->messages()->create([
                'contact_id' => $contact->id,
                'number' => $recipient['number'],
                'name' => $recipient['name'],
                'status' => CampaignMessage::STATUS_PENDING,
            ]);
        }

        return response()->json($campaign, 201);
    }

    public function destroy(Campaign $campaign): JsonResponse
    {
        if ($campaign->status === Campaign::STATUS_RUNNING) {
            $this->whatsapp->controlCampaign((string) $campaign->id, 'stop');
        }

        if ($campaign->media_path !== null) {
            Storage::disk('media')->delete($campaign->media_path);
        }
        foreach ($campaign->media_paths ?? [] as $path) {
            Storage::disk('media')->delete($path);
        }

        $campaign->delete();

        return response()->json(['ok' => true]);
    }

    public function start(Campaign $campaign): JsonResponse
    {
        if (in_array($campaign->status, [Campaign::STATUS_RUNNING, Campaign::STATUS_PAUSED], true)) {
            return response()->json(['error' => 'Campaign is already running'], 409);
        }

        $whatsapp = $this->whatsapp->status($campaign->company_id);

        if (isset($whatsapp['error']) || ! ($whatsapp['connected'] ?? false)) {
            return response()->json([
                'error' => 'WhatsApp is not connected. Start the WhatsApp service and scan the QR code first.',
            ], 409);
        }

        $pending = $campaign->messages()
            ->where('status', CampaignMessage::STATUS_PENDING)
            ->get(['id', 'number', 'name']);

        if ($pending->isEmpty()) {
            return response()->json(['error' => 'No pending recipients available'], 409);
        }

        $recipients = $pending->map(fn (CampaignMessage $m) => [
            'id' => $m->id,
            'number' => $m->number,
            'name' => $m->name,
        ])->values()->all();

        $storedMediaPaths = is_array($campaign->media_paths) && $campaign->media_paths !== []
            ? $campaign->media_paths
            : ($campaign->media_path ? [$campaign->media_path] : []);
        $mediaPaths = array_map(fn (string $path) => Storage::disk('media')->path($path), $storedMediaPaths);

        $result = $this->whatsapp->startCampaign(
            campaignId: (string) $campaign->id,
            recipients: $recipients,
            template: $campaign->message,
            mediaPaths: $mediaPaths,
            delayMin: $campaign->delay_min ?: 2,
            delayMax: $campaign->delay_max ?: 5,
            companyId: $campaign->company_id,
        );

        if (! $result['ok']) {
            $campaign->update([
                'status' => Campaign::STATUS_FAILED,
                'error' => $result['error'] ?? 'Failed to start campaign',
            ]);

            return response()->json(['error' => $campaign->error], 409);
        }

        $campaign->update([
            'status' => Campaign::STATUS_RUNNING,
            'started_at' => now(),
            'error' => null,
            'finished_at' => null,
        ]);

        return response()->json(['ok' => true, 'campaign' => $campaign->fresh()]);
    }

    public function pause(Campaign $campaign): JsonResponse
    {
        if ($campaign->status !== Campaign::STATUS_RUNNING) {
            return response()->json(['error' => 'Campaign is not running'], 409);
        }

        $this->whatsapp->controlCampaign((string) $campaign->id, 'pause');
        $campaign->update(['status' => Campaign::STATUS_PAUSED]);

        return response()->json(['ok' => true, 'campaign' => $campaign->fresh()]);
    }

    public function resume(Campaign $campaign): JsonResponse
    {
        if ($campaign->status !== Campaign::STATUS_PAUSED) {
            return response()->json(['error' => 'Campaign is not paused'], 409);
        }

        $this->whatsapp->controlCampaign((string) $campaign->id, 'resume');
        $campaign->update(['status' => Campaign::STATUS_RUNNING]);

        return response()->json(['ok' => true, 'campaign' => $campaign->fresh()]);
    }

    public function stop(Campaign $campaign): JsonResponse
    {
        if (! in_array($campaign->status, [Campaign::STATUS_RUNNING, Campaign::STATUS_PAUSED], true)) {
            return response()->json(['error' => 'Campaign is not running'], 409);
        }

        $this->whatsapp->controlCampaign((string) $campaign->id, 'stop');

        $campaign->messages()
            ->where('status', CampaignMessage::STATUS_PENDING)
            ->update(['status' => CampaignMessage::STATUS_CANCELLED]);

        $campaign->update([
            'status' => Campaign::STATUS_STOPPED,
            'finished_at' => now(),
        ]);

        return response()->json(['ok' => true, 'campaign' => $campaign->fresh()]);
    }

    /**
     * Merge manually entered numbers with a CSV upload.
     *
     * @return array<int, array{number: string, name: string|null}>
     */
    private function mergeRecipients(array $data, ?UploadedFile $csv): array
    {
        $recipients = [];

        if (($data['recipients'] ?? '') !== '') {
            $decoded = json_decode((string) $data['recipients'], true);

            foreach ((array) ($decoded ?? []) as $value) {
                if (is_string($value)) {
                    $number = RecipientParser::normalizeNumber($value);

                    if ($number !== null) {
                        $recipients[$number] = ['number' => $number, 'name' => null];
                    }
                }
            }
        }

        if ($csv instanceof UploadedFile) {
            foreach (RecipientParser::parseCsv($csv->getRealPath()) as $recipient) {
                $recipients[$recipient['number']] = $recipient;
            }
        }

        return array_values($recipients);
    }
}