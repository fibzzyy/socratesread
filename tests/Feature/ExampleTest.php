<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_requires_authentication_for_the_student_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    public function test_teacher_visiting_root_redirects_to_teacher_dashboard(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($teacher)
            ->get('/')
            ->assertRedirect(route('teacher.dashboard'));
    }

    public function test_student_can_render_dashboard(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'entry_grade' => 10,
            'enrollment_year' => (int) date('Y'),
            'graduation_year' => (int) date('Y') + 2,
        ]);

        $response = $this->actingAs($student)->get('/');

        $response->assertOk();
        $response->assertSee('Ruang belajar');
        $response->assertSee('Kelas 10');
    }

    public function test_transfer_student_sees_empty_state_for_prior_grade(): void
    {
        // Siswa pindahan mendaftar langsung di Kelas 11
        $transferStudent = User::factory()->create([
            'role' => 'student',
            'entry_grade' => 11,
            'enrollment_year' => (int) date('Y'),
            'graduation_year' => (int) date('Y') + 1,
        ]);

        // Saat mengakses default dashboard, otomatis membuka Kelas 11
        $response = $this->actingAs($transferStudent)->get('/');
        $response->assertOk();
        $response->assertSee('Kelas 11');
        $response->assertDontSee('Belum Ada Riwayat di Kelas');

        // Saat memilih Kelas 10 (sebelum masuk), halaman menampilkan Empty State informatif
        $priorResponse = $this->actingAs($transferStudent)->get('/?grade=10');
        $priorResponse->assertOk();
        $priorResponse->assertSee('Belum Ada Riwayat di Kelas 10');
        $priorResponse->assertSee('terdaftar di SocratesRead pada jenjang <strong>Kelas 11</strong>', false);
        $priorResponse->assertSee('Beralih ke Kelas 11');
    }
}
