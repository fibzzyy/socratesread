<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_update_profile_details(): void
    {
        $user = User::factory()->create(['role' => 'student']);

        $this->actingAs($user)->put(route('settings.profile.update'), [
            'name' => 'Nama Baru',
            'email' => 'baru@example.test',
        ])->assertRedirect(route('settings.profile'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nama Baru',
            'email' => 'baru@example.test',
        ]);
    }

    public function test_teacher_can_update_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'teacher']);

        $file = UploadedFile::fake()->create('avatar.jpg', 100, 'image/jpeg');

        $this->actingAs($user)->put(route('settings.profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $file,
        ])->assertRedirect(route('settings.profile'));

        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);
    }

    public function test_profile_update_rejects_duplicate_email(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        User::factory()->create(['email' => 'used@example.test']);

        $this->actingAs($user)->put(route('settings.profile.update'), [
            'name' => $user->name,
            'email' => 'used@example.test',
        ])->assertSessionHasErrors('email');
    }
}
