<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\PromotionalTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PromotionalTermTest extends TestCase
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

    public function test_public_can_retrieve_default_promotional_terms_content(): void
    {
        $response = $this->getJson('/api/promotional-terms');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.prize', 0)
            ->assertJsonPath('data.list', []);
    }

    public function test_public_can_retrieve_saved_promotional_terms_content(): void
    {
        PromotionalTerm::factory()->create([
            'prize' => 1000,
            'list' => [
                'This promotional offer is available for a limited time only.',
                'Each user is eligible for this promotional price offer only once.',
            ],
        ]);

        $response = $this->getJson('/api/promotional-terms');

        $response
            ->assertOk()
            ->assertJsonPath('data.prize', 1000)
            ->assertJsonPath(
                'data.list.0',
                'This promotional offer is available for a limited time only.',
            )
            ->assertJsonPath(
                'data.list.1',
                'Each user is eligible for this promotional price offer only once.',
            );
    }

    public function test_admin_can_show_promotional_terms_content(): void
    {
        $admin = $this->createAdmin();

        PromotionalTerm::factory()->create([
            'prize' => 500,
            'list' => ['First term', 'Second term'],
        ]);

        $response = $this->withHeaders($this->authHeadersFor($admin))->getJson(
            '/api/admin/promotional-terms',
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.prize', 500)
            ->assertJsonPath('data.list.0', 'First term')
            ->assertJsonPath('data.list.1', 'Second term');
    }

    public function test_admin_can_update_promotional_terms_content(): void
    {
        $admin = $this->createAdmin();

        $response = $this->withHeaders($this->authHeadersFor($admin))->putJson(
            '/api/admin/promotional-terms',
            [
                'prize' => 1000,
                'list' => ['sdfsdf', 'asdfasdf', 'asdfdsf'],
            ],
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Promotional terms updated successfully',
            )
            ->assertJsonPath('data.prize', 1000)
            ->assertJsonPath('data.list.0', 'sdfsdf')
            ->assertJsonPath('data.list.1', 'asdfasdf')
            ->assertJsonPath('data.list.2', 'asdfdsf');

        $this->assertDatabaseCount('promotional_terms', 1);
        $this->assertSame(
            [
                'prize' => 1000,
                'list' => ['sdfsdf', 'asdfasdf', 'asdfdsf'],
            ],
            PromotionalTerm::currentContent(),
        );
    }

    public function test_admin_update_overwrites_existing_promotional_terms_content(): void
    {
        $admin = $this->createAdmin();

        PromotionalTerm::factory()->create([
            'prize' => 100,
            'list' => ['Old term'],
        ]);

        $this->withHeaders($this->authHeadersFor($admin))
            ->putJson('/api/admin/promotional-terms', [
                'prize' => 200,
                'list' => ['New term'],
            ])
            ->assertOk();

        $this->assertDatabaseCount('promotional_terms', 1);
        $this->assertSame(
            [
                'prize' => 200,
                'list' => ['New term'],
            ],
            PromotionalTerm::currentContent(),
        );
    }

    public function test_admin_update_validates_payload(): void
    {
        $admin = $this->createAdmin();

        $response = $this->withHeaders($this->authHeadersFor($admin))->putJson(
            '/api/admin/promotional-terms',
            [
                'prize' => -1,
                'list' => ['Valid term', 123],
            ],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['prize', 'list.1']);
    }

    public function test_admin_routes_require_authentication_and_admin_role(): void
    {
        $this->getJson('/api/admin/promotional-terms')->assertUnauthorized();
        $this->putJson('/api/admin/promotional-terms', [
            'prize' => 1000,
            'list' => ['Term'],
        ])->assertUnauthorized();

        $user = User::factory()->create();
        $user->assignRole(UserRole::USER->value);

        $this->withHeaders($this->authHeadersFor($user))
            ->getJson('/api/admin/promotional-terms')
            ->assertForbidden();

        $this->withHeaders($this->authHeadersFor($user))
            ->putJson('/api/admin/promotional-terms', [
                'prize' => 1000,
                'list' => ['Term'],
            ])
            ->assertForbidden();
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $admin->assignRole(UserRole::SUPER_ADMIN->value);

        return $admin;
    }

    private function authHeadersFor(User $user): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }
}
