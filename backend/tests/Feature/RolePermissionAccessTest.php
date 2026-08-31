<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RolePermissionAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_admin_route(): void
    {
        $company = Company::create([
            'name' => 'Acme CRM',
            'slug' => 'acme-crm',
            'status' => 'active',
        ]);

        $user = User::create([
            'company_id' => $company->id,
            'name' => 'Admin User',
            'email' => 'admin2@acme.test',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->actingAs($user, 'web');

        $response = $this->getJson('/api/admin/users');

        $response->assertOk();
        $response->assertJsonPath('role', 'admin');
    }

    public function test_employee_cannot_access_admin_route(): void
    {
        $company = Company::create([
            'name' => 'Acme CRM',
            'slug' => 'acme-crm-2',
            'status' => 'active',
        ]);

        $user = User::create([
            'company_id' => $company->id,
            'name' => 'Employee User',
            'email' => 'employee@acme.test',
            'password' => Hash::make('secret123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->actingAs($user, 'web');

        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(403);
    }
}
