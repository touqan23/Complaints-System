<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService) {
        $this->authService = $authService;
    }

    public function login(LoginRequest $request)
    {
        $validated = $request->validated();

        if (isset($validated['serial_number'])) {
            $user = $this->authService->Elogin($validated['serial_number'], $validated['password']);
        } else {
            $user = $this->authService->Clogin($validated['identifier'], $validated['password']);
        }

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

        $response = $this->authService->sendOtp($request->identifier);

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
        $response = $this->authService->verifyOtp($request->identifier, $request->otp);

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

        $result = $this->authService->verifyRegisterOtp(
            $request->identifier,
            $request->otp
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
        $response = $this->authService->resetPassword($request->identifier, $request->password);

        if (isset($response['error'])) {
            return response()->json($response, 404);
        }

        return response()->json([
            'status'=>'success',
            'response' => $response
            , 200]);
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
