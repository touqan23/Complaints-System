<?php

namespace App\Http\Controllers;

use App\Http\Requests\CitizenRegisterRequest;
use App\Models\Citizen;
use App\Models\Notification;
use App\Services\EmployeeService;
use App\Services\FirebaseNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CitizenController extends Controller
{
    protected $authService, $notify;

    public function __construct(EmployeeService $authService, FirebaseNotificationService $notify) {
        $this->authService = $authService;
        $this->notify = $notify;
    }

    public function register(CitizenRegisterRequest $request)
    {
        $validated = $request->validated();
        $result = $this->authService->registerCitizen($validated);
        return response()->json([
            "status"=> "success",
            'result' => $result
            , 200]);

    }

    /////////////////////notification
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

        if(Auth::id() === $notification->user_id){
            $this->notify->markAsRead($notification);
            return response()->json(['status'=>'success','message' => 'Marked as read']);
        }
        return response()->json(['message' => 'you are not allowed']);
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
