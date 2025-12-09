<?php

namespace App\Services;

use App\Jobs\SendFirebaseNotificationJob;
use App\Models\Notification;
use App\Models\User;
use App\Repositories\Eloquent\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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

    /*public function notifyUser(int $user_id, string $title, string $body, array $data = [] ?? null)
    {
        print "in notify service";

        $user = User::find($user_id);
        if ($user->fcm_token) {
            //Save notification in DB
            $notification = Notification::create([
                'id'      => (string) Str::uuid(),
                'user_id' => $user_id,
                'title'   => $title,
                'body'    => $body,
                'data'    => $data,
            ]);

            SendFirebaseNotificationJob::dispatch(
                $title,
                $body,
                [$user->fcm_token],   // tokens array
                array_merge($data, ['notification_id' => $notification->id])
            );
            //Send push notification if FCM token exists
            $message = CloudMessage::withTarget('token', $user->fcm_token)
                ->withNotification(FcmNotification::create($title, $body))
                ->withData(array_merge($data, ['notification_id' => $notification->id]));

            try {
                $this->messaging->send($message);
                $this->logs->system($notification,"Notification send SUCCESS", [
                    "notification_id" => $notification->id,
                ]);
            } catch (\Exception $e) {
                // log but don’t break app
                $this->logs->system($notification,"Notification send FAILED", [
                    "notification_id" => $notification->id,
                ]);
                $notification->delete();
            }
        }

        return $notification;
    }*/

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

    /**
     * Actually send FCM push notification (Used inside JOB)
     */
    public function sendFCM(User $user, string $title, string $body, array $data = [])
    {
        if (!$user->fcm_token) {
            return;
        }

        $message = CloudMessage::withTarget('token', $user->fcm_token)
            ->withNotification(FcmNotification::create($title, $body))
            ->withData($data);

        return $this->messaging->send($message);
    }

    /**
     * Public method used by controllers/services
     * Saves notification + dispatches job
     */
    public function notifyUser(int $user_id, string $title, string $body, array $data = [])
    {
        $user = User::findOrFail($user_id);

        // 1) Save notification in DB
        $notification = $this->storeNotification($user_id, $title, $body, $data);

        // 2) Dispatch JOB (send later)
        \App\Jobs\SendFirebaseNotificationJob::dispatch(
            $title,
            $body,
            [$user->fcm_token],  // array of tokens
            array_merge($data, ['notification_id' => $notification->id])
        );

        return $notification;
    }

    public function getUserNotifications($user)
    {
        return $this->userRepo->getUserNotifications($user);
    }

    public function markAsRead(Notification $notification)
    {
        return $this->userRepo->update($notification, ['read_at' => now()]);
    }

    public function delete(Notification $notification)
    {
        return $this->userRepo->delete($notification);
    }
}
