<?php

namespace App\Repositories\Cached;

use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use App\Models\User;

class CachedUserRepository extends CachedBaseRepository implements UserRepositoryInterface
{
    public function __construct(UserRepository $repository)
    { parent::__construct($repository); }


    protected function forgetUserCache(User $user): void
    {
        // by phone
        if ($user->phone_number) {
            Cache::forget("auth:user:identifier:{$user->phone_number}");
        }

        // by email
        if ($user->citizen?->email) {
            Cache::forget("auth:user:identifier:{$user->citizen->email}");
        }

        // notifications
        Cache::tags(['notifications'])->forget("user:{$user->id}:notifications");
    }
    protected function invalidateSpecificCache($model): void
    {
        if ($model instanceof User) {
            $this->forgetUserCache($model);
        }
    }

    // READ METHODS (CACHE)
    public function findByIdentifier(string $identifier)
    {
        return Cache::tags(['users'])->remember(
            "auth:user:$identifier",
            now()->addMinutes(10),
            fn () => $this->repository->findByIdentifier($identifier)
        );
    }

    public function getUserNotifications(User $user, int $limit = 20)
    {
        return Cache::tags(['notifications'])->remember(
            "user:{$user->id}:notifications",
            now()->addMinutes(3),
            fn () => $this->repository->getUserNotifications($user, $limit)
        );
    }


    //WRITE METHODS (INVALIDATE)
    public function createCitizenUser(array $data)
    {
        $user = $this->repository->createCitizenUser($data);
        $this->forgetUserCache($user);
        return $user;
    }

    public function createEmployeeUser(array $data)
    {
        $user = $this->repository->createEmployeeUser($data);
        $this->forgetUserCache($user);
        return $user;
    }

    public function updateEmployeeUser(User $user, array $data)
    {
        $updated = $this->repository->updateEmployeeUser($user, $data);
        $this->forgetUserCache($user);
        return $updated;
    }

    public function updateOtp(User $user, string $otp)
    {
        $result = $this->repository->updateOtp($user, $otp);
        $this->forgetUserCache($user);
        return $result;
    }

    public function updatePassword(User $user, string $password)
    {
        $result = $this->repository->updatePassword($user, $password);
        $this->forgetUserCache($user);
        return $result;
    }

    public function incrementFailedAttempts(User $user)
    {
        $this->repository->incrementFailedAttempts($user);
        $this->forgetUserCache($user);
    }

    public function resetFailedAttempts(User $user)
    {
        $this->repository->resetFailedAttempts($user);
        $this->forgetUserCache($user);
    }

    public function updateFCM(User $user, string $fcm_token)
    {
        $result = $this->repository->updateFCM($user, $fcm_token);
        $this->forgetUserCache($user);
        return $result;
    }

    //no cache methods
    public function isLocked(User $user)
    {
        return $user->lock_until && now()->lessThan($user->lock_until);
    }

    public function verifyOtp($model, string $otp)
    {
        return $model->otp === $otp && $model->otp_expires_at && $model->otp_expires_at->isFuture();
    }

}
