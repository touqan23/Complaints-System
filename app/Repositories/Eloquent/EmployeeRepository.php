<?php

namespace App\Repositories\Eloquent;

use App\Models\Citizen;
use App\Models\Employee;
use App\Models\User;
use App\Repositories\Interfaces\CitizenRepositoryInterface;
use App\Repositories\Interfaces\EmployeeRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;

class EmployeeRepository extends BaseRepository implements EmployeeRepositoryInterface {

    public function __construct()
    {
        parent::__construct(Employee::class);
    }

    public function create(array $data)
    {
        return Employee::create([
            'user_id' => $data['user_id'],
            'department_id' => $data['department_id'],
            'serial_number' => $data['serial_number'],
            'status' => 'active',
        ]);
    }

}

