<?php

namespace App\Repositories\Eloquent;

use App\Models\Citizen;
use App\Models\Notification;
use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Services\LoggingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class UserRepository extends BaseRepository implements UserRepositoryInterface {

    public function __construct(User $user)
    {
        parent::__construct($user);
    }

    public function findByIdentifier(string $identifier)
    {
        // Citizen by email
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return User::whereHas('citizen', function ($q) use ($identifier) {
                $q->where('email', $identifier);
            })->first();
        }

        // Citizen by phone
        if (preg_match('/^\+963\d{9}$/', $identifier)) { //'/^09\d{8}$/'  //'/^\+963\d{9}$/'
            return User::where('phone_number', $identifier)->first();
        }

        // Employee by serial number
        if (is_numeric($identifier)) {
            return User::whereHas('employee', function ($q) use ($identifier) {
                $q->where('serial_number', $identifier);
            })->first();
        }

        return null;
    }

    public function createCitizenUser(array $data)
    {
        $userData = [
            'f_name' => $data['f_name'],
            'l_name' => $data['l_name'],
            'password' => Hash::make($data['password']),
            'role_id' => 3,
        ];

        if (filter_var($data['identifier'], FILTER_VALIDATE_EMAIL)) {
            $userData['phone_number'] = null;
        } else {
            $userData['phone_number'] = $data['identifier'];
        }

        return User::create($userData);
    }

    public function createEmployeeUser(array $data)
    {
        return User::create([
            'f_name' => $data['f_name'],
            'l_name' => $data['l_name'],
            'phone_number' => $data['phone_number'],
            'password' => Hash::make($data['password']),
            'role_id' => 2,
        ]);
    }

    public function updateEmployeeUser(User $user, array $data)
    {
        $filtered = collect($data)->only([
            'f_name',
            'l_name',
            'phone_number'
        ])->filter()->toArray();

        if (!empty($filtered)) {
            $user->update($filtered);
        }

        return $user->fresh();
    }

    public function updateOtp(User $user, string $otp)
    {
        return $this->update($user, [
            'otp' => $otp,
            'otp_expires_at' => now()->addMinutes(10),
        ]);
    }

    public function verifyOtp($model, string $otp)
    {
        return $model->otp === $otp
            && $model->otp_expires_at
            && $model->otp_expires_at->isFuture();
    }

    public function updatePassword(User $user, string $password)
    {
        return $this->update($user, [
            'password' => Hash::make($password),
        ]);
    }

    public function incrementFailedAttempts(User $user)
    {
        $user->failed_attempts++;

        if ($user->failed_attempts >= 3) {
            $user->lock_until = now()->addMinutes(5);
            app(LoggingService::class)->auth("ACCOUNT LOCKED — too many attempts", [
                "user_id" => $user->id,
                "locked_until" => $user->lock_until
            ]);
        }

        $user->save();
    }

    public function resetFailedAttempts(User $user)
    {
         $this->update($user, [
            'failed_attempts' => 0,
            'lock_until' => null,
        ]);
    }

    public function isLocked(User $user)
    {
        return $user->lock_until && now()->lessThan($user->lock_until);
    }

    public function updateFCM(User $user, string $fcm_token)
    {
        return $this->update($user, [
            'fcm_token' => $fcm_token
        ]);
    }

    public function getUserNotifications(User $user, int $limit = 20)
    {
        return Notification::where('user_id', $user->id)
            ->latest()
            ->take($limit)
            ->get();
    }


}

