<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignMessage;
use App\Models\Lead;
use Tests\TestCase;

class DashboardInsightsFeatureTest extends TestCase
{
    public function test_dashboard_returns_ai_business_insights(): void
    {
        Campaign::create([
            'name' => 'Launch Blast',
            'message' => 'Hi {{name}}',
            'status' => 'completed',
            'total' => 100,
            'success' => 80,
            'failed' => 20,
            'pending' => 0,
            'sent' => 80,
        ]);

        CampaignMessage::create([
            'campaign_id' => 1,
            'number' => '15551234567',
            'name' => 'Alice',
            'status' => CampaignMessage::STATUS_SENT,
        ]);

        Lead::create([
            'name' => 'Test Lead',
            'phone' => '15557654321',
            'source' => 'website',
            'stage' => 'new',
            'value' => 1200,
        ]);

        $response = $this->getJson('/api/dashboard');

        $response->assertOk()
            ->assertJsonPath('ai_insights.0.type', 'revenue')
            ->assertJsonPath('ai_insights.0.title', 'Revenue opportunity')
            ->assertJsonPath('business_summary.quality_score', 80);
    }
}
