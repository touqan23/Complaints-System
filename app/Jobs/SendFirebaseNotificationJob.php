<?php

namespace App\Jobs;

use App\Enums\NotificationPlatform;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use App\Services\LoggingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class SendFirebaseNotificationJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public $tries = 3;      // retry 3 times
    public $timeout = 20;   // timeout after 20 seconds

    protected $user_id;
    protected $title;
    protected $body;
    protected $token;
    protected $data;
    protected string $platform;

    public function __construct($user_id, $title, $body, $token, string $platform, $data = [])
    {
        $this->user_id = $user_id;
        $this->title   = $title;
        $this->body    = $body;
        $this->token   = $token;
        $this->data    = $data;
        $this->platform = $platform;
    }

    public function handle(FirebaseNotificationService $service,  LoggingService $logs)
    {
        $user = User::find($this->user_id);

        if (!$user) {
            $logs->systemWarning(null, 'Notification job failed - user not found',
                ['user_id' => $this->user_id]
            );
            return;
        }

        if (!$this->token) {
            $logs->systemWarning($user, 'Notification job skipped - missing FCM token');
            return;
        }

        // Send FCM notification
        try{
            $result = $service->sendFCM($user, NotificationPlatform::from($this->platform), $this->title, $this->body, $this->data);

            // Only store notification if SUCCESS
            if ($result && isset($result['status']) && $result['status'] === 'success') {
                $service->storeNotification($user->id, $this->title, $this->body, $this->data);
                $logs->system($user,
                    'Notification job completed successfully', ['notification' => 'stored']);
            } else {
                $logs->systemWarning($user, 'Notification job failed logically',
                    ['error' => $result['error'] ?? null]
                );
            }
        } catch (\Throwable $e) {
            $logs->systemError($user, 'Notification job execution failed', $e);
            throw $e;
        }
    }

    public function failed(\Throwable $exception)
    {
        app(LoggingService::class)->systemError(
            null, 'Notification job permanently failed', $exception,
            ['user_id' => $this->user_id]
        );
    }

}
