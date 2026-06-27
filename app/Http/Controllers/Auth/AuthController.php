<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\LoginOtp;
use App\Models\Referral;
use App\Models\User;
use App\Notifications\LoginOtpNotification;
use App\Support\ProfileBalanceData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * Register a new user
     *
     * @return JsonResponse
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'artist_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'game_id' => 'nullable|exists:games,id',
            'c_password' => 'required|same:password',
            'referral_id' => 'nullable|string',
            'address' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'zip_code' => 'nullable|string|max:20',
            'state' => 'nullable|string|max:255',
            'social_verification_status' => 'nullable|boolean',
            'social_verification_number' => 'nullable|string|max:255',
            'is_player' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'Validation Error.',
                $validator->errors(),
                422,
            );
        }

        $data = $request->only([
            'first_name',
            'middle_name',
            'last_name',
            'artist_name',
            'email',
            'password',
            'address',
            'city',
            'zip_code',
            'state',
            'social_verification_status',
            'social_verification_number',
        ]);
        $data['password'] = bcrypt($data['password']);
        $data['game_id'] = $request->game_id;
        $data['is_player'] = $request->boolean('is_player');

        $referralUser = null;

        if ($request->filled('referral_id')) {
            $referralUser = User::where(
                'referral_no',
                $request->referral_id,
            )->first();

            if (! $referralUser) {
                return $this->sendError('Invalid referral code.', [], 422);
            }

            $data['referral_user_id'] = $referralUser->id;
        }

        $user = User::create($data);

        $user->assignRole(UserRole::USER);
        $user->userBalance()->create();

        if ($referralUser) {
            Referral::create([
                'user_id' => $referralUser->id,
                'referral_user_id' => $user->id,
            ]);
        }

        $user->sendEmailVerificationNotification();

        return $this->sendResponse(
            UserResource::make($user),
            'A verification email has been sent to your email.',
        );
    }

    public function login()
    {
        $credentials = request(['email', 'password']);

        if (! ($token = auth()->attempt($credentials))) {
            return $this->sendError(
                'Invalid username or password.',
                ['error' => 'Invalid username or password'],
                401,
            );
        }

        $user = auth()->user();

        if ($user->isSuspended()) {
            auth()->logout();

            $suspension = $user->suspension;

            $data = [
                'suspended' => $user->isSuspended(),
                'permanent' => $suspension?->is_permanent ?? false,
                'until' => $suspension?->suspended_until,
                'reason' => $suspension?->reason,
                'note' => $suspension?->note,
            ];

            return $this->sendError('Your account is suspended.', $data, 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
            auth()->logout();

            return $this->sendError(
                'Please verify your email.',
                ['verified' => false],
                403,
            );
        }

        auth()->logout();

        $otp = (string) random_int(100000, 999999);

        LoginOtp::updateOrCreate(['email' => $user->email], ['otp' => $otp]);

        Notification::route('mail', $user->email)->notify(
            new LoginOtpNotification($otp),
        );

        return $this->sendResponse(
            ['email' => $user->email],
            'OTP sent to your email. Please verify to complete login.',
        );
    }

    public function verifyLoginOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'otp' => ['required', 'digits:6'],
        ]);

        $loginOtp = LoginOtp::where('email', $request->email)->first();

        if (! $loginOtp) {
            return $this->sendError('Invalid email.', [], 422);
        }

        $isValid =
            $loginOtp->otp === $request->otp &&
            $loginOtp->updated_at->addMinutes(10)->isFuture();

        if (! $isValid) {
            return $this->sendError('Invalid or expired OTP.', [], 422);
        }

        $loginOtp->delete();

        $user = User::where('email', $request->email)->first();
        $token = auth()->login($user);

        return $this->sendResponse(
            $this->respondWithToken($token),
            'User login successfully.',
        );
    }

    public function me()
    {
        $user = auth()->user();
        $user->loadMissing('userBalance');
        $user->loadChallengeRecordCounts();

        $data = [
            'user' => UserResource::make($user),
            ...ProfileBalanceData::forUser($user, viewer: $user),
        ];

        return $this->sendResponse($data, 'User informations.');
    }

    public function logout()
    {
        auth()->logout();

        return $this->sendResponse([], 'Successfully logged out.');
    }

    public function refresh()
    {
        try {
            $token = JWTAuth::parseToken()->refresh();
            auth()->setToken($token)->authenticate();

            $success = $this->respondWithToken($token);

            return $this->sendResponse(
                $success,
                'Refresh token return successfully.',
            );
        } catch (\Exception $e) {
            return $this->sendError('Token cannot be refreshed'.$e, [], 401);
        }
    }

    public function verify_email($id, $hash, Request $request)
    {
        $user = User::find($id);

        if (! $user) {
            return $this->sendError('User not found.');
        }

        if (
            ! hash_equals((string) $hash, sha1($user->getEmailForVerification()))
        ) {
            return $this->sendError('Invalid verification link.', [], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect()->to(
            rtrim(config('app.frontend_url'), '/').
                '/'.
                ltrim(config('app.frontend_login'), '/').
                '?email_verified=true',
        );
    }

    public function resend_verification(Request $request)
    {
        $user = User::where('email', $request->email)->first();

        if (! $user) {
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

            'user' => UserResource::make($user),
        ];
    }
}
