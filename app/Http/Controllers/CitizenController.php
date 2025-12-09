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
        $this->notification = $notify;
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
        $this->notify->getUserNotifications(Auth::id());
        return response()->json(['status'=>'success','message' => 'get notifications success']);
    }
    public function markAsRead(Notification $notification)
    {
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
