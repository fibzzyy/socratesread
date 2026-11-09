<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\TeacherChallenge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherChallengeTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_create_challenges_for_document(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $document = Document::query()->create([
            'uploaded_by' => $teacher->id,
            'title' => 'Materi Sistem Operasi',
            'category' => 'Informatika',
            'target_grade' => '10',
            'body' => 'Sistem operasi mengelola sumber daya perangkat keras dan menyediakan layanan aplikasi.',
            'excerpt' => 'Konsep OS',
            'estimated_minutes' => 5,
            'is_published' => true,
        ]);

        $response = $this->actingAs($teacher)->postJson(route('teacher.documents.challenges.store', $document), [
            'challenges' => [
                [
                    'type' => 'multiple_choice',
                    'question' => 'Apa fungsi utama sistem operasi?',
                    'options' => [
                        'A' => 'Mengelola sumber daya hardware',
                        'B' => 'Mengetik dokumen',
                        'C' => 'Membuat video',
                        'D' => 'Membersihkan layar',
                    ],
                    'correct_answer' => 'A',
                ],
                [
                    'type' => 'essay',
                    'question' => 'Jelaskan mengapa manajemen memori sangat krusial dalam multi-tasking.',
                    'rubric' => 'Harus menyebutkan alokasi memori, isolasi proses, dan mencegah crash.',
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'total_challenges' => 2,
            'points_per_question' => 50,
        ]);

        $this->assertDatabaseCount('teacher_challenges', 2);
        $this->assertDatabaseHas('teacher_challenges', [
            'document_id' => $document->id,
            'type' => 'multiple_choice',
            'correct_answer' => 'A',
            'sequence' => 1,
        ]);
        $this->assertDatabaseHas('teacher_challenges', [
            'document_id' => $document->id,
            'type' => 'essay',
            'sequence' => 2,
        ]);
    }

    public function test_student_can_open_and_submit_teacher_challenge(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student', 'entry_grade' => 10, 'enrollment_year' => 2026]);

        $document = Document::query()->create([
            'uploaded_by' => $teacher->id,
            'title' => 'Jaringan Komputer',
            'category' => 'Informatika',
            'target_grade' => '10',
            'body' => 'Topologi jaringan komputer mencakup bus, star, dan ring. Topologi star menggunakan switch pusat.',
            'excerpt' => 'Konsep topologi',
            'estimated_minutes' => 5,
            'is_published' => true,
        ]);

        $q1 = TeacherChallenge::query()->create([
            'document_id' => $document->id,
            'type' => 'multiple_choice',
            'sequence' => 1,
            'question' => 'Topologi manakah yang menggunakan perangkat switch terpusat?',
            'options' => ['A' => 'Star', 'B' => 'Bus', 'C' => 'Ring', 'D' => 'Mesh'],
            'correct_answer' => 'A',
        ]);

        $q2 = TeacherChallenge::query()->create([
            'document_id' => $document->id,
            'type' => 'essay',
            'sequence' => 2,
            'question' => 'Jelaskan kelebihan utama topologi star dibanding topologi bus.',
            'rubric' => 'Kelebihan star: jika satu kabel putus yang lain tetap berjalan, mudah isolasi masalah.',
        ]);

        // Student opens page
        $openRes = $this->actingAs($student)->get(route('student.teacher-challenges.show', $document));
        $openRes->assertOk();
        $openRes->assertSee('Tantangan: Jaringan Komputer');
        $openRes->assertSee('50');
        $openRes->assertSee('Poin');

        // Student submits answers: PG is correct, Essay has thoughtful text
        $submitRes = $this->actingAs($student)->postJson(route('student.teacher-challenges.submit', $document), [
            'answers' => [
                $q1->id => 'A',
                $q2->id => 'Kelebihan topologi star adalah jika salah satu node mengalami gangguan atau kabel putus, jaringan lainnya tetap dapat beroperasi normal karena terhubung ke switch pusat.',
            ],
        ]);

        $submitRes->assertOk();
        $submitRes->assertJsonStructure([
            'completed',
            'score',
            'xp_awarded',
            'answers_breakdown',
        ]);

        $this->assertDatabaseHas('student_teacher_challenge_submissions', [
            'user_id' => $student->id,
            'document_id' => $document->id,
            'status' => 'completed',
        ]);

        // Learning activity recorded
        $this->assertDatabaseHas('learning_activities', [
            'user_id' => $student->id,
            'document_id' => $document->id,
            'event_type' => 'teacher_challenge',
        ]);
    }

    public function test_achievements_page_displays_cohort_ranking_by_grade(): void
    {
        $student10A = User::factory()->create(['name' => 'Aditya Kelas 10', 'role' => 'student', 'entry_grade' => 10, 'enrollment_year' => 2026]);
        $student10B = User::factory()->create(['name' => 'Bima Kelas 10', 'role' => 'student', 'entry_grade' => 10, 'enrollment_year' => 2026]);
        $student11 = User::factory()->create(['name' => 'Citra Kelas 11', 'role' => 'student', 'entry_grade' => 11, 'enrollment_year' => 2026]);

        $response10 = $this->actingAs($student10A)->get(route('achievements', ['grade' => 10]));
        $response10->assertOk();
        $response10->assertSee('Ranking Angkatan Kelas 10');
        $response10->assertSee('Aditya Kelas 10');
        $response10->assertSee('Bima Kelas 10');
        $response10->assertDontSee('Citra Kelas 11');

        $response11 = $this->actingAs($student10A)->get(route('achievements', ['grade' => 11]));
        $response11->assertOk();
        $response11->assertSee('Ranking Angkatan Kelas 11');
        $response11->assertSee('Citra Kelas 11');
        $response11->assertDontSee('Bima Kelas 10');
    }

    public function test_teacher_can_fetch_challenge_suggestions(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $document = Document::query()->create([
            'uploaded_by' => $teacher->id,
            'title' => 'Struktur Data Antrian',
            'category' => 'Informatika',
            'target_grade' => '10',
            'body' => "Queue menggunakan prinsip FIFO.\n\nOperasi enqueue menambahkan elemen ke bagian belakang.",
            'excerpt' => 'Konsep antrian.',
            'estimated_minutes' => 5,
            'is_published' => true,
        ]);

        $response = $this->actingAs($teacher)->getJson(route('teacher.documents.challenges.suggestions', $document));
        $response->assertOk();
        $response->assertJsonStructure(['suggestions']);
        $suggestions = $response->json('suggestions');
        $this->assertNotEmpty($suggestions);
        $this->assertEquals('essay', $suggestions[0]['type']);
    }

    public function test_student_can_open_teacher_challenge_with_automatic_seeding_when_empty(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $document = Document::query()->create([
            'uploaded_by' => $teacher->id,
            'title' => 'Jaringan Komputer',
            'category' => 'Informatika',
            'target_grade' => '10',
            'body' => "Topologi bintang menghubungkan semua simpul ke pusat switch.\n\nTopologi bus menggunakan satu kabel backbone bersama.",
            'excerpt' => 'Pengenalan topologi jaringan.',
            'estimated_minutes' => 5,
            'is_published' => true,
        ]);

        $this->assertEquals(0, $document->teacherChallenges()->count());

        $response = $this->actingAs($student)->get(route('student.teacher-challenges.show', $document));
        $response->assertOk();
        $response->assertSee('Tantangan khusus dari guru pengampu');
        $response->assertSee('100 poin');

        $this->assertGreaterThan(0, $document->teacherChallenges()->count());
    }

    public function test_student_submitting_incorrect_essay_gets_zero_points_and_sees_correct_model_answer(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student', 'entry_grade' => 10, 'enrollment_year' => 2026]);

        $document = Document::query()->create([
            'uploaded_by' => $teacher->id,
            'title' => 'Pengenalan Basis Data',
            'category' => 'Informatika',
            'target_grade' => '10',
            'body' => "Basis data relasional menggunakan tabel dengan baris dan kolom untuk menyimpan data.\n\nKunci utama (primary key) menjamin keunikan setiap baris dalam tabel.",
            'excerpt' => 'Konsep basis data relasional.',
            'estimated_minutes' => 5,
            'is_published' => true,
        ]);

        $challenge = TeacherChallenge::query()->create([
            'document_id' => $document->id,
            'type' => 'essay',
            'sequence' => 1,
            'question' => 'Apa fungsi utama kunci utama (primary key) dalam tabel relasional?',
            'rubric' => 'Kunci utama (primary key) berfungsi menjamin keunikan setiap record/baris dan mencegah duplikasi data.',
        ]);

        // Submit completely wrong / off-topic answer
        $response = $this->actingAs($student)->post(route('student.teacher-challenges.submit', $document), [
            'answers' => [
                $challenge->id => 'Saya suka makan nasi goreng bersama teman di kantin sekolah kemarin siang.',
            ],
        ]);

        $response->assertRedirect(route('student.teacher-challenges.show', $document));

        $viewResponse = $this->actingAs($student)->get(route('student.teacher-challenges.show', $document));
        $viewResponse->assertOk();
        $viewResponse->assertSee('Jawaban Salah / Belum Tepat');
        $viewResponse->assertSee('0.00 / 100 Poin');
        $viewResponse->assertSee('Kunci Jawaban yang Benar Berdasarkan Materi');
        $viewResponse->assertSee('Kunci utama (primary key) berfungsi menjamin keunikan');
    }
}
