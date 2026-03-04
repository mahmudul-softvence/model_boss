<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\FileHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\SuspendUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserSuspension;
use App\Notifications\UserSuspendedNotification;
use App\Notifications\UserUnsuspendedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::with('roles')
            ->paginate();

        return $this->sendResponse(UserResource::collection($users));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();

        $imagePath = null;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')
                ->store('users/images', 'public');
        }

        $user = User::create([
            'name'        => $validated['name'],
            'email'       => $validated['email'],
            'password'    => bcrypt($validated['password']),
            'image'       => $imagePath,
            'referral_no' => Str::random(10),
        ]);

        $user->assignRole($validated['role']);

        $user->userBalance()->create();

        $user->load('roles');

        return $this->sendResponse(UserResource::make($user), 'User created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        return $this->sendResponse(UserResource::make($user));
    }

    /**
     * Update the specified resource in storage.
     */

    public function update(UpdateUserRequest $request, User $user)
    {
        $validated = $request->validated();

        $user->name = $validated['name'];

        if ($request->hasFile('image')) {

            if ($user->image) {
                Storage::disk('public')->delete($user->image);
            }

            $user->image = $request->file('image')
                ->store('users/images', 'public');
        }

        $user->save();

        $user->syncRoles($validated['role']);

        $user->load('roles');

        return $this->sendResponse(UserResource::make($user), 'User updated successfully.');
    }



    /**
     * Remove the specified resource from storage.
     */
    public function suspend(SuspendUserRequest $request, User $user)
    {
        $validated = $request->validated();

        $duration = $validated['duration'];

        $data = [
            'reason' => $validated['reason_category'],
            'note'   => $validated['note'] ?? null,
        ];

        if ($duration === 'permanent') {
            $data['is_permanent'] = true;
            $data['suspended_until'] = null;
        } else {
            $days = (int) $duration;

            if ($days <= 0) {
                return $this->sendError('Invalid suspension duration.');
            }

            $data['is_permanent'] = false;
            $data['suspended_until'] = Carbon::now()->addDays($days);
        }

        UserSuspension::updateOrCreate(
            ['user_id' => $user->id],
            $data
        );

        if (!empty($validated['notify_email'])) {
            $user->notify(new UserSuspendedNotification($user));
        }

        return $this->sendResponse(null, 'User suspended successfully.');
    }




    public function unsuspend(Request $request, User $user)
    {
        $validated = $request->validate([
            'notify_email' => 'nullable|boolean',
        ]);

        $user->suspension()?->delete();

        if (!empty($validated['notify_email'])) {
            $user->notify(new UserUnsuspendedNotification());
        }

        return $this->sendResponse(null, 'User unsuspended successfully.');
    }


    public function search()
    {
        $keyword = request('keyword');
        $role = request('role', 'all');

        $usersQuery = User::query();

        if ($keyword) {
            $usersQuery->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%");
            });
        } else {

            $usersQuery->limit(4);
        }

        if ($role !== 'all') {
            $usersQuery->role($role);
        }

        $users = $usersQuery->with('roles')->get();

        return $this->sendResponse(UserResource::collection($users));
    }


    public function change_role(User $user)
    {
        $currentRole = $user->roles()->first();

        if (!$currentRole) {
            return $this->sendError('User has no role assigned.');
        }

        if ($currentRole->name === 'user') {
            $user->syncRoles('artist');
            $user->load('roles');
            return $this->sendResponse(UserResource::make($user), 'User role changed to artist successfully.');
        }

        return $this->sendError('Role change not allowed for this user.');
    }
}
