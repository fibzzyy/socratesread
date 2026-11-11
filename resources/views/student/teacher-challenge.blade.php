@extends('layouts.student')

@section('title', 'Tantangan Guru: ' . $document->title)

@section('breadcrumb_items')
    <span>/</span><a href="{{ route('quiz.index') }}" class="hover:text-blue-600">Challenge Socrates</a><span>/</span><strong>Tantangan Guru</strong>
@endsection

@section('content')
    <section class="stage-heading" aria-labelledby="challenge-heading">
        <div>
            <p class="eyebrow">TANTANGAN DARI GURU PENGAMPU</p>
            <h1 id="challenge-heading">Tantangan: {{ $document->title }}</h1>
            @if ($totalQuestions > 0)
                <p class="welcome-subtitle">Tantangan khusus dari guru pengampu. Terdiri dari {{ $totalQuestions }} soal bernilai total 100 poin (masing-masing maks {{ $pointsPerQuestion }} poin).</p>
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
                    <span>Kembali ke Challenge Socrates</span>
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
                    <div class="flex items-center gap-2">
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
                @php($isGibberish = in_array(mb_strtolower($studentAnswerText), ['how are you?', 'how are you', 'how are u', 'hei', 'hai', 'halo', 'tes', 'test', 'tidak tahu', 'fine mas fine']))
                @php($isStrictlyWrong = ($isEssay && ($wordCount < 4 || $isGibberish || ($ans['quality_percent'] ?? 0) < 40)))

                @php($earned = $isStrictlyWrong ? 0.0 : (float) ($ans['points_earned'] ?? 0))
                @php($status = $isStrictlyWrong ? 'salah' : ($ans['evaluation_status'] ?? ($earned > 0 ? (($ans['quality_percent'] ?? 0) >= 70 ? 'benar' : 'hampir_benar') : 'salah')))
                @php($levelText = $isStrictlyWrong ? 'Salah / Belum Tepat' : ($ans['understanding_level'] ?? ($status === 'salah' ? 'Salah / Belum Tepat' : 'Tepat & Sesuai')))
                @php($qualityScore = $isStrictlyWrong ? 0 : (int) ($ans['quality_percent'] ?? 0))
                @php($feedbackText = $isStrictlyWrong ? 'Jawaban belum tepat atau tidak sesuai dengan materi bacaan. Pelajari kunci jawaban yang benar di bawah ini.' : ($ans['feedback'] ?? 'Jawaban dianalisis berdasarkan kesesuaian argumen terhadap isi bacaan materi.'))

                @php($resolved = app(\App\Http\Controllers\Student\StudentTeacherChallengeController::class)->resolveModelAnswer($q, $document))
                @php($rawModel = $ans['model_answer'] ?? ($q->rubric ?: ''))
                @php($isMetaRubric = (bool) preg_match('/^(?:gunakan nalar|perhatikan|fokuslah|cari bagian|analisis|pikirkan|kembangkan|telaah|rancang)/i', $rawModel))
                @php($modelAns = (! empty($rawModel) && ! $isMetaRubric) ? $rawModel : $resolved['model_answer'])
                @php($rawExcerpt = $ans['reference_excerpt'] ?? '')
                @php($refExcerpt = (! empty($rawExcerpt) && ! preg_match('/^(?:gunakan nalar|perhatikan)/i', $rawExcerpt)) ? $rawExcerpt : $resolved['reference_excerpt'])

                <article class="panel p-5 sm:p-6 rounded-2xl border shadow-xs bg-white {{ $status === 'salah' ? 'border-rose-200/90' : ($status === 'hampir_benar' ? 'border-amber-200/90' : 'border-emerald-200/90') }}">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <div class="flex items-center gap-2">
                            <span class="rounded-lg bg-slate-100 text-slate-700 font-bold text-xs px-2.5 py-1">Soal #{{ $idx + 1 }}</span>
                            <span class="rounded-md {{ $isEssay ? 'bg-purple-50 text-purple-700 border border-purple-200/60' : 'bg-blue-50 text-blue-700 border border-blue-200/60' }} text-[10px] font-bold uppercase px-2 py-0.5">
                                {{ $isEssay ? 'Esai Analisis' : 'Pilihan Ganda' }}
                            </span>
                            @if ($status === 'salah')
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
                            Nilai: {{ number_format($earned, 2) }} / {{ $pointsPerQuestion }} Poin
                        </div>
                    </div>

                    <h3 class="text-sm sm:text-base font-bold text-slate-900 mb-4">{{ $q->question }}</h3>

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

                        @if (! empty($ans['feedback']))
                            <div class="p-3 rounded-xl {{ ($ans['is_correct'] ?? false) ? 'bg-emerald-50/70 border border-emerald-200 text-emerald-900' : 'bg-rose-50/70 border border-rose-200 text-rose-900' }} text-xs">
                                <strong>{{ ($ans['is_correct'] ?? false) ? '✅ Evaluasi:' : '❌ Evaluasi:' }}</strong>
                                <span>{{ $ans['feedback'] }}</span>
                            </div>
                        @endif
                    @else
                        {{-- Review Soal Esai: Desain Bersih & Elegan Mirip Gemini NotebookLM --}}
                        <div class="space-y-4">
                            <!-- 1. Box Jawaban Anda (NotebookLM Style) -->
                            <div class="p-4 sm:p-5 rounded-2xl bg-slate-50 border border-slate-200/90 text-xs">
                                <span class="text-xs font-bold text-slate-500 block mb-2">Jawaban Anda</span>
                                <p class="text-slate-800 text-xs sm:text-sm leading-relaxed whitespace-pre-line font-medium">{{ $ans['student_answer'] ?? '(Tidak diisi)' }}</p>
                            </div>

                            <!-- 2. Box Ulasan / Evaluasi Analisis AI (NotebookLM Style: 1 Box Utuh & Rapi) -->
                            @php($boxTheme = $status === 'salah' ? 'border-rose-400 bg-rose-50/50 text-rose-950' : ($status === 'hampir_benar' ? 'border-amber-400 bg-amber-50/50 text-amber-950' : 'border-emerald-400 bg-emerald-50/50 text-emerald-950'))
                            @php($iconTheme = $status === 'salah' ? 'text-rose-600' : ($status === 'hampir_benar' ? 'text-amber-600' : 'text-emerald-600'))

                            <div class="p-4 sm:p-5 rounded-2xl border {{ $boxTheme }} transition-all shadow-2xs">
                                <!-- Status Header murni persis NotebookLM Gemini + Persentase Ketepatan Jawaban -->
                                <div class="flex items-center justify-between gap-2 mb-2.5">
                                    <div class="flex items-center gap-1.5 text-xs sm:text-sm font-bold {{ $iconTheme }}">
                                        @if ($status === 'salah')
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
                                    <span class="text-xs font-black px-2.5 py-0.5 rounded-full {{ $status === 'salah' ? 'bg-rose-100 text-rose-800' : ($status === 'hampir_benar' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800') }}">
                                        Ketepatan {{ $qualityScore }}%
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
                <span>Kembali ke Challenge Socrates</span>
            </a>
            <div class="flex items-center gap-2">
                <a href="{{ route('progress') }}" class="outline-button !border-purple-200 !text-purple-700 bg-purple-50/50 hover:bg-purple-100/70">
                    <x-icon name="trending-up" class="w-4 h-4" />
                    <span>Lihat Progress</span>
                </a>
                <a href="{{ route('quiz.index') }}" class="challenge-button">
                    <span>Lanjut ke Challenge Socrates</span>
                    <x-icon name="arrow-right" class="w-4 h-4" />
                </a>
            </div>
        </div>
    @else
        {{-- ============================================================== --}}
        {{-- TAMPILAN 2: FORM PENGERJAAN INTERAKTIF TANTANGAN GURU          --}}
        {{-- ============================================================== --}}
        <form action="{{ route('student.teacher-challenges.submit', ['document' => $document->id]) }}" method="POST" id="teacherChallengeForm">
            @csrf
            <div class="space-y-5 mb-8">
                @foreach ($challenges as $idx => $q)
                    <article class="panel p-5 sm:p-6 rounded-2xl border border-slate-200/80 shadow-xs bg-white">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                            <div class="flex items-center gap-2">
                                <span class="rounded-lg bg-blue-50 text-blue-700 font-bold text-xs px-2.5 py-1 border border-blue-200/60">
                                    Soal #{{ $idx + 1 }} dari {{ $totalQuestions }}
                                </span>
                                <span class="rounded-md {{ $q->type === 'essay' ? 'bg-purple-50 text-purple-700 border border-purple-200/60' : 'bg-slate-100 text-slate-700' }} text-[10px] font-bold uppercase px-2 py-0.5">
                                    {{ $q->type === 'essay' ? 'Esai Analisis' : 'Pilihan Ganda' }}
                                </span>
                            </div>
                            <span class="text-xs font-bold text-slate-400">
                                Maksimal <strong class="text-slate-800">{{ $pointsPerQuestion }}</strong> Poin
                            </span>
                        </div>

                        <h3 class="text-sm sm:text-base font-bold text-slate-900 mb-4">{{ $q->question }}</h3>

                        @if ($q->type === 'multiple_choice')
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5" data-mcq-group="{{ $q->id }}">
                                @foreach ($q->options ?? [] as $optKey => $optVal)
                                    <label class="relative flex items-center gap-3 p-3.5 rounded-xl border border-slate-200 hover:border-blue-400 hover:bg-blue-50/40 cursor-pointer transition select-none has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50/70 has-[:checked]:ring-2 has-[:checked]:ring-blue-500/20" data-mcq-label>
                                        <input type="radio" name="answers[{{ $q->id }}]" value="{{ $optKey }}" class="sr-only" data-mcq-radio>
                                        <span class="w-7 h-7 rounded-lg bg-slate-100 text-slate-700 font-bold text-xs grid place-items-center shrink-0 border border-slate-200">
                                            {{ $optKey }}
                                        </span>
                                        <span class="text-xs font-semibold text-slate-800">{{ $optVal }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <div class="space-y-1.5">
                                <label for="essay_{{ $q->id }}" class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">
                                    Tulis Jawaban Analisis Anda:
                                </label>
                                <textarea id="essay_{{ $q->id }}"
                                          name="answers[{{ $q->id }}]"
                                          rows="5"
                                          class="w-full rounded-xl border border-slate-200 p-3.5 text-xs text-slate-800 placeholder-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition"
                                          placeholder="Jelaskan alasan, temuan bukti dari bacaan, dan kesimpulan Anda..."
                                          required></textarea>
                                <p class="text-[11px] text-slate-400">Jawaban akan dianalisis secara cerdas oleh AI Socrates berdasarkan kesesuaian konsep materi dan bukti bacaan.</p>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>

            <div class="flex items-center justify-between p-4 rounded-2xl bg-white border border-slate-200/80 shadow-xs">
                <a href="{{ route('quiz.index') }}" class="outline-button">
                    <x-icon name="arrow-left" class="w-4 h-4" />
                    <span>Kembali ke Challenge Socrates</span>
                </a>
                <button type="button" id="btnOpenSubmitTeacherModal" class="challenge-button">
                    <span>Kirim Jawaban Tantangan</span>
                    <x-icon name="arrow-right" class="w-4 h-4" />
                </button>
            </div>
        </form>

        <!-- Modal Pop-Up Konfirmasi Pengumpulan Jawaban Tantangan Guru -->
        <div id="teacherSubmitModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity" role="dialog" aria-modal="true">
            <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl border border-slate-100 text-center transform transition-all scale-100">
                <div class="w-16 h-16 rounded-2xl mx-auto mb-4 grid place-items-center bg-blue-100 text-blue-600">
                    <x-icon name="send" class="w-8 h-8" />
                </div>
                <h3 class="text-xl font-bold text-slate-900 mb-2">Kumpulkan Jawaban Tantangan?</h3>
                <p class="text-xs text-slate-600 leading-relaxed mb-6">
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
            // Modal Konfirmasi Pengumpulan
            const teacherSubmitModal = document.getElementById('teacherSubmitModal');
            const btnOpenSubmitTeacherModal = document.getElementById('btnOpenSubmitTeacherModal');
            const btnCancelTeacherSubmit = document.getElementById('btnCancelTeacherSubmit');
            const btnConfirmTeacherSubmit = document.getElementById('btnConfirmTeacherSubmit');
            const teacherChallengeForm = document.getElementById('teacherChallengeForm');

            if (btnOpenSubmitTeacherModal && teacherSubmitModal) {
                btnOpenSubmitTeacherModal.addEventListener('click', () => {
                    teacherSubmitModal.classList.remove('hidden');
                });
            }

            if (btnCancelTeacherSubmit && teacherSubmitModal) {
                btnCancelTeacherSubmit.addEventListener('click', () => {
                    teacherSubmitModal.classList.add('hidden');
                });
            }

            if (btnConfirmTeacherSubmit && teacherChallengeForm) {
                btnConfirmTeacherSubmit.addEventListener('click', () => {
                    teacherSubmitModal.classList.add('hidden');
                    teacherChallengeForm.submit();
                });
            }

            // Klik 1x untuk memilih jawaban, klik lagi pada opsi yang sama untuk membatalkan pilihan
            document.querySelectorAll('[data-mcq-group]').forEach(group => {
                const radios = group.querySelectorAll('[data-mcq-radio]');
                radios.forEach(radio => {
                    radio.addEventListener('click', function(e) {
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
        </script>
    @endif
@endsection
