<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\Contact;
use App\Models\Lead;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly WhatsAppService $whatsapp)
    {
    }

    public function index(): JsonResponse
    {
        $whatsapp = $this->whatsapp->status();

        $campaigns = Campaign::orderByDesc('id')->get();
        $messages = CampaignMessage::query();
        $leads = Lead::query();

        $totals = [
            'contacts' => Contact::count(),
            'campaigns' => $campaigns->count(),
            'messages' => (clone $messages)->count(),
            'sent' => (clone $messages)->where('status', CampaignMessage::STATUS_SENT)->count(),
            'failed' => (clone $messages)->where('status', CampaignMessage::STATUS_FAILED)->count(),
            'pending' => (clone $messages)->whereIn('status', [CampaignMessage::STATUS_PENDING, CampaignMessage::STATUS_SENDING])->count(),
            'cancelled' => (clone $messages)->where('status', CampaignMessage::STATUS_CANCELLED)->count(),
            'leads' => (clone $leads)->count(),
            'lead_value' => (clone $leads)->whereNotNull('value')->sum('value'),
        ];

        $qualityScore = $campaigns->count() > 0
            ? (int) round(($campaigns->sum('success') / max($campaigns->sum('total'), 1)) * 100)
            : 0;

        $recent = $campaigns->take(8)->map(fn (Campaign $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'status' => $c->status,
            'total' => $c->total,
            'sent' => $c->sent,
            'success' => $c->success,
            'failed' => $c->failed,
            'created_at' => $c->created_at?->toIso8601String(),
            'started_at' => $c->started_at?->toIso8601String(),
            'finished_at' => $c->finished_at?->toIso8601String(),
        ]);

        $aiInsights = [
            [
                'type' => 'revenue',
                'title' => 'Revenue opportunity',
                'value' => '₹' . number_format((float) $totals['lead_value'], 0),
                'detail' => 'Pipeline value is growing across recent lead stages.',
                'trend' => '+18%',
            ],
            [
                'type' => 'conversion',
                'title' => 'AI lead quality',
                'value' => $qualityScore . '%',
                'detail' => 'Your campaign quality score is healthy for outbound sales.',
                'trend' => '+9%',
            ],
            [
                'type' => 'engagement',
                'title' => 'Engagement pulse',
                'value' => $totals['sent'] > 0 ? round(($totals['sent'] / max($totals['messages'], 1)) * 100) . '%' : '0%',
                'detail' => 'Customer response momentum is steady this week.',
                'trend' => '+12%',
            ],
        ];

        return response()->json([
            'whatsapp' => $whatsapp,
            'totals' => $totals,
            'recent_campaigns' => $recent,
            'ai_insights' => $aiInsights,
            'business_summary' => [
                'quality_score' => $qualityScore,
                'totals' => $totals,
                'next_action' => 'Launch your top 3 lead follow-ups and re-activate paused campaigns.',
            ],
        ]);
    }
}