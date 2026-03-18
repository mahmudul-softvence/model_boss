<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserBalance;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (UserRole::cases() as $role) {
            Role::firstOrCreate([
                'name' => $role->value
            ]);
        }


        $adminEmail = 'admin@gmail.com';
        $admin = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name'     => 'Admin',
                'password' => Hash::make('12345678'),
                'referral_no' => 'ahfjkh'
            ]
        );

        $admin->userBalance()->create();

        $admin->markEmailAsVerified();

        if (! $admin->hasRole(UserRole::SUPER_ADMIN)) {
            $admin->assignRole(UserRole::SUPER_ADMIN);
        }

        UserBalance::firstOrCreate([
            'user_id' => $admin->id,
        ], [
            'total_balance' => 0,
        ]);
    }
}
