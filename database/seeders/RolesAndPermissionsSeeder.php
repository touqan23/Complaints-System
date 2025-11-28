<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        ///بعمل هيك لانو لارفيل بتجيب الرولز من الكاش فدايما اي تعديل على الرولز والبرميشن بحدث الكاش
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        /*
        |--------------------------------------------------------------------------
        | Define Permissions
        |--------------------------------------------------------------------------
        */

        $permissions = [
            // Citizen
            'submit complaints',
            'view own complaints',

            // Employee
            'view complaints of entity',
            'update complaint status',
            'add complaint notes',

            // Admin
            'manage users',
            'manage all complaints',
            'export reports',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        /*
        |--------------------------------------------------------------------------
        | Define Roles
        |--------------------------------------------------------------------------
        */

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $employee = Role::firstOrCreate(['name' => 'employee']);
        $citizen = Role::firstOrCreate(['name' => 'citizen']);

        /*
        |--------------------------------------------------------------------------
        | Assign Permissions to Roles
        |--------------------------------------------------------------------------
        */

        // Admin gets all permissions
        $admin->givePermissionTo(Permission::all());

        // Employee permissions
        $employee->givePermissionTo([
            'view complaints of entity',
            'update complaint status',
            'add complaint notes',
        ]);

        // Citizen permissions
        $citizen->givePermissionTo([
            'submit complaints',
            'view own complaints',
        ]);

        // Refresh cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
