<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;

class CitizenRegisterRequest extends FormRequest
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
            'national_number' => 'required|string|digits:11|unique:citizens,national_number',
            'identifier' => [
                'required', 'string',
                function ($attribute, $value, $fail) {
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL) && !preg_match('/^09\d{8}$/', $value)) {
                        $fail('The identifier must be a valid email or Syrian phone number.');
                    }
                    if (Cache::has("pending_register:$value")) {
                        $fail('Registration already in progress. Please complete OTP verification.');
                    }
                },
            ],
            'password' => 'required|string|min:6|confirmed',
        ];
    }
}
