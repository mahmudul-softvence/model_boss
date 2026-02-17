<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Referral;
use App\Models\User;
use App\Models\UserBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|unique:users,email',
            'password'    => 'required|min:8',
            'c_password'  => 'required|same:password',
            'referral_id' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $data = $request->only(['name', 'email', 'password']);
        $data['password'] = bcrypt($data['password']);

        $referralUser = null;

        if ($request->filled('referral_id')) {

            $referralUser = User::where('referral_no', $request->referral_id)->first();

            if (!$referralUser) {
                return $this->sendError('Invalid referral code.', [], 422);
            }

            $data['referral_user_id'] = $referralUser->id;
        }

        $user = User::create($data);

        $user->assignRole(UserRole::USER);
        $user->userBalance()->create();

        if ($referralUser) {
            Referral::create([
                'user_id'          => $referralUser->id,
                'referral_user_id' => $user->id
            ]);
        }

        $user->sendEmailVerificationNotification();

        return $this->sendResponse(
            UserResource::make($user),
            'A verification email has been sent to your email.'
        );
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

        return redirect()->to(
            rtrim(config('app.frontend_url'), '/') . '/' .
                ltrim(config('app.frontend_login'), '/') .
                '?email_verified=true'
        );
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

            'user' => UserResource::make($user)
        ];
    }
}
