<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\TeacherChallenge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentAchievementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('achievements'));
        $response->assertRedirect(route('login'));
    }

    public function test_teacher_cannot_access_student_achievements_page(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $response = $this->actingAs($teacher)->get(route('achievements'));
        $response->assertForbidden();
    }

    public function test_student_can_view_default_cohort_ranking_for_their_grade(): void
    {
        $student10 = User::factory()->create([
            'name' => 'Dimas Angkatan 10',
            'role' => 'student',
            'entry_grade' => 10,
            'enrollment_year' => 2026,
        ]);

        $response = $this->actingAs($student10)->get(route('achievements'));

        $response->assertOk();
        $response->assertSee('Ranking Angkatan Kelas 10');
        $response->assertSee('Dimas Angkatan 10');
        $response->assertSee('PODIUM JUARA ANGKATAN KELAS 10');
        $response->assertSee('Lencana Prestasi Belajar');
    }

    public function test_student_can_switch_cohort_grade_filter(): void
    {
        $student10 = User::factory()->create([
            'name' => 'Dimas Kelas 10',
            'role' => 'student',
            'entry_grade' => 10,
            'enrollment_year' => 2026,
        ]);

        $student12 = User::factory()->create([
            'name' => 'Rangga Kelas 12',
            'role' => 'student',
            'entry_grade' => 12,
            'enrollment_year' => 2026,
        ]);

        // View Grade 10
        $response10 = $this->actingAs($student10)->get(route('achievements', ['grade' => 10]));
        $response10->assertOk();
        $response10->assertSee('Ranking Angkatan Kelas 10');
        $response10->assertSee('Dimas Kelas 10');
        $response10->assertDontSee('Rangga Kelas 12');

        // View Grade 12
        $response12 = $this->actingAs($student10)->get(route('achievements', ['grade' => 12]));
        $response12->assertOk();
        $response12->assertSee('Ranking Angkatan Kelas 12');
        $response12->assertSee('Rangga Kelas 12');
    }
}
