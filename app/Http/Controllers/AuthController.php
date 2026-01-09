<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\AuthService;
use App\Services\EmployeeService;
use App\Support\ServiceExecutor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AuthController extends BaseController
{
    protected $authService;

    public function __construct(AuthService $authService, ServiceExecutor $executor) {
        parent::__construct($executor);
        $this->authService = $authService;
    }

    public function login(LoginRequest $request)
    {
        $validated = $request->validated();

        $user = $this->exec(
            fn () => $this->authenticate($validated),
            channel: 'auth',
            action: 'User login',
            context: [
                'identifier'    => $data['identifier'] ?? null,
                'serial_number' => $data['serial_number'] ?? null
            ]
        );

        // حالة الحساب مقفول
        if (is_array($user) && isset($user['locked']) && $user['locked']) {
            return response()->json([
                'status' => 'locked',
                'message' => $user['message']
            ], 423);
        }

        if (!$user) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        return response()->json([
            'status'=>'success',
            'user' => $user], 200);
    }

    public function logout()
    {
        Auth::user()->tokens()->delete();
        return response()->json([
            'status'=>'success',
            'message' => 'Logged out successfully'], 201);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
        'identifier' => 'required|string',
        ]);

        //$response = $this->authService->sendOtp($request->identifier);
        $response = $this->exec(
            fn () => $this->authService->sendOtp($request->identifier),
            channel: 'auth',
            action: 'Send reset OTP',
            context: ['identifier' => $request->identifier]
        );

        if (isset($response['error'])) {
            return response()->json($response, 404);
        }

        return response()->json([
            'status'=>'success',
            'otp' => $response], 200);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'otp' => 'required|string|size:4'
        ]);
        //$response = $this->authService->verifyOtp($request->identifier, $request->otp);
        $response = $this->exec(
            fn () => $this->authService->verifyOtp(
                $request->identifier,
                $request->otp
            ),
            channel: 'auth',
            action: 'Verify reset OTP',
            context: ['identifier' => $request->identifier]
        );

        if (isset($response['error'])) {
            return response()->json($response, 400);
        }

        return response()->json([
            'status'=>'success',
            'response' => $response
            , 200]);
    }

    public function verifyRegisterOtp(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'otp' => 'required|string'
        ]);

        /*$result = $this->authService->verifyRegisterOtp(
            $request->identifier,
            $request->otp
        );*/
        $result = $this->exec(
            fn () => $this->authService->verifyRegisterOtp(
                $request->identifier,
                $request->otp
            ),
            channel: 'auth',
            action: 'Verify registration OTP',
            context: ['identifier' => $request->identifier]
        );

        if (isset($result['error'])) {
            return response()->json($result, 400);
        }

        return response()->json([
            'status'=>'success',
            'result' => $result
            , 201]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'password' => 'required|string|min:6|confirmed'
        ]);

        //$response = $this->authService->resetPassword($request->identifier, $request->password);
        $response = $this->exec(
            fn () => $this->authService->resetPassword(
                $request->identifier,
                $request->password
            ),
            channel: 'auth',
            action: 'Reset password',
            context: ['identifier' => $request->identifier]
        );

        if (isset($response['error'])) {
            return response()->json($response, 404);
        }

        return response()->json([
            'status'=>'success',
            'response' => $response
            , 200]);
    }

    ////////////////////////helpers
    private function authenticate(array $data)
    {
        return isset($data['serial_number'])
            ? $this->authService->Elogin(
                $data['serial_number'],
                $data['password'],
                $data['fcm_token'] ?? null
            )
            : $this->authService->Clogin(
                $data['identifier'],
                $data['password'],
                $data['fcm_token'] ?? null
            );
    }

//    public function try(Request $request){
//        $path = $request->file('file')->storePublicly('public/images');
//        return response()->json([
//
//            'path' => "https://touqa200.s3.eu-north-1.amazonaws.com/$path",
//            'msg' =>'success',
//        ]);
//    }

}
