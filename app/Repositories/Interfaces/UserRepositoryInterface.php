<?php

namespace App\Repositories\Interfaces;

use App\Models\User;

interface UserRepositoryInterface extends BaseRepositoryInterface {

    public function createCitizenUser(array $data);
    public function createEmployeeUser(array $data);
    public function updateEmployeeUser(User $user, array $data);
    public function findByIdentifier(string $identifier);
    public function updateOtp(User $user, string $otp);
    public function verifyOtp($model, string $otp);
    public function updatePassword(User $user, string $password);
    public function incrementFailedAttempts(User $user);
    public function resetFailedAttempts(User $user);
    public function isLocked(User $user);
    public function updateFCM(User $user, string $fcm_token);
    public function getUserNotifications(User $user, int $limit = 20);
}
