<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
            'c_password' => 'required|same:password',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $input = $request->all();
        $input['password'] = bcrypt($input['password']);
        $user = User::create($input);
        $user->assignRole(UserRole::USER);

        $user->userBalance()->create();

        $user->sendEmailVerificationNotification();


        $success['user'] =  $user;

        return $this->sendResponse($success, 'A varification email has been sent to you email.');
    }

    public function login()
    {
        $credentials = request(['email', 'password']);

        if (! $token = auth()->attempt($credentials)) {
            return $this->sendError('Unauthorised.', ['error' => 'Unauthorised'], 401);
        }

        $user = auth()->user();

        if ($user->hasRole(UserRole::SUPER_ADMIN)) {
            auth()->logout();

            return $this->sendError('Unauthorised.', ['error' => 'Unauthorised'], 401);
        }

        if ($user->isSuspended()) {
            auth()->logout();

            $data = [
                'suspended' => true,
                'permanent' => $user->is_permanent_suspended,
                'until'     => $user->suspended_until,
                'reason'    => $user->suspension_reason,
                'note'      => $user->suspension_note
            ];

            return $this->sendError('Your account is suspended.', $data, 403);
        }


        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
            auth()->logout();

            return $this->sendError('Please verify your email.', ['verified' => false], 403);
        }

        $data = $this->respondWithToken($token);

        return $this->sendResponse($data, 'User login successfully.');
    }

    public function admin_login()
    {
        $credentials = request(['email', 'password']);

        if (! $token = auth()->attempt($credentials)) {
            return $this->sendError('Unauthorised.', ['error' => 'Unauthorised'], 401);
        }

        $user = auth()->user();

        if (! $user->hasRole(UserRole::SUPER_ADMIN)) {
            auth()->logout();

            return $this->sendError('Unauthorised.', ['error' => 'Unauthorised'], 401);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
            auth()->logout();

            return $this->sendError('Please verify your email.', ['verified' => false], 403);
        }

        $data = $this->respondWithToken($token);

        return $this->sendResponse($data, 'User login successfully.');
    }

    public function me()
    {
        $user = auth()->user();
        $userBalance = UserBalance::where('user_id', $user->id)->first();

        $data = [
            'name' => $user->name,
            'email' => $user->email,
            'image' => $user->image,
            'email_verified' => !is_null($user->email_verified_at),
            'role' => $user->getRoleNames()->first(),
            'total_earning' => $userBalance->total_earning,
            'total_referral_earning' => $userBalance->total_referral_earning,
            'total_tip_received' => $userBalance->total_tip_received,
            'total_withdraw' => $userBalance->total_withdraw,
            'total_balance' => $userBalance->total_balance,
            'total_bet' => $userBalance->total_bet,
        ];;

        return $this->sendResponse($data, 'User informations.');
    }

    public function logout()
    {
        auth()->logout();

        return $this->sendResponse([], 'Successfully logged out.');
    }

    public function refresh()
    {
        $success = $this->respondWithToken(auth()->refresh());

        return $this->sendResponse($success, 'Refresh token return successfully.');
    }

    public function verify_email($id, $hash, Request $request)
    {
        $user = User::find($id);

        if (!$user) {
            return $this->sendError('User not found.');
        }

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return $this->sendError('Invalid verification link.', [], 403);
        }

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return $this->sendResponse([], 'Email verified successfully!');
    }

    public function resend_verification(Request $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return $this->sendError('User not found.');
        }

        if ($user->hasVerifiedEmail()) {
            return $this->sendError('Email already verified.', [], 400);
        }

        $user->sendEmailVerificationNotification();

        return $this->sendResponse([], 'Verification link resent!');
    }

    protected function respondWithToken($token)
    {
        $user = auth()->user();
        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,

            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'image' => $user->image,
                'email_verified' => !is_null($user->email_verified_at),
                'role' => $user->getRoleNames()->first(),
            ]
        ];
    }
}
