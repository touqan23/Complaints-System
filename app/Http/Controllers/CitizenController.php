<?php

namespace App\Http\Controllers;

use App\Http\Requests\CitizenRegisterRequest;
use App\Models\Citizen;
use App\Models\Notification;
use App\Services\AuthService;
use App\Services\EmployeeService;
use App\Services\FirebaseNotificationService;
use App\Services\LoggingService;
use App\Support\ServiceExecutor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;

class CitizenController extends BaseController
{
    protected $authService, $notify, $logs;

    public function __construct(
        AuthService $authService,
        FirebaseNotificationService $notify,
        LoggingService $logs,
        ServiceExecutor $executor
    ){
        parent::__construct($executor);
        $this->authService = $authService;
        $this->notify = $notify;
        $this->logs = $logs;
    }

    public function register(CitizenRegisterRequest $request)
    {
        $validated = $request->validated();
        $result = $this->exec(
            fn () => $this->authService->registerCitizen($validated),
            channel: 'auth',
            action: 'Citizen registration',
            context: [
                'phone' => $validated['phone'] ?? null,
            ],
            transactional: false
        );

        return response()->json([
            'status' => 'success',
            'result' => $result
        ]);
    }
}
