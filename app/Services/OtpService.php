<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Eloquent\CitizenRepository;
use App\Repositories\Eloquent\EmployeeRepository;
use App\Repositories\Interfaces\CitizenRepositoryInterface;
use App\Repositories\Interfaces\EmployeeRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

class OtpService {

    protected $client, $apiKey, $apiUrl;
    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = "51577f8a-18df-4c7e-a10c-acee03ee2a79";
        $this->apiUrl ="http://192.168.1.100:8082/";
        //$this->apiUrl ="https://www.cloud.smschef.com/api/send/sms";
    }

    public function sendSMS($phone)
    {

        //$body = '{"to": "+963998047973","message": "your verification code is: 4537"}';
        $otp = rand(1000, 9999);
        $message = "Your verification code is: $otp";
        try {
            $response = $this->client->post("https://www.cloud.smschef.com/api/send/sms", [
                'form_params' => [
                    'secret' => env('SMSCHEF_SECRET'),
                    'mode' => 'devices',
                    'device' => env('DEVICE_ID'),
                    'phone' => $phone,
                    'message' => $message,
                    'sim' => 1,
                ]
            ]);

            return [
                'status' => 'sent',
                'otp' => $otp,
                'response' => $response->getBody()->getContents()
            ];

        } catch (\Exception $e) {
            return $e->getMessage();
        }
        /*$requestBody = [
            'to' => $phone,
            'message' => $bodyMessage,
        ];

        $response = $this->client->request('POST', $this->apiUrl, [
            'headers' => [
                'Authorization' => $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => $requestBody,
        ]);
        return $response->getBody()->getContents();*/
    }

    public function sendEmail($email,$otp)
    {
        //$otp = rand(1000, 9999);
        $emailContent = "Dear User, \n"
            . "Your One Time Password (OTP) is: \n"
            . "{$otp}\n";

        Mail::raw($emailContent, function ($message) use ($email) {
            $message->to($email)
                ->subject('Reset Password OTP');
        });
        return response()->json([
            'status' => 'success',
            'message' => 'Your email has been sent successfully.',
        ]);
    }


}
