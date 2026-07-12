<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageUrlUsesS3DiskTest extends TestCase
{
    public function test_default_disk_is_controlled_by_env(): void
    {
        $this->assertSame(
            env('FILESYSTEM_DISK', 'local'),
            config('filesystems.default'),
        );
    }

    public function test_user_image_url_resolves_through_the_default_disk(): void
    {
        $user = new User(['image' => 'users/images/avatar.png']);

        $this->assertSame(
            Storage::disk()->url('users/images/avatar.png'),
            $user->image_url,
        );
    }

    public function test_user_image_url_is_null_without_an_image(): void
    {
        $this->assertNull((new User)->image_url);
    }

    public function test_game_image_accessor_strips_legacy_storage_prefix(): void
    {
        $game = new Game(['image' => 'storage/games/cover.png']);

        $this->assertSame(
            Storage::disk()->url('games/cover.png'),
            $game->image,
        );
    }

    public function test_game_image_accessor_passes_through_absolute_urls(): void
    {
        $game = new Game(['image' => 'https://cdn.example.com/cover.png']);

        $this->assertSame('https://cdn.example.com/cover.png', $game->image);
    }

    public function test_uploads_write_to_the_default_disk(): void
    {
        Storage::fake();

        $path = Storage::disk()->putFileAs(
            'logos',
            UploadedFile::fake()->create('logo.png'),
            'logo.png',
        );

        $this->assertSame('logos/logo.png', $path);
        Storage::disk()->assertExists('logos/logo.png');
    }
}
