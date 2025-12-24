<?php

namespace App\Services;

use App\Models\Citizen;
use App\Models\User;
use App\Repositories\Interfaces\CitizenRepositoryInterface;
use App\Repositories\Interfaces\EmployeeRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;


class AuthService {
    protected $userRepository, $citizenRepository, $employeeRepository,
        $otpService , $logs, $notification;

    public function __construct(
        UserRepositoryInterface $userRepository,
        CitizenRepositoryInterface $citizenRepository,
        EmployeeRepositoryInterface $employeeRepository,
        OtpService $otpService,
        LoggingService $logs,
        FirebaseNotificationService $notification
    ){
        $this->userRepository = $userRepository;
        $this->citizenRepository = $citizenRepository;
        $this->employeeRepository = $employeeRepository;
        $this->otpService = $otpService;
        $this->logs = $logs;
        $this->notification = $notification;
    }

    /*public function Elogin(int $serial, string $password)
    {
        $employee = $this->employeeRepository->findBy('serial_number',$serial);

        if (!$employee || !Hash::check($password, $employee->user->password)) {
            $this->logs->auth("Employee login FAILED", [
                "serial" => $serial
            ]);
            return null;
        }

        $employee->update(['last_login_at' => Carbon::now()]);
        $token = $employee->user->createToken('auth_token')->plainTextToken;
        $employee->load('user');

        $this->logs->auth("Employee login SUCCESS", [
            "employee_id" => $employee->id,
            "serial"      => $serial
        ]);

        $employee->user->token = $token;
        return $employee;
    }*/
    /*public function Clogin(string $identifier, string $password)
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $citizen = $this->citizenRepository->findBy('email', $identifier);
            $user = $citizen?->user;
        } elseif (preg_match('/^\+963\d{9}$/', $identifier)) { //'/^\+963\d{9}$/'   '/^09\d{8}$/'
            $user = $this->userRepository->findBy('phone_number',$identifier);
        } else {
            $this->logs->auth("Citizen login FAILED — invalid identifier format", [
                "identifier" => $identifier
            ]);

            return null;
        }

        if (!$user || !Hash::check($password, $user->password)) {
            $this->logs->auth("Citizen login FAILED — invalid credentials", [
                "identifier" => $identifier
            ]);
            return null;
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $this->logs->auth("Citizen login SUCCESS", [
            "user_id" => $user->id,
            "identifier" => $identifier
        ]);

        $user->token = $token;
        $user->load('citizen');

        return $user;
    }*/
    public function Elogin(int $serial, string $password/*, string $FCM*/)
    {
        $employee = $this->employeeRepository->findBy('serial_number', $serial);
        $user = $employee?->user;


      
        if ($user && $this->userRepository->isLocked($user)) {//check if is locked
            $this->logs->auth($employee,"LOGIN BLOCKED — account locked", [
                "serial" => $serial
            ]);

            $this->notification->notifyUser(
                $user,
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

        if (!$employee || !Hash::check($password, $user->password)) {//enter invalid credental

            if ($user) {
                $this->userRepository->incrementFailedAttempts($user);
            }

            $this->logs->auth($employee,"Employee login FAILED", ["serial" => $serial]);

            return null;
        }

        //Success → reset failed attempts
        //$this->userRepository->updateFCM($user, $FCM);
        $this->userRepository->resetFailedAttempts($user);

        $employee->update(['last_login_at' => now()]);
        $token = $user->createToken('auth_token')->plainTextToken;
        $employee->load('user');

        $this->logs->auth($employee,"Employee login SUCCESS", [
            "employee_id" => $employee->id,
            "serial"      => $serial
        ]);

        $employee->user->token = $token;
        return $employee;
    }

    public function Clogin(string $identifier, string $password, string $FCM)
    {
        usleep(200);//0.2 sec delay
        $user = null;

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $citizen = $this->citizenRepository->findBy('email', $identifier);
            $user = $citizen?->user;
        } elseif (preg_match('/^\+963\d{9}$/', $identifier)) {
            $user = $this->userRepository->findBy('phone_number', $identifier);
        }


        if ($user && $this->userRepository->isLocked($user)) {// Check if locked
            $this->logs->auth($user,"LOGIN BLOCKED — account locked", [
                "identifier" => $identifier
            ]);

            $this->notification->notifyUser(
                $user,
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

        if (!$user || !Hash::check($password, $user->password)) {//invalid credentials
            if ($user) {
                $this->userRepository->incrementFailedAttempts($user);
            }

            $this->logs->auth($user,"Citizen login FAILED — invalid credentials", [
                "identifier" => $identifier
            ]);
            return null;
        }

        // success
        $this->userRepository->updateFCM($user, $FCM);
        $this->userRepository->resetFailedAttempts($user);

        $token = $user->createToken('auth_token')->plainTextToken;

        $this->logs->auth($user,"Citizen login SUCCESS", [
            "user_id" => $user->id,
            "identifier" => $identifier
        ]);

        $user->token = $token;
        $user->load('citizen');
        return $user;
    }

    public function registerCitizen(array $data)
    {
        $this->logs->auth(new User(),"Citizen registration STARTED", [
            "identifier" => $data['identifier']
        ]);

        $identifier = $data['identifier'];

        Cache::put("pending_register:$identifier", $data, now()->addMinutes(15));

        $otp = rand(1000, 9999);
        Cache::put("otp:$identifier", $otp, now()->addMinutes(5));

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            //$this->otpService->sendEmail($identifier, $otp);
            $email = $identifier;
        } else {
            //$this->otpService->sendSMS($identifier, $otp);
            $phone = $identifier;
        }

        $this->logs->auth(new user(),"Citizen OTP SENT for registration", [
            "identifier" => $identifier
        ]);

        return ['message' => 'OTP sent to your identifier', 'otp' => $otp];
    }

    public function verifyRegisterOtp(string $identifier, string $inputOtp)
    {
        $cachedOtp = Cache::get("otp:$identifier");
        $pending = Cache::get("pending_register:$identifier");

        if (!$cachedOtp || !$pending) {
            $this->logs->auth(new Citizen(),"Citizen registration OTP FAILED — expired", [
                "identifier" => $identifier
            ]);
            return ['error' => 'OTP expired or invalid session'];
        }

        if ($cachedOtp != $inputOtp) {
            $this->logs->auth(new Citizen(),"Citizen registration OTP FAILED — wrong OTP", [
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
        $this->logs->auth($user,"Citizen registration SUCCESS", [
            "user_id" => $user->id
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
            $this->logs->system($user,"Send OTP FAILED — user not found", [
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

        $this->logs->system($user,"OTP SENT", [
            "user_id" => $user->id,
            "identifier" => $identifier
        ]);
        return $otp;
    }

    public function verifyOtp(string $identifier, string $otp)
    {
        $user = $this->userRepository->findByIdentifier($identifier);

        if (!$user) {
            $this->logs->auth($user,"OTP verify FAILED — no user", [
                "identifier" => $identifier
            ]);
            return ['error' => 'User not found'];
        }

        if (!$this->userRepository->verifyOtp($user, $otp)) {
            $this->logs->auth($user,"OTP verify FAILED — wrong/expired OTP", [
                "identifier" => $identifier
            ]);
            return ['error' => 'Invalid or expired OTP'];
        }

        $this->userRepository->update($user, [
            'otp' => null,
            'otp_expires_at' =>null
        ]);

        $this->logs->auth($user,"OTP verified SUCCESS", [
            "user_id" => $user->id
        ]);
        return ['message' => 'OTP verified successfully'];
    }

    public function resetPassword(string $identifier, string $newPassword)
    {
        $user = $this->userRepository->findByIdentifier($identifier);

        if (!$user) {
            $this->logs->auth($user,"Password reset FAILED — no user found", [
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

