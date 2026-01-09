<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\FirebaseNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    protected $authService, $notify, $logs;

    public function __construct(FirebaseNotificationService $notify){
        $this->notify = $notify;
    }
    public function getNotifications()
    {
        $user = Auth::user();
        $notifications = $this->notify->getUserNotifications($user);
        return response()->json(['status'=>'success','notifications' => $notifications]);
    }
    public function markAsRead(Notification $notification)
    {
        if(!$notification)
            return response()->json(['status'=>'error','message' => 'notifications not found']);

        if (Auth::id() !== $notification->user_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not allowed'
            ], 403);
        }

        $this->notify->markAsRead($notification);

        return response()->json([
            'status' => 'success',
            'message' => 'Marked as read'
        ]);
    }

    public function delete(Notification $notification)
    {
        if(Auth::id() === $notification->user_id){
            $this->notify->delete($notification);
            return response()->json(['status'=>'success','message' => 'deleted']);
        }
        return response()->json(['message' => 'you are not allowed']);
    }
}
