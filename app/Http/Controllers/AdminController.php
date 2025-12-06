<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRegisterRequest;
use App\Services\AuthService;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService) {
        $this->authService = $authService;
    }
    public function addEmployee(EmployeeRegisterRequest $request)
    {
        $validated = $request->validated();
        $result = $this->authService->registerEmployee($validated);
        return response()->json([
            'status'=>'success',
            'result' => $result,
            ]);
    }

}
