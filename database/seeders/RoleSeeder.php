<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'api');
        }

        $email = config('app.admin_email');
        $password = config('app.admin_password');

        if (! is_string($email) || trim($email) === '' || ! is_string($password) || $password === '') {
            return;
        }

        $admin = User::firstOrCreate(
            ['email' => trim($email)],
            [
                'name' => config('app.admin_name', 'Admin'),
                'password' => Hash::make($password),
            ]
        );

        $admin->userBalance()->firstOrCreate([], [
            'total_balance' => 0,
        ]);

        $admin->markEmailAsVerified();

        if (! $admin->hasRole(UserRole::SUPER_ADMIN->value)) {
            $admin->assignRole(UserRole::SUPER_ADMIN->value);
        }
    }
}
