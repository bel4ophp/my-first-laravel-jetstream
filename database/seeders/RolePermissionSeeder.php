<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Define the Permissions
        $permissions = [
            // JETSTREAM INTERNAL (Required for the UI to work)
            'create'              => 'Create Access',
            'read'                => 'Read Access',
            'update'              => 'Update Access',
            'delete'              => 'Delete Access',
            'view-any'            => 'View Any Access',
            'remove-team-member'  => 'Remove Team Member Access',
            'update-team-member'  => 'Update Team Member Access',
            'add-team-member'     => 'Add Team Member Access',
            // Attendance
            'view-attendance'     => 'View Attendance Access',
            'create-time-entries' => 'Create Time Entries Access',
            'update-time-entries' => 'Update Time Entries Access',
        ];

        $permissionModels = [];
        foreach ($permissions as $key => $name) {
            $permissionModels[$key] = Permission::updateOrCreate(
                ['key' => $key],
                ['name' => $name]
            );
        }

        // Define the Roles
        $admin = Role::updateOrCreate(['key' => 'admin'], ['name' => 'Administrator']);
        $manager = Role::updateOrCreate(['key' => 'manager'], ['name' => 'Manager']);
        $employee = Role::updateOrCreate(['key' => 'employee'], ['name' => 'Employee']);

        // Assign Permissions to Roles

        // Admin: Everything
        $admin->permissions()->sync(collect($permissionModels)->pluck('id'));

        // Manager
        $manager->permissions()->sync([
            $permissionModels['read']->id,
            $permissionModels['update']->id,
            $permissionModels['add-team-member']->id,
            $permissionModels['update-team-member']->id,
            $permissionModels['remove-team-member']->id,
            $permissionModels['view-attendance']->id,
            $permissionModels['create-time-entries']->id,
            $permissionModels['update-time-entries']->id,
        ]);

        // Employee: read-only, own data visible via policy ownership check
        $employee->permissions()->sync([
            $permissionModels['read']->id,
        ]);
    }
}
