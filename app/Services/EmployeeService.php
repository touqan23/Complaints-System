<?php

namespace App\Services;

use App\Models\Citizen;
use App\Models\Employee;
use App\Models\User;
use App\Repositories\Eloquent\EmployeeRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Interfaces\CitizenRepositoryInterface;
use App\Repositories\Interfaces\ActivityLogRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;


class EmployeeService{
    public $users, $employees, $logs;

    public function __construct(UserRepository $users, EmployeeRepository $employees, LoggingService $logs)
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

        $this->logs->admin($user, "Employee registration SUCCESS", [
            "user_id" => $user->id,
            "serial_number" => $data['serial_number'],
            "department_id" => $data['department_id'],
            "employee_name" => "{$user->f_name} {$user->l_name}"
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
        $this->logs->admin($employee, "Employee updated SUCCESS", [
            "employee_id" => $employee->id,
            "user_id" => $employee->user_id,
            "serial_number" => $employee->serial_number,
        ]);

        return $employee->load('user');
    }

    public function delete(Employee $employee)
    {
        $result = $this->users->delete($employee->user);

        $this->logs->admin($employee, "Employee deleted SUCCESS", [
            "employee_id" => $employee->id,
        ]);
        return $result;
    }

    public function findEmployee($id)
    {
        $employee = $this->employees->find($id);

        if ($employee) {
            $this->logs->admin($employee, "Employee find SUCCESS", [
                "employee_id" => $id
            ]);
        } else {
            $this->logs->adminWarning(null, "Employee find FAILED — not found", [
                "employee_id" => $id
            ]);
        }

        return $employee;
    }
}
