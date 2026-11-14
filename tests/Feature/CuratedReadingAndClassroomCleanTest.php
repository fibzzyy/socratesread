<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Services\AI\DocumentAiProcessor;
use App\Services\ReadingMaterialFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class CuratedReadingAndClassroomCleanTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_edit_curated_reading_body(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);

        $document = Document::query()->create([
            'uploaded_by' => $teacher->id,
            'title' => 'Barisan Geometri',
            'category' => 'Matematika',
            'grade_level' => '10',
            'is_published' => true,
            'body' => 'Paragraf awal materi barisan geometri yang menjelaskan konsep rasio konstan antar dua suku berurutan.',
        ]);

        $newBody = "### 1. Definisi Barisan Geometri\n\nBarisan geometri adalah barisan bilangan di mana perbandingan dua suku yang berurutan selalu bernilai tetap atau konstan, yang disebut sebagai rasio (r).\n\nRumus umum suku ke-n barisan geometri dinyatakan sebagai Un = a * r^(n-1).";

        // 1. Authorized teacher updates reading body via AJAX/PATCH
        $response = $this->actingAs($teacher)->patchJson(route('teacher.documents.update_body', $document), [
            'body' => $newBody,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Teks bacaan berhasil diperbarui.',
            ])
            ->assertJsonStructure(['body', 'formatted_body_html', 'chars']);

        $document->refresh();
        $this->assertStringContainsString('Definisi Barisan Geometri', $document->body);
        $this->assertStringContainsString('Un = a * r^(n-1)', $document->body);

        // 2. Unauthorized teacher is forbidden
        $forbiddenResponse = $this->actingAs($otherTeacher)->patchJson(route('teacher.documents.update_body', $document), [
            'body' => 'Perubahan ilegal dari guru lain.',
        ]);
        $forbiddenResponse->assertForbidden();

        // 3. Validation fails for empty or too short body
        $invalidResponse = $this->actingAs($teacher)->patchJson(route('teacher.documents.update_body', $document), [
            'body' => 'Terlalu pendek',
        ]);
        $invalidResponse->assertStatus(422);
    }

    public function test_reading_material_cleaner_removes_classroom_homework_and_margin_footers(): void
    {
        $formatter = new ReadingMaterialFormatter();

        $rawText = "Teks laporan hasil observasi berfungsi sebagai alat pendokumentasian suatu objek atau kegiatan. 21Bahasa Indonesia\n\n"
            . "Tugas 1\n1. Simpulkanlah fungsi teks laporan hasil observasi berdasarkan penjelasan di atas!\n\n"
            . "2. Afiksasi\nImbuhan membentuk kata baru.\nNo. Kata Berimbuhan Jenis Imbuhan Kata Dasar\n1. Disebut verba di- sebut\nKelas X SMA/MA/SMK/MAK36\n\n"
            . "Tugas 2 Analisislah afiksasi yang terjadi pada kata berimbuhan di bawah ini.\nNo. Kata Berimbuhan\n1. pertunjukan\n2. menguasai";

        $cleaned = $formatter->cleanClassroomHomework($rawText);

        // Footer margins removed
        $this->assertStringNotContainsString('21Bahasa Indonesia', $cleaned);
        $this->assertStringNotContainsString('Kelas X SMA/MA/SMK/MAK36', $cleaned);

        // End-of-chapter empty homework table removed
        $this->assertStringNotContainsString('Tugas 2 Analisislah afiksasi', $cleaned);
        $this->assertStringNotContainsString('pertunjukan', $cleaned);

        // Instructional core content preserved
        $this->assertStringContainsString('Teks laporan hasil observasi berfungsi sebagai alat pendokumentasian', $cleaned);
        $this->assertStringContainsString('2. Afiksasi', $cleaned);
        $this->assertStringContainsString('Disebut verba di- sebut', $cleaned);
    }

    public function test_anti_classroom_boilerplate_filters_group_activities_from_quiz_sentences(): void
    {
        $processor = app(DocumentAiProcessor::class);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('extractSubstantiveSentences');
        $method->setAccessible(true);

        $body = "Ayo, bereksplorasi dengan melipat selembar kertas menjadi dua bagian sama besar.\n"
            . "Siapkan kertas karton dan gunting bersama teman kelompokmu.\n"
            . "Berkumpullah dengan kelompokmu untuk mendiskusikan tugas ini.\n"
            . "Barisan geometri adalah susunan bilangan di mana perbandingan antara dua suku yang berurutan selalu bernilai tetap.\n"
            . "Kerjakan latihan berikut di buku tugas masing-masing.";

        $sentences = $method->invoke($processor, $body, 'Barisan Geometri');

        $this->assertNotEmpty($sentences);
        $this->assertContains('Barisan geometri adalah susunan bilangan di mana perbandingan antara dua suku yang berurutan selalu bernilai tetap.', $sentences);

        // Ensure physical classroom chore lines are strictly filtered out
        foreach ($sentences as $s) {
            $this->assertStringNotContainsString('melipat selembar kertas', $s);
            $this->assertStringNotContainsString('Siapkan kertas karton', $s);
            $this->assertStringNotContainsString('Berkumpullah dengan kelompokmu', $s);
            $this->assertStringNotContainsString('Kerjakan latihan berikut', $s);
        }
    }

    public function test_compact_reference_excerpt_shortens_and_focuses_evidence_sentence(): void
    {
        $processor = app(DocumentAiProcessor::class);

        // Multiple sentences should be compacted to the first concise evidence sentence
        $multiSentence = "Wayang suket merupakan salah satu inovasi wayang yang menggunakan rumput suket sebagai bahan utama. Inovasi ini digagas oleh dalang Slamet Gundono untuk memperkenalkan kesenian wayang secara sederhana kepada kalangan pemuda.";
        $compacted = $processor->compactReferenceExcerpt($multiSentence);

        $this->assertEquals("Wayang suket merupakan salah satu inovasi wayang yang menggunakan rumput suket sebagai bahan utama.", $compacted);

        // Extremely long single sentence without periods should be trimmed cleanly at word boundary
        $longSentence = str_repeat("Konsep rasio konstan pada barisan geometri menentukan laju pertambahan nilai secara eksponensial ", 5);
        $trimmed = $processor->compactReferenceExcerpt($longSentence);

        $this->assertLessThanOrEqual(205, mb_strlen($trimmed));
        $this->assertStringEndsWith('...', $trimmed);
    }
}
