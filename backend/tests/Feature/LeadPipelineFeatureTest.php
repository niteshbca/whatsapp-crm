<?php

namespace Tests\Feature;

use Tests\TestCase;

class LeadPipelineFeatureTest extends TestCase
{
    public function test_leads_can_be_created_and_listed(): void
    {
        $create = $this->postJson('/api/leads', [
            'name' => 'Aditi Sharma',
            'phone' => '15551234567',
            'email' => 'aditi@example.com',
            'source' => 'website',
            'stage' => 'new',
            'notes' => 'Interested in demo',
        ]);

        $create->assertStatus(201)
            ->assertJsonPath('data.name', 'Aditi Sharma')
            ->assertJsonPath('data.stage', 'new');

        $response = $this->getJson('/api/leads');
        $response->assertOk()->assertJsonPath('data.0.name', 'Aditi Sharma');
    }

    public function test_leads_pipeline_stats_are_available(): void
    {
        $this->postJson('/api/leads', [
            'name' => 'Lead One',
            'phone' => '15559876543',
            'source' => 'campaign',
            'stage' => 'new',
        ]);

        $this->postJson('/api/leads', [
            'name' => 'Lead Two',
            'phone' => '15557654321',
            'source' => 'referral',
            'stage' => 'qualified',
        ]);

        $response = $this->getJson('/api/leads/stats');
        $response->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.by_stage.new', 1)
            ->assertJsonPath('data.by_stage.qualified', 1);
    }
}
