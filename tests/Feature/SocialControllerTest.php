<?php

namespace Tests\Feature;

use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class SocialControllerTest extends TestCase
{
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
}
