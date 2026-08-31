<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignMessage;
use Tests\TestCase;

class CampaignAnalyticsFeatureTest extends TestCase
{
    public function test_campaign_analytics_are_available(): void
    {
        $campaign = Campaign::create([
            'name' => 'Spring Promo',
            'message' => 'Hello {{name}}',
            'status' => 'completed',
            'total' => 10,
            'success' => 8,
            'failed' => 2,
            'pending' => 0,
            'sent' => 8,
        ]);

        CampaignMessage::create([
            'campaign_id' => $campaign->id,
            'number' => '15551234567',
            'name' => 'John',
            'status' => CampaignMessage::STATUS_SENT,
        ]);

        CampaignMessage::create([
            'campaign_id' => $campaign->id,
            'number' => '15559876543',
            'name' => 'Jane',
            'status' => CampaignMessage::STATUS_FAILED,
            'error' => 'Blocked',
        ]);

        $response = $this->getJson('/api/campaigns/analytics');

        $response->assertOk()
            ->assertJsonPath('data.total_campaigns', 1)
            ->assertJsonPath('data.total_recipients', 10)
            ->assertJsonPath('data.success_rate', 80)
            ->assertJsonPath('data.status_breakdown.completed', 1);
    }
}
