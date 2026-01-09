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
        $adminRole    = Role::where('name', 'admin')->first();
        $employeeRole = Role::where('name', 'employee')->first();
        $citizenRole  = Role::where('name', 'citizen')->first();

        /*
        |--------------------------------------------------------------------------
        | 1) مستخدم المدير (Admin)
        |--------------------------------------------------------------------------
        */
        $admin = User::create([
            'f_name'       => 'المدير',
            'l_name'       => 'العام',
            'phone_number' => '+963700000001',
            'password'     => Hash::make('Admin123'),
            'role_id'      => $adminRole->id,
        ]);

        $admin->assignRole($adminRole);

        Employee::create([
            'user_id'       => $admin->id,
            'department_id' => 101,
            'serial_number' => 111111,
            'status'        => 'active',
        ]);

        /*
        |--------------------------------------------------------------------------
        | 2) مستخدمو المواطن (2)
        |--------------------------------------------------------------------------
        */
        for ($i = 1; $i <= 2; $i++) {

            $user = User::create([
                'f_name'       => "مواطن{$i}",
                'l_name'       => "سوري{$i}",
                'phone_number' => "+96370000010{$i}",
                'password'     => Hash::make("user@{$i}"),
                'role_id'      => $citizenRole->id,
            ]);

            Citizen::create([
                'national_number' => "1002003011{$i}",
                'email'           => "mowaten{$i}@example.com",
                'user_id'         => $user->id,
            ]);

            $user->assignRole($citizenRole);
        }

        /*
        |--------------------------------------------------------------------------
        | 3) مستخدمو الموظفين (5)
        |--------------------------------------------------------------------------
        */

        $departments = Department::pluck('id')->toArray();

        if (count($departments) < 5) {
            throw new \Exception("⚠ يجب إضافة 5 أقسام على الأقل قبل تشغيل AuthSeeder.");
        }

        for ($i = 1; $i <= 5; $i++) {

            $user = User::create([
                'f_name'       => "موظف{$i}",
                'l_name'       => "حكومي{$i}",
                'phone_number' => "+96370000020{$i}",
                'password'     => Hash::make("employee{$i}"),
                'role_id'      => $employeeRole->id,
            ]);

            Employee::create([
                'user_id'       => $user->id,
                'department_id' => $departments[$i - 1],
                'serial_number' => 5000 + $i,
                'status'        => 'active',
            ]);

            $user->assignRole($employeeRole);
        }
    }
}
