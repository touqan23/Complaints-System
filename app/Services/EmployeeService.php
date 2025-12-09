<?php

namespace App\Services;

use App\Models\Citizen;
use App\Models\Employee;
use App\Models\User;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Interfaces\CitizenRepositoryInterface;
use App\Repositories\Interfaces\EmployeeRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;


class EmployeeService{
    public $users, $employees, $logs;

    public function __construct(UserRepositoryInterface $users,EmployeeRepositoryInterface $employees, LoggingService $logs)
    {
        $this->users = $users;
        $this->employees = $employees;
        $this->logs = $logs;
    }

    public function registerEmployee(array $data)
    {
        $user = $this->users->createEmployeeUser($data);

        $user->assignRole('employee');

        $this->employees->create([
            'user_id' => $user->id,
            'department_id' => $data['department_id'],
            'serial_number' => $data['serial_number'],
        ]);
        $user->load('employee', 'roles', 'permissions');

        $this->logs->admin($user," Add Employee SUCCESS", [
            "serial_number" => $data['serial_number']
        ]);
        return ['user' => $user,];
    }

    public function update(Employee $employee, array $data)
    {
        // user related fields
        $userFields = [
            'f_name',
            'l_name',
            'phone_number'
        ];

        // employee related fields
        $employeeFields = [
            'serial_number',
            'department_id'
        ];

        // update user if required
        $userData = collect($data)->only($userFields)->toArray();
        if (!empty($userData)) {
            $this->users->updateEmployeeUser($employee->user, $userData);
        }

        // update employee if required
        $employeeData = collect($data)->only($employeeFields)->toArray();
        if (!empty($employeeData)) {
            $employee = $this->employees->updateEmployee($employee, $employeeData);
        }

        return $employee->load('user');
    }

    public function delete(Employee $employee)
    {
        return $this->users->delete($employee->user);
    }

    public function findEmployee($id)
    {
        return $this->employees->find($id);
    }
}
