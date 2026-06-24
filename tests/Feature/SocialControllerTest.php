<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_apple_redirect_returns_a_target_url(): void
    {
        $provider = Mockery::mock();
        $redirectResponse = Mockery::mock();

        $provider->shouldReceive('stateless')->once()->andReturnSelf();
        $provider->shouldReceive('scopes')->once()->with(['name', 'email'])->andReturnSelf();
        $provider->shouldReceive('redirect')->once()->andReturn($redirectResponse);

        $redirectResponse->shouldReceive('getTargetUrl')
            ->once()
            ->andReturn('https://appleid.apple.com/auth/authorize');

        Socialite::shouldReceive('driver')->once()->with('apple')->andReturn($provider);

        $response = $this->getJson('/api/apple/redirect');

        $response->assertOk()->assertJson([
            'success' => true,
            'message' => 'Success',
            'data' => [
                'url' => 'https://appleid.apple.com/auth/authorize',
            ],
        ]);
    }

    public function test_social_callback_route_accepts_post_requests(): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($route) => $route->uri() === 'api/{provider}/callback');

        $this->assertNotNull($route);
        $this->assertContains('GET', $route->methods());
        $this->assertContains('POST', $route->methods());
    }

    public function test_unsupported_social_provider_is_rejected(): void
    {
        $response = $this->getJson('/api/twitter/redirect');

        $response->assertStatus(400)->assertJson([
            'success' => false,
            'message' => 'Unsupported provider',
        ]);
    }

    public function test_google_login_stores_a_high_resolution_avatar(): void
    {
        Role::findOrCreate(UserRole::USER->value, 'api');
        Storage::fake('public');
        Http::fake([
            'lh3.googleusercontent.com/*' => Http::response('fake-image-bytes', 200),
        ]);

        $socialUser = Mockery::mock(SocialiteUser::class);
        $socialUser->shouldReceive('getId')->andReturn('google-123');
        $socialUser->shouldReceive('getEmail')->andReturn('newuser@example.com');
        $socialUser->shouldReceive('getName')->andReturn('New User');
        $socialUser->shouldReceive('getNickname')->andReturn(null);
        // Google returns a low-res avatar URL with a size token.
        $socialUser->shouldReceive('getAvatar')
            ->andReturn('https://lh3.googleusercontent.com/a/ABC123=s96-c');

        $provider = Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($socialUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/api/google/callback')->assertRedirect();

        // The high-res variant must be requested, not the blurry s96 one.
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '=s500-c')
                && ! str_contains($request->url(), '=s96-c');
        });

        $user = User::where('email', 'newuser@example.com')->firstOrFail();
        $this->assertNotNull($user->image);
        Storage::disk('public')->assertExists($user->image);
    }
}
