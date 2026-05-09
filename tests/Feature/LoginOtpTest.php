<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\LoginOtp;
use App\Models\User;
use App\Notifications\LoginOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LoginOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'api');
        }
    }

    private function createVerifiedUser(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'email_verified_at' => now(),
            'password' => bcrypt('secret123'),
        ], $attributes));

        $user->assignRole(UserRole::USER);

        return $user;
    }

    public function test_login_sends_otp_when_credentials_are_valid_and_email_is_verified(): void
    {
        Notification::fake();

        $user = $this->createVerifiedUser(['email' => 'test@example.com']);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.email', 'test@example.com')
            ->assertJsonMissing(['access_token']);

        $this->assertDatabaseHas('login_otps', ['email' => 'test@example.com']);

        Notification::assertSentOnDemand(LoginOtpNotification::class);
    }

    public function test_login_returns_error_for_invalid_credentials(): void
    {
        $this->createVerifiedUser(['email' => 'test@example.com']);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_returns_error_when_email_is_not_verified(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'unverified@example.com',
            'email_verified_at' => null,
            'password' => bcrypt('secret123'),
        ]);
        $user->assignRole(UserRole::USER);

        $response = $this->postJson('/api/login', [
            'email' => 'unverified@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('data.verified', false);
    }

    public function test_verify_login_otp_returns_access_token_on_valid_otp(): void
    {
        $user = $this->createVerifiedUser(['email' => 'test@example.com']);

        LoginOtp::create([
            'email' => $user->email,
            'otp' => '123456',
        ]);

        $response = $this->postJson('/api/verify-login-otp', [
            'email' => $user->email,
            'otp' => '123456',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['access_token', 'token_type', 'expires_in', 'user']]);

        $this->assertDatabaseMissing('login_otps', ['email' => $user->email]);
    }

    public function test_verify_login_otp_fails_with_wrong_otp(): void
    {
        $user = $this->createVerifiedUser(['email' => 'test@example.com']);

        LoginOtp::create([
            'email' => $user->email,
            'otp' => '123456',
        ]);

        $response = $this->postJson('/api/verify-login-otp', [
            'email' => $user->email,
            'otp' => '999999',
        ]);

        $response->assertStatus(422);
    }

    public function test_verify_login_otp_fails_when_otp_is_expired(): void
    {
        $user = $this->createVerifiedUser(['email' => 'test@example.com']);

        LoginOtp::create([
            'email' => $user->email,
            'otp' => '123456',
        ]);

        DB::table('login_otps')
            ->where('email', $user->email)
            ->update(['updated_at' => now()->subMinutes(11)]);

        $response = $this->postJson('/api/verify-login-otp', [
            'email' => $user->email,
            'otp' => '123456',
        ]);

        $response->assertStatus(422);
    }

    public function test_verify_login_otp_fails_with_invalid_email(): void
    {
        $response = $this->postJson('/api/verify-login-otp', [
            'email' => 'nonexistent@example.com',
            'otp' => '123456',
        ]);

        $response->assertStatus(422);
    }
}
