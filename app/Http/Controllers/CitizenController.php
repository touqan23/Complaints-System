<?php

namespace App\Http\Controllers;

use App\Http\Requests\CitizenRegisterRequest;
use App\Models\Citizen;
use App\Services\AuthService;
use Illuminate\Http\Request;

class CitizenController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService) {
        $this->authService = $authService;
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


}
