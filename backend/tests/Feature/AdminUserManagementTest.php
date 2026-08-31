<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_employee_user(): void
    {
        $company = Company::create([
            'name' => 'Acme CRM',
            'slug' => 'acme-crm',
            'status' => 'active',
        ]);

        $admin = User::create([
            'company_id' => $company->id,
            'name' => 'Owner',
            'email' => 'owner@acme.test',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'status' => 'active',
            'permissions' => ['users:read', 'users:write'],
        ]);

        $this->actingAs($admin, 'web');

        $response = $this->postJson('/api/admin/users', [
            'company_id' => $company->id,
            'name' => 'Agent One',
            'email' => 'agent1@acme.test',
            'password' => 'secret123',
            'role' => 'employee',
            'status' => 'active',
            'permissions' => ['campaigns:read', 'messages:write'],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Agent One')
            ->assertJsonPath('data.role', 'employee');

        $this->assertDatabaseHas('users', [
            'email' => 'agent1@acme.test',
            'company_id' => $company->id,
            'role' => 'employee',
        ]);
    }

    public function test_admin_can_list_company_users(): void
    {
        $company = Company::create([
            'name' => 'Flow CRM',
            'slug' => 'flow-crm',
            'status' => 'active',
        ]);

        $admin = User::create([
            'company_id' => $company->id,
            'name' => 'Admin',
            'email' => 'admin@flow.test',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        User::create([
            'company_id' => $company->id,
            'name' => 'Manager',
            'email' => 'manager@flow.test',
            'password' => Hash::make('secret123'),
            'role' => 'manager',
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'web');

        $response = $this->getJson('/api/admin/users');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }
}
