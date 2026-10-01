<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_photo_is_stored_and_shared_as_the_user_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('profile.photo.update'), [
            'photo' => UploadedFile::fake()->image('me.jpg', 300, 300),
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));
        $path = $user->refresh()->avatar_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($user)->get(route('profile.edit'))->assertInertia(
            fn (Assert $page) => $page->where('auth.user.avatar', Storage::disk('public')->url($path)),
        );
    }

    public function test_replacing_the_photo_deletes_the_previous_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('profile.photo.update'), ['photo' => UploadedFile::fake()->image('old.jpg')]);
        $old = $user->refresh()->avatar_path;

        $this->actingAs($user)->post(route('profile.photo.update'), ['photo' => UploadedFile::fake()->image('new.png')]);

        $new = $user->refresh()->avatar_path;
        $this->assertNotSame($old, $new);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($new);
    }

    public function test_a_file_that_is_not_an_image_is_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('profile.photo.update'), [
            'photo' => UploadedFile::fake()->create('notes.pdf', 50, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors('photo');
        $this->assertNull($user->refresh()->avatar_path);
    }

    public function test_photo_can_be_removed(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('profile.photo.update'), ['photo' => UploadedFile::fake()->image('me.jpg')]);
        $path = $user->refresh()->avatar_path;

        $response = $this->actingAs($user)->delete(route('profile.photo.destroy'));

        $response->assertRedirect(route('profile.edit'));
        $this->assertNull($user->refresh()->avatar_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_user_without_a_photo_has_no_avatar(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile.edit'))->assertInertia(
            fn (Assert $page) => $page->where('auth.user.avatar', null),
        );
    }

    public function test_guests_cannot_upload_a_photo(): void
    {
        $this->post(route('profile.photo.update'), [
            'photo' => UploadedFile::fake()->image('me.jpg'),
        ])->assertRedirect(route('login'));
    }
}
