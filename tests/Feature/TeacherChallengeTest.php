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
            'body' => "Queue adalah struktur data yang menggunakan prinsip FIFO (First In First Out).\n\nOperasi enqueue berfungsi menambahkan elemen ke bagian belakang antrian.",
            'excerpt' => 'Konsep antrian.',
            'estimated_minutes' => 5,
            'is_published' => true,
        ]);

        $response = $this->actingAs($teacher)->getJson(route('teacher.documents.challenges.suggestions', $document));
        $response->assertOk();
        $response->assertJsonStructure(['suggestions', 'max_xp']);
        $suggestions = $response->json('suggestions');
        $this->assertNotEmpty($suggestions);
        $this->assertEquals('essay', $suggestions[0]['type']);
        $this->assertArrayHasKey('context', $suggestions[0]);
        $this->assertArrayHasKey('difficulty', $suggestions[0]);
        $this->assertContains($suggestions[0]['difficulty'], ['easy', 'medium', 'hard']);
        $this->assertStringNotContainsString('(Konteks:', $suggestions[0]['question']);
    }

    public function test_teacher_custom_challenges_receive_weighted_difficulty_scoring_and_reward_xp(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $document = Document::query()->create([
            'uploaded_by' => $teacher->id,
            'title' => 'Trigatra Bangun Bahasa',
            'category' => 'Bahasa Indonesia',
            'target_grade' => '11',
            'body' => 'Trigatra Bangun Bahasa memadukan pengutamaan bahasa Indonesia, pelestarian bahasa daerah, dan penguasaan bahasa asing.',
            'excerpt' => 'Konsep kebahasaan nasional.',
            'estimated_minutes' => 5,
            'is_published' => true,
        ]);

        $response = $this->actingAs($teacher)->postJson(route('teacher.documents.challenges.store', $document), [
            'challenges' => [
                [
                    'type' => 'essay',
                    'question' => 'Apa pengertian Trigatra Bangun Bahasa?',
                    'context' => 'Fokus pada definisi pokok.',
                    'rubric' => 'Strategi memadukan bahasa Indonesia, daerah, dan asing.',
                ],
                [
                    'type' => 'essay',
                    'question' => 'Rancang strategi konkret menjaga keseimbangan bahasa Indonesia dan bahasa asing di era digital!',
                    'context' => 'Berikan contoh nyata.',
                    'rubric' => 'Mengutamakan bahasa Indonesia di ruang publik sekaligus menguasai bahasa asing untuk akses iptek.',
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(20, $response->json('max_xp'));

        $saved = $response->json('challenges');
        $this->assertSame('easy', $saved[0]['difficulty']);
        $this->assertSame('hard', $saved[1]['difficulty']);
        // Easy (1) vs Hard (3) => total weight 4 => Easy 25.00 poin, Hard 75.00 poin
        $this->assertEquals(25.0, $saved[0]['points_max']);
        $this->assertEquals(75.0, $saved[1]['points_max']);
    }

    public function test_student_sees_empty_message_when_teacher_challenge_is_empty(): void
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
        $response->assertSee('Tidak Ada Tantangan dari Guru');
        $response->assertSee('Challenge Socrates');

        // Pastikan tidak melakukan auto-seed soal
        $this->assertEquals(0, $document->fresh()->teacherChallenges()->count());

        // Halaman katalog Challenge Socrates juga menampilkan pesan kosong tanpa tombol 0 soal
        $quizIndexResponse = $this->actingAs($student)->get(route('quiz.index'));
        $quizIndexResponse->assertOk();
        $quizIndexResponse->assertSee('Tidak ada tantangan dari guru');
        $quizIndexResponse->assertDontSee('Buka Tantangan Guru (0 Soal)');
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

    public function test_teacher_can_clear_all_challenges_for_document(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $document = Document::query()->create([
            'uploaded_by' => $teacher->id,
            'title' => 'Materi Uji',
            'category' => 'SISTEM OPERASI',
            'target_grade' => '10',
            'body' => 'Pengujian penghapusan seluruh tantangan guru.',
            'excerpt' => 'Konsep pengujian.',
            'estimated_minutes' => 5,
            'is_published' => true,
        ]);

        TeacherChallenge::query()->create([
            'document_id' => $document->id,
            'type' => 'essay',
            'sequence' => 1,
            'question' => 'Pertanyaan 1',
        ]);

        $this->assertEquals(1, $document->teacherChallenges()->count());

        $response = $this->actingAs($teacher)->postJson(route('teacher.documents.challenges.store', $document), [
            'challenges' => [],
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'total_challenges' => 0,
            'points_per_question' => 0,
        ]);

        $this->assertEquals(0, $document->fresh()->teacherChallenges()->count());

        // Calling index should also return 0 without auto-seeding
        $indexResponse = $this->actingAs($teacher)->getJson(route('teacher.documents.challenges.index', $document));
        $indexResponse->assertOk();
        $indexResponse->assertJson([
            'total_challenges' => 0,
            'challenges' => [],
        ]);
    }

    public function test_browser_resubmit_on_completed_teacher_challenge_redirects_instead_of_dumping_json(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);

        $document = Document::query()->create([
            'uploaded_by' => $teacher->id,
            'title' => 'Trigatra Bangun Bahasa',
            'category' => 'Bahasa Indonesia',
            'target_grade' => '10',
            'body' => "Setelah mengikuti pembelajaran ini, mahasiswa mampu:\n- Menjelaskan pengertian Trigatra Bangun Bahasa.\n\nTrigatra Bangun Bahasa adalah konsep yang diperkenalkan oleh Anton Moeliono (1985).",
            'excerpt' => 'Konsep Trigatra Bangun Bahasa.',
            'estimated_minutes' => 5,
            'is_published' => true,
        ]);

        $challenge = TeacherChallenge::query()->create([
            'document_id' => $document->id,
            'type' => 'essay',
            'sequence' => 1,
            'question' => 'APA YANG DIMAKSUD DENGAN TRIGATRA BANGUN BAHASA?',
            'rubric' => 'pengertian trigatra bangun bahasa',
            'difficulty' => 'easy',
            'difficulty_weight' => 1,
        ]);

        // Submit pertama via browser form (non-JSON)
        $firstRes = $this->actingAs($student)->post(route('student.teacher-challenges.submit', $document), [
            'answers' => [$challenge->id => 'tidak tahu'],
        ]);
        $firstRes->assertRedirect(route('student.teacher-challenges.show', $document));

        // Submit kedua (double-submit browser form) harus redirect rapi, bukan layar JSON 403
        $secondRes = $this->actingAs($student)->post(route('student.teacher-challenges.submit', $document), [
            'answers' => [$challenge->id => 'tidak tahu'],
        ]);
        $secondRes->assertRedirect(route('student.teacher-challenges.show', $document));

        // Pastikan kunci jawaban mengambil definisi nyata, bukan kalimat tujuan pembelajaran
        $reviewRes = $this->actingAs($student)->get(route('student.teacher-challenges.show', $document));
        $reviewRes->assertOk();
        $reviewRes->assertSee('Anton Moeliono (1985)');
        $reviewRes->assertDontSee('Menjelaskan pengertian Trigatra Bangun Bahasa.');
    }
}
