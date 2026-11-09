<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
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
        ])->assertRedirect(route('settings.profile'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nama Baru',
            'email' => $user->email,
        ]);
    }

    public function test_student_profile_and_edit_profile_are_separate_and_include_student_details(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'nisn' => '0012345678',
            'school_origin' => 'SMA Negeri 1 Jakarta',
            'entry_grade' => 11,
            'enrollment_year' => (int) now()->year,
            'bio' => 'Suka membaca dan berdiskusi.',
        ]);

        $this->actingAs($user)->get(route('profile'))
            ->assertOk()
            ->assertSee('student-profile-overview')
            ->assertSee('SMA Negeri 1 Jakarta')
            ->assertSee('0012345678')
            ->assertSee('Suka membaca dan berdiskusi.')
            ->assertSee(route('settings.profile'))
            ->assertDontSee('Edit Profil Siswa');

        $this->get(route('settings.profile'))
            ->assertOk()
            ->assertSee('Edit Profil Siswa')
            ->assertSee('name="nisn"', false)
            ->assertSee('name="bio"', false)
            ->assertSee(route('profile'))
            ->assertDontSee('student-profile-overview');
    }

    public function test_student_can_update_nisn_and_bio_from_edit_profile(): void
    {
        $user = User::factory()->create(['role' => 'student']);

        $this->actingAs($user)->put(route('settings.profile.update'), [
            'name' => $user->name,
            'nisn' => '0098765432',
            'bio' => 'Senang belajar melalui bacaan.',
        ])->assertRedirect(route('settings.profile'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'nisn' => '0098765432',
            'bio' => 'Senang belajar melalui bacaan.',
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

    public function test_teacher_profile_and_edit_profile_are_separate_full_width_pages(): void
    {
        $user = User::factory()->create([
            'role' => 'teacher',
            'school_origin' => 'SMA Negeri 1 Jakarta',
            'subject_area' => 'Bahasa Indonesia',
        ]);

        $this->actingAs($user)->get(route('profile'))
            ->assertOk()
            ->assertSee('teacher-profile-overview')
            ->assertSee(route('settings.profile'))
            ->assertSee('SMA Negeri 1 Jakarta')
            ->assertDontSee('Perbarui Data Guru');

        $this->get(route('settings.profile'))
            ->assertOk()
            ->assertSee('Edit Profil Guru')
            ->assertSee(route('profile'))
            ->assertSee('Perbarui Data Guru')
            ->assertDontSee('teacher-profile-overview');
    }

    public function test_teacher_can_update_professional_profile_details(): void
    {
        $user = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($user)->put(route('settings.profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'school_origin' => 'SMA Negeri 1 Jakarta',
            'nip_nuptk' => '198001012005011001',
            'subject_area' => 'Bahasa Indonesia',
            'bio' => 'Mengajar dengan pendekatan diskusi dan membaca kritis.',
        ])->assertRedirect(route('settings.profile'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'school_origin' => 'SMA Negeri 1 Jakarta',
            'teacher_identifier_type' => 'nuptk',
            'nip_nuptk' => '198001012005011001',
            'subject_area' => 'Bahasa Indonesia',
            'bio' => 'Mengajar dengan pendekatan diskusi dan membaca kritis.',
        ]);
    }

    public function test_teacher_profile_displays_nuptk_as_the_only_identifier(): void
    {
        $user = User::factory()->create([
            'role' => 'teacher',
            'teacher_identifier_type' => 'nip',
            'nip_nuptk' => '198001012005011001',
        ]);

        $this->actingAs($user)->get(route('profile'))
            ->assertOk()
            ->assertSee('<dt>NUPTK</dt>', false)
            ->assertDontSee('<dt>NIP</dt>', false);

        $this->get(route('settings.profile'))
            ->assertOk()
            ->assertSee('for="nip_nuptk">NUPTK</label>', false)
            ->assertDontSee('Jenis Identitas Guru');

        $this->put(route('settings.profile.update'), [
            'name' => $user->name,
        ])->assertRedirect(route('settings.profile'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'teacher_identifier_type' => 'nuptk',
        ]);
    }

    public function test_teacher_profile_shows_professional_details_and_activity_counts(): void
    {
        $user = User::factory()->create([
            'role' => 'teacher',
            'school_origin' => 'SMA Negeri 1 Jakarta',
            'subject_area' => 'Bahasa Indonesia',
        ]);

        $this->actingAs($user)->get(route('profile'))
            ->assertOk()
            ->assertSee('SMA Negeri 1 Jakarta')
            ->assertSee('Bahasa Indonesia')
            ->assertSee('Ringkasan aktivitas mengajar');
    }

    public function test_student_profile_shows_password_change_form(): void
    {
        $user = User::factory()->create(['role' => 'student']);

        $this->actingAs($user)->get(route('settings.profile'))
            ->assertOk()
            ->assertSee('Ubah Kata Sandi')
            ->assertSee(route('settings.password.update'));

        $this->get(route('settings.profile'))
            ->assertOk()
            ->assertSee('Edit Profil Siswa')
            ->assertSee('Alamat Email')
            ->assertSee(route('settings.email.update'));
    }

    public function test_teacher_can_change_password_with_current_password(): void
    {
        $user = User::factory()->create(['role' => 'teacher', 'password' => 'OldPassword123']);

        $this->actingAs($user)->put(route('settings.password.update'), [
            'current_password' => 'OldPassword123',
            'new_password' => 'NewPassword456',
            'new_password_confirmation' => 'NewPassword456',
        ])->assertRedirect(route('settings.profile'))
            ->assertSessionHas('status', 'Kata sandi berhasil diperbarui.');

        $this->assertTrue(Hash::check('NewPassword456', $user->fresh()->password));
    }

    public function test_teacher_can_update_email_after_confirming_current_password(): void
    {
        $user = User::factory()->create([
            'role' => 'teacher',
            'password' => 'OldPassword123',
            'email_verified_at' => now()->subDay(),
        ]);

        $this->actingAs($user)->put(route('settings.email.update'), [
            'email' => 'guru.baru@example.test',
            'email_current_password' => 'OldPassword123',
        ])->assertRedirect(route('settings.profile'))
            ->assertSessionHas('status', 'Alamat email berhasil diperbarui.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'guru.baru@example.test',
        ]);
        $this->assertNotNull($user->fresh()->email_verified_at);

        $this->post(route('logout'))
            ->assertRedirect(route('login'));
        $this->post(route('login'), [
            'email' => 'guru.baru@example.test',
            'password' => 'OldPassword123',
        ])->assertRedirect(route('teacher.dashboard'));
    }

    public function test_email_update_rejects_an_incorrect_current_password(): void
    {
        $user = User::factory()->create(['role' => 'teacher', 'password' => 'OldPassword123']);

        $this->actingAs($user)->put(route('settings.email.update'), [
            'email' => 'guru.baru@example.test',
            'email_current_password' => 'WrongPassword123',
        ])->assertSessionHasErrors('email_current_password');

        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_email_update_rejects_an_email_used_by_another_account(): void
    {
        $user = User::factory()->create(['role' => 'teacher', 'password' => 'OldPassword123']);
        User::factory()->create(['email' => 'used@example.test']);

        $this->actingAs($user)->put(route('settings.email.update'), [
            'email' => 'used@example.test',
            'email_current_password' => 'OldPassword123',
        ])->assertSessionHasErrors('email');

        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_student_can_update_email_after_confirming_current_password(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'password' => 'OldPassword123',
            'email_verified_at' => now()->subDay(),
        ]);

        $this->actingAs($student)->put(route('settings.email.update'), [
            'email' => 'student.new@example.test',
            'email_current_password' => 'OldPassword123',
        ])->assertRedirect(route('settings.profile'))
            ->assertSessionHas('status', 'Alamat email berhasil diperbarui.');

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'email' => 'student.new@example.test',
        ]);
        $this->assertNotNull($student->fresh()->email_verified_at);

        $this->post(route('logout'))
            ->assertRedirect(route('login'));
        $this->post(route('login'), [
            'email' => 'student.new@example.test',
            'password' => 'OldPassword123',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_student_email_update_rejects_an_incorrect_current_password(): void
    {
        $student = User::factory()->create(['role' => 'student', 'password' => 'OldPassword123']);

        $this->actingAs($student)->put(route('settings.email.update'), [
            'email' => 'student.new@example.test',
            'email_current_password' => 'WrongPassword123',
        ])->assertSessionHasErrors('email_current_password');

        $this->assertSame($student->email, $student->fresh()->email);
    }

    public function test_student_email_update_rejects_an_email_used_by_another_account(): void
    {
        $student = User::factory()->create(['role' => 'student', 'password' => 'OldPassword123']);
        User::factory()->create(['email' => 'used@example.test']);

        $this->actingAs($student)->put(route('settings.email.update'), [
            'email' => 'used@example.test',
            'email_current_password' => 'OldPassword123',
        ])->assertSessionHasErrors('email');

        $this->assertSame($student->email, $student->fresh()->email);
    }

    public function test_password_change_rejects_an_incorrect_current_password(): void
    {
        $user = User::factory()->create(['role' => 'student', 'password' => 'OldPassword123']);

        $this->actingAs($user)->put(route('settings.password.update'), [
            'current_password' => 'WrongPassword123',
            'new_password' => 'NewPassword456',
            'new_password_confirmation' => 'NewPassword456',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('OldPassword123', $user->fresh()->password));
    }

    public function test_password_change_requires_matching_confirmation_and_minimum_length(): void
    {
        $user = User::factory()->create(['role' => 'student', 'password' => 'OldPassword123']);

        $this->actingAs($user)->put(route('settings.password.update'), [
            'current_password' => 'OldPassword123',
            'new_password' => 'short',
            'new_password_confirmation' => 'different',
        ])->assertSessionHasErrors(['new_password']);

        $this->assertTrue(Hash::check('OldPassword123', $user->fresh()->password));
    }

    public function test_profile_update_rejects_duplicate_email(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        User::factory()->create(['email' => 'used@example.test']);

        $this->actingAs($user)->put(route('settings.email.update'), [
            'email' => 'used@example.test',
            'email_current_password' => 'password',
        ])->assertSessionHasErrors('email');
    }
}
