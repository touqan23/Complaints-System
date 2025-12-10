<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\FirebaseNotificationService;
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

    public function __construct($user_id, $title, $body, $token, $data = [])
    {
        $this->user_id = $user_id;
        $this->title   = $title;
        $this->body    = $body;
        $this->token   = $token;
        $this->data    = $data;
    }

    public function handle(FirebaseNotificationService $service)
    {
        Log::channel('system')->info("Job started for user: {$this->user_id}");

        if (!$this->token) {
            Log::channel('system')->warning("Empty token for user: {$this->user_id}");
            return;
        }

        $user = User::find($this->user_id);

        if (!$user) {
            Log::channel('system')->warning("User not found: {$this->user_id}");
            return;
        }

        Log::channel('system')->info("Sending notification to user: {$user->id}");

        // Send FCM notification
        $result = $service->sendFCM($user, $this->title, $this->body, $this->data);

        Log::channel('system')->info("FCM Result: " . json_encode($result));

        // Only store notification if SUCCESS
        if ($result && isset($result['status']) && $result['status'] === 'success') {
            Log::channel('system')->info("Storing notification for user: {$user->id}");
            $service->storeNotification($user->id, $this->title, $this->body, $this->data);
        } else {
            Log::channel('system')->warning("Notification failed for user: {$user->id}", [
                'error' => $result['error'] ?? 'Unknown error'
            ]);
        }

        Log::channel('system')->info("Job completed for user: {$this->user_id}");
    }

    public function failed(\Throwable $exception)
    {
        Log::channel('system')->error("Job failed for user: {$this->user_id}", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }

}
