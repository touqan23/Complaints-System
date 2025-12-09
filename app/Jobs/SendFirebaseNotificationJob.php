<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendFirebaseNotificationJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public $tries = 3;      // retry 3 times
    public $timeout = 20;   // timeout after 20 seconds

    protected $title;
    protected $body;
    protected $tokens;
    protected $data;

    public function __construct($title, $body, $tokens, $data = [])
    {
        $this->title  = $title;
        $this->body   = $body;
        $this->tokens = $tokens;
        $this->data   = $data;
    }

    public function handle(FirebaseNotificationService $service)
    {
        foreach ($this->tokens as $token) {

            if (!$token) continue;

            $user = User::where('fcm_token', $token)->first();

            if ($user) {
                // Send FCM ONLY (no save, no dispatch)
                $service->sendFCM($user, $this->title, $this->body, $this->data);
            }
        }
    }
}
