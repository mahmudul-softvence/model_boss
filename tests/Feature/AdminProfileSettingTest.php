<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminProfileSettingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'api');
        }
    }

    public function test_admin_can_update_profile_via_json_put(): void
    {
        $admin = $this->createAdmin();

        $this->withHeaders($this->authHeadersFor($admin))
            ->putJson('/api/admin/settings', [
                'name' => 'Model Boss',
                'email' => 'modelbossoffers@gmail.com',
                'phone' => '',
                'nationality' => '',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'User updated successfully')
            ->assertJsonPath('data.name', 'Model Boss')
            ->assertJsonPath('data.email', 'modelbossoffers@gmail.com');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'name' => 'Model Boss',
            'email' => 'modelbossoffers@gmail.com',
        ]);
    }

    public function test_admin_can_update_profile_via_multipart_post(): void
    {
        Storage::fake();

        $admin = $this->createAdmin();

        $this->withHeaders($this->authHeadersFor($admin))
            ->post('/api/admin/settings', [
                'name' => 'Model_1',
                'email' => 'modelbossoffers@gmail.com',
                'phone' => '',
                'nationality' => '',
                'image' => UploadedFile::fake()->image('avatar.jpg'),
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'User updated successfully')
            ->assertJsonPath('data.name', 'Model_1');

        $admin->refresh();

        $this->assertSame('Model_1', $admin->name);
        $this->assertNotNull($admin->image);
        Storage::assertExists($admin->image);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->putJson('/api/admin/settings', [
            'name' => 'Model Boss',
            'email' => 'modelbossoffers@gmail.com',
        ])->assertUnauthorized();
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'name' => 'Admin',
        ]);
        $admin->assignRole(UserRole::SUPER_ADMIN->value);

        return $admin;
    }

    private function authHeadersFor(User $user): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }
}
