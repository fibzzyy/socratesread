<?php

namespace App\Console\Commands;

use App\Http\Controllers\Student\StudentTeacherChallengeController;
use App\Models\Document;
use App\Models\TeacherChallenge;
use App\Services\AI\DocumentAiProcessor;
use App\Services\ReadingMaterialFormatter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;

class RefreshDocumentsContent extends Command
{
    protected $signature = 'documents:refresh-content {--force-quiz : Regenerasi semua soal kuis meski bukan templat lama} {--force-challenges : Regenerasi ulang semua soal tantangan guru} {--reset-progress : Reset bersih seluruh riwayat baca, kuis, dan tantangan siswa ke kondisi awal} {--offline : Jalankan tanpa memanggil API OpenRouter}';

    protected $description = 'Ekstrak ulang & rapikan isi materi PDF yang tersimpan serta perbarui soal/kesulitan tantangan.';

    public function handle(ReadingMaterialFormatter $formatter, DocumentAiProcessor $processor, StudentTeacherChallengeController $challengeController): int
    {
        if ($this->option('offline')) {
            config(['services.openrouter.api_key' => '']);
        }

        if ($this->option('reset-progress')) {
            $this->resetStudentLearningProgress();
        }
        $documents = Document::query()->orderBy('id')->get();
        if ($documents->isEmpty()) {
            $this->info('Tidak ada dokumen untuk diperbarui.');

            return self::SUCCESS;
        }

        $parserConfig = new Config();
        $parserConfig->setDecodeMemoryLimit(128 * 1024 * 1024);
        $parser = new Parser([], $parserConfig);

        foreach ($documents as $doc) {
            $this->line("Memproses #{$doc->id}: {$doc->title}...");

            // 1. Ekstrak ulang teks utuh dari PDF tersimpan (jika tersedia)
            $rawText = (string) $doc->body;
            if ($doc->source_file_path && Storage::disk('local')->exists($doc->source_file_path)) {
                $fullPath = Storage::disk('local')->path($doc->source_file_path);
                $pdf = $parser->parseFile($fullPath);
                $extracted = $pdf->getText();
                $extracted = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $extracted) ?? $extracted;
                if (trim($extracted) !== '') {
                    $rawText = $extracted;
                }
            }

            $formattedBody = $formatter->format($rawText, (string) $doc->title);
            $excerpt = $processor->generateHeuristicExcerpt($formattedBody, (string) $doc->title);
            preg_match_all('/[\p{L}\p{N}]+/u', $formattedBody, $words);
            $estimatedMinutes = min(60, max(1, (int) ceil(count($words[0]) / 200)));

            $doc->update([
                'body' => $formattedBody,
                'excerpt' => $excerpt,
                'estimated_minutes' => $estimatedMinutes,
            ]);

            // 2. Perbarui soal kuis: hilangkan templat lama, hapus duplikasi, & rapikan bank soal sesuai target unik
            $existingQuiz = $doc->quizQuestions()->orderBy('sequence')->get();
            $targetQuizCount = max((int) ($doc->quiz_question_count ?: 10), 10);
            $hasDuplicateQuiz = $existingQuiz->pluck('question')->map(fn ($q) => mb_strtolower(trim((string) $q)))->unique()->count() !== $existingQuiz->count();
            $hasBloatedQuiz = $existingQuiz->count() > $targetQuizCount;
            $freshQuiz = $processor->generateQuizQuestions($formattedBody, (string) $doc->title, $doc->category, min(100, $targetQuizCount));

            $legacyPattern = '/Istilah atau konsep yang tepat untuk melengkapi|Kalimat lengkap dalam materi berbunyi|Berdasarkan isi bacaan, apa tujuan dari|Sesuai isi materi, kelanjutan penjelasan|logicodix|subtema:|@[a-z0-9_]+|benar-benar|ServerAdmin = Alamat|masukan perintah/iu';

            foreach ($freshQuiz as $idx => $fq) {
                $seq = $idx + 1;
                $existingRow = $existingQuiz->firstWhere('sequence', $seq);

                if ($existingRow) {
                    $isLegacy = preg_match($legacyPattern, (string) $existingRow->question)
                        || preg_match($legacyPattern, (string) $existingRow->explanation);
                    if ($this->option('force-quiz') || $isLegacy || $hasDuplicateQuiz || $hasBloatedQuiz) {
                        $existingRow->update([
                            'question' => $fq['question'],
                            'options' => $fq['options'],
                            'correct_answer' => $fq['correct_answer'],
                            'explanation' => $fq['explanation'],
                        ]);
                    }
                } else {
                    $doc->quizQuestions()->create([
                        'sequence' => $seq,
                        'question' => $fq['question'],
                        'options' => $fq['options'],
                        'correct_answer' => $fq['correct_answer'],
                        'explanation' => $fq['explanation'],
                    ]);
                }
            }

            if ($existingQuiz->count() > count($freshQuiz) && ($this->option('force-quiz') || $hasDuplicateQuiz || $hasBloatedQuiz)) {
                $doc->quizQuestions()->where('sequence', '>', count($freshQuiz))->delete();
            }

            // 2b. Perbarui soal Challenge Socrates agar sudut pertanyaannya berbeda dari Tantangan Guru & kunci jawabannya berparafrase rapi
            $socraticQuestions = $processor->generateHeuristicSocraticQuestions($formattedBody, (string) $doc->title, $doc->category);
            $existingSocratic = $doc->questions()->orderBy('sequence')->get();
            foreach ($socraticQuestions as $idx => $sq) {
                $seq = $idx + 1;
                $doc->questions()->updateOrCreate(
                    ['sequence' => $seq],
                    [
                        'skill' => $sq['skill'],
                        'prompt' => $sq['prompt'],
                        'context' => $sq['context'] ?? '',
                        'hint' => $sq['hint'] ?? '',
                    ]
                );
            }
            if ($existingSocratic->count() > count($socraticQuestions)) {
                $doc->questions()->where('sequence', '>', count($socraticQuestions))->delete();
            }

            // 3. Perbarui/lengkapi tantangan guru (regenerasi otomatis set rekomendasi lama atau lengkapi difficulty & reference_excerpt soal manual)
            $existingChallenges = $doc->teacherChallenges()->orderBy('sequence')->get();
            $isAutoGeneratedSet = $existingChallenges->isNotEmpty()
                && $existingChallenges->every(fn (TeacherChallenge $tc) => $tc->type === 'essay')
                && ($existingChallenges->contains(fn (TeacherChallenge $tc) => (bool) preg_match(
                    '/Fokuskan penjelasan pada definisi konseptual|Fokuskan jawaban pada definisi, fungsi dasar|Uraikan fungsi spesifik serta tujuan|Jelaskan alasan logis, fungsi spesifik|Identifikasi elemen, aturan hukum\/pasal|Jabarkan alasan teoretis|Hubungkan konsep utama dalam bacaan|Jelaskan langkah-langkah konkret|Gunakan nalar kritis|Menjelaskan pengertian/iu',
                    (string) ($tc->context . ' ' . $tc->rubric)
                ) || (bool) preg_match('/^Apa (?:yang dimaksud dengan .+, dan gagasan pokok apa yang dijelaskan di dalamnya\?|fungsi dan gagasan utama penggunaan|gagasan utama yang dijelaskan terkait)|sebagaimana dijelaskan pada awal materi\?$/iu', (string) $tc->question)));

            if ($existingChallenges->isEmpty() || $isAutoGeneratedSet || $this->option('force-challenges')) {
                $suggestions = $processor->generateTeacherChallengeSuggestions($formattedBody, (string) $doc->title, $doc->category);
                foreach ($suggestions as $idx => $s) {
                    $seq = $idx + 1;
                    $diff = $s['difficulty'] ?? 'medium';
                    $payload = [
                        'type' => 'essay',
                        'question' => $s['question'],
                        'context' => $s['context'] ?? null,
                        'options' => null,
                        'correct_answer' => null,
                        'rubric' => $s['rubric'] ?? '',
                        'reference_excerpt' => $s['reference_excerpt'] ?? null,
                        'difficulty' => $diff,
                        'difficulty_weight' => TeacherChallenge::weightForDifficulty($diff),
                    ];

                    $existing = $existingChallenges->firstWhere('sequence', $seq);
                    if ($existing && ($isAutoGeneratedSet || $this->option('force-challenges'))) {
                        $existing->update($payload);
                    } else {
                        $doc->teacherChallenges()->create(array_merge(['sequence' => $seq], $payload));
                    }
                }
            } else {
                $payload = $existingChallenges->map(fn (TeacherChallenge $tc) => [
                    'type' => $tc->type,
                    'question' => $tc->question,
                    'rubric' => $tc->rubric,
                    'options' => $tc->options,
                    'correct_answer' => $tc->correct_answer,
                    'force_analyze' => true,
                ])->all();
                $analyzed = $processor->analyzeTeacherChallenges($formattedBody, (string) $doc->title, $payload);
                foreach ($existingChallenges as $idx => $tc) {
                    $diff = $analyzed[$idx]['difficulty'] ?? 'medium';
                    $updateData = [
                        'difficulty' => $diff,
                        'difficulty_weight' => TeacherChallenge::weightForDifficulty($diff),
                    ];
                    if (empty(trim((string) $tc->reference_excerpt))) {
                        $resolvedRef = $challengeController->resolveModelAnswer($tc, $doc);
                        if (! empty($resolvedRef['reference_excerpt'])) {
                            $updateData['reference_excerpt'] = $resolvedRef['reference_excerpt'];
                        }
                    }
                    $tc->update($updateData);
                }
            }

            $this->info("  ✓ Selesai #{$doc->id} (panjang body: " . mb_strlen($formattedBody) . " karakter)");
        }

        return self::SUCCESS;
    }

    private function resetStudentLearningProgress(): void
    {
        $tables = [
            'student_answers',
            'reading_coach_messages',
            'learning_activities',
            'student_quiz_attempts',
            'student_teacher_challenge_submissions',
            'reading_sessions',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        $this->warn('Seluruh riwayat membaca, chat Socrates, kuis, tantangan guru, dan progress belajar siswa telah direset ke kondisi awal.');
    }
}
