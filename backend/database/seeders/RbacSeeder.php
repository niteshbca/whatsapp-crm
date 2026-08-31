<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'Dashboard Access', 'slug' => 'dashboard:view', 'description' => 'View dashboard', 'group' => 'dashboard'],
            ['name' => 'Manage Users', 'slug' => 'users:manage', 'description' => 'Manage users', 'group' => 'users'],
            ['name' => 'Manage Roles', 'slug' => 'roles:manage', 'description' => 'Manage roles', 'group' => 'roles'],
            ['name' => 'Manage Contacts', 'slug' => 'contacts:manage', 'description' => 'Manage contacts', 'group' => 'contacts'],
            ['name' => 'Create Campaigns', 'slug' => 'campaigns:create', 'description' => 'Create campaigns', 'group' => 'campaigns'],
            ['name' => 'Send Messages', 'slug' => 'messages:send', 'description' => 'Send messages', 'group' => 'messages'],
            ['name' => 'Manage WhatsApp', 'slug' => 'whatsapp:manage', 'description' => 'Manage WhatsApp accounts', 'group' => 'whatsapp'],
            ['name' => 'View Reports', 'slug' => 'reports:view', 'description' => 'View analytics and reports', 'group' => 'reports'],
        ];

        $createdPermissions = [];
        foreach ($permissions as $permission) {
            $createdPermissions[] = Permission::firstOrCreate(
                ['slug' => $permission['slug']],
                $permission
            );
        }

        $roles = [
            ['name' => 'Admin', 'slug' => 'admin', 'description' => 'Full access', 'is_system' => true],
            ['name' => 'Manager', 'slug' => 'manager', 'description' => 'Department and campaign management', 'is_system' => true],
            ['name' => 'Employee', 'slug' => 'employee', 'description' => 'Restricted operations', 'is_system' => true],
            ['name' => 'Viewer', 'slug' => 'viewer', 'description' => 'Read-only access', 'is_system' => true],
        ];

        $permissionCollection = collect($createdPermissions);

        foreach ($roles as $roleData) {
            $role = Role::firstOrCreate(
                ['slug' => $roleData['slug']],
                $roleData
            );

            if ($role->slug === 'admin') {
                $role->permissions()->sync($permissionCollection->pluck('id')->all());
            }
            if ($role->slug === 'manager') {
                $role->permissions()->sync(
                    $permissionCollection->whereIn('slug', ['dashboard:view', 'contacts:manage', 'campaigns:create', 'messages:send', 'reports:view'])->pluck('id')->all()
                );
            }
            if ($role->slug === 'employee') {
                $role->permissions()->sync(
                    $permissionCollection->whereIn('slug', ['dashboard:view', 'contacts:manage', 'messages:send'])->pluck('id')->all()
                );
            }
            if ($role->slug === 'viewer') {
                $role->permissions()->sync(
                    $permissionCollection->whereIn('slug', ['dashboard:view', 'reports:view'])->pluck('id')->all()
                );
            }
        }
    }
}
