<?php

namespace Tests\Feature;

use App\Models\TeacherClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassroomEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_join_an_active_class_with_its_code(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $classroom = TeacherClass::query()->create([
            'teacher_id' => $teacher->id,
            'name' => 'Kelas Literasi',
            'join_code' => 'BACA2026',
            'is_active' => true,
        ]);

        $this->actingAs($student)
            ->post(route('classrooms.join'), ['join_code' => 'baca2026'])
            ->assertRedirect();

        $this->assertDatabaseHas('classroom_memberships', [
            'classroom_id' => $classroom->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_student_cannot_join_an_inactive_class(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        TeacherClass::query()->create([
            'teacher_id' => $teacher->id,
            'name' => 'Kelas Nonaktif',
            'join_code' => 'TUTUP123',
            'is_active' => false,
        ]);

        $this->actingAs($student)
            ->from(route('dashboard'))
            ->post(route('classrooms.join'), ['join_code' => 'TUTUP123'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors('join_code');

        $this->assertDatabaseCount('classroom_memberships', 0);
    }

    public function test_student_can_view_classrooms_page(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'name' => 'Pak Budi']);
        $student = User::factory()->create(['role' => 'student']);
        $classroom = TeacherClass::query()->create([
            'teacher_id' => $teacher->id,
            'name' => 'Kelas Literasi X',
            'join_code' => 'LITERA01',
            'target_grade' => '10',
            'is_active' => true,
        ]);

        $student->classrooms()->attach($classroom->id, ['joined_at' => now()]);

        $this->actingAs($student)
            ->get(route('classrooms.index'))
            ->assertOk()
            ->assertSee('Kelas saya')
            ->assertSee('Kelas Literasi X')
            ->assertSee('Pak Budi')
            ->assertSee('Kelas 10');
    }

    public function test_teacher_cannot_access_student_classrooms_page(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($teacher)
            ->get(route('classrooms.index'))
            ->assertForbidden();
    }

    public function test_teacher_cannot_join_a_class_through_the_student_route(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($teacher)
            ->post(route('classrooms.join'), ['join_code' => 'BACA2026'])
            ->assertForbidden();
    }

    public function test_teacher_can_only_list_their_own_classes(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        TeacherClass::query()->create([
            'teacher_id' => $teacher->id, 'name' => 'Kelas Saya', 'join_code' => 'KELAS001',
        ]);
        TeacherClass::query()->create([
            'teacher_id' => $otherTeacher->id, 'name' => 'Kelas Orang Lain', 'join_code' => 'KELAS002',
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher.classrooms'))
            ->assertOk()
            ->assertSee('Kelas Saya')
            ->assertDontSee('Kelas Orang Lain');
    }

    public function test_public_registration_can_register_student_and_teacher(): void
    {
        $currentYear = (int) date('Y');

        $this->post('/register', [
            'name' => 'Siswa Baru',
            'email' => 'siswabaru@example.test',
            'role' => 'student',
            'school_origin' => 'SMPN 1 Jakarta',
            'nisn' => '0098765432',
            'entry_grade' => 10,
            'password' => 'SocratesRead123',
            'password_confirmation' => 'SocratesRead123',
        ])->assertRedirect(route('verification.otp'));

        $this->assertDatabaseHas('users', [
            'email' => 'siswabaru@example.test',
            'role' => 'student',
            'school_origin' => 'SMPN 1 Jakarta',
            'nisn' => '0098765432',
            'entry_grade' => 10,
            'enrollment_year' => $currentYear,
            'graduation_year' => $currentYear + 2,
        ]);

        // Siswa pindahan mendaftar langsung di Kelas 11
        $this->post('/register', [
            'name' => 'Siswa Pindahan',
            'email' => 'pindahan@example.test',
            'role' => 'student',
            'school_origin' => 'SMA Luar Kota',
            'nisn' => '0098765433',
            'entry_grade' => 11,
            'password' => 'SocratesRead123',
            'password_confirmation' => 'SocratesRead123',
        ])->assertRedirect(route('verification.otp'));

        $this->assertDatabaseHas('users', [
            'email' => 'pindahan@example.test',
            'role' => 'student',
            'entry_grade' => 11,
            'enrollment_year' => $currentYear,
            'graduation_year' => $currentYear + 1,
        ]);

        $this->post('/register', [
            'name' => 'Guru Baru',
            'email' => 'gurubaru@example.test',
            'role' => 'teacher',
            'school_origin' => 'SMAN 1 Jakarta',
            'nip_nuptk' => '198001012005011001',
            'password' => 'SocratesRead123',
            'password_confirmation' => 'SocratesRead123',
        ])->assertRedirect(route('verification.otp'));

        $this->assertDatabaseHas('users', [
            'email' => 'gurubaru@example.test',
            'role' => 'teacher',
            'school_origin' => 'SMAN 1 Jakarta',
            'teacher_identifier_type' => 'nuptk',
            'nip_nuptk' => '198001012005011001',
        ]);
    }

    public function test_login_routes_users_to_their_role_home_even_with_another_intended_url(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'email' => 'guru@example.test',
            'password' => 'SocratesRead123',
        ]);

        $this->withSession(['url.intended' => route('progress')])
            ->post('/login', [
                'email' => $teacher->email,
                'password' => 'SocratesRead123',
            ])
            ->assertRedirect(route('teacher.dashboard'));
    }

    public function test_teacher_registration_requires_nuptk(): void
    {
        $this->from('/register')->post('/register', [
            'name' => 'Guru Baru',
            'email' => 'guruidentitas@example.test',
            'role' => 'teacher',
            'school_origin' => 'SMAN 1 Jakarta',
            'password' => 'SocratesRead123',
            'password_confirmation' => 'SocratesRead123',
        ])->assertRedirect('/register')->assertSessionHasErrors('nip_nuptk');

        $this->assertDatabaseMissing('users', ['email' => 'guruidentitas@example.test']);
    }
}
