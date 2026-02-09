<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{

    /**
     * Register a User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
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

        $user->sendEmailVerificationNotification();


        $success['user'] =  $user;

        return $this->sendResponse($success, 'A varification email has been sent to you email.');
    }


    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login()
    {
        $credentials = request(['email', 'password']);

        if (! $token = auth()->attempt($credentials)) {
            return $this->sendError('Unauthorised.', ['error' => 'Unauthorised'], 401);
        }

        $user = auth()->user();

        // if (! $user->hasVerifiedEmail()) {
        //     $user->sendEmailVerificationNotification();
        //     auth()->logout();

        //     return $this->sendError('Please verify your email.', ['verified' => false], 403);
        // }

        $data = $this->respondWithToken($token);

        return $this->sendResponse($data, 'User login successfully.');
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        $user = auth()->user();

        $success = [
            'name' => $user->name,
            'email' => $user->email,
            'image' => $user->image,
            'email_verified' => !is_null($user->email_verified_at),
            'role' => $user->getRoleNames()->first(),
        ];;

        return $this->sendResponse($success, 'User informations.');
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->logout();

        return $this->sendResponse([], 'Successfully logged out.');
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        $success = $this->respondWithToken(auth()->refresh());

        return $this->sendResponse($success, 'Refresh token return successfully.');
    }


    /**
     * Verify user's email.
     *
     * @param  int  $id, $hash
     *
     * @return \Illuminate\Http\JsonResponse
     */

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



    /**
     * Resend email verification link.
     *
     * @return \Illuminate\Http\JsonResponse
     */

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


    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
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
