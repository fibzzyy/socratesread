<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\TeacherChallenge;
use App\Models\TeacherClass;
use App\Models\User;
use App\Services\AI\Contracts\LanguageModel;
use App\Services\AI\DocumentAiProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherChallengeCoPilotAndAlignmentTest extends TestCase
{
    use RefreshDatabase;

    private string $virtualHostBody = <<<'TEXT'
3.5.6. VirtualHost (SubDomain)

Selain domain utama semisal amjapoltesa.net, masih bisa kita tambahkan lagi Sub-Domain dari domain utama tersebut. Sehingga kita lebih menghemat Ip Address dan juga domain. Contoh subdomain adalah, mail.yahoo.com dan mail.google.com. Kata mail pada domain tersebutlah yang dinamakan “sub” domain.

Jika pada saat mengkonfigurasi dns mengikuti apa yang saya konfigurasikan sama dengan modul ini maka anda menemukan beberapa sub-domain yaitu ftp.amjapoltesa.net , mail.amjapoltesa.net, portal.amjapoltesa.net inilah sub domain yang anda buat akan tetapi jika anda membuka sub-domain tersebut maka yang keluar adalah sama lalu bagaimana anda dapat mengisi atau menganti dari setiap sub-domain anda cukup membuat virtualhost saja lalu anda aktifkan melalui apache atau anda dapat mengarahkanya ke server lain

Pertama buat sebuah folder dan file index untuk isi konten sub domain seperti portal yaitu saya membuat di dalam folder /var/www saya membuat folder dengan nama portal dengan perintah ”#mkdir portal” lalu membuat file index.html denga perintah “#nano /var/www/portal/index.html” kemudian saya isikan script seperti gambar dibawah

Simpan dan keluar dengan menekan ctrl+x tekan y tekan enter , kemudian anda berpindah directori untuk memulai konfigurasi virtualhost dengan perintah seperti dibawah

Anda berpindah directory ke sites-availeble, untuk mempermudah konfigurasi silahkan anda salin file default ke nama sub-domain seperti portal

**Keterangan :**
- ServerName = Nama domain
- ServerAlias = Nama subdomain yang ingin dialihkan/dijadikan virtual alias.
- ServerAdmin = Alamat email pemilik domain.
- DocumentRoot = Tempat pengalihan direktori dari Virtual Alias yang dibuat.

Kemudian anda masukan perintah : #a2ensite portal Enabling site portal. To activate the new configuration, you need to run: service apache2 reload

Setelah itu anda coba buka melalui browser client jika tidak bisa terbuka atau masih sama coba masukan perintah “service apache2 restart” atau tekan f5 dibrowser anda atau mungkin lagi periksa konfigurasi anda jika berhasil akan terbuka di client seperti gambar dibawah
TEXT;

    private string $trigatraBody = <<<'TEXT'
### TRIGATRA BANGUN BAHASA

Setelah mengikuti pembelajaran ini, mahasiswa diharapkan mampu:
- Menjelaskan pengertian Trigatra Bangun Bahasa.

### TUJUAN PEMBELAJARAN

- Trigatra Bangun Bahasa adalah konsep yang diperkenalkan oleh Anton Moeliono (1985) melalui buku Pengembangan dan Pembinaan Bahasa: Trigatra Bangun Bahasa.
- Trigatra Bangun Bahasa adalah strategi kebahasaan Indonesia yang memadukan peran bahasa Indonesia, bahasa daerah, dan bahasa asing dalam pembangunan bangsa.
- Trigatra bangun bahasa adalah konsep yang menekankan pada tiga aspek utama pembinaan bahasa Indonesia.

### DEFINISI

- Bahasa Indonesia sebagai bahasa persatuan dan identitas nasional.
- Bahasa Daerah sebagai penopang kebudayaan dan kekayaan identitas lokal.
- Bahasa Asing sebagai sarana komunikasi global dan pengembangan ilmu pengetahuan.

Kedudukan: bahasa nasional & bahasa negara (UUD 1945 **Pasal 36**). Fungsi utama:
- Lambang identitas nasional.
- Alat pemersatu bangsa.
- Sarana komunikasi resmi & ilmu pengetahuan. Contoh penggunaan: pendidikan, pemerintahan, media, karya ilmiah.

### BAHASA INDONESIA

- Kedudukan: bagian dari kebudayaan nasional yang dilindungi UUD 1945 **Pasal 32**.
- Fungsi utama:
  - Sarana komunikasi di lingkungan lokal.
  - Media pelestarian tradisi, sastra, dan kearifan lokal.
  - Fondasi pembentukan bahasa Indonesia.
- Contoh penggunaan: percakapan sehari -hari di daerah, kesenian, upacara adat.

### BAHASA DAERAH

- Bahasa Indonesia -> diutamakan sebagai identitas bangsa.
- Bahasa Daerah -> dilestarikan agar tidak punah.
- Bahasa Asing -> dikuasai untuk bersaing global.
- Prinsip: "Mengutamakan bahasa Indonesia, melestarikan bahasa daerah, menguasai bahasa asing."
TEXT;

    public function test_subdomain_virtualhost_suggestions_directly_answer_each_question_without_context_or_ctrl_x_mismatch(): void
    {
        $processor = app(DocumentAiProcessor::class);
        $suggestions = $processor->generateTeacherChallengeSuggestions(
            $this->virtualHostBody,
            'SUB DOMAIN VIRTUAL HOST',
            'ADMINISTRASI JARINGAN'
        );

        $this->assertCount(5, $suggestions);

        foreach ($suggestions as $item) {
            $this->assertNull($item['context']);
            $this->assertStringNotContainsStringIgnoringCase('ctrl+x', $item['rubric']);
            $this->assertNotEmpty($item['reference_excerpt']);
        }

        // Q1 harus langsung menjawab fungsi & gagasan utama Sub-Domain
        $this->assertStringContainsStringIgnoringCase('fungsi dan gagasan utama penggunaan sub-domain', $suggestions[0]['question']);
        $this->assertStringContainsStringIgnoringCase('fungsi dan gagasan utama', $suggestions[0]['rubric']);
        $this->assertStringContainsStringIgnoringCase('hemat', $suggestions[0]['rubric']);

        // Q2 harus menanyakan VirtualHost & menjawab dengan mekanisme membedakan tampilan sub-domain via Apache/server lain
        $this->assertStringContainsStringIgnoringCase('virtualhost', $suggestions[1]['question']);
        $this->assertStringContainsStringIgnoringCase('halaman yang sama', $suggestions[1]['rubric']);
        $this->assertStringContainsStringIgnoringCase('apache', $suggestions[1]['rubric']);
    }

    public function test_teacher_endpoint_recommends_direct_answer_from_material_for_manual_question(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);

        $document = Document::query()->create([
            'uploaded_by' => $teacher->id,
            'title' => 'TRIGATRA BANGUN BAHASA',
            'category' => 'BAHASA INDONESIA',
            'target_grade' => '10',
            'body' => $this->trigatraBody,
            'excerpt' => 'Konsep Trigatra Bangun Bahasa.',
            'estimated_minutes' => 5,
            'is_published' => true,
        ]);

        // Pendamping harus langsung merekomendasikan definisi Anton Moeliono & strategi 3 bahasa dari materi
        $res = $this->actingAs($teacher)->postJson(
            route('teacher.documents.challenges.recommend_answer', $document),
            ['question' => 'APA PENGERTIAN TRIGATRA BANGUN BAHASA YANG KAMU KETAHUI?']
        );

        $res->assertOk();
        $res->assertJsonStructure(['recommended_answer', 'reference_excerpt', 'difficulty', 'difficulty_weight']);
        $answer = (string) $res->json('recommended_answer');
        $this->assertStringContainsStringIgnoringCase('Anton Moeliono', $answer);
        $this->assertStringContainsStringIgnoringCase('bahasa daerah', $answer);

        // Guru lain tidak berhak mengakses endpoint materi ini
        $this->actingAs($otherTeacher)->postJson(
            route('teacher.documents.challenges.recommend_answer', $document),
            ['question' => 'Apa pengertian Trigatra Bangun Bahasa?']
        )->assertForbidden();
    }

    public function test_recommend_answer_uses_ai_payload_when_language_model_is_configured(): void
    {
        $fakeLm = new class implements LanguageModel
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function respond(array $messages, ?string $instructions = null, array $options = []): string
            {
                return json_encode([
                    'recommended_answer' => 'Trigatra Bangun Bahasa adalah konsep pembinaan kebahasaan oleh Anton Moeliono (1985) yang menyelaraskan pengutamaan bahasa Indonesia, pelestarian bahasa daerah, dan penguasaan bahasa asing.',
                    'reference_excerpt' => 'Trigatra Bangun Bahasa adalah strategi kebahasaan Indonesia yang memadukan peran bahasa Indonesia, bahasa daerah, dan bahasa asing dalam pembangunan bangsa.',
                    'difficulty' => 'easy',
                ], JSON_THROW_ON_ERROR);
            }
        };

        $this->app->instance(LanguageModel::class, $fakeLm);

        $teacher = User::factory()->create(['role' => 'teacher']);
        $document = Document::query()->create([
            'uploaded_by' => $teacher->id,
            'title' => 'TRIGATRA BANGUN BAHASA',
            'category' => 'BAHASA INDONESIA',
            'target_grade' => '10',
            'body' => $this->trigatraBody,
            'excerpt' => 'Konsep Trigatra.',
            'estimated_minutes' => 5,
            'is_published' => true,
        ]);

        $res = $this->actingAs($teacher)->postJson(
            route('teacher.documents.challenges.recommend_answer', $document),
            ['question' => 'APA PENGERTIAN TRIGATRA BANGUN BAHASA YANG KAMU KETAHUI?']
        );

        $res->assertOk();
        $res->assertJsonPath('difficulty', 'easy');
        $res->assertJsonPath('difficulty_weight', 1);
        $this->assertStringContainsString('Anton Moeliono (1985)', (string) $res->json('recommended_answer'));
    }

    public function test_teacher_documents_ui_hides_context_input_and_exposes_direct_answer_clear_button(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        Document::query()->create([
            'uploaded_by' => $teacher->id,
            'title' => 'TRIGATRA BANGUN BAHASA',
            'category' => 'BAHASA INDONESIA',
            'target_grade' => '10',
            'body' => $this->trigatraBody,
            'excerpt' => 'Konsep Trigatra.',
            'estimated_minutes' => 5,
            'is_published' => true,
        ]);

        $res = $this->actingAs($teacher)->get(route('teacher.documents'));
        $res->assertOk();
        $res->assertDontSee('Konteks / Arahan Berpikir');
        $res->assertDontSee('id="newQContext"', false);
        $res->assertSee('Kunci Jawaban:');
        $res->assertSee('data-clear-rubric', false);
        $res->assertSee('id="btnClearAllChallenges"', false);
        $res->assertSee('data-recommend-answer-url=', false);
    }

    public function test_manual_essay_saved_without_context_populates_reference_excerpt_and_evaluates_student_paraphrase(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student', 'entry_grade' => 10, 'enrollment_year' => 2026]);

        $document = Document::query()->create([
            'uploaded_by' => $teacher->id,
            'title' => 'TRIGATRA BANGUN BAHASA',
            'category' => 'BAHASA INDONESIA',
            'target_grade' => '10',
            'body' => $this->trigatraBody,
            'excerpt' => 'Konsep Trigatra.',
            'estimated_minutes' => 5,
            'is_published' => true,
        ]);

        $storeRes = $this->actingAs($teacher)->postJson(route('teacher.documents.challenges.store', $document), [
            'challenges' => [
                [
                    'type' => 'essay',
                    'question' => 'APA PENGERTIAN TRIGATRA BANGUN BAHASA YANG KAMU KETAHUI?',
                    'context' => null,
                    'rubric' => 'Trigatra Bangun Bahasa adalah konsep kebahasaan yang dicetuskan Anton Moeliono untuk memadukan peran bahasa Indonesia, bahasa daerah, dan bahasa asing dalam pembangunan bangsa.',
                ],
            ],
        ]);
        $storeRes->assertOk();

        $savedChallenge = TeacherChallenge::query()->where('document_id', $document->id)->firstOrFail();
        $this->assertNull($savedChallenge->context);
        $this->assertNotEmpty($savedChallenge->reference_excerpt);
        $this->assertStringContainsStringIgnoringCase('Trigatra', $savedChallenge->reference_excerpt);

        $submitRes = $this->actingAs($student)->post(route('student.teacher-challenges.submit', $document), [
            'answers' => [
                $savedChallenge->id => 'Trigatra Bangun Bahasa merupakan gagasan Anton Moeliono yang menggabungkan peran bahasa Indonesia, bahasa daerah, dan bahasa asing untuk membangun bangsa.',
            ],
        ]);
        $submitRes->assertRedirect(route('student.teacher-challenges.show', $document));

        $reviewRes = $this->actingAs($student)->get(route('student.teacher-challenges.show', $document));
        $reviewRes->assertOk();
        $reviewRes->assertDontSee('Konteks:');
        $reviewRes->assertSee('<strong class="font-bold text-slate-950">', false);
        $reviewRes->assertDontSee('Jawaban Salah / Belum Tepat');
    }

    public function test_store_saves_and_edits_challenges_instantly_without_calling_external_ai(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $document = Document::query()->create([
            'uploaded_by' => $teacher->id,
            'title' => 'TRIGATRA BANGUN BAHASA',
            'category' => 'BAHASA INDONESIA',
            'target_grade' => '10',
            'body' => $this->trigatraBody,
            'excerpt' => 'Konsep Trigatra.',
            'estimated_minutes' => 5,
            'is_published' => true,
        ]);

        // Even when LanguageModel is configured, saving challenges must NOT make a slow external AI call.
        $this->app->instance(LanguageModel::class, new class implements LanguageModel
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function respond(array $messages, string $instructions = '', array $options = []): string
            {
                throw new \RuntimeException('store() must not call external LanguageModel::respond()');
            }
        });

        $initialRes = $this->actingAs($teacher)->postJson(route('teacher.documents.challenges.store', $document), [
            'challenges' => [
                [
                    'type' => 'essay',
                    'question' => 'Apa pengertian Trigatra Bangun Bahasa?',
                    'rubric' => 'Konsep awal Trigatra.',
                    'force_analyze' => true,
                ],
                [
                    'type' => 'multiple_choice',
                    'question' => 'Bahasa apa yang menjadi bahasa negara menurut materi?',
                    'options' => [
                        'A' => 'Bahasa Indonesia',
                        'B' => 'Bahasa Daerah',
                        'C' => 'Bahasa Asing',
                        'D' => 'Bahasa Pergaulan',
                    ],
                    'correct_answer' => 'A',
                    'difficulty' => 'easy',
                ],
            ],
        ]);

        $initialRes->assertOk();
        $initialRes->assertJsonPath('total_challenges', 2);

        // Simulate teacher editing both the essay (question, rubric, difficulty) and PG options/key
        $editRes = $this->actingAs($teacher)->postJson(route('teacher.documents.challenges.store', $document), [
            'challenges' => [
                [
                    'type' => 'essay',
                    'question' => 'Mengapa kedudukan Bahasa Indonesia harus diutamakan dalam Trigatra Bangun Bahasa?',
                    'rubric' => 'Bahasa Indonesia berkedudukan sebagai bahasa negara sekaligus pemersatu bangsa sesuai UUD 1945 Pasal 36.',
                    'difficulty' => 'medium',
                ],
                [
                    'type' => 'multiple_choice',
                    'question' => 'Di bawah ini yang termasuk peran bahasa asing adalah...',
                    'options' => [
                        'A' => 'Bahasa resmi kenegaraan',
                        'B' => 'Akses ilmu pengetahuan dan teknologi global',
                        'C' => 'Pelestarian tradisi lokal',
                        'D' => 'Bahasa pengantar daerah',
                    ],
                    'correct_answer' => 'B',
                    'difficulty' => 'hard',
                ],
            ],
        ]);

        $editRes->assertOk();
        $editRes->assertJsonPath('total_challenges', 2);
        $editRes->assertJsonPath('challenges.0.question', 'Mengapa kedudukan Bahasa Indonesia harus diutamakan dalam Trigatra Bangun Bahasa?');
        $editRes->assertJsonPath('challenges.0.difficulty', 'medium');
        $editRes->assertJsonPath('challenges.1.correct_answer', 'B');
        $editRes->assertJsonPath('challenges.1.options.B', 'Akses ilmu pengetahuan dan teknologi global');

        $saved = $document->teacherChallenges()->orderBy('sequence')->get();
        $this->assertCount(2, $saved);
        $this->assertSame('Mengapa kedudukan Bahasa Indonesia harus diutamakan dalam Trigatra Bangun Bahasa?', $saved[0]->question);
        $this->assertStringContainsString('UUD 1945 Pasal 36', (string) $saved[0]->rubric);
        $this->assertNotEmpty($saved[0]->reference_excerpt);
        $this->assertSame('B', $saved[1]->correct_answer);
        $this->assertSame('hard', $saved[1]->difficulty);
    }

    public function test_teacher_documents_and_classrooms_use_web_confirm_popup_and_support_challenge_editing(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        Document::query()->create([
            'uploaded_by' => $teacher->id,
            'title' => 'TRIGATRA BANGUN BAHASA',
            'category' => 'BAHASA INDONESIA',
            'target_grade' => '10',
            'body' => $this->trigatraBody,
            'excerpt' => 'Konsep Trigatra.',
            'estimated_minutes' => 5,
            'is_published' => true,
        ]);

        TeacherClass::query()->create([
            'teacher_id' => $teacher->id,
            'name' => 'BAHASA INDONESIA',
            'target_grade' => 10,
            'join_code' => 'BINDO10',
            'is_active' => true,
        ]);

        $docsRes = $this->actingAs($teacher)->get(route('teacher.documents'));
        $docsRes->assertOk();
        $docsRes->assertDontSee('return confirm(', false);
        $docsRes->assertDontSee('alert(', false);
        $docsRes->assertSee('id="webConfirmModal"', false);
        $docsRes->assertSee('id="webToastContainer"', false);
        $docsRes->assertSee('window.SocratesUI', false);
        $docsRes->assertSee('data-confirm-title="Hapus Bacaan Secara Permanen?"', false);
        $docsRes->assertSee('data-edit-idx=', false);
        $docsRes->assertSee('data-edit-question', false);
        $docsRes->assertSee('data-edit-rubric', false);
        $docsRes->assertSee('data-edit-recommend-rubric', false);
        $docsRes->assertSee('data-edit-clear-rubric', false);
        $docsRes->assertSee('data-edit-apply', false);
        $docsRes->assertSee('syncDocumentChallengeSummary', false);

        $classRes = $this->actingAs($teacher)->get(route('teacher.classrooms'));
        $classRes->assertOk();
        $classRes->assertDontSee('return confirm(', false);
        $classRes->assertSee('id="webConfirmModal"', false);
        $classRes->assertSee('data-confirm-title="Arsipkan Kelas?"', false);
    }
}
