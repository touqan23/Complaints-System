<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Citizen;
use App\Models\Employee;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use App\Models\Department;

class AuthSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('name', 'admin')->first();
        $employeeRole = Role::where('name', 'employee')->first();
        $citizenRole = Role::where('name', 'citizen')->first();

        /*
        |--------------------------------------------------------------------------
        | 1) Admin User
        |--------------------------------------------------------------------------
        */
        $admin = User::create([
            'f_name'      => 'Admin',
            'l_name'      => 'User',
            'phone_number'=> '+963700000001',
            'password'    => Hash::make('Admin123'),
            'role_id'     => $adminRole->id,
        ]);

       Employee::create([
            'user_id'       => $admin->id,
            'department_id' => 1,  // Assign unique department
            'serial_number' => 111111,
            'status'        => 'active',
        ]);

        /*
        |--------------------------------------------------------------------------
        | 2) Citizen Users (2)
        |--------------------------------------------------------------------------
        */
        for ($i = 1; $i <= 2; $i++) {
            $user = User::create([
                'f_name'      => "Citizen{$i}",
                'l_name'      => "User{$i}",
                'phone_number'=> "+96370000010{$i}",
                'password'    => Hash::make("user@{$i}"),
                'role_id'     => $citizenRole->id,
            ]);

            Citizen::create([
                'national_number' => "1002003011{$i}",
                'email'           => "citizen{$i}@example.com",
                'user_id'         => $user->id,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 3) Employee Users (5 total: 2 original + 3 extra)
        |--------------------------------------------------------------------------
        */

        // Get all departments
        $departments = Department::pluck('id')->toArray();

        // Make sure you have at least 5 departments
        if (count($departments) < 5) {
            throw new \Exception("⚠ You must seed at least 5 departments before running AuthSeeder.");
        }

        // Create 5 employee users, each in a different department
        for ($i = 1; $i <= 5; $i++) {

            $user = User::create([
                'f_name'      => "Employee{$i}",
                'l_name'      => "Worker{$i}",
                'phone_number'=> "+96370000020{$i}",
                'password'    => Hash::make("employee{$i}"),
                'role_id'     => $employeeRole->id,
            ]);

            Employee::create([
                'user_id'       => $user->id,
                'department_id' => $departments[$i - 1],  // Assign unique department
                'serial_number' => 5000 + $i,
                'status'        => 'active',
            ]);
        }
    }
}
