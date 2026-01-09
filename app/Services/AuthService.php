<?php

namespace App\Services;

use App\Enums\NotificationPlatform;
use App\Models\Citizen;
use App\Models\User;
use App\Repositories\Eloquent\EmployeeRepository;
use App\Repositories\Interfaces\CitizenRepositoryInterface;
use App\Repositories\Interfaces\ActivityLogRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;


class AuthService {
    protected $userRepository, $citizenRepository, $employeeRepository,
        $otpService , $logs, $notification;

    public function __construct(
        UserRepositoryInterface        $userRepository,
        CitizenRepositoryInterface     $citizenRepository,
        EmployeeRepository $employeeRepository,
        OtpService                     $otpService,
        LoggingService                 $logs,
        FirebaseNotificationService    $notification
    ){
        $this->userRepository = $userRepository;
        $this->citizenRepository = $citizenRepository;
        $this->employeeRepository = $employeeRepository;
        $this->otpService = $otpService;
        $this->logs = $logs;
        $this->notification = $notification;
    }

    public function Elogin(int $serial, string $password, string $FCM)
    {
        $employee = $this->employeeRepository->findBy('serial_number', $serial);
        $user = $employee?->user;

        if (!$employee || !$user|| !Hash::check($password, $user->password)) {//enter invalid credental

            if ($user) {
                $this->userRepository->incrementFailedAttempts($user);
                $this->logs->authWarning($employee, "Employee login FAILED — invalid credentials", [
                    "serial" => $serial,
                ]);
            }

            if ($user && $this->userRepository->isLocked($user)) {//check if is locked
                $this->logs->authWarning($employee, "LOGIN BLOCKED — account locked", [
                    "serial" => $serial,
                    "reason" => "exceeded_failed_attempts"
                ]);

                $this->notification->notifyUser(
                    $user->id,
                    NotificationPlatform::WEB,
                    "Your account has been temporarily locked",
                    "Your account has been temporarily locked because you exceeded the allowed number of login attempts.Please try again later.",
                    [
                        'type' => 'Locked Account',
                        'action' => 'login_failed',
                    ]
                );

                return [
                    'message' => 'Account locked. Try again later.',
                    'locked' => true,
                ];
            }
            return null;
        }


        //Success → reset failed attempts
        $this->userRepository->updateFCM($user, $FCM);
        $this->userRepository->resetFailedAttempts($user);

        $employee->update(['last_login_at' => now()]);
        $token = $user->createToken('auth_token')->plainTextToken;
        $employee->load('user');

        $this->logs->auth($employee, "Employee login SUCCESS", [
            "employee_id" => $employee->id,
            "serial" => $serial,
            "department_id" => $employee->department_id
        ]);

        $employee->user->token = $token;
        return $employee;
    }

    public function Clogin(string $identifier, string $password, string $FCM)
    {
        $user = null;

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $citizen = $this->citizenRepository->findBy('email', $identifier);
            $user = $citizen?->user;
        } elseif (preg_match('/^\+963\d{9}$/', $identifier)) {
            $user = $this->userRepository->findBy('phone_number', $identifier);
        }

        if (!$user || !Hash::check($password, $user->password)) {//invalid credentials
            if ($user) {
                $this->userRepository->incrementFailedAttempts($user);
                $this->logs->authWarning($user, "Citizen login FAILED — invalid credentials", [
                    "identifier" => $identifier,
                    "attempts_remaining" => $user ? (5 - $user->failed_login_attempts) : null
                ]);
            }

            if ($user && $this->userRepository->isLocked($user)) {// Check if locked
                $this->logs->authWarning($user, "LOGIN BLOCKED — account locked", [
                    "identifier" => $identifier,
                    "reason" => "exceeded_failed_attempts"
                ]);

                $this->notification->notifyUser(
                    $user->id,
                    NotificationPlatform::MOBILE,
                    "Your account has been temporarily locked",
                    "Your account has been temporarily locked because you exceeded the allowed number of login attempts.Please try again later.",
                    [
                        'type' => 'Locked Account',
                        'action' => 'login_failed',
                    ]
                );

                return [
                    'message' => 'Account locked. Try again later.',
                    'locked' => true,
                ];
            }
            return null;
        }

        // success
        $this->userRepository->updateFCM($user, $FCM);
        $this->userRepository->resetFailedAttempts($user);

        $token = $user->createToken('auth_token')->plainTextToken;

        $this->logs->auth($user, "Citizen login SUCCESS", [
            "user_id" => $user->id,
            "identifier" => $identifier
        ]);

        $user->token = $token;
        $user->load('citizen');
        return $user;
    }

    public function registerCitizen(array $data)
    {
        $this->logs->auth(null, "Citizen registration STARTED", [
            "identifier" => $data['identifier'],
            "national_number" => $data['national_number'] ?? null
        ]);

        $identifier = $data['identifier'];

        Cache::put("auth:pending_register:$identifier", $data, now()->addMinutes(15));

        $otp = rand(1000, 9999);
        Cache::put("auth:otp:$identifier", $otp, now()->addMinutes(5));

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            //$this->otpService->sendEmail($identifier, $otp);
            $email = $identifier;
        } else {
            //$this->otpService->sendSMS($identifier, $otp);
            $phone = $identifier;
        }

        $this->logs->auth(null, "Citizen registration OTP SENT", [
            "identifier" => $identifier,
            "otp_expires_at" => now()->addMinutes(5)->toDateTimeString()
        ]);

        return ['message' => 'OTP sent to your identifier', 'otp' => $otp];
    }

    public function verifyRegisterOtp(string $identifier, string $inputOtp)
    {
        $cachedOtp = Cache::get("auth:otp:$identifier");
        $pending = Cache::get("auth:pending_register:$identifier");

        if (!$cachedOtp || !$pending) {
            $this->logs->authWarning(null, "Citizen registration OTP FAILED — expired session", [
                "identifier" => $identifier,
                "has_cached_otp" => !empty($cachedOtp),
                "has_pending_data" => !empty($pending)
            ]);
            return ['error' => 'OTP expired or invalid session'];
        }

        if ($cachedOtp != $inputOtp) {
            $this->logs->authWarning(null, "Citizen registration OTP FAILED — wrong OTP", [
                "identifier" => $identifier
            ]);
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
        $this->logs->auth($user, "Citizen registration SUCCESS", [
            "user_id" => $user->id,
            "national_number" => $pending['national_number']
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;
        $user->token = $token;
        $user->load('citizen');

        Cache::forget("auth:pending_register:$identifier");
        Cache::forget("auth:otp:$identifier");

        return [
            'message' => 'Citizen registered successfully',
            'user' => $user
        ];
    }

    public function sendOtp($identifier)
    {
        $otp = rand(1000, 9999);
        $user = $this->userRepository->findByIdentifier($identifier);

        if (!$user) {
            $this->logs->systemWarning(null, "Send OTP FAILED — user not found", [
                "identifier" => $identifier
            ]);
            return ['error' => 'User not found'];
        }
        $this->userRepository->updateOtp($user, $otp);

        if ($user->citizen && $user->citizen->email) {//ارسال ايميل
            //$this->otpService->sendEmail($user->citizen->email, $otp);
            $email = $user->citizen->email;
        } elseif ($user->phone_number) {//ارسال sms
            //$this->otpService->sendSMS($user->phone_number, $otp);
            $phone = $user->phone_number;
        }

        $this->logs->system($user, "OTP SENT for password reset", [
            "user_id" => $user->id,
            "identifier" => $identifier,
            "otp_expires_at" => now()->addMinutes(5)->toDateTimeString()
        ]);
        return $otp;
    }

    public function verifyOtp(string $identifier, string $otp)
    {
        $user = $this->userRepository->findByIdentifier($identifier);

        if (!$user) {
            $this->logs->authWarning(null, "OTP verify FAILED — user not found", [
                "identifier" => $identifier
            ]);
            return ['error' => 'User not found'];
        }

        if (!$this->userRepository->verifyOtp($user, $otp)) {
            $this->logs->authWarning($user,"OTP verify FAILED — wrong/expired OTP", [
                "identifier" => $identifier
            ]);
            return ['error' => 'Invalid or expired OTP'];
        }

        $this->userRepository->update($user, [
            'otp' => null,
            'otp_expires_at' =>null
        ]);

        $this->logs->auth($user,"OTP verified SUCCESS", [
            "user_id" => $user->id,
            "identifier" => $identifier
        ]);
        return ['message' => 'OTP verified successfully'];
    }

    public function resetPassword(string $identifier, string $newPassword)
    {
        $user = $this->userRepository->findByIdentifier($identifier);

        if (!$user) {
            $this->logs->authWarning($user,"Password reset FAILED — no user found", [
                "identifier" => $identifier
            ]);
            return ['error' => 'User not found'];
        }

        $this->userRepository->updatePassword($user, $newPassword);
        $this->logs->auth($user,"Password reset SUCCESS", [
            "user_id" => $user->id
        ]);
        return ['message' => 'Password reset successfully'];
    }
}

