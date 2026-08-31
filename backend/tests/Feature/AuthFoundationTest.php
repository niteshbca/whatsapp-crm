<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_and_receive_profile(): void
    {
        $company = Company::create([
            'name' => 'Acme CRM',
            'slug' => 'acme-crm',
            'status' => 'active',
        ]);

        $user = User::create([
            'company_id' => $company->id,
            'name' => 'System Admin',
            'email' => 'admin@acme.test',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'admin@acme.test',
            'password' => 'secret123',
        ]);

        $response->assertOk();
        $response->assertJsonPath('user.id', $user->id);
        $response->assertJsonPath('user.company_id', $company->id);
        $response->assertJsonPath('user.role', 'admin');
    }

    public function test_authenticated_user_can_fetch_profile(): void
    {
        $company = Company::create([
            'name' => 'Acme CRM',
            'slug' => 'acme-crm-2',
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->actingAs($user, 'web');

        $response = $this->getJson('/api/me');

        $response->assertOk();
        $response->assertJsonPath('company_id', $company->id);
        $response->assertJsonPath('role', 'employee');
    }
}
