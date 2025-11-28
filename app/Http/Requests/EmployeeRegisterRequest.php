<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeRegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
            return [
                'f_name' => 'required|string|max:50',
                'l_name' => 'required|string|max:50',
                'phone_number' => 'required|string|digits:9|unique:users,phone_number',
                'serial_number' => 'required|integer|unique:employees,serial_number',
                'department_id' => 'required|exists:departments,id',
                'password' => 'required|string|min:6|confirmed',
            ];
    }
}
