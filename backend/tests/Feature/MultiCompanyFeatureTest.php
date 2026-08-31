<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MultiCompanyFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_company_and_api_key(): void
    {
        $response = $this->postJson('/api/companies', [
            'name' => 'Acme Co',
            'slug' => 'acme-co',
            'status' => 'active',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Acme Co');

        $companyId = $response->json('data.id');

        $this->assertDatabaseHas('companies', [
            'slug' => 'acme-co',
            'name' => 'Acme Co',
        ]);

        $keyResponse = $this->postJson('/api/api-keys', [
            'company_id' => $companyId,
            'name' => 'Primary API Key',
            'permissions' => ['send_messages', 'campaigns:read'],
        ]);

        $keyResponse->assertStatus(201)
            ->assertJsonPath('data.name', 'Primary API Key');

        $this->assertDatabaseHas('api_keys', [
            'company_id' => $companyId,
            'name' => 'Primary API Key',
        ]);
    }

    public function test_company_can_connect_whatsapp_number(): void
    {
        $company = \App\Models\Company::create([
            'name' => 'Ops Studio',
            'slug' => 'ops-studio',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/companies/'.$company->id.'/connect-number', [
            'label' => 'Main Number',
            'session_name' => 'ops-studio-session',
            'phone_number' => '15551234567',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.company_id', $company->id)
            ->assertJsonPath('data.label', 'Main Number')
            ->assertJsonPath('data.phone_number', '15551234567')
            ->assertJsonPath('data.status', 'disconnected');

        $this->assertDatabaseHas('whatsapp_accounts', [
            'company_id' => $company->id,
            'phone_number' => '15551234567',
            'session_name' => 'ops-studio-session',
            'status' => 'disconnected',
        ]);
    }

    public function test_company_logout_targets_only_that_company_session(): void
    {
        Http::fake([
            'http://127.0.0.1:3001/*' => Http::response(['ok' => true], 200),
        ]);

        $company = \App\Models\Company::create([
            'name' => 'Ops Studio',
            'slug' => 'ops-studio',
            'status' => 'active',
        ]);

        $this->postJson('/api/companies/'.$company->id.'/logout-number')
            ->assertOk();

        Http::assertSent(function ($request) use ($company) {
            return $request->url() === 'http://127.0.0.1:3001/api/logout'
                && $request['company_id'] === $company->id;
        });
    }
}
