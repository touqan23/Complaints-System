<?php

namespace App\Repositories\Interfaces;

use App\Models\Employee;
use App\Models\User;

interface EmployeeRepositoryInterface extends BaseRepositoryInterface {

    public function updateEmployee(Employee $employee, array $data);
}
