<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserManagementExtendedTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_user_role_and_permissions(): void
    {
        $company = Company::create([
            'name' => 'Ops Labs',
            'slug' => 'ops-labs',
            'status' => 'active',
        ]);

        $admin = User::create([
            'company_id' => $company->id,
            'name' => 'Admin',
            'email' => 'admin@ops.test',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $employee = User::create([
            'company_id' => $company->id,
            'name' => 'Agent',
            'email' => 'agent@ops.test',
            'password' => Hash::make('secret123'),
            'role' => 'employee',
            'status' => 'active',
            'permissions' => ['messages:send'],
        ]);

        $this->actingAs($admin, 'web');

        $response = $this->putJson('/api/admin/users/'.$employee->id, [
            'role' => 'manager',
            'status' => 'inactive',
            'permissions' => ['campaigns:create', 'reports:view'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.role', 'manager')
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('data.permissions.0', 'campaigns:create');

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'role' => 'manager',
            'status' => 'inactive',
        ]);
    }

    public function test_admin_can_delete_company_user(): void
    {
        $company = Company::create([
            'name' => 'Flow CRM',
            'slug' => 'flow-crm',
            'status' => 'active',
        ]);

        $admin = User::create([
            'company_id' => $company->id,
            'name' => 'Owner',
            'email' => 'owner@flow.test',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $employee = User::create([
            'company_id' => $company->id,
            'name' => 'Support',
            'email' => 'support@flow.test',
            'password' => Hash::make('secret123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'web');

        $response = $this->deleteJson('/api/admin/users/'.$employee->id);

        $response->assertOk();
        $this->assertDatabaseMissing('users', [
            'id' => $employee->id,
        ]);
    }
}
