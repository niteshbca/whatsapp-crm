<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReminderFeatureTest extends TestCase
{
    public function test_reminders_can_be_created_and_listed(): void
    {
        $response = $this->postJson('/api/reminders', [
            'title' => 'Follow-up reminder',
            'channel' => 'whatsapp',
            'scheduled_for' => '2026-09-05T09:00:00Z',
            'message' => 'Hi, this is a reminder for your appointment.',
            'status' => 'scheduled',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Follow-up reminder')
            ->assertJsonPath('data.status', 'scheduled');

        $listing = $this->getJson('/api/reminders');
        $listing->assertOk()->assertJsonPath('data.0.title', 'Follow-up reminder');
    }

    public function test_reminders_summary_is_available(): void
    {
        $this->postJson('/api/reminders', [
            'title' => 'Demo reminder',
            'channel' => 'email',
            'scheduled_for' => '2026-09-06T10:00:00Z',
            'message' => 'Reminder email body',
            'status' => 'scheduled',
        ]);

        $response = $this->getJson('/api/reminders/summary');

        $response->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.upcoming', 1)
            ->assertJsonPath('data.by_channel.whatsapp', 0);
    }
}
