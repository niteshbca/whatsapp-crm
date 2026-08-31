<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppointmentFeatureTest extends TestCase
{
    public function test_appointments_can_be_created_and_listed(): void
    {
        $response = $this->postJson('/api/appointments', [
            'title' => 'Demo Call',
            'contact_name' => 'Riya',
            'phone' => '15551234567',
            'scheduled_at' => '2026-09-01T10:00:00Z',
            'notes' => 'Follow up on pricing',
            'status' => 'scheduled',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Demo Call')
            ->assertJsonPath('data.status', 'scheduled');

        $listing = $this->getJson('/api/appointments');
        $listing->assertOk()->assertJsonPath('data.0.title', 'Demo Call');
    }

    public function test_appointments_summary_is_available(): void
    {
        $this->postJson('/api/appointments', [
            'title' => 'Sales callback',
            'contact_name' => 'Karan',
            'phone' => '15557654321',
            'scheduled_at' => '2026-09-02T09:00:00Z',
            'status' => 'scheduled',
        ]);

        $response = $this->getJson('/api/appointments/summary');

        $response->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.upcoming', 1)
            ->assertJsonPath('data.by_status.scheduled', 1);
    }
}
