<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
class EmployeeUpdateRequest extends FormRequest
{
    public function rules()
    {
        //$employeeId = $this->route('admin'); // from route model binding

        return [
            'employee_id' => 'required|exists:employees,id',
            'f_name'        => 'sometimes|required|string|max:50',
            'l_name'        => 'sometimes|required|string|max:50',
            'phone_number'  => "sometimes|required|string|digits:9|unique:users,phone_number",
            'serial_number' => "sometimes|required|integer|unique:employees,serial_number",
            'department_id' => 'sometimes|required|exists:departments,id',
        ];
    }
}

