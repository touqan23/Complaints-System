<?php

namespace App\Services;

use App\Repositories\Interfaces\CitizenRepositoryInterface;
use App\Repositories\Interfaces\EmployeeRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;


class AuthService {
    protected $userRepository, $citizenRepository,
                $employeeRepository, $otpService;

    public function __construct(
        UserRepositoryInterface $userRepository,
        CitizenRepositoryInterface $citizenRepository,
        EmployeeRepositoryInterface $employeeRepository,
        OtpService $otpService
    ){
        $this->userRepository = $userRepository;
        $this->citizenRepository = $citizenRepository;
        $this->employeeRepository = $employeeRepository;
        $this->otpService = $otpService;
    }

    public function Elogin(int $serial, string $password)
    {
        $employee = $this->employeeRepository->findBy('serial_number',$serial);

        if (!$employee || !Hash::check($password, $employee->user->password)) {
            return null;
        }

        $employee->update(['last_login_at' => Carbon::now()]);
        $token = $employee->user->createToken('auth_token')->plainTextToken;
        $employee->load('user');

        $employee->user->token = $token;

       // $employee->load('user');

        return $employee;
    }

    public function Clogin(string $identifier, string $password)
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $citizen = $this->citizenRepository->findBy('email', $identifier);
            $user = $citizen?->user;
        } elseif (preg_match('/^\+963\d{9}$/', $identifier)) { //'/^\+963\d{9}$/'   '/^09\d{8}$/'
            $user = $this->userRepository->findBy('phone_number',$identifier);
        } else {
            return null;
        }

        if (!$user || !Hash::check($password, $user->password)) {
            return null;
        }

        $user->update(['last_login_at' => Carbon::now()]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $user->token = $token;
        $user->load('citizen');

        return $user;
    }

    public function registerCitizen(array $data)
    {
        $identifier = $data['identifier'];

        Cache::put("pending_register:$identifier", $data, now()->addMinutes(15));

        $otp = rand(1000, 9999);
        Cache::put("otp:$identifier", $otp, now()->addMinutes(5));

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $this->otpService->sendEmail($identifier, $otp);
        } else {
            //$this->otpService->sendSMS($identifier, $otp);
            $phone = $identifier;
        }

        return ['message' => 'OTP sent to your identifier'];
    }

    public function registerEmployee(array $data)
    {
        $user = $this->userRepository->createEmployeeUser($data);

        $user->assignRole('employee');

        $this->employeeRepository->create([
            'user_id' => $user->id,
            'department_id' => $data['department_id'],
            'serial_number' => $data['serial_number'],
        ]);
        $user->load('employee', 'roles', 'permissions');

        return ['user' => $user,];
    }

    public function employeePhoneNumber(int $serial)
    {
        $employee = $this->employeeRepository->findBy('serial_number',$serial);
        return $employee->serial_number;
    }

    public function verifyRegisterOtp(string $identifier, string $inputOtp)
    {
        $cachedOtp = Cache::get("otp:$identifier");
        $pending = Cache::get("pending_register:$identifier");

        if (!$cachedOtp || !$pending) {
            return ['error' => 'OTP expired or invalid session'];
        }

        if ($cachedOtp != $inputOtp) {
            return ['error' => 'Invalid OTP'];
        }

        // Create User
        $user = $this->userRepository->createCitizenUser($pending);
        $user->assignRole('citizen');

        // Create Citizen
        $this->citizenRepository->create([
            'national_number' => $pending['national_number'],
            'identifier' => $pending['identifier'],
            'user_id' => $user->id
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;
        $user->token = $token;
        $user->load('citizen');

        Cache::forget("pending_register:$identifier");
        Cache::forget("otp:$identifier");

        return [
            'message' => 'Citizen registered successfully',
            'user' => $user
        ];
    }

public function sendOtp($identifier){

        $otp = rand(1000, 9999);
        $user = $this->userRepository->findByIdentifier($identifier);

        if (!$user) {
            return ['error' => 'User not found'];
        }
        $this->userRepository->updateOtp($user, $otp);

        if ($user->citizen && $user->citizen->email) {//ارسال ايميل
            $this->otpService->sendEmail($user->citizen->email, $otp);
        } elseif ($user->phone_number) {//ارسال sms
            //$this->otpService->sendSMS($user->phone_number, $otp);
            $phone = $user->phone_number;
        }
        return $otp;
    }

    public function verifyOtp(string $identifier, string $otp)
    {
        $user = $this->userRepository->findByIdentifier($identifier);

        if (!$user) {
            return ['error' => 'User not found'];
        }

        if (!$this->userRepository->verifyOtp($user, $otp)) {
            return ['error' => 'Invalid or expired OTP'];
        }

        $this->userRepository->update($user, [
            'otp' => null,
            'otp_expires_at' =>null
        ]);
        return ['message' => 'OTP verified successfully'];
    }

    public function resetPassword(string $identifier, string $newPassword)
    {
        $user = $this->userRepository->findByIdentifier($identifier);

        if (!$user) {
            return ['error' => 'User not found'];
        }

        $this->userRepository->updatePassword($user, $newPassword);

        return ['message' => 'Password reset successfully'];
    }
}

