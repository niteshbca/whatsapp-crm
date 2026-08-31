<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderController extends Controller
{
    /**
     * Progress callbacks coming from the Node.js WhatsApp service.
     * Body: { campaign: int, item_id?: int, status?: 'sending'|'sent'|'failed',
     *        error?: string, message_id?: string, done?: bool, final_status?: string }
     */
    public function progress(Request $request): JsonResponse
    {
        $data = $request->validate([
            'campaign' => ['required', 'integer'],
            'item_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:20'],
            'error' => ['nullable', 'string', 'max:1000'],
            'message_id' => ['nullable', 'string', 'max:255'],
            'done' => ['nullable', 'boolean'],
            'final_status' => ['nullable', 'string', 'max:20'],
        ]);

        $campaign = Campaign::find($data['campaign']);

        if (! $campaign) {
            return response()->json(['error' => 'Campaign not found'], 404);
        }

        if (! empty($data['item_id']) && ! empty($data['status'])) {
            $item = CampaignMessage::whereKey($data['item_id'])
                ->where('campaign_id', $campaign->id)
                ->first();

            if ($item) {
                if ($data['status'] === 'sending') {
                    $item->update(['status' => CampaignMessage::STATUS_SENDING]);
                } elseif (in_array($data['status'], ['sent', 'failed'], true)) {
                    $item->update([
                        'status' => $data['status'] === 'sent' ? CampaignMessage::STATUS_SENT : CampaignMessage::STATUS_FAILED,
                        'error' => $data['error'] ?? null,
                        'message_id' => $data['message_id'] ?? null,
                        'sent_at' => now(),
                    ]);
                }

                $this->recomputeCounts($campaign);
            }
        }

        // Recompute counts even for status-only updates so counters stay in sync.
        $this->recomputeCounts($campaign);

        if (! empty($data['done'])) {
            $final = $data['final_status'] ?? Campaign::STATUS_COMPLETED;

            $campaign->update([
                'status' => in_array($final, [Campaign::STATUS_COMPLETED, Campaign::STATUS_STOPPED, Campaign::STATUS_FAILED], true)
                    ? $final
                    : Campaign::STATUS_COMPLETED,
                'finished_at' => now(),
            ]);

            if ($final === Campaign::STATUS_STOPPED) {
                $campaign->messages()
                    ->where('status', CampaignMessage::STATUS_PENDING)
                    ->update(['status' => CampaignMessage::STATUS_CANCELLED]);
            }

            $this->recomputeCounts($campaign);
        }

        return response()->json(['ok' => true]);
    }

    private function recomputeCounts(Campaign $campaign): void
    {
        $counts = $campaign->messages()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $pending = (int) ($counts['pending'] ?? 0);
        $sending = (int) ($counts['sending'] ?? 0);
        $sent = (int) ($counts['sent'] ?? 0);
        $failed = (int) ($counts['failed'] ?? 0);
        $cancelled = (int) ($counts['cancelled'] ?? 0);

        $campaign->updateQuietly([
            'pending' => $pending + $sending,
            'sent' => $sent,
            'success' => $sent,
            'failed' => $failed,
            'total' => $pending + $sending + $sent + $failed + $cancelled,
        ]);
    }
}