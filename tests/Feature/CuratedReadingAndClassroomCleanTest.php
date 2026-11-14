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

    public function test_restructure_and_format_pedagogy_creates_headings_and_cleans_in_text_chores(): void
    {
        $formatter = new ReadingMaterialFormatter();

        $rawBody = "1. Kata serta Frasa Verba dan Nomina Jenis kata dan frasa penting dalam observasi. "
            . "a. Nomina Paragraf Kata Frasa I wayang seni pertunjukan yang telah ditetapkan sebagai warisan budaya asli Indonesia sebuah warisan mahakarya dunia yang menggunakan boneka kayu\n\n"
            . "b. Verba Paragraf Kata Frasa I adalah sudah membagi menetapkan disesuaikan dibuat berarti merupakan mengembangkan\n\n"
            . "Tugas 1 Selanjutnya, lakukan analisis kebahasaan sebagaimana contoh di atas. Tulislah hasil analisis kamu dalam tabel seperti dalam contoh berikut! Paragraf ke:\n\n"
            . "Kata Nomina Frasa Nomina (tabel ini hanya contoh)\n\n"
            . "2. Afiksasi Dalam kegiatan berbahasa kata dasar dapat berubah jenis. "
            . "Contoh 3 Suku pertama barisan geometri adalah 4 dan suku ke-4 adalah 108. Alternatif penyelesaian:\n\n"
            . "Substitusi nilai a.\n\n"
            . "Eksplorasi 2.3 Melipat kertas Siapkan kertas berbentuk persegi panjang. Ayo, bereksplorasi melipat kertas beberapa kali. Tabel 2.2 Jumlah Lipatan Kertas Banyaknya bagian sama besar Jelaskan.\n\n"
            . "U = 2 × … (2 dikali 3 sebanyak … kali) = 2 × 3 … Suku ke-n";

        $formatted = $formatter->format($rawBody, 'Analisis Struktur');

        // Subheadings cleanly promoted to Markdown H3
        $this->assertStringContainsString('### 1. Kata serta Frasa Verba dan Nomina', $formatted);
        $this->assertStringContainsString('### 2. Afiksasi', $formatted);

        // Examples and alternative solutions properly structured
        $this->assertStringContainsString('#### Contoh 3', $formatted);
        $this->assertStringContainsString('**Alternatif penyelesaian:**', $formatted);

        // Flattened table transformed into readable bullet categories
        $this->assertStringContainsString('Contoh Identifikasi Kata dan Frasa Nomina', $formatted);
        $this->assertStringContainsString('Contoh Identifikasi Kata dan Frasa Verba', $formatted);

        // Middle-of-text classroom chores and paper folding stripped
        $this->assertStringNotContainsString('Tugas 1 Selanjutnya, lakukan analisis', $formatted);
        $this->assertStringNotContainsString('(tabel ini hanya contoh)', $formatted);
        $this->assertStringNotContainsString('Melipat kertas Siapkan kertas', $formatted);
        $this->assertStringNotContainsString('2 dikali 3 sebanyak … kali', $formatted);
    }

    public function test_paragraph_continuity_nested_steps_and_asterisk_cleaner(): void
    {
        $formatter = app(ReadingMaterialFormatter::class);

        // 1. Verifikasi penyambungan kalimat yang terpotong di tengah penjelasan akibat pemisah halaman
        $brokenSentenceText = "Wayang kulit di Timur, wayang wong di Jawa Tengah, dan wayang golek di Jawa Barat. Wayang kulit dibuat\n\n"
            . "dari kulit hewan ternak, misalnya kulit kerbau, sapi, atau kambing.\n\n"
            . "Wayang motekar adalah sejenis pertunjukan teater bayang-bayang\n\n"
            . "atau serupa wayang kulit. Namun, jika wayang kulit memiliki bayangan hitam, wayang motekar berwarna-warni.";

        $glued = $formatter->glueBrokenParagraphSentences($brokenSentenceText);
        $this->assertStringContainsString('Wayang kulit dibuat dari kulit hewan ternak', $glued);
        $this->assertStringContainsString('teater bayang-bayang atau serupa wayang kulit. Namun,', $glued);

        // 2. Verifikasi pembersihan simbol asterisk liar & heading kosong
        $dirtySymbolsText = "### Judul Bagian\n\n**\n\n**\n\nEksplorasi 2.4 Pembelahan Bakteri merupakan makhluk******\n\n"
            . "###\n\n###\n\nAlternatif penyelesaian:\n\n**\n\n**\n\n(substitusi nilai a)";

        $cleaned = $formatter->cleanAsterisksAndEmptyHeadings($dirtySymbolsText);
        $this->assertStringNotContainsString("\n**\n", $cleaned);
        $this->assertStringNotContainsString("\n###\n", $cleaned);
        $this->assertStringNotContainsString('******', $cleaned);

        // 3. Verifikasi struktur langkah-langkah bertingkat RRI
        $rriText = "Langkah-langkahmenulis\n1. Menulis Ringkasan\n"
            . "1. Baca teks asli dengan saksama. 2. Tandai kalimat utama setiap paragraf. 3. Buat kerangka isi. 4. Tulis kembali. 5. Periksa kesesuaian.\n"
            . "2. Menulis Rangkuman\n"
            . "1. Catat ide-ide penting. 2. Gabungkan ide-ide penting. 3. Susun ulang secara logis. 4. Gunakan bahasa sendiri.\n"
            . "3. Menulis Ikhtisar\n"
            . "1. Baca dan pahami isi teks. 2. Tentukan gagasan pokok. 3. Tulis kembali. 4. Boleh disertai penilaian atau tanggapan singkat.";

        $formattedRri = $formatter->restructureAndFormatPedagogy($rriText, 'Ringkasan Rangkuman Ikhtisar');
        $this->assertStringContainsString('### Langkah-langkah Menulis', $formattedRri);
        $this->assertStringContainsString('**A. Langkah Menulis Ringkasan:**', $formattedRri);
        $this->assertStringContainsString('**B. Langkah Menulis Rangkuman:**', $formattedRri);
        $this->assertStringContainsString('**C. Langkah Menulis Ikhtisar:**', $formattedRri);

        // 4. Verifikasi Document::formatted_body_html accessor menghasilkan markup paragraf terindeks
        $doc = new Document([
            'title' => 'Materi Uji Paragraf',
            'body' => "Paragraf pertama menjelaskan konsep dasar.\n\nParagraf kedua menguraikan implementasi nyata.",
        ]);

        $html = $doc->formatted_body_html;
        $this->assertStringContainsString('data-paragraph-index="1"', $html);
        $this->assertStringContainsString('data-paragraph-index="2"', $html);
        $this->assertStringContainsString('¶1', $html);
        $this->assertStringContainsString('¶2', $html);
    }

    public function test_diamond_bullets_and_slide_media_credits_cleaner(): void
    {
        $formatter = app(ReadingMaterialFormatter::class);

        $slideRawText = "❖Kapan bahasa Indonesia lahir, bagaimana proses kelahirannya, dan peristiwa apa saja yang melatarbelakanginya? "
            . "❖Lahirnya bahasa Indonesia tentu bukanlah hal yang mudah, pasti penuh tantangan dan perjuangan keras. "
            . "❖Bahasa Indonesia diresmikan penggunaannya satu hari setelah Proklamasi Kemerdekaan Indonesia pada 18 Agustus 1945. "
            . "Sumber Gambar: Ilustrasi Kongres Bahasa Indonesia I. tirto.id/Deadnauval Sumber Video: https://www.youtube.com/watch?v=hnLP9sUba-4\n\n"
            . "Naskah Melayu kuno dengan pengaruh Arab\n\n"
            . "Sumber: aperofublic.com Sumber: bersamawisata.com\n\n"
            . "ma’moer,)\n\n"
            . "•\n•\n•\n•\n•\n";

        $formatted = $formatter->format($slideRawText, 'PKN');

        // Pertanyaan pembuka topik slide dijadikan paragraf pengantar bersih (bukan butir daftar)
        $this->assertStringContainsString('Kapan bahasa Indonesia lahir', $formatted);
        $this->assertStringNotContainsString('- Kapan bahasa Indonesia lahir', $formatted);
        $this->assertStringContainsString('- Lahirnya bahasa Indonesia tentu bukanlah hal yang mudah', $formatted);
        $this->assertStringContainsString('- Bahasa Indonesia diresmikan penggunaannya satu hari setelah Proklamasi', $formatted);

        // Kredit gambar, tautan YouTube, dan watermark slide wajib hilang
        $this->assertStringNotContainsString('Sumber Gambar:', $formatted);
        $this->assertStringNotContainsString('youtube.com', $formatted);
        $this->assertStringNotContainsString('tirto.id', $formatted);
        $this->assertStringNotContainsString('aperofublic.com', $formatted);
        $this->assertStringNotContainsString('ma’moer', $formatted);
        $this->assertStringNotContainsString('ma\'moer', $formatted);
    }

    public function test_inline_bullet_splitting_and_rhetorical_question_excerpt_filter(): void
    {
        $formatter = app(ReadingMaterialFormatter::class);
        $aiProcessor = app(DocumentAiProcessor::class);

        // 1. Uji pemecahan bullet sebaris (inline hyphens)
        $doc28Inline = "- Kapan bahasa Indonesia lahir, bagaimana proses kelahirannya, dan peristiwa apa saja yang melatarbelakanginya? - Lahirnya bahasa Indonesia tentu bukanlah hal yang mudah, pasti penuh tantangan, pemikiran, dan perjuangan keras. - Bahasa Indonesia diresmikan penggunaannya satu hari setelah Proklamasi Kemerdekaan Indonesia, tepatnya pada tanggal 18 Agustus 1945, bersamaan dengan mulai berlakunya Undang-undang Dasar Republik Indonesia 1945.\n\nNaskah Melayu kuno dengan pengaruh Arab";
        $formatted28 = $formatter->format($doc28Inline, 'PKN');

        $this->assertStringContainsString("Kapan bahasa Indonesia lahir", $formatted28);
        $this->assertStringNotContainsString("- Kapan bahasa Indonesia lahir", $formatted28);
        $this->assertStringContainsString("- Lahirnya bahasa Indonesia tentu bukanlah hal yang mudah", $formatted28);
        $this->assertStringContainsString("- Bahasa Indonesia diresmikan penggunaannya", $formatted28);
        // Pastikan tidak menyatu dalam satu baris dengan inline hyphen
        $this->assertStringNotContainsString("? - Lahirnya", $formatted28);
        $this->assertStringNotContainsString("keras. - Bahasa", $formatted28);

        // 2. Uji bullet sebaris pada kaidah kebahasaan
        $doc29Inline = "Unsur-Unsur Kaidah Bahasa\n\n- Diksi (pilihan kata) → tepat, sesuai konteks. - Ejaan (EBI) → huruf kapital, tanda baca, penulisan kata. - Kalimat → subjek & predikat jelas. - Kohesi & Koherensi → kepaduan bentuk & makna. - Gaya Bahasa → majas sesuai tujuan.";
        $formatted29 = $formatter->format($doc29Inline, 'PKL');

        $this->assertStringContainsString("- Diksi (pilihan kata)", $formatted29);
        $this->assertStringContainsString("- Ejaan (EBI)", $formatted29);
        $this->assertStringContainsString("- Kalimat → subjek & predikat jelas.", $formatted29);
        $this->assertStringContainsString("- Kohesi & Koherensi", $formatted29);
        $this->assertStringContainsString("- Gaya Bahasa", $formatted29);

        // 3. Pastikan teks bold Markdown **Judul** tidak rusak menjadi - *Judul
        $boldText = "Contoh Perbaikan Kalimat\n\n**Latihan Perbaikan Kalimat Perbaiki kalimat berikut:**\n\n1. Kami sudah mempersiapkan.";
        $formattedBold = $formatter->format($boldText, 'Latihan');
        $this->assertStringContainsString("**Latihan Perbaikan Kalimat Perbaiki kalimat berikut:**", $formattedBold);
        $this->assertStringNotContainsString("- *Latihan", $formattedBold);

        // 4. Pastikan intisari bacaan (excerpt) menolak pertanyaan retoris yang diakhiri tanda tanya
        $excerpt28 = $aiProcessor->generateHeuristicExcerpt($formatted28, 'PKN');
        $this->assertStringNotContainsString("Kapan bahasa Indonesia lahir", $excerpt28);
        $this->assertStringContainsString("Lahirnya bahasa Indonesia tentu bukanlah hal yang mudah", $excerpt28);
        $this->assertFalse(str_ends_with(trim($excerpt28), '?'));

        // 5. Pastikan pertanyaan dengan jawaban substantif hanya mengambil jawabannya
        $qAndA = "Apa yang dimaksud dengan kaidah kebahasaan? seperangkat aturan dan norma yang mengatur penggunaan bahasa Indonesia agar komunikasi dapat berjalan dengan baik, benar, dan jelas.";
        $excerpt29 = $aiProcessor->generateHeuristicExcerpt($qAndA, 'PKL');
        $this->assertStringNotContainsString("Apa yang dimaksud", $excerpt29);
        $this->assertStringContainsString("Seperangkat aturan dan norma yang mengatur", $excerpt29);
    }
}
