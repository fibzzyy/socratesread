@extends('layouts.student')

@section('title', 'Tantangan Guru: ' . $document->title)

@section('breadcrumb_items')
    <span>/</span><a href="{{ route('quiz.index') }}" class="hover:text-blue-600">Tantangan Socrates</a><span>/</span><strong>Tantangan Guru</strong>
@endsection

@section('content')
    <section class="stage-heading" aria-labelledby="challenge-heading">
        <div>
            <p class="eyebrow">TANTANGAN DARI GURU PENGAMPU</p>
            <h1 id="challenge-heading">Tantangan: {{ $document->title }}</h1>
            @if ($totalQuestions > 0)
                <p class="welcome-subtitle">Tantangan khusus dari guru pengampu. Terdiri dari {{ $totalQuestions }} soal bernilai total 100 poin proporsional berdasarkan tingkat kesulitan (Maksimal +{{ $maxRewardXp ?? 35 }} XP).</p>
            @else
                <p class="welcome-subtitle">Informasi tantangan khusus dari guru pengampu untuk materi ini.</p>
            @endif
        </div>
        <div class="inline-flex items-center gap-2 rounded-2xl bg-white border border-slate-200/80 px-4 py-2 text-xs font-semibold text-slate-700 shadow-2xs">
            <x-icon name="book-open-text" class="w-4 h-4 text-blue-600" />
            <span>Kategori: {{ $document->category }}</span>
        </div>
    </section>

    @if (session('status'))
        <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50/90 p-4 text-xs font-bold text-emerald-900 shadow-2xs flex items-center gap-2.5">
            <x-icon name="sparkles" class="w-4 h-4 text-emerald-600 shrink-0" />
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($challenges->isEmpty() && ! ($submission && $submission->isCompleted()))
        <section class="panel p-8 sm:p-10 rounded-2xl border border-slate-200/80 bg-white text-center shadow-xs space-y-4">
            <div class="w-14 h-14 rounded-2xl bg-slate-100 text-slate-500 grid place-items-center mx-auto">
                <x-icon name="info" class="w-7 h-7" />
            </div>
            <div class="max-w-md mx-auto space-y-1.5">
                <h2 class="text-lg font-bold text-slate-900">Tidak Ada Tantangan dari Guru</h2>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Saat ini belum ada soal tantangan dari guru untuk materi <strong>{{ $document->title }}</strong>. Kamu tetap bisa membaca materi atau mengerjakan Kuis Timer Pilihan Ganda.
                </p>
            </div>
            <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
                <a href="{{ route('quiz.index') }}" class="outline-button">
                    <x-icon name="arrow-left" class="w-4 h-4" />
                    <span>Kembali ke Tantangan Socrates</span>
                </a>
                <a href="{{ route('reading', ['document' => $document->id]) }}" class="challenge-button">
                    <x-icon name="book-open-text" class="w-4 h-4" />
                    <span>Buka Ruang Baca</span>
                </a>
            </div>
        </section>
    @elseif ($submission && $submission->isCompleted())
        {{-- ============================================================== --}}
        {{-- TAMPILAN 1: HASIL PENGERJAAN TANTANGAN TERKUNCI & TERANALISIS  --}}
        {{-- ============================================================== --}}
        @php($answersBreakdown = $submission->answers ?? [])
        @php($calculatedTotal = 0)
        @foreach ($challenges as $item)
            @php($a = $answersBreakdown[$item->id] ?? null)
            @php($stText = trim((string)($a['student_answer'] ?? '')))
            @php($isWrong = ($item->type === 'essay' && (count(preg_split('/\s+/u', $stText, -1, PREG_SPLIT_NO_EMPTY) ?: []) < 4 || in_array(mb_strtolower($stText), ['how are you?', 'how are you', 'how are u', 'hei', 'hai', 'halo', 'tes', 'test', 'tidak tahu', 'fine mas fine']) || ($a['quality_percent'] ?? 0) < 40)))
            @php($calculatedTotal += $isWrong ? 0 : (float)($a['points_earned'] ?? 0))
        @endforeach
        @php($displayTotalScore = (int) round(min(100, $calculatedTotal)))

        <div class="mb-6 rounded-2xl border border-emerald-200 bg-gradient-to-r from-emerald-50 via-teal-50 to-emerald-50/80 p-5 text-emerald-950 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="w-12 h-12 rounded-2xl bg-emerald-600 text-white grid place-items-center shrink-0 shadow-md">
                    <x-icon name="award" class="w-6 h-6" />
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-emerald-900">Tantangan Guru Selesai</span>
                        <span class="rounded-full bg-emerald-600 text-white text-[10px] font-black px-2.5 py-0.5">Skor {{ $displayTotalScore }} / 100</span>
                        <span class="rounded-full bg-blue-600 text-white text-[10px] font-black px-2 py-0.5">+{{ $submission->xp_awarded }} XP</span>
                    </div>
                    <p class="text-xs text-emerald-800 mt-1">Dikumpulkan pada {{ $submission->completed_at ? $submission->completed_at->locale('id')->translatedFormat('d F Y, H:i') : 'sebelumnya' }}. Jawaban esai telah dianalisis berdasarkan bobot kecocokan.</p>
                </div>
            </div>
            <div class="flex items-center gap-2 self-start sm:self-center">
                <a href="{{ route('achievements') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white text-emerald-900 font-bold text-xs border border-emerald-200 shadow-2xs hover:bg-emerald-50 transition">
                    <x-icon name="trophy" class="w-4 h-4 text-amber-500" />
                    <span>Lihat Ranking Angkatan</span>
                </a>
            </div>
        </div>

        <div class="space-y-5 mb-8">
            @foreach ($challenges as $idx => $q)
                @php($ans = $answersBreakdown[$q->id] ?? null)
                @php($isEssay = $q->type === 'essay')
                @php($studentAnswerText = trim((string) ($ans['student_answer'] ?? '')))
                @php($words = preg_split('/\s+/u', $studentAnswerText, -1, PREG_SPLIT_NO_EMPTY) ?: [])
                @php($wordCount = count($words))
                @php($isBlankAnswer = $isEssay ? ($studentAnswerText === '' || $studentAnswerText === '(Tidak diisi)') : empty($ans['chosen']))
                @php($isGibberish = in_array(mb_strtolower($studentAnswerText), ['how are you?', 'how are you', 'how are u', 'hei', 'hai', 'halo', 'tes', 'test', 'tidak tahu', 'fine mas fine']))
                @php($isStrictlyWrong = ($isEssay && ! $isBlankAnswer && ($wordCount < 4 || $isGibberish || ($ans['quality_percent'] ?? 0) < 40)))

                @php($earned = ($isBlankAnswer || $isStrictlyWrong) ? 0.0 : (float) ($ans['points_earned'] ?? 0))
                @php($status = $isBlankAnswer ? 'kosong' : ($isStrictlyWrong ? 'salah' : ($ans['evaluation_status'] ?? ($earned > 0 ? (($ans['quality_percent'] ?? 0) >= 70 ? 'benar' : 'hampir_benar') : 'salah'))))
                @php($levelText = $isBlankAnswer ? 'Jawaban Kosong' : ($isStrictlyWrong ? 'Salah / Belum Tepat' : ($ans['understanding_level'] ?? ($status === 'salah' ? 'Salah / Belum Tepat' : 'Tepat & Sesuai'))))
                @php($qualityScore = ($isBlankAnswer || $isStrictlyWrong) ? 0 : (int) ($ans['quality_percent'] ?? 0))
                @php($feedbackText = $isBlankAnswer ? 'Anda belum mengisi jawaban pada soal ini (0 poin). Berikut kunci jawaban yang benar berdasarkan materi untuk dipelajari.' : ($isStrictlyWrong ? 'Jawaban belum tepat atau tidak sesuai dengan materi bacaan. Pelajari kunci jawaban yang benar di bawah ini.' : ($ans['feedback'] ?? 'Jawaban dianalisis berdasarkan kesesuaian argumen terhadap isi bacaan materi.')))

                @php($resolved = app(\App\Http\Controllers\Student\StudentTeacherChallengeController::class)->resolveModelAnswer($q, $document))
                @php($storedRef = trim((string) ($q->reference_excerpt ?? '')))
                @php($storedRubric = trim((string) ($q->rubric ?? '')))
                @php($isLegacyBadRubric = (bool) preg_match('/Menjelaskan pengertian|Berdasarkan bacaan:\s*Menjelaskan/i', $storedRubric))
                @php($isMetaStoredRubric = (bool) preg_match('/^(?:gunakan nalar|perhatikan|fokuslah|cari bagian|analisis|pikirkan|kembangkan|telaah|rancang)/i', $storedRubric))
                @php($storedRubricWords = preg_split('/\s+/u', $storedRubric, -1, PREG_SPLIT_NO_EMPTY) ?: [])
                @php($isRichStoredRubric = mb_strlen($storedRubric) >= 35 && count($storedRubricWords) >= 6 && ! $isMetaStoredRubric && ! $isLegacyBadRubric)

                @php($rawModel = $ans['model_answer'] ?? $storedRubric)
                @php($isLegacyBadModel = (bool) preg_match('/Menjelaskan pengertian|Berdasarkan bacaan:\s*Menjelaskan/i', (string) $rawModel))
                @php($isMetaRubric = (bool) preg_match('/^(?:gunakan nalar|perhatikan|fokuslah|cari bagian|analisis|pikirkan|kembangkan|telaah|rancang)/i', (string) $rawModel))
                @php($modelAns = ($isRichStoredRubric && $storedRef !== '') ? $resolved['model_answer'] : ((! empty($rawModel) && ! $isMetaRubric && ! $isLegacyBadModel && mb_strlen(trim((string) $rawModel)) >= 35) ? $rawModel : $resolved['model_answer']))

                @php($rawExcerpt = $ans['reference_excerpt'] ?? '')
                @php($isLegacyBadExcerpt = (bool) preg_match('/Menjelaskan pengertian|^(?:gunakan nalar|perhatikan)/i', (string) $rawExcerpt))
                @php($refExcerpt = $storedRef !== '' ? $storedRef : ((! empty($rawExcerpt) && ! $isLegacyBadExcerpt) ? $rawExcerpt : $resolved['reference_excerpt']))

                @php($qMaxPoints = $ans['points_max'] ?? ($pointsMap[$q->id] ?? $pointsPerQuestion))
                @php($qDiff = strtolower((string) ($q->difficulty ?? ($ans['difficulty'] ?? 'medium'))))
                @php($diffLabel = match ($qDiff) { 'easy' => 'Easy', 'hard' => 'Hard', default => 'Medium' })
                @php($diffCls = match ($qDiff) { 'easy' => 'bg-emerald-100 text-emerald-800 border-emerald-200', 'hard' => 'bg-rose-100 text-rose-800 border-rose-200', default => 'bg-amber-100 text-amber-800 border-amber-200' })

                <article class="panel p-5 sm:p-6 rounded-2xl border shadow-xs bg-white {{ ($status === 'salah' || $status === 'kosong') ? 'border-rose-200/90' : ($status === 'hampir_benar' ? 'border-amber-200/90' : 'border-emerald-200/90') }}">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="rounded-lg bg-slate-100 text-slate-700 font-bold text-xs px-2.5 py-1">Soal #{{ $idx + 1 }}</span>
                            <span class="rounded-md {{ $isEssay ? 'bg-purple-50 text-purple-700 border border-purple-200/60' : 'bg-blue-50 text-blue-700 border border-blue-200/60' }} text-[10px] font-bold uppercase px-2 py-0.5">
                                {{ $isEssay ? 'Esai Analisis' : 'Pilihan Ganda' }}
                            </span>
                            <span class="rounded-md border {{ $diffCls }} text-[10px] font-bold uppercase px-2 py-0.5">
                                {{ $diffLabel }}
                            </span>
                            @if ($status === 'kosong')
                                <span class="rounded-full bg-slate-100 border border-slate-300 text-slate-700 text-[10px] font-extrabold px-2.5 py-0.5 inline-flex items-center gap-1">
                                    <x-icon name="minus-circle" class="w-3 h-3 text-slate-500" />
                                    <span>Jawaban Kosong</span>
                                </span>
                            @elseif ($status === 'salah')
                                <span class="rounded-full bg-rose-50 border border-rose-200 text-rose-700 text-[10px] font-extrabold px-2.5 py-0.5 inline-flex items-center gap-1">
                                    <x-icon name="x-circle" class="w-3 h-3 text-rose-600" />
                                    <span>Jawaban Salah / Belum Tepat</span>
                                </span>
                            @elseif ($status === 'hampir_benar')
                                <span class="rounded-full bg-amber-50 border border-amber-200 text-amber-800 text-[10px] font-extrabold px-2.5 py-0.5 inline-flex items-center gap-1">
                                    <x-icon name="alert-triangle" class="w-3 h-3 text-amber-600" />
                                    <span>Jawaban Hampir Benar</span>
                                </span>
                            @else
                                <span class="rounded-full bg-emerald-50 border border-emerald-200 text-emerald-800 text-[10px] font-extrabold px-2.5 py-0.5 inline-flex items-center gap-1">
                                    <x-icon name="check-circle" class="w-3 h-3 text-emerald-600" />
                                    <span>Jawaban Tepat & Sesuai</span>
                                </span>
                            @endif
                        </div>
                        <div class="text-xs font-black {{ $earned > 0 ? ($status === 'hampir_benar' ? 'text-amber-600' : 'text-emerald-600') : 'text-rose-600' }}">
                            Nilai: {{ number_format($earned, 2) }} / {{ $qMaxPoints }} Poin
                        </div>
                    </div>

                    <h3 class="text-sm sm:text-base font-bold text-slate-900 {{ ! empty($q->context) ? 'mb-2' : 'mb-4' }}">{{ $q->question }}</h3>
                    @if (! empty($q->context))
                        <p class="text-xs text-slate-600 bg-slate-50 border border-slate-200/80 rounded-xl px-3.5 py-2 mb-4">
                            <strong class="text-slate-700">Konteks:</strong> {{ $q->context }}
                        </p>
                    @endif

                    @if (! $isEssay)
                        {{-- Review Pilihan Ganda --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-3">
                            @foreach ($q->options ?? [] as $optKey => $optVal)
                                @php($isChosen = ($ans['chosen'] ?? '') === $optKey)
                                @php($isCorrect = ($q->correct_answer ?? '') === $optKey)
                                <div class="p-3 rounded-xl border text-xs font-semibold flex items-center gap-2.5 {{ $isCorrect ? 'bg-emerald-50 border-emerald-300 text-emerald-900' : ($isChosen ? 'bg-rose-50 border-rose-300 text-rose-900' : 'bg-slate-50 border-slate-200 text-slate-700') }}">
                                    <span class="w-6 h-6 rounded-lg grid place-items-center font-bold {{ $isCorrect ? 'bg-emerald-600 text-white' : ($isChosen ? 'bg-rose-600 text-white' : 'bg-slate-200 text-slate-700') }}">
                                        {{ $optKey }}
                                    </span>
                                    <span class="flex-grow">{{ $optVal }}</span>
                                    @if ($isCorrect)
                                        <span class="text-[10px] font-black text-emerald-700 uppercase">Kunci Benar</span>
                                    @elseif ($isChosen)
                                        <span class="text-[10px] font-black text-rose-700 uppercase">Pilihan Anda</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        @php($pgCorrectKey = strtoupper((string) ($q->correct_answer ?? '')))
                        @php($pgCorrectText = $q->options[$pgCorrectKey] ?? $pgCorrectKey)
                        @php($pgChosenKey = strtoupper((string) ($ans['chosen'] ?? '')))
                        @php($pgIsCorrect = ($ans['is_correct'] ?? false))
                        <div class="p-4 rounded-xl border text-xs space-y-1.5 {{ $pgIsCorrect ? 'bg-emerald-50/70 border-emerald-200 text-emerald-950' : 'bg-rose-50/70 border-rose-200 text-rose-950' }}">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-bold text-blue-900">💡 Pembahasan Kunci Jawaban:</span>
                                <span class="text-[11px] font-extrabold px-2.5 py-0.5 rounded-full {{ $pgIsCorrect ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                    {{ $pgIsCorrect ? '✓ Jawaban Anda Benar' : ($pgChosenKey ? "✗ Anda Memilih {$pgChosenKey}" : '✗ Tidak Dijawab') }}
                                </span>
                            </div>
                            <p class="font-bold text-slate-900">Kunci Jawaban Benar: {{ $pgCorrectKey }} — {{ $pgCorrectText }}</p>
                            @if (! empty($ans['feedback']))
                                <p class="text-slate-700 leading-relaxed">{{ $ans['feedback'] }}</p>
                            @endif
                        </div>
                    @else
                        {{-- Review Soal Esai: Desain Bersih & Elegan Mirip Gemini NotebookLM --}}
                        <div class="space-y-4">
                            <!-- 1. Box Jawaban Anda (NotebookLM Style + Highlight Kata Kunci Benar) -->
                            @php($highlightService = app(\App\Services\Evaluation\AnswerHighlightService::class))
                            @php($savedSpans = is_array($ans['matched_spans'] ?? null) ? $ans['matched_spans'] : [])
                            @php($highlightSpans = (! in_array($status, ['salah', 'kosong'], true) && mb_strlen($studentAnswerText) >= 12) ? (! empty($savedSpans) ? $savedSpans : $highlightService->extractMatchedSpans($studentAnswerText, $modelAns ?: $refExcerpt, $document)) : [])
                            @php($highlightedAnswerHtml = $highlightService->formatHighlightedHtml($studentAnswerText ?: '(Tidak diisi)', $highlightSpans, ! in_array($status, ['salah', 'kosong'], true)))
                            <div class="p-4 sm:p-5 rounded-2xl bg-slate-50 border border-slate-200/90 text-xs">
                                <span class="text-xs font-bold text-slate-500 block mb-2">Jawaban Anda</span>
                                <p class="text-slate-700 text-xs sm:text-sm leading-relaxed whitespace-pre-line font-normal {{ $status === 'kosong' ? 'italic text-slate-400' : '' }}">{!! $highlightedAnswerHtml !!}</p>
                            </div>

                            <!-- 2. Box Ulasan / Evaluasi Analisis AI (NotebookLM Style: 1 Box Utuh & Rapi) -->
                            @php($boxTheme = $status === 'kosong' ? 'border-slate-300 bg-slate-50/80 text-slate-900' : ($status === 'salah' ? 'border-rose-400 bg-rose-50/50 text-rose-950' : ($status === 'hampir_benar' ? 'border-amber-400 bg-amber-50/50 text-amber-950' : 'border-emerald-400 bg-emerald-50/50 text-emerald-950')))
                            @php($iconTheme = $status === 'kosong' ? 'text-slate-600' : ($status === 'salah' ? 'text-rose-600' : ($status === 'hampir_benar' ? 'text-amber-600' : 'text-emerald-600')))

                            <div class="p-4 sm:p-5 rounded-2xl border {{ $boxTheme }} transition-all shadow-2xs">
                                <!-- Status Header murni persis NotebookLM Gemini + Persentase Ketepatan Jawaban -->
                                <div class="flex items-center justify-between gap-2 mb-2.5">
                                    <div class="flex items-center gap-1.5 text-xs sm:text-sm font-bold {{ $iconTheme }}">
                                        @if ($status === 'kosong')
                                            <x-icon name="minus-circle" class="w-4 h-4 stroke-[2.5]" />
                                            <span>Jawaban kosong (Belum diisi).</span>
                                        @elseif ($status === 'salah')
                                            <x-icon name="x" class="w-4 h-4 stroke-[3]" />
                                            <span>Belum tepat.</span>
                                        @elseif ($status === 'hampir_benar')
                                            <x-icon name="alert-triangle" class="w-4 h-4 stroke-[2.5]" />
                                            <span>Hampir benar.</span>
                                        @else
                                            <x-icon name="check" class="w-4 h-4 stroke-[3]" />
                                            <span>Benar.</span>
                                        @endif
                                    </div>
                                    <span class="text-xs font-black px-2.5 py-0.5 rounded-full {{ $status === 'kosong' ? 'bg-slate-200 text-slate-700' : ($status === 'salah' ? 'bg-rose-100 text-rose-800' : ($status === 'hampir_benar' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800')) }}">
                                        {{ $status === 'kosong' ? '0 Poin' : "Ketepatan {$qualityScore}%" }}
                                    </span>
                                </div>

                                @php($cleanFeedback = trim(preg_replace('/\s*Menambahkan keterkaitan bukti konkret akan membuatnya lebih kuat\.?/i', '', (string) $feedbackText)))
                                @if (empty($cleanFeedback) || ($status === 'benar' && str_contains($feedbackText, 'Menambahkan keterkaitan bukti')))
                                    @php($cleanFeedback = 'Jawaban Anda sudah sangat tepat. Anda berhasil memahami konsep yang ditanyakan dengan baik menggunakan pemikiran dan bahasa Anda sendiri.')
                                @endif

                                <!-- Narrative Feedback AI (Respons / Analisis atas jawaban siswa) -->
                                <p class="text-xs sm:text-sm leading-relaxed text-slate-800 font-normal">{{ $cleanFeedback }}</p>

                                @if ($status !== 'benar')
                                    {{-- Bagian perbaikan & kunci jawaban HANYA dimunculkan jika jawaban SALAH atau HAMPIR BENAR --}}
                                    @if (! $isStrictlyWrong && ! empty($ans['missing_concepts']) && is_array($ans['missing_concepts']))
                                        <div class="mt-3 pt-3 border-t border-slate-200/60 text-xs">
                                            <strong class="font-bold text-slate-800 block mb-1">Poin yang belum lengkap / perlu diperbaiki:</strong>
                                            <ul class="list-disc list-inside space-y-0.5 text-slate-700">
                                                @foreach ($ans['missing_concepts'] as $concept)
                                                    <li>{{ $concept }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif

                                    <!-- Kunci Jawaban yang Benar Berdasarkan Materi (Muncul untuk bahan belajar saat jawaban belum tepat) -->
                                    <div class="mt-3.5 pt-3 border-t border-slate-200/70 text-xs">
                                        <strong class="font-bold text-slate-800 block mb-1">Kunci Jawaban yang Benar Berdasarkan Materi:</strong>
                                        <p class="text-slate-700 leading-relaxed font-medium whitespace-pre-line">{{ $modelAns }}</p>
                                    </div>

                                    <!-- Rujukan Bukti Bacaan -->
                                    @if (! empty($refExcerpt))
                                        <div class="mt-2.5 pt-2 border-t border-slate-200/50 text-[11px] text-slate-500">
                                            <span class="font-semibold text-slate-700">Rujukan Teks:</span>
                                            <span class="italic">"{{ $refExcerpt }}"</span>
                                        </div>
                                    @endif
                                @else
                                    <details class="mt-3 pt-2.5 border-t border-emerald-200/60 text-xs">
                                        <summary class="cursor-pointer font-semibold text-emerald-800 hover:text-emerald-950 select-none">
                                            Lihat Kunci Jawaban & Rujukan Teks
                                        </summary>
                                        <div class="mt-2 space-y-1.5 text-slate-700">
                                            <p class="leading-relaxed">
                                                <strong class="font-bold text-slate-800">Jawaban Acuan Materi:</strong>
                                                <span class="whitespace-pre-line">{{ $modelAns }}</span>
                                            </p>
                                            @if (! empty($refExcerpt))
                                                <p class="text-[11px] text-slate-500">
                                                    <span class="font-semibold text-slate-700">Rujukan Teks:</span>
                                                    <span class="italic">"{{ $refExcerpt }}"</span>
                                                </p>
                                            @endif
                                        </div>
                                    </details>
                                @endif
                            </div>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('quiz.index') }}" class="outline-button">
                <x-icon name="arrow-left" class="w-4 h-4" />
                <span>Kembali ke Tantangan Socrates</span>
            </a>
            <div class="flex items-center gap-2">
                <a href="{{ route('progress') }}" class="outline-button !border-purple-200 !text-purple-700 bg-purple-50/50 hover:bg-purple-100/70">
                    <x-icon name="trending-up" class="w-4 h-4" />
                    <span>Lihat Progress</span>
                </a>
                <a href="{{ route('quiz.index') }}" class="challenge-button">
                    <span>Lanjut ke Tantangan Socrates</span>
                    <x-icon name="arrow-right" class="w-4 h-4" />
                </a>
            </div>
        </div>
    @else
        {{-- ============================================================== --}}
        {{-- TAMPILAN 2: FORM PENGERJAAN INTERAKTIF PER-SOAL (NOTEBOOKLM)    --}}
        {{-- ============================================================== --}}
        {{-- Bar Progres & Navigator Soal Gaya Gemini Notebook --}}
        <div class="mb-5 p-4 sm:p-5 rounded-2xl bg-white border border-slate-200/80 shadow-xs space-y-3" id="interactiveQuizHeader">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2.5">
                    <span id="currentStepBadge" class="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 text-white text-xs font-extrabold px-3 py-1.5">
                        <span id="currentStepNumber">{{ ($initialActiveIndex ?? 0) + 1 }}</span> / {{ $totalQuestions }}
                    </span>
                    <span class="text-xs font-semibold text-slate-600">
                        <strong id="checkedCounterText" class="text-slate-900">{{ $checkedCount ?? 0 }}</strong> dari {{ $totalQuestions }} soal telah diperiksa
                    </span>
                </div>
                <button type="button" id="btnToggleChallengeMode" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-[11px] font-bold text-slate-700 transition">
                    <x-icon name="layers" class="w-3.5 h-3.5 text-blue-600" />
                    <span id="toggleModeLabel">Tampilkan Semua Soal</span>
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-1.5" id="questionStepPills">
                @foreach ($challenges as $idx => $q)
                    @php($preAns = ($existingAnswers ?? [])[$q->id] ?? null)
                    @php($preStatus = is_array($preAns) ? ($preAns['evaluation_status'] ?? null) : null)
                    @php($pillCls = match ($preStatus) { 'benar' => 'bg-emerald-100 text-emerald-800 border-emerald-300', 'hampir_benar' => 'bg-amber-100 text-amber-800 border-amber-300', 'salah' => 'bg-rose-100 text-rose-800 border-rose-300', default => 'bg-slate-100 text-slate-700 border-slate-200 hover:bg-slate-200/70' })
                    <button type="button"
                            data-step-pill="{{ $idx }}"
                            data-pill-status="{{ $preStatus ?? 'pending' }}"
                            class="w-8 h-8 rounded-lg border text-xs font-bold transition grid place-items-center {{ $pillCls }} {{ $idx === ($initialActiveIndex ?? 0) ? 'ring-2 ring-blue-600 ring-offset-1' : '' }}">
                        {{ $idx + 1 }}
                    </button>
                @endforeach
            </div>
        </div>

        <form action="{{ route('student.teacher-challenges.submit', ['document' => $document->id]) }}" method="POST" id="teacherChallengeForm">
            @csrf
            <div class="space-y-5 mb-8" id="challengeCardsContainer">
                @foreach ($challenges as $idx => $q)
                    @php($qMaxPoints = $pointsMap[$q->id] ?? $pointsPerQuestion)
                    @php($qDiff = strtolower((string) ($q->difficulty ?? 'medium')))
                    @php($diffLabel = match ($qDiff) { 'easy' => 'Easy', 'hard' => 'Hard', default => 'Medium' })
                    @php($diffCls = match ($qDiff) { 'easy' => 'bg-emerald-100 text-emerald-800 border-emerald-200', 'hard' => 'bg-rose-100 text-rose-800 border-rose-200', default => 'bg-amber-100 text-amber-800 border-amber-200' })
                    @php($preEvaluated = isset(($existingAnswers ?? [])[$q->id]) && is_array(($existingAnswers ?? [])[$q->id]) ? ($existingAnswers ?? [])[$q->id] : null)
                    @php($preStatus = $preEvaluated['evaluation_status'] ?? null)
                    @php($cardBorder = match ($preStatus) { 'benar' => 'border-emerald-300', 'hampir_benar' => 'border-amber-300', 'salah' => 'border-rose-300', default => 'border-slate-200/80' })
                    <article data-challenge-card="{{ $idx }}"
                             data-challenge-id="{{ $q->id }}"
                             data-challenge-type="{{ $q->type }}"
                             data-checked="{{ $preEvaluated ? 'true' : 'false' }}"
                             class="panel p-5 sm:p-6 rounded-2xl border {{ $cardBorder }} shadow-xs bg-white transition-all {{ $idx === ($initialActiveIndex ?? 0) ? '' : 'hidden' }}">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="rounded-lg bg-blue-50 text-blue-700 font-bold text-xs px-2.5 py-1 border border-blue-200/60">
                                    Soal #{{ $idx + 1 }} dari {{ $totalQuestions }}
                                </span>
                                <span class="rounded-md {{ $q->type === 'essay' ? 'bg-purple-50 text-purple-700 border border-purple-200/60' : 'bg-slate-100 text-slate-700' }} text-[10px] font-bold uppercase px-2 py-0.5">
                                    {{ $q->type === 'essay' ? 'Esai Analisis' : 'Pilihan Ganda' }}
                                </span>
                                <span class="rounded-md border {{ $diffCls }} text-[10px] font-bold uppercase px-2 py-0.5">
                                    {{ $diffLabel }}
                                </span>
                            </div>
                            <span class="text-xs font-bold text-slate-400">
                                Maksimal <strong class="text-slate-800">{{ $qMaxPoints }}</strong> Poin
                            </span>
                        </div>

                        <h3 class="text-sm sm:text-base font-bold text-slate-900 {{ ! empty($q->context) ? 'mb-2' : 'mb-4' }}">{{ $q->question }}</h3>
                        @if (! empty($q->context))
                            <p class="text-xs text-slate-600 bg-slate-50 border border-slate-200/80 rounded-xl px-3.5 py-2 mb-4">
                                <strong class="text-slate-700">Konteks:</strong> {{ $q->context }}
                            </p>
                        @endif

                        @if ($q->type === 'multiple_choice')
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5" data-mcq-group="{{ $q->id }}">
                                @foreach ($q->options ?? [] as $optKey => $optVal)
                                    @php($isPreChosen = $preEvaluated && ($preEvaluated['chosen'] ?? '') === $optKey)
                                    <label class="relative flex items-center gap-3 p-3.5 rounded-xl border border-slate-200 hover:border-blue-400 hover:bg-blue-50/40 cursor-pointer transition select-none has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50/70 has-[:checked]:ring-2 has-[:checked]:ring-blue-500/20" data-mcq-label>
                                        <input type="radio"
                                               name="answers[{{ $q->id }}]"
                                               value="{{ $optKey }}"
                                               class="sr-only"
                                               data-mcq-radio
                                               @checked($isPreChosen)
                                               @disabled((bool) $preEvaluated)>
                                        <span class="w-7 h-7 rounded-lg bg-slate-100 text-slate-700 font-bold text-xs grid place-items-center shrink-0 border border-slate-200">
                                            {{ $optKey }}
                                        </span>
                                        <span class="text-xs font-semibold text-slate-800">{{ $optVal }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <div class="space-y-1.5" data-essay-input-wrap="{{ $q->id }}">
                                <label for="essay_{{ $q->id }}" class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">
                                    Tulis Jawaban Analisis Anda:
                                </label>
                                <textarea id="essay_{{ $q->id }}"
                                          name="answers[{{ $q->id }}]"
                                          rows="5"
                                          data-essay-input="{{ $idx + 1 }}"
                                          @disabled((bool) $preEvaluated)
                                          class="w-full rounded-xl border border-slate-200 p-3.5 text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition disabled:bg-slate-50 disabled:text-slate-600"
                                          placeholder="Jelaskan pendapat atau alasan Anda dengan kata-kata sendiri...">{{ $preEvaluated['student_answer'] ?? '' }}</textarea>
                                <p class="text-[11px] text-slate-400">Klik <strong>Periksa Jawaban</strong> untuk langsung melihat penilaian & ulasan AI, atau lanjutkan hingga akhir untuk mengumpulkan semuanya.</p>
                            </div>
                        @endif

                        <p data-check-error="{{ $q->id }}" class="hidden mt-3 text-xs font-semibold text-rose-600 bg-rose-50 border border-rose-200 rounded-xl px-3 py-2"></p>

                        <div class="mt-4 flex flex-wrap items-center justify-between gap-2 {{ $preEvaluated ? 'hidden' : '' }}" data-check-action-row="{{ $q->id }}">
                            <button type="button"
                                    data-check-btn="{{ $q->id }}"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold shadow-xs transition cursor-pointer">
                                <x-icon name="sparkles" class="w-3.5 h-3.5 text-amber-300" />
                                <span>Periksa Jawaban</span>
                            </button>
                            <span class="text-[11px] text-slate-400 font-medium">Hasil langsung muncul & terkunci per nomor</span>
                        </div>

                        {{-- Area Feedback Langsung Gaya Gemini NotebookLM --}}
                        <div data-instant-feedback="{{ $q->id }}" class="mt-5 space-y-3.5 {{ $preEvaluated ? '' : 'hidden' }}">
                            @if ($preEvaluated)
                                @php($pStatus = $preEvaluated['evaluation_status'] ?? 'salah')
                                @php($pTheme = $pStatus === 'salah' ? 'border-rose-400 bg-rose-50/50 text-rose-950' : ($pStatus === 'hampir_benar' ? 'border-amber-400 bg-amber-50/50 text-amber-950' : 'border-emerald-400 bg-emerald-50/50 text-emerald-950'))
                                @php($pIconTheme = $pStatus === 'salah' ? 'text-rose-600' : ($pStatus === 'hampir_benar' ? 'text-amber-600' : 'text-emerald-600'))
                                @php($pBadgeTheme = $pStatus === 'salah' ? 'bg-rose-100 text-rose-800' : ($pStatus === 'hampir_benar' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'))
                                @php($pLabel = $pStatus === 'benar' ? 'Benar.' : ($pStatus === 'hampir_benar' ? 'Hampir benar.' : 'Belum tepat.'))
                                @if ($q->type === 'essay')
                                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/90 text-xs">
                                        <span class="text-xs font-bold text-slate-500 block mb-1.5">Jawaban Anda</span>
                                        <p class="text-slate-700 text-xs sm:text-sm leading-relaxed whitespace-pre-line">{!! $preEvaluated['highlighted_answer_html'] ?? e($preEvaluated['student_answer'] ?? '') !!}</p>
                                    </div>
                                @endif
                                <div class="p-4 sm:p-5 rounded-2xl border {{ $pTheme }} shadow-2xs space-y-2.5">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-xs sm:text-sm font-bold {{ $pIconTheme }}">{{ $pLabel }}</span>
                                        <span class="text-xs font-black px-2.5 py-0.5 rounded-full {{ $pBadgeTheme }}">
                                            {{ number_format((float) ($preEvaluated['points_earned'] ?? 0), 2) }} / {{ $preEvaluated['points_max'] ?? $qMaxPoints }} Poin
                                        </span>
                                    </div>
                                    <p class="text-xs sm:text-sm leading-relaxed text-slate-800">{{ $preEvaluated['feedback'] ?? '' }}</p>
                                    @if ($q->type === 'essay' && ! empty($preEvaluated['model_answer']))
                                        @if ($pStatus !== 'benar')
                                            <div class="pt-2.5 border-t border-slate-200/70 text-xs space-y-1">
                                                <strong class="font-bold text-slate-800 block">Kunci Jawaban yang Benar Berdasarkan Materi:</strong>
                                                <p class="text-slate-700 leading-relaxed font-medium whitespace-pre-line">{{ $preEvaluated['model_answer'] }}</p>
                                            </div>
                                        @else
                                            <details class="pt-2 border-t border-emerald-200/60 text-xs">
                                                <summary class="cursor-pointer font-semibold text-emerald-800 select-none">Lihat Kunci Jawaban & Rujukan Teks</summary>
                                                <p class="mt-1.5 text-slate-700 leading-relaxed whitespace-pre-line">{{ $preEvaluated['model_answer'] }}</p>
                                            </details>
                                        @endif
                                    @elseif ($q->type === 'multiple_choice' && ! empty($preEvaluated['correct_answer']))
                                        <p class="text-xs font-bold text-slate-800 pt-1 border-t border-slate-200/60">
                                            Kunci Jawaban Benar: {{ $preEvaluated['correct_answer'] }} — {{ $preEvaluated['correct_text'] ?? '' }}
                                        </p>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 p-4 rounded-2xl bg-white border border-slate-200/80 shadow-xs">
                <div class="flex items-center gap-2">
                    <a href="{{ route('quiz.index') }}" class="outline-button">
                        <x-icon name="arrow-left" class="w-4 h-4" />
                        <span>Keluar</span>
                    </a>
                    <button type="button" id="btnPrevQuestion" class="outline-button">
                        <x-icon name="chevron-left" class="w-4 h-4" />
                        <span>Sebelumnya</span>
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" id="btnNextQuestion" class="outline-button !border-blue-200 !text-blue-700 bg-blue-50/70 hover:bg-blue-100">
                        <span>Berikutnya</span>
                        <x-icon name="chevron-right" class="w-4 h-4" />
                    </button>
                    <button type="button" id="btnOpenSubmitTeacherModal" class="challenge-button">
                        <span>Selesaikan & Kumpulkan</span>
                        <x-icon name="arrow-right" class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </form>

        <!-- Modal Pop-Up Konfirmasi Pengumpulan Jawaban Tantangan Guru -->
        <div id="teacherSubmitModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity" role="dialog" aria-modal="true">
            <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl border border-slate-100 text-center transform transition-all scale-100">
                <div class="w-16 h-16 rounded-2xl mx-auto mb-4 grid place-items-center bg-blue-100 text-blue-600">
                    <x-icon name="send" class="w-8 h-8" />
                </div>
                <h3 id="teacherModalTitle" class="text-xl font-bold text-slate-900 mb-2">Kumpulkan Jawaban Tantangan?</h3>
                <p id="teacherModalMessage" class="text-xs text-slate-600 leading-relaxed mb-6">
                    Seluruh jawaban esai dan pilihan ganda Anda akan dikumpulkan dan dinilai secara objektif oleh AI Socrates. Pastikan semua pertanyaan telah dijawab dengan seksama.
                </p>
                <div class="flex items-center justify-center gap-3">
                    <button type="button" id="btnCancelTeacherSubmit" class="outline-button w-1/2 justify-center py-2.5 text-xs font-semibold">
                        Periksa Kembali
                    </button>
                    <button type="button" id="btnConfirmTeacherSubmit" class="challenge-button !bg-blue-600 hover:!bg-blue-700 !border-blue-600 w-1/2 justify-center py-2.5 text-xs font-bold">
                        Kumpulkan Sekarang
                    </button>
                </div>
            </div>
        </div>

        <script>
            (function () {
                const checkUrl = @json(route('student.teacher-challenges.check-question', ['document' => $document->id]));
                const csrfToken = document.querySelector('#teacherChallengeForm input[name="_token"]')?.value || '';
                const totalQuestions = {{ $totalQuestions }};
                let activeIndex = {{ $initialActiveIndex ?? 0 }};
                let showAllMode = false;

                const cards = Array.from(document.querySelectorAll('[data-challenge-card]'));
                const stepPills = Array.from(document.querySelectorAll('[data-step-pill]'));
                const currentStepNumber = document.getElementById('currentStepNumber');
                const checkedCounterText = document.getElementById('checkedCounterText');
                const btnPrev = document.getElementById('btnPrevQuestion');
                const btnNext = document.getElementById('btnNextQuestion');
                const btnToggleMode = document.getElementById('btnToggleChallengeMode');
                const toggleModeLabel = document.getElementById('toggleModeLabel');

                function syncView() {
                    cards.forEach((card, idx) => {
                        const isVisible = showAllMode || idx === activeIndex;
                        card.classList.toggle('hidden', !isVisible);
                    });

                    stepPills.forEach((pill, idx) => {
                        const isActive = !showAllMode && idx === activeIndex;
                        pill.classList.toggle('ring-2', isActive);
                        pill.classList.toggle('ring-blue-600', isActive);
                        pill.classList.toggle('ring-offset-1', isActive);
                    });

                    if (currentStepNumber) {
                        currentStepNumber.textContent = String(activeIndex + 1);
                    }
                    if (btnPrev) {
                        btnPrev.disabled = showAllMode || activeIndex <= 0;
                        btnPrev.classList.toggle('opacity-50', btnPrev.disabled);
                    }
                    if (btnNext) {
                        btnNext.disabled = showAllMode || activeIndex >= totalQuestions - 1;
                        btnNext.classList.toggle('opacity-50', btnNext.disabled);
                    }
                }

                stepPills.forEach((pill) => {
                    pill.addEventListener('click', () => {
                        showAllMode = false;
                        if (toggleModeLabel) toggleModeLabel.textContent = 'Tampilkan Semua Soal';
                        activeIndex = Number(pill.dataset.stepPill || 0);
                        syncView();
                    });
                });

                if (btnPrev) {
                    btnPrev.addEventListener('click', () => {
                        if (activeIndex > 0) {
                            activeIndex--;
                            syncView();
                        }
                    });
                }

                if (btnNext) {
                    btnNext.addEventListener('click', () => {
                        if (activeIndex < totalQuestions - 1) {
                            activeIndex++;
                            syncView();
                        }
                    });
                }

                if (btnToggleMode) {
                    btnToggleMode.addEventListener('click', () => {
                        showAllMode = !showAllMode;
                        if (toggleModeLabel) {
                            toggleModeLabel.textContent = showAllMode ? 'Mode Per-Soal (Fokus)' : 'Tampilkan Semua Soal';
                        }
                        syncView();
                    });
                }

                function updateStepPillVisual(cardIdx, status) {
                    const pill = stepPills[cardIdx];
                    if (!pill) return;
                    pill.dataset.pillStatus = status;
                    pill.classList.remove(
                        'bg-slate-100', 'text-slate-700', 'border-slate-200',
                        'bg-emerald-100', 'text-emerald-800', 'border-emerald-300',
                        'bg-amber-100', 'text-amber-800', 'border-amber-300',
                        'bg-rose-100', 'text-rose-800', 'border-rose-300'
                    );
                    if (status === 'benar') {
                        pill.classList.add('bg-emerald-100', 'text-emerald-800', 'border-emerald-300');
                    } else if (status === 'hampir_benar') {
                        pill.classList.add('bg-amber-100', 'text-amber-800', 'border-amber-300');
                    } else {
                        pill.classList.add('bg-rose-100', 'text-rose-800', 'border-rose-300');
                    }
                }

                function renderInstantEvaluation(card, data, challengeId) {
                    const status = data.evaluation_status || 'salah';
                    const isEssay = card.dataset.challengeType === 'essay';
                    const container = card.querySelector(`[data-instant-feedback="${challengeId}"]`);
                    if (!container) return;

                    container.textContent = '';

                    card.classList.remove('border-slate-200/80', 'border-emerald-300', 'border-amber-300', 'border-rose-300');
                    card.classList.add(
                        status === 'benar' ? 'border-emerald-300' : (status === 'hampir_benar' ? 'border-amber-300' : 'border-rose-300')
                    );

                    if (isEssay) {
                        const ansBox = document.createElement('div');
                        ansBox.className = 'p-4 rounded-2xl bg-slate-50 border border-slate-200/90 text-xs';
                        const ansLabel = document.createElement('span');
                        ansLabel.className = 'text-xs font-bold text-slate-500 block mb-1.5';
                        ansLabel.textContent = 'Jawaban Anda';
                        const ansBody = document.createElement('p');
                        ansBody.className = 'text-slate-700 text-xs sm:text-sm leading-relaxed whitespace-pre-line';
                        if (data.highlighted_answer_html) {
                            ansBody.innerHTML = data.highlighted_answer_html;
                        } else {
                            ansBody.textContent = data.student_answer || '';
                        }
                        ansBox.append(ansLabel, ansBody);
                        container.appendChild(ansBox);
                    }

                    const evalBox = document.createElement('div');
                    const themeCls = status === 'benar'
                        ? 'border-emerald-400 bg-emerald-50/50 text-emerald-950'
                        : (status === 'hampir_benar' ? 'border-amber-400 bg-amber-50/50 text-amber-950' : 'border-rose-400 bg-rose-50/50 text-rose-950');
                    evalBox.className = `p-4 sm:p-5 rounded-2xl border ${themeCls} shadow-2xs space-y-2.5`;

                    const topRow = document.createElement('div');
                    topRow.className = 'flex items-center justify-between gap-2';

                    const statusTitle = document.createElement('span');
                    statusTitle.className = 'text-xs sm:text-sm font-bold ' + (status === 'benar' ? 'text-emerald-600' : (status === 'hampir_benar' ? 'text-amber-600' : 'text-rose-600'));
                    statusTitle.textContent = status === 'benar' ? '✓ Benar.' : (status === 'hampir_benar' ? '! Hampir benar.' : '✕ Belum tepat.');

                    const ptsBadge = document.createElement('span');
                    ptsBadge.className = 'text-xs font-black px-2.5 py-0.5 rounded-full ' + (status === 'benar' ? 'bg-emerald-100 text-emerald-800' : (status === 'hampir_benar' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800'));
                    const earnedNum = Number(data.points_earned || 0).toFixed(2);
                    ptsBadge.textContent = `${earnedNum} / ${data.points_max} Poin` + (isEssay && typeof data.quality_percent !== 'undefined' ? ` (${data.quality_percent}%)` : '');

                    topRow.append(statusTitle, ptsBadge);
                    evalBox.appendChild(topRow);

                    const fbPara = document.createElement('p');
                    fbPara.className = 'text-xs sm:text-sm leading-relaxed text-slate-800';
                    fbPara.textContent = data.feedback || '';
                    evalBox.appendChild(fbPara);

                    if (isEssay && Array.isArray(data.missing_concepts) && data.missing_concepts.length > 0 && status !== 'benar') {
                        const missWrap = document.createElement('div');
                        missWrap.className = 'pt-2 border-t border-slate-200/60 text-xs';
                        const missHead = document.createElement('strong');
                        missHead.className = 'font-bold text-slate-800 block mb-1';
                        missHead.textContent = 'Poin yang dapat dilengkapi:';
                        const missList = document.createElement('ul');
                        missList.className = 'list-disc list-inside space-y-0.5 text-slate-700';
                        data.missing_concepts.forEach((m) => {
                            const li = document.createElement('li');
                            li.textContent = String(m);
                            missList.appendChild(li);
                        });
                        missWrap.append(missHead, missList);
                        evalBox.appendChild(missWrap);
                    }

                    if (isEssay && data.model_answer) {
                        if (status !== 'benar') {
                            const keyWrap = document.createElement('div');
                            keyWrap.className = 'pt-2.5 border-t border-slate-200/70 text-xs space-y-1';
                            const keyHead = document.createElement('strong');
                            keyHead.className = 'font-bold text-slate-800 block';
                            keyHead.textContent = 'Kunci Jawaban yang Benar Berdasarkan Materi:';
                            const keyBody = document.createElement('p');
                            keyBody.className = 'text-slate-700 leading-relaxed font-medium whitespace-pre-line';
                            keyBody.textContent = data.model_answer;
                            keyWrap.append(keyHead, keyBody);
                            if (data.reference_excerpt) {
                                const refPara = document.createElement('p');
                                refPara.className = 'text-[11px] text-slate-500 pt-1';
                                refPara.textContent = `Rujukan Teks: "${data.reference_excerpt}"`;
                                keyWrap.appendChild(refPara);
                            }
                            evalBox.appendChild(keyWrap);
                        } else {
                            const det = document.createElement('details');
                            det.className = 'pt-2 border-t border-emerald-200/60 text-xs';
                            const sum = document.createElement('summary');
                            sum.className = 'cursor-pointer font-semibold text-emerald-800 select-none';
                            sum.textContent = 'Lihat Kunci Jawaban & Rujukan Teks';
                            const keyBody = document.createElement('p');
                            keyBody.className = 'mt-1.5 text-slate-700 leading-relaxed whitespace-pre-line';
                            keyBody.textContent = data.model_answer;
                            det.append(sum, keyBody);
                            evalBox.appendChild(det);
                        }
                    } else if (!isEssay && data.correct_answer) {
                        const pgKey = document.createElement('p');
                        pgKey.className = 'text-xs font-bold text-slate-800 pt-2 border-t border-slate-200/60';
                        pgKey.textContent = `Kunci Jawaban Benar: ${data.correct_answer} — ${data.correct_text || ''}`;
                        evalBox.appendChild(pgKey);
                    }

                    container.appendChild(evalBox);
                    container.classList.remove('hidden');
                }

                document.querySelectorAll('[data-check-btn]').forEach((btn) => {
                    btn.addEventListener('click', async () => {
                        const challengeId = btn.dataset.checkBtn;
                        const card = btn.closest('[data-challenge-card]');
                        if (!card || card.dataset.checked === 'true') return;

                        const errEl = card.querySelector(`[data-check-error="${challengeId}"]`);
                        const actionRow = card.querySelector(`[data-check-action-row="${challengeId}"]`);
                        const isEssay = card.dataset.challengeType === 'essay';
                        let answerValue = '';

                        if (isEssay) {
                            const textarea = card.querySelector('textarea');
                            answerValue = textarea ? textarea.value.trim() : '';
                        } else {
                            const checkedRadio = card.querySelector('input[type="radio"]:checked');
                            answerValue = checkedRadio ? checkedRadio.value : '';
                        }

                        if (errEl) {
                            errEl.classList.add('hidden');
                            errEl.textContent = '';
                        }

                        const origHtml = btn.innerHTML;
                        btn.disabled = true;
                        btn.textContent = '⏳ Menilai jawaban...';

                        try {
                            const res = await fetch(checkUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                },
                                body: JSON.stringify({
                                    challenge_id: Number(challengeId),
                                    answer: answerValue,
                                }),
                            });

                            const payload = await res.json();
                            if (!res.ok) {
                                if (errEl) {
                                    errEl.textContent = payload.message || 'Gagal memeriksa jawaban.';
                                    errEl.classList.remove('hidden');
                                }
                                btn.disabled = false;
                                btn.innerHTML = origHtml;
                                return;
                            }

                            card.dataset.checked = 'true';
                            card.querySelectorAll('textarea, input[type="radio"]').forEach((el) => {
                                el.disabled = true;
                            });
                            if (actionRow) actionRow.classList.add('hidden');

                            if (payload.evaluated) {
                                renderInstantEvaluation(card, payload.evaluated, challengeId);
                                updateStepPillVisual(Number(card.dataset.challengeCard || 0), payload.evaluated.evaluation_status || 'salah');
                            }
                            if (checkedCounterText && typeof payload.checked_count !== 'undefined') {
                                checkedCounterText.textContent = String(payload.checked_count);
                            }
                        } catch (_) {
                            if (errEl) {
                                errEl.textContent = 'Koneksi terganggu saat memeriksa jawaban. Silakan coba lagi.';
                                errEl.classList.remove('hidden');
                            }
                            btn.disabled = false;
                            btn.innerHTML = origHtml;
                        }
                    });
                });

                syncView();

                // Modal Konfirmasi Pengumpulan + Proteksi Double-Submit
                const teacherSubmitModal = document.getElementById('teacherSubmitModal');
                const teacherModalTitle = document.getElementById('teacherModalTitle');
                const teacherModalMessage = document.getElementById('teacherModalMessage');
                const btnOpenSubmitTeacherModal = document.getElementById('btnOpenSubmitTeacherModal');
                const btnCancelTeacherSubmit = document.getElementById('btnCancelTeacherSubmit');
                const btnConfirmTeacherSubmit = document.getElementById('btnConfirmTeacherSubmit');
                const teacherChallengeForm = document.getElementById('teacherChallengeForm');
                let isSubmittingTeacherForm = false;

                if (btnOpenSubmitTeacherModal && teacherSubmitModal) {
                    btnOpenSubmitTeacherModal.addEventListener('click', () => {
                        if (isSubmittingTeacherForm) return;
                        const unanswered = [];

                        cards.forEach((card, idx) => {
                            if (card.dataset.checked === 'true') return;
                            if (card.dataset.challengeType === 'essay') {
                                const el = card.querySelector('[data-essay-input]');
                                if (!el || !el.value || el.value.trim().length < 5) {
                                    unanswered.push(idx + 1);
                                }
                            } else {
                                const checked = card.querySelector('input[type="radio"]:checked');
                                if (!checked) {
                                    unanswered.push(idx + 1);
                                }
                            }
                        });

                        if (unanswered.length > 0 && teacherModalTitle && teacherModalMessage) {
                            teacherModalTitle.textContent = `Ada ${unanswered.length} Soal Belum Dijawab!`;
                            teacherModalMessage.innerHTML = `Soal nomor <strong class="text-amber-700 font-bold">${unanswered.join(', ')}</strong> masih kosong atau terlalu singkat.<br><br>Apakah Anda tetap yakin ingin mengumpulkan sekarang?`;
                        } else if (teacherModalTitle && teacherModalMessage) {
                            teacherModalTitle.textContent = 'Kumpulkan Jawaban Tantangan?';
                            teacherModalMessage.textContent = 'Seluruh jawaban esai dan pilihan ganda Anda akan dikumpulkan dan diselesaikan untuk menghitung skor akhir dan XP.';
                        }

                        teacherSubmitModal.classList.remove('hidden');
                    });
                }

                if (btnCancelTeacherSubmit && teacherSubmitModal) {
                    btnCancelTeacherSubmit.addEventListener('click', () => {
                        if (isSubmittingTeacherForm) return;
                        teacherSubmitModal.classList.add('hidden');
                    });
                }

                if (btnConfirmTeacherSubmit && teacherChallengeForm) {
                    btnConfirmTeacherSubmit.addEventListener('click', () => {
                        if (isSubmittingTeacherForm) return;
                        isSubmittingTeacherForm = true;
                        btnConfirmTeacherSubmit.disabled = true;
                        btnConfirmTeacherSubmit.textContent = '⏳ Menyelesaikan tantangan...';
                        if (btnCancelTeacherSubmit) btnCancelTeacherSubmit.disabled = true;
                        if (btnOpenSubmitTeacherModal) btnOpenSubmitTeacherModal.disabled = true;
                        teacherChallengeForm.submit();
                    });

                    teacherChallengeForm.addEventListener('submit', () => {
                        if (isSubmittingTeacherForm) return;
                        isSubmittingTeacherForm = true;
                    });
                }

                // Klik 1x untuk memilih jawaban, klik lagi pada opsi yang sama untuk membatalkan pilihan
                document.querySelectorAll('[data-mcq-group]').forEach(group => {
                    const radios = group.querySelectorAll('[data-mcq-radio]');
                    radios.forEach(radio => {
                        radio.addEventListener('click', function () {
                            if (this.disabled) return;
                            const isAlreadyChecked = this.getAttribute('data-selected') === 'true';
                            if (isAlreadyChecked) {
                                this.checked = false;
                                this.removeAttribute('data-selected');
                                this.dispatchEvent(new Event('change', { bubbles: true }));
                            } else {
                                radios.forEach(r => r.removeAttribute('data-selected'));
                                this.setAttribute('data-selected', 'true');
                            }
                        });
                    });
                });
            })();
        </script>
    @endif
@endsection
