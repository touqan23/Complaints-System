<?php

namespace App\Services;

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
    public function __construct(private Messaging $messaging, LoggingService $logs, UserRepository $userRepo ) {
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

    public function sendFCM(User $user, string $title, string $body, array $data = [])
    {

        if (!$user->fcm_token) {
            return ['status' => 'failed', 'error' => 'No token'];
        }

        try {
            $message = CloudMessage::withTarget('token', $user->fcm_token)
                ->withNotification(FcmNotification::create($title, $body))
                ->withData($data);

            $result = $this->messaging->send($message);
            $this->logs->system($user,"Notification sending SUCCESS");
            //return $result;

            return [
                'status' => 'success',
                'result' => $result
            ];

        }catch (\Exception $exception){
            $this->logs->system($user,"Notification sending FAILED");
            return [
                'status' => 'failed',
                'error'  => $exception->getMessage()
            ];
        }
    }

    public function notifyUser(int $user_id, string $title, string $body, array $data = [])
    {
        $user = User::findOrFail($user_id);

        // Dispatch JOB (send first, store only on success)
        SendFirebaseNotificationJob::dispatch(
            $user_id,
            $title,
            $body,
            $user->fcm_token,
            $data
        );

        return ['status' => 'queued'];
//        $user = User::findOrFail($user_id);
//        // 1) Save notification in DB
//         $notification = $this->storeNotification($user_id, $title, $body, $data);
//        // 2) Dispatch JOB (send later)
//        SendFirebaseNotificationJob::dispatch( $title, $body, [$user->fcm_token],
//            array_merge($data, ['notification_id' => $notification->id]) );
//        return $notification;
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
