<?php

namespace App\Services;

use App\Enums\NotificationPlatform;
use App\Factories\FirebaseMessagingFactory;
use App\Jobs\SendFirebaseNotificationJob;
use App\Models\Notification;
use App\Models\User;
use App\Repositories\Eloquent\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;

class FirebaseNotificationService
{
    public $logs, $userRepo;
    public function __construct(LoggingService $logs, UserRepository $userRepo ) {
        $this->logs = $logs;
        $this->userRepo = $userRepo;
    }

    public function storeNotification(int $user_id, string $title, string $body, array $data = [])
    {
        return Notification::create([
            'id'      => (string) Str::uuid(),
            'user_id' => $user_id,
            'title'   => $title,
            'body'    => $body,
            'data'    => $data,
        ]);
    }

    public function sendFCM(User $user, NotificationPlatform $platform, string $title, string $body, array $data = [])
    {
        $this->logs->system(
            $user,
            'FCM notification sending attempt',
            [
                'title' => $title,
                'data'  => $data
            ]
        );
        if (!$user->fcm_token) {
            $this->logs->systemWarning(
                $user,
                'FCM notification failed - no token',
                ['user_id' => $user->id]
            );
            return ['status' => 'failed', 'error' => 'No token'];
        }

        $messaging = FirebaseMessagingFactory::make($platform);
        $message = CloudMessage::withTarget('token', $user->fcm_token)
            ->withNotification(FcmNotification::create($title, $body))
            ->withData($data);

        //$result = $this->messaging->send($message);
        $result = $messaging->send($message);
        $this->logs->system(
            $user,
            'FCM notification sent successfully',
            ['message_id' => $result]
        );
        //return $result;

        return [
            'status' => 'success',
            'result' => $result
        ];
    }

    public function notifyUser(int $user_id, NotificationPlatform $platform, string $title, string $body, array $data = [])
    {
        $user = User::findOrFail($user_id);

        $this->logs->system(
            $user,
            'FCM notification queued', [
            'title' => $title,
            'data'  => $data
        ]);

        // Dispatch JOB (send first, store only on success)
        SendFirebaseNotificationJob::dispatch(
            $user_id,
            $title,
            $body,
            $user->fcm_token,
            $platform->value,
            $data
        );

        return ['status' => 'queued'];
    }

    public function notifyAdmin()
    {
        try {
            $Admin_id = 1;
            $this->notifyUser(
                $Admin_id,
                NotificationPlatform::WEB,
                'System Error',
                'There is System Error happend , check it !!'
            );
        } catch (\Throwable $e) {
            logger()->error('Admin notification failed', [
                'exception' => $e->getMessage()
            ]);
        }
    }

    public function getUserNotifications($user)
    {
        return $this->userRepo->getUserNotifications($user);
    }

    public function markAsRead(Notification $notification)
    {
        return $this->userRepo->update($notification, ['is_read' => true]);
    }

    public function delete(Notification $notification)
    {
        return $this->userRepo->delete($notification);
    }
}
