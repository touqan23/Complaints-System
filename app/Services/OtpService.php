<?php

namespace App\Services;


use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Mail;

class OtpService {

    protected $client,$logs;
    public function __construct(Client $client, LoggingService $logs)
    {
        $this->client = $client;
        $this->logs = $logs;
    }

    public function sendSMS($phone)
    {
        //$body = '{"to": "+963998047973","message": "your verification code is: 4537"}';
        $this->logs->system(new User(),"SMS OTP sending attempt", [
            "phone" => $phone
        ]);
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

            $this->logs->system(new User(),"SMS OTP sent SUCCESS", [
                "phone" => $phone
            ]);
            return [
                'status' => 'sent',
                'otp' => $otp,
                'response' => $response->getBody()->getContents()
            ];

        } catch (\Exception $e) {
            $this->logs->system(new User(),"SMS sending FAILED", [
                "phone" => $phone,
                "error" => $e->getMessage()
            ]);
            return [
                'status' => 'failed',
                'error'  => $e->getMessage()
            ];
        }
    }

    public function sendEmail($email,$otp)
    {
        $this->logs->system(new User(),"Email OTP sending attempt", [
            "email" => $email
        ]);
        $emailContent = "Dear User, \n"
            . "Your One Time Password (OTP) is: \n"
            . "{$otp}\n";

        try {
            Mail::raw($emailContent, function ($message) use ($email) {
                $message->to($email)
                    ->subject('Reset Password OTP');
            });
            $this->logs->system(new User(),"Email OTP sent SUCCESS", [
                "email" => $email
            ]);
            return response()->json([
                'status' => 'success',
                'message' => 'Your email has been sent successfully.',
            ]);
        }catch (\Exception $e) {
            $this->logs->system(new User(),"Email sending FAILED", [
                "email" => $email,
                "error" => $e->getMessage()
            ]);
            return [
                'status' => 'failed',
                'error'  => $e->getMessage()
            ];
        }
    }


}
