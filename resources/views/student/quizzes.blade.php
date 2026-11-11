@extends('layouts.student')

@section('title', 'Challenge Socrates - Evaluasi Bacaan')
@section('breadcrumb', 'Challenge Socrates')

@section('content')
    <section class="stage-heading" aria-labelledby="quizzes-heading">
        <div>
            <p class="eyebrow">EVALUASI PEMAHAMAN BACAAN · CHALLENGE SOCRATES</p>
            <h1 id="quizzes-heading">Challenge Socrates</h1>
            <p class="welcome-subtitle">Pilih materi bacaan yang ingin Anda kerjakan tantangannya. Setiap tantangan menguji kecepatan & ketepatan analisis dengan batas waktu tertentu.</p>
        </div>
        <div class="inline-flex items-center gap-2 rounded-2xl bg-white border border-slate-200/80 px-4 py-2.5 text-xs font-bold text-slate-700 shadow-2xs">
            <x-icon name="gamepad-2" class="w-4 h-4 text-indigo-600" />
            <span>Challenge Terjadwal & Terpandu AI</span>
        </div>
    </section>

    <!-- Stat Metrics Row -->
    <section class="grid grid-cols-2 lg:grid-cols-4 gap-3.5 sm:gap-4 mb-7" aria-label="Statistik Kuis">
        <article class="panel p-4 rounded-2xl border border-slate-200/80 shadow-2xs flex items-center gap-3.5 bg-white">
            <div class="w-11 h-11 rounded-xl bg-indigo-50 text-indigo-600 grid place-items-center shrink-0 border border-indigo-100">
                <x-icon name="gamepad-2" class="w-5 h-5" />
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Kuis</p>
                <h3 class="text-xl font-extrabold text-slate-800">{{ $totalQuizzes }} <span class="text-xs font-normal text-slate-400">Materi</span></h3>
            </div>
        </article>

        <article class="panel p-4 rounded-2xl border border-slate-200/80 shadow-2xs flex items-center gap-3.5 bg-white">
            <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-600 grid place-items-center shrink-0 border border-emerald-100">
                <x-icon name="check-circle" class="w-5 h-5" />
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Kuis Selesai</p>
                <h3 class="text-xl font-extrabold text-emerald-700">{{ $completedCount }} <span class="text-xs font-normal text-slate-400">/ {{ $totalQuizzes }}</span></h3>
            </div>
        </article>

        <article class="panel p-4 rounded-2xl border border-slate-200/80 shadow-2xs flex items-center gap-3.5 bg-white">
            <div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-600 grid place-items-center shrink-0 border border-amber-100">
                <x-icon name="award" class="w-5 h-5" />
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Rata-rata Skor</p>
                <h3 class="text-xl font-extrabold text-amber-900">{{ $averageScore }} <span class="text-xs font-normal text-slate-400">/ 100</span></h3>
            </div>
        </article>

        <article class="panel p-4 rounded-2xl border border-slate-200/80 shadow-2xs flex items-center gap-3.5 bg-white">
            <div class="w-11 h-11 rounded-xl bg-blue-50 text-blue-600 grid place-items-center shrink-0 border border-blue-100">
                <x-icon name="zap" class="w-5 h-5" />
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total XP Kuis</p>
                <h3 class="text-xl font-extrabold text-blue-700">+{{ $totalXpEarned }} <span class="text-xs font-normal text-slate-400">XP</span></h3>
            </div>
        </article>
    </section>

    <!-- Filter Bar (Status & Category) -->
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 bg-white p-3.5 px-4 rounded-2xl border border-slate-200/80 shadow-2xs">
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('quiz.index', ['status' => 'all', 'category' => $selectedCategory]) }}"
               class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition border {{ $selectedStatus === 'all' ? 'bg-indigo-600 text-white border-indigo-600 shadow-2xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100' }}">
                Semua ({{ $totalQuizzes }})
            </a>
            <a href="{{ route('quiz.index', ['status' => 'not_started', 'category' => $selectedCategory]) }}"
               class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition border {{ $selectedStatus === 'not_started' ? 'bg-slate-800 text-white border-slate-800 shadow-2xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100' }}">
                Belum Dikerjakan ({{ $notStartedCount }})
            </a>
            @if ($inProgressCount > 0)
                <a href="{{ route('quiz.index', ['status' => 'in_progress', 'category' => $selectedCategory]) }}"
                   class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition border {{ $selectedStatus === 'in_progress' ? 'bg-amber-600 text-white border-amber-600 shadow-2xs' : 'bg-amber-50 text-amber-800 border-amber-200 hover:bg-amber-100' }}">
                    Sedang Berjalan ({{ $inProgressCount }})
                </a>
            @endif
            <a href="{{ route('quiz.index', ['status' => 'completed', 'category' => $selectedCategory]) }}"
               class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition border {{ $selectedStatus === 'completed' ? 'bg-emerald-600 text-white border-emerald-600 shadow-2xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100' }}">
                Selesai ({{ $completedCount }})
            </a>
        </div>

        @if ($categories->isNotEmpty())
            <div class="flex items-center gap-2">
                <span class="text-xs text-slate-400 font-bold">Kategori:</span>
                <select onchange="window.location.href = this.value" class="text-xs font-semibold py-1.5 px-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-700 cursor-pointer focus:ring-indigo-500">
                    <option value="{{ route('quiz.index', ['status' => $selectedStatus, 'category' => 'all']) }}" @selected($selectedCategory === 'all')>Semua Kategori</option>
                    @foreach ($categories as $cat)
                        <option value="{{ route('quiz.index', ['status' => $selectedStatus, 'category' => $cat]) }}" @selected($selectedCategory === $cat)>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>

    <!-- Quiz Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @forelse ($quizItems as $item)
            @php
                $doc = $item['document'];
                $isCompleted = $item['status'] === 'completed';
                $isInProgress = $item['status'] === 'in_progress';
            @endphp
            <article class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs hover:shadow-md transition flex flex-col overflow-hidden group">
                <!-- Card Header -->
                <div class="p-5 pb-4 border-b border-slate-100 flex flex-col">
                    <div>
                        <div class="flex items-start justify-between gap-2 mb-2.5">
                            <div class="flex flex-col items-start gap-1.5 min-w-0">
                                <span class="rounded-lg bg-indigo-50 text-indigo-700 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider border border-indigo-100">
                                    {{ $doc->category ?: 'Umum' }}
                                </span>
                                <span class="rounded-lg bg-slate-100 text-slate-600 px-2 py-0.5 text-[10px] font-semibold">
                                    {{ $doc->targetGradeLabel() }}
                                </span>
                            </div>

                            @if ($isCompleted)
                                <span class="shrink-0 whitespace-nowrap inline-flex items-center gap-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 px-2.5 py-0.5 text-[10px] font-bold">
                                    <x-icon name="check-circle" class="w-3 h-3 text-emerald-600" />
                                    <span>Selesai</span>
                                </span>
                            @elseif ($isInProgress)
                                <span class="shrink-0 whitespace-nowrap inline-flex items-center gap-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200 px-2.5 py-0.5 text-[10px] font-bold animate-pulse">
                                    <x-icon name="clock" class="w-3 h-3 text-amber-600" />
                                    <span>Sedang Berjalan</span>
                                </span>
                            @else
                                <span class="shrink-0 whitespace-nowrap inline-flex items-center gap-1 rounded-full bg-slate-100 text-slate-600 px-2.5 py-0.5 text-[10px] font-semibold">
                                    Belum Dikerjakan
                                </span>
                            @endif
                        </div>

                        <h3 class="text-sm font-extrabold text-slate-900 group-hover:text-indigo-600 transition line-clamp-2 leading-snug mb-1.5">
                            {{ $doc->title }}
                        </h3>

                        <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1 text-[11px] text-slate-500 mb-3">
                            <span class="inline-flex items-center gap-1">
                                <x-icon name="user" class="w-3 h-3 text-slate-400" />
                                <span>{{ $doc->uploader?->name ?? 'Guru Pengampu' }}</span>
                            </span>
                            <span class="text-slate-300">•</span>
                            <span class="inline-flex items-center gap-1">
                                <x-icon name="book-open" class="w-3 h-3 text-slate-400" />
                                <span>{{ $doc->estimated_minutes ?: 5 }}m baca</span>
                            </span>
                        </div>
                    </div>

                    <!-- Quiz Meta Chips -->
                    <div class="grid grid-cols-3 gap-1.5 pt-3 border-t border-slate-100/80 text-center">
                        <div class="p-2 rounded-xl bg-slate-50 border border-slate-100">
                            <span class="block text-[10px] font-bold uppercase text-slate-400">Durasi</span>
                            <span class="text-xs font-bold text-slate-700 flex items-center justify-center gap-1 mt-0.5">
                                <span>⏱️</span> {{ $item['duration_minutes'] }}m
                            </span>
                        </div>
                        <div class="p-2 rounded-xl bg-slate-50 border border-slate-100">
                            <span class="block text-[10px] font-bold uppercase text-slate-400">Soal</span>
                            <span class="text-xs font-bold text-slate-700 flex items-center justify-center gap-1 mt-0.5">
                                <span>📝</span> {{ $item['question_count'] }} Soal
                            </span>
                        </div>
                        <div class="p-2 rounded-xl bg-blue-50/70 border border-blue-100">
                            <span class="block text-[10px] font-bold uppercase text-blue-500">Reward</span>
                            <span class="text-xs font-bold text-blue-700 flex items-center justify-center gap-1 mt-0.5">
                                <span>⚡</span> +150 XP
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Status / Result Box -->
                @if ($isCompleted)
                    <div class="px-5 py-3 bg-emerald-50/70 border-b border-emerald-100 flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 rounded-lg bg-emerald-600 text-white grid place-items-center font-extrabold text-xs">
                                ✓
                            </span>
                            <div>
                                <span class="font-extrabold text-emerald-950 block">Skor: {{ $item['score'] }} / 100</span>
                                <span class="text-[10px] text-emerald-700 font-medium">Benar {{ $item['correct_count'] }} dari {{ $item['question_count'] }} soal</span>
                            </div>
                        </div>
                        <span class="rounded bg-blue-600 text-white font-extrabold px-2 py-0.5 text-[10px]">
                            +{{ $item['xp_awarded'] }} XP
                        </span>
                    </div>
                @elseif ($isInProgress)
                    <div class="px-5 py-3 bg-amber-50/60 border-b border-amber-100 flex items-center gap-2.5 text-xs">
                        <span class="w-7 h-7 rounded-lg bg-amber-100 text-amber-700 grid place-items-center shrink-0">
                            <x-icon name="clock" class="w-3.5 h-3.5" />
                        </span>
                        <div class="min-w-0">
                            <span class="font-bold text-amber-950 block">Sesi kuis sedang aktif</span>
                            <span class="text-[10px] text-amber-700 font-medium block truncate">Lanjutkan pengerjaan untuk mengunci nilai</span>
                        </div>
                    </div>
                @else
                    <div class="px-5 py-3 bg-indigo-50/40 border-b border-slate-100 flex items-center gap-2.5 text-xs">
                        <span class="w-7 h-7 rounded-lg bg-indigo-100 text-indigo-600 grid place-items-center shrink-0">
                            <x-icon name="sparkles" class="w-3.5 h-3.5" />
                        </span>
                        <div class="min-w-0">
                            <span class="font-bold text-slate-800 block">Siap dikerjakan</span>
                            <span class="text-[10px] text-slate-500 font-medium block truncate">Raih skor terbaik & kumpulkan hingga +150 XP</span>
                        </div>
                    </div>
                @endif

                <!-- Action Footer -->
                <div class="p-4 bg-slate-50/70 flex-1 flex flex-col gap-3">
                    <!-- 1. Challenge Socrates Section -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-700">1. Challenge Socrates (Pilihan Ganda)</span>
                            <span class="text-[10px] text-slate-400 font-semibold">⏱️ {{ $item['duration_minutes'] }}m</span>
                        </div>
                        @if ($isCompleted)
                            <a href="{{ route('quiz.timed', ['document' => $doc->id]) }}"
                               class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs transition">
                                <x-icon name="lock" class="w-3.5 h-3.5" />
                                <span>Review Challenge Socrates (Skor: {{ $item['score'] }}/100)</span>
                            </a>
                        @elseif ($isInProgress)
                            <a href="{{ route('quiz.timed', ['document' => $doc->id]) }}"
                               class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs shadow-xs transition animate-pulse">
                                <x-icon name="play" class="w-3.5 h-3.5" />
                                <span>Lanjutkan Kuis (Timer Berjalan)</span>
                            </a>
                        @else
                            <button type="button"
                                    onclick="openStartQuizModal('{{ $doc->id }}', '{{ addslashes($doc->title) }}', '{{ $item['question_count'] }}', '{{ $item['duration_minutes'] }}', '{{ route('quiz.timed', ['document' => $doc->id]) }}', '{{ route('quiz.practice', ['document' => $doc->id]) }}')"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-xs transition">
                                <x-icon name="play" class="w-3.5 h-3.5" />
                                <span>Mulai Kuis Timer ({{ $item['question_count'] }} Soal) ⏱️</span>
                            </button>
                        @endif
                    </div>

                    <!-- 2. Tantangan Guru Section -->
                    <div class="space-y-1.5 pt-2.5 border-t border-slate-200/80">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-purple-700">2. Tantangan Guru (Esai & PG)</span>
                            @if ($item['teacher_is_completed'] || $item['teacher_challenges_count'] > 0)
                                <span class="text-[10px] text-purple-600 font-semibold">Total 100 Poin</span>
                            @endif
                        </div>
                        @if ($item['teacher_is_completed'])
                            <a href="{{ route('student.teacher-challenges.show', ['document' => $doc->id]) }}"
                               class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-purple-700 hover:bg-purple-800 text-white font-bold text-xs shadow-xs transition">
                                <x-icon name="award" class="w-3.5 h-3.5 text-purple-200" />
                                <span>Review Tantangan Guru (Skor: {{ $item['teacher_score'] }}/100)</span>
                            </a>
                        @elseif ($item['teacher_challenges_count'] > 0)
                            <a href="{{ route('student.teacher-challenges.show', ['document' => $doc->id]) }}"
                               class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-purple-50 hover:bg-purple-100 text-purple-900 border border-purple-300 font-bold text-xs shadow-2xs transition">
                                <x-icon name="award" class="w-3.5 h-3.5 text-purple-600" />
                                <span>Buka Tantangan Guru ({{ $item['teacher_challenges_count'] }} Soal)</span>
                            </a>
                        @else
                            <div class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-slate-50 text-slate-500 border border-slate-200 font-semibold text-xs">
                                <x-icon name="info" class="w-3.5 h-3.5 text-slate-400" />
                                <span>Tidak ada tantangan dari guru</span>
                            </div>
                        @endif
                    </div>

                    <div class="mt-auto flex items-center justify-between text-[11px] pt-2 px-1 border-t border-slate-100">
                        <a href="{{ route('reading', ['document' => $doc->id]) }}" class="text-slate-500 hover:text-blue-600 font-semibold inline-flex items-center gap-1 transition">
                            <x-icon name="book-open" class="w-3 h-3" />
                            <span>Baca Teks Materi</span>
                        </a>
                        @if (! $isCompleted)
                            <a href="{{ route('quiz.practice', ['document' => $doc->id]) }}" class="text-slate-500 hover:text-indigo-600 font-semibold inline-flex items-center gap-1 transition" title="Latihan mandiri tanpa timer">
                                <span>Mode Latihan</span>
                            </a>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="col-span-full py-16 text-center rounded-2xl bg-white border border-dashed border-slate-300 p-8">
                <div class="w-16 h-16 rounded-2xl bg-indigo-50 text-indigo-500 grid place-items-center mx-auto mb-3">
                    <x-icon name="gamepad-2" class="w-8 h-8" />
                </div>
                <h3 class="text-sm font-bold text-slate-800 mb-1">Belum Ada Kuis Tersedia</h3>
                <p class="text-xs text-slate-500 max-w-md mx-auto mb-4">
                    Belum ada kuis yang sesuai dengan filter atau diterbitkan oleh guru untuk bacaan ini. Silakan buka Reading Room untuk membaca materi.
                </p>
                <a href="{{ route('reading') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-blue-600 text-white text-xs font-bold hover:bg-blue-700 transition">
                    <x-icon name="book-open-text" class="w-4 h-4" />
                    <span>Buka Reading Room</span>
                </a>
            </div>
        @endforelse
    </div>

    <!-- Modal Konfirmasi Mulai Kuis (Mencegah Salah Klik / Kuis Terbuka Tanpa Sengaja) -->
    <div id="startQuizModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="startQuizTitle">
        <div id="startQuizBackdrop" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs transition-opacity"></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl border border-slate-200 overflow-hidden z-10 animate-scale-in p-6">
            <div class="w-12 h-12 rounded-2xl bg-indigo-100 text-indigo-700 grid place-items-center mb-4">
                <x-icon name="clock" class="w-6 h-6" />
            </div>

            <h3 id="startQuizTitle" class="text-base font-bold text-slate-900 mb-1">Konfirmasi Mulai Kuis</h3>
            <p class="text-xs text-slate-600 leading-relaxed mb-4" id="startQuizDocTitle">
                Apakah Anda siap memulai pengerjaan kuis?
            </p>

            <div class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-950 text-xs mb-5 space-y-1.5">
                <div class="flex items-center gap-2 font-bold text-amber-900">
                    <span>⚠️ Perhatian Sebelum Memulai:</span>
                </div>
                <p class="text-[11px] text-amber-800 leading-normal">
                    • Kuis memiliki <strong id="modalQCount">20 Soal</strong> dengan batas waktu <strong id="modalDuration">20 Menit</strong>.<br>
                    • Timer akan langsung berjalan saat kuis dimulai.<br>
                    • Jawaban tidak dapat diulang setelah dikumpulkan.
                </p>
            </div>

            <div class="flex items-center gap-2.5">
                <button type="button" id="btnCancelStart" class="flex-1 py-2.5 px-4 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 transition">
                    Batal
                </button>
                <a id="btnProceedStart" href="#" class="flex-1 py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold text-center transition shadow-xs">
                    Mulai Sekarang ⏱️
                </a>
            </div>
        </div>
    </div>

    <script>
        const startQuizModal = document.getElementById('startQuizModal');
        const startQuizBackdrop = document.getElementById('startQuizBackdrop');
        const btnCancelStart = document.getElementById('btnCancelStart');
        const btnProceedStart = document.getElementById('btnProceedStart');
        const startQuizDocTitle = document.getElementById('startQuizDocTitle');
        const modalQCount = document.getElementById('modalQCount');
        const modalDuration = document.getElementById('modalDuration');

        function openStartQuizModal(docId, title, qCount, duration, timedUrl, practiceUrl) {
            startQuizDocTitle.textContent = `Materi: "${title}"`;
            modalQCount.textContent = `${qCount} Soal Pilihan Ganda`;
            modalDuration.textContent = `${duration} Menit`;
            btnProceedStart.href = timedUrl;

            startQuizModal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function closeStartQuizModal() {
            startQuizModal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        startQuizBackdrop?.addEventListener('click', closeStartQuizModal);
        btnCancelStart?.addEventListener('click', closeStartQuizModal);
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !startQuizModal.classList.contains('hidden')) {
                closeStartQuizModal();
            }
        });
    </script>
@endsection
