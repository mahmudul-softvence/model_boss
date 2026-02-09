<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ForgotPasswordOtp;
use App\Models\User;
use App\Notifications\ForgotPasswordOtpNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    public function forgot_password(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        try {
            $email = $validated['email'];

            $token = Str::random(64);
            $otp   = random_int(100000, 999999);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                [
                    'token'      => $token,
                    'created_at' => now(),
                ]
            );

            ForgotPasswordOtp::updateOrCreate(
                ['email' => $email],
                ['otp' => $otp]
            );

            Notification::route('mail', $email)
                ->notify(new ForgotPasswordOtpNotification($token, $otp));

            $data = [
                'token' => $token,
                'email' => $email,
            ];

            return $this->sendResponse($data, 'OTP sent successfully', 200);
        } catch (\Throwable $e) {
            return $this->sendError('Something went wrong. Please try again.', [], 500);
        }
    }

    public function verify_forgot_password(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'otp'   => ['required', 'digits:6'],
        ]);

        $forgotOtp = ForgotPasswordOtp::where('email', $validated['email'])->first();

        if (! $forgotOtp) {
            return $this->sendError('Invalid email', [], 422);
        }

        $isValidOtp = $forgotOtp->otp === $validated['otp']
            && now()->diffInSeconds($forgotOtp->created_at) <= 300;

        if (! $isValidOtp) {
            return $this->sendError('Invalid or expired OTP', [], 422);
        }

        return $this->sendResponse([], 'OTP verified successfully');
    }


    public function reset_password(Request $request)
    {
        $validated = $request->validate([
            'email'                 => ['required', 'email'],
            'token'                 => ['required', 'string'],
            'new_password'          => ['required', 'string'],
            'confirm_new_password'  => ['required', 'same:new_password'],
        ]);

        try {
            $tokenExists = DB::table('password_reset_tokens')
                ->where('email', $validated['email'])
                ->where('token', $validated['token'])
                ->exists();

            if (! $tokenExists) {
                return $this->sendError('Invalid token', [], 422);
            }

            User::where('email', $validated['email'])->update([
                'password' => Hash::make($validated['new_password']),
            ]);

            DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();
            ForgotPasswordOtp::where('email', $validated['email'])->delete();

            return $this->sendResponse([], 'Your password has been changed successfully');
        } catch (\Throwable $e) {
            return $this->sendError('Something went wrong. Please try again.', [], 500);
        }
    }
}
