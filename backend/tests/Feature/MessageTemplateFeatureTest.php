<?php

namespace Tests\Feature;

use Tests\TestCase;

class MessageTemplateFeatureTest extends TestCase
{
    public function test_message_templates_can_be_listed_and_created(): void
    {
        $response = $this->getJson('/api/message-templates');
        $response->assertOk();

        $createResponse = $this->postJson('/api/message-templates', [
            'company_id' => null,
            'name' => 'Welcome Offer',
            'content' => 'Hi {{name}}, welcome to our store!',
        ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('data.name', 'Welcome Offer')
            ->assertJsonPath('data.content', 'Hi {{name}}, welcome to our store!');
    }

    public function test_message_templates_can_be_updated_and_deleted(): void
    {
        $this->postJson('/api/message-templates', [
            'company_id' => null,
            'name' => 'Follow Up',
            'content' => 'Hello {{name}}',
        ]);

        $template = $this->getJson('/api/message-templates')->json('data.0');

        $updateResponse = $this->putJson('/api/message-templates/'.$template['id'], [
            'name' => 'Follow Up Updated',
            'content' => 'Hi {{name}}, here is your reminder.',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.name', 'Follow Up Updated');

        $deleteResponse = $this->deleteJson('/api/message-templates/'.$template['id']);
        $deleteResponse->assertOk();
    }
}
