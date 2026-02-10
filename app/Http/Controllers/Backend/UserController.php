<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\FileHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\SuspendUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Notifications\UserSuspendedNotification;
use App\Notifications\UserUnsuspendedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::with('roles')
            ->paginate();

        return $this->sendResponse(UserResource::collection($users), 'All users');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();

        $imagePath = FileHelper::uploadImage($request->file('image'));

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => bcrypt($validated['password']),
            'image'    => $imagePath,
        ]);

        $user->assignRole($validated['role']);

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

        $user->name  = $validated['name'];

        $user->image = FileHelper::uploadImage($request->file('image'), $user->image);

        if (!empty($validated['password'])) {
            $user->password = bcrypt($validated['password']);
        }

        $user->save();

        $user->syncRoles($validated['role']);

        $user->load('roles');

        return $this->sendResponse(UserResource::make($user), 'User updated successfully.');
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SuspendUserRequest $request, User $user)
    {
        $validated = $request->validated();

        if ($validated['duration'] === 'permanent') {
            $user->is_permanent_suspended = true;
            $user->suspended_until = null;
        } else {
            $user->suspended_until = Carbon::now()->addDays((int) $validated['duration']);
            $user->is_permanent_suspended = false;
        }

        $user->suspension_reason = $validated['reason_category'];
        $user->suspension_note   = $validated['note'] ?? null;
        $user->save();

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

        $user->is_permanent_suspended = false;
        $user->suspended_until = null;
        $user->suspension_reason = null;
        $user->suspension_note = null;

        $user->save();

        if (!empty($validated['notify_email'])) {
            $user->notify(new UserUnsuspendedNotification());
        }

        return $this->sendResponse(null, 'User unsuspended successfully.');
    }
}
