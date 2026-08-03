<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomePageTest extends TestCase
{
    public function test_home_page_renders_coming_soon_content(): void
    {
        config(['app.name' => 'Model Boss']);

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Model Boss', false)
            ->assertSee('We’re building something worth the wait.', false)
            ->assertSee('In progress', false)
            ->assertSee(asset('css/style.css'), false);
    }
}
