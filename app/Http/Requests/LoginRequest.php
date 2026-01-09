<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // لو المستخدم موظف (عنده serial_number)
        if ($this->filled('serial_number')) {
            return [
                'serial_number' => 'required|integer',
                'password' => 'required|string|min:6',
                'fcm_token' => 'required|string'
            ];
        }

        // لو مواطن (عنده identifier: email أو phone)
        return [
            'identifier' => 'required|string',
            'password' => 'required|string|min:6',
            'fcm_token' => 'required|string'
        ];
    }

    public function messages(): array
    {
        return [
            'serial_number.required' => 'رقم الموظف مطلوب.',
            'identifier.required' => 'يجب إدخال البريد أو رقم الهاتف.',
            'password.required' => 'كلمة المرور مطلوبة.',
        ];
    }
}
