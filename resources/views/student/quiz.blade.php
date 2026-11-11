@extends('layouts.student')

@php
    $isTimed = $mode === 'timed_quiz';
    $durationMinutes = (int) ($quizDurationMinutes ?? ($document->quiz_duration_minutes ?: 15));
    $durationSeconds = (int) ($quizDurationSeconds ?? ($durationMinutes * 60));
    $displayCount = $questionsPayload->count();
    $modeTitle = $isTimed ? 'Kuis Timer Pilihan Ganda' : 'Latihan Pilihan Ganda';
    $modeSubtitle = $isTimed
        ? "Kuis {$displayCount} Soal berbasis bacaan dengan batas waktu total {$durationMinutes} menit. Uji ketepatan & kecepatan analisismu."
        : "Kuis {$displayCount} Soal evaluasi pemahaman bacaan tanpa batas waktu. Baca teks dengan teliti dan temukan jawaban terbaik.";
@endphp

@section('title', $modeTitle)
@section('breadcrumb_items')
    <span>/</span><a href="{{ route('quiz.index') }}" class="hover:text-blue-600">Challenge Socrates</a><span>/</span><strong>{{ $modeTitle }}</strong>
@endsection

@section('content')
    <style>
        /* Focus / Fullscreen Mode saat kuis aktif (menghilangkan sidebar aplikasi) */
        .app-shell.quiz-fullscreen-mode .sidebar,
        .app-shell.quiz-fullscreen-mode .scrim,
        .app-shell.quiz-fullscreen-mode .topbar .menu-toggle {
            display: none !important;
        }
        .app-shell.quiz-fullscreen-mode .main-content {
            margin-left: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            padding: 20px 32px !important;
        }

        /* Styling Animasi & Indikator Opsi Jawaban Terpilih */
        .zep-card {
            position: relative;
            transition: all 0.18s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .zep-card.is-selected {
            transform: scale(1.03) !important;
            box-shadow: 0 0 0 4px #ffffff, 0 0 0 7px #3b82f6, 0 10px 20px -3px rgba(0, 0, 0, 0.4) !important;
            filter: brightness(1.08) !important;
        }
        .zep-selected-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 11px;
            font-weight: 800;
            background: rgba(255, 255, 255, 0.95);
            color: #1e293b;
            padding: 2px 8px;
            border-radius: 999px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            animation: popIn 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
            margin-left: auto;
            flex-shrink: 0;
        }
        @keyframes popIn {
            0% { transform: scale(0.6); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        .quiz-timer-overall {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 12px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1d4ed8;
            font-weight: 800;
            font-family: monospace;
            font-size: 14px;
        }
        .quiz-timer-overall.is-urgent {
            background: #fef2f2 !important;
            border-color: #fecaca !important;
            color: #dc2626 !important;
            animation: pulse 1s infinite;
        }
    </style>

    <section class="stage-heading" aria-labelledby="quiz-heading-title">
        <div>
            <p class="eyebrow">{{ $isTimed ? "EVALUASI DENGAN WAKTU · TOTAL {$durationMinutes} MENIT" : 'LATIHAN MANDIRI · PILIHAN GANDA' }}</p>
            <h1 id="quiz-heading-title">{{ $modeTitle }}</h1>
            <p class="welcome-subtitle">{{ $modeSubtitle }}</p>
        </div>
        <div class="flex items-center gap-2.5">
            <a class="back-link" href="{{ route('quiz.index') }}" title="Kembali ke Challenge Socrates">
                <x-icon name="arrow-left" /> <span>Kembali</span>
            </a>
            @if ($isLocked)
                <a class="back-link !bg-purple-50 !text-purple-700 !border-purple-200 hover:!bg-purple-100" href="{{ route('progress') }}" title="Lihat Progress Belajar">
                    <x-icon name="trending-up" /> <span>Lihat Progress</span>
                </a>
            @endif
        </div>
    </section>

    @if ($isLocked)
        <div class="mb-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-xl border border-emerald-200 bg-emerald-50/90 p-4 text-emerald-900 shadow-xs" role="alert">
            <div class="flex items-start sm:items-center gap-3.5">
                <div class="grid w-10 h-10 rounded-xl bg-emerald-200/70 text-emerald-800 place-items-center shrink-0">
                    <x-icon name="lock" class="w-5 h-5" />
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-900">Hasil Pengerjaan Terkunci & Tersimpan</h2>
                        <span class="rounded bg-emerald-600 px-2 py-0.5 text-[10px] font-bold text-white">Skor {{ $attempt->score }}/100</span>
                        <span class="rounded bg-blue-600 px-2 py-0.5 text-[10px] font-bold text-white">+{{ $attempt->xp_awarded ?? 0 }} XP</span>
                    </div>
                    <p class="text-xs text-emerald-700 mt-1">Diselesaikan pada {{ $attempt->completed_at ? $attempt->completed_at->locale('id')->translatedFormat('d F Y, H:i') : 'sebelumnya' }} · Menjawab benar <strong>{{ $attempt->correct_count }}</strong> dari <strong>{{ $attempt->total_questions }}</strong> soal.</p>
                </div>
            </div>
            <div class="flex items-center gap-2 self-start sm:self-center">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-white text-emerald-800 border border-emerald-200 shadow-2xs">
                    <x-icon name="shield-check" class="w-4 h-4 text-emerald-600" /> Mode Review Kunci Jawaban
                </span>
            </div>
        </div>
    @endif

    <div class="quiz-room-grid" data-quiz data-attempt-id="{{ $attempt->id }}" data-mode="{{ $mode }}" data-is-locked="{{ $isLocked ? '1' : '0' }}" data-total-seconds="{{ $durationSeconds }}" data-submit-url="{{ route('quiz.submit') }}">
        <!-- Left Column: Active Question Area -->
        <div class="quiz-main-column">
            <!-- Retro Pixel Character Stage -->
            <div class="pixel-stage-banner" data-pixel-stage>
                <div class="pixel-character-box">
                    <div class="pixel-sprite-wrapper is-idle" data-sprite-wrapper>
                        <!-- Socrates SVG (Filsuf Bijak Klasik) -->
                        <div data-char-avatar="socrates">
                            <svg class="char-art" viewBox="0 0 100 100" width="86" height="86" xmlns="http://www.w3.org/2000/svg">
                                <defs>
                                    <radialGradient id="soc-halo" cx="50%" cy="50%" r="50%">
                                        <stop offset="0%" stop-color="#fbbf24" stop-opacity="0.35"/>
                                        <stop offset="100%" stop-color="#fbbf24" stop-opacity="0"/>
                                    </radialGradient>
                                </defs>
                                <circle cx="50" cy="50" r="46" fill="url(#soc-halo)"/>
                                <path d="M26 95 C26 72 32 60 40 56 C46 53 54 53 60 56 C68 60 74 72 74 95 Z" fill="#f8fafc" stroke="#cbd5e1" stroke-width="2"/>
                                <path d="M36 58 C44 68 56 80 66 95 L54 95 C46 82 36 70 30 60 Z" fill="#f59e0b"/>
                                <path d="M34 58 C40 66 50 78 58 90" stroke="#d97706" stroke-width="1.5" fill="none"/>
                                <circle cx="34" cy="58" r="4" fill="#fbbf24" stroke="#d97706" stroke-width="1.5"/>
                                <ellipse cx="50" cy="52" rx="10" ry="7" fill="#fed7aa"/>
                                <ellipse cx="50" cy="40" rx="19" ry="20" fill="#fed7aa"/>
                                <ellipse cx="31" cy="40" rx="3.5" ry="5.5" fill="#fdba74"/>
                                <ellipse cx="69" cy="40" rx="3.5" ry="5.5" fill="#fdba74"/>
                                <path d="M29 36 C27 28 32 20 40 18 C43 20 45 22 46 25 C42 24 36 27 33 33 Z" fill="#e2e8f0"/>
                                <path d="M71 36 C73 28 68 20 60 18 C57 20 55 22 54 25 C58 24 64 27 67 33 Z" fill="#e2e8f0"/>
                                <circle cx="30" cy="32" r="6" fill="#e2e8f0"/>
                                <circle cx="30" cy="40" r="5" fill="#e2e8f0"/>
                                <circle cx="70" cy="32" r="6" fill="#e2e8f0"/>
                                <circle cx="70" cy="40" r="5" fill="#e2e8f0"/>
                                <path d="M34 26 C37 20 43 17 50 17 C57 17 63 20 66 26" fill="none" stroke="#ca8a04" stroke-width="2"/>
                                <ellipse cx="37" cy="22" rx="3.5" ry="2" transform="rotate(-30 37 22)" fill="#fbbf24"/>
                                <ellipse cx="43" cy="18" rx="3.5" ry="2" transform="rotate(-15 43 18)" fill="#fbbf24"/>
                                <ellipse cx="50" cy="16" rx="3.5" ry="2" fill="#fbbf24"/>
                                <ellipse cx="57" cy="18" rx="3.5" ry="2" transform="rotate(15 57 18)" fill="#fbbf24"/>
                                <ellipse cx="63" cy="22" rx="3.5" ry="2" transform="rotate(30 63 22)" fill="#fbbf24"/>
                                <path d="M38 33 Q43 31 47 34" stroke="#94a3b8" stroke-width="2.5" stroke-linecap="round" fill="none"/>
                                <path d="M62 33 Q57 31 53 34" stroke="#94a3b8" stroke-width="2.5" stroke-linecap="round" fill="none"/>
                                <circle cx="43" cy="37" r="2.8" fill="#1e293b"/>
                                <circle cx="44" cy="36" r="1" fill="#ffffff"/>
                                <circle cx="57" cy="37" r="2.8" fill="#1e293b"/>
                                <circle cx="58" cy="36" r="1" fill="#ffffff"/>
                                <circle cx="37" cy="42" r="3.5" fill="#fca5a5" opacity="0.6"/>
                                <circle cx="63" cy="42" r="3.5" fill="#fca5a5" opacity="0.6"/>
                                <path d="M50 36 L48 42 L52 42 Z" fill="#fb923c"/>
                                <path d="M35 44 C35 55 42 66 50 66 C58 66 65 55 65 44 C65 43 63 45 61 46 C57 48 53 47 50 48 C47 47 43 48 39 46 C37 45 35 43 35 44 Z" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="1.5"/>
                                <path d="M50 44 C46 41 40 43 38 46 C42 47 47 47 50 49 C53 47 58 47 62 46 C60 43 54 41 50 44 Z" fill="#ffffff" stroke="#cbd5e1" stroke-width="1"/>
                                <path d="M47 46 Q50 48 53 46" stroke="#e11d48" stroke-width="1.5" stroke-linecap="round" fill="none"/>
                                <g transform="translate(62, 68) rotate(-15)">
                                    <rect x="0" y="0" width="22" height="9" rx="3" fill="#fef3c7" stroke="#d97706" stroke-width="1.5"/>
                                    <line x1="11" y1="0" x2="11" y2="9" stroke="#ef4444" stroke-width="2"/>
                                    <line x1="4" y1="3" x2="8" y2="3" stroke="#b45309" stroke-width="1"/>
                                    <line x1="4" y1="6" x2="8" y2="6" stroke="#b45309" stroke-width="1"/>
                                    <line x1="14" y1="3" x2="18" y2="3" stroke="#b45309" stroke-width="1"/>
                                    <line x1="14" y1="6" x2="18" y2="6" stroke="#b45309" stroke-width="1"/>
                                </g>
                                <circle cx="68" cy="74" r="4.5" fill="#fed7aa"/>
                                <polygon points="18,22 20,16 22,22 28,24 22,26 20,32 18,26 12,24" fill="#fde047"/>
                                <polygon points="82,48 83,44 85,48 89,49 85,50 83,54 82,50 78,49" fill="#fde047"/>
                            </svg>
                        </div>
                        <!-- Arya SVG (Siswa Cerdas & Antusias) -->
                        <div data-char-avatar="arya" class="hidden">
                            <svg class="char-art" viewBox="0 0 100 100" width="86" height="86" xmlns="http://www.w3.org/2000/svg">
                                <defs>
                                    <radialGradient id="arya-halo" cx="50%" cy="50%" r="50%">
                                        <stop offset="0%" stop-color="#38bdf8" stop-opacity="0.35"/>
                                        <stop offset="100%" stop-color="#38bdf8" stop-opacity="0"/>
                                    </radialGradient>
                                </defs>
                                <circle cx="50" cy="50" r="46" fill="url(#arya-halo)"/>
                                <path d="M26 95 C26 74 34 62 44 58 C47 57 53 57 56 58 C66 62 74 74 74 95 Z" fill="#2563eb" stroke="#1d4ed8" stroke-width="2"/>
                                <path d="M43 58 L50 70 L57 58 Z" fill="#f97316"/>
                                <path d="M45 58 L50 67 L55 58 Z" fill="#ffffff"/>
                                <path d="M50 70 L50 95" stroke="#f97316" stroke-width="2.5"/>
                                <path d="M30 52 C30 62 40 66 50 66 C60 66 70 62 70 52" fill="none" stroke="#0f172a" stroke-width="4.5" stroke-linecap="round"/>
                                <rect x="27" y="48" width="6" height="12" rx="3" fill="#f97316" stroke="#0f172a" stroke-width="1.5"/>
                                <rect x="67" y="48" width="6" height="12" rx="3" fill="#f97316" stroke="#0f172a" stroke-width="1.5"/>
                                <ellipse cx="50" cy="52" rx="8" ry="6" fill="#fed7aa"/>
                                <ellipse cx="50" cy="38" rx="18" ry="19" fill="#fed7aa"/>
                                <ellipse cx="32" cy="38" rx="3" ry="5" fill="#fdba74"/>
                                <ellipse cx="68" cy="38" rx="3" ry="5" fill="#fdba74"/>
                                <path d="M31 34 C30 22 40 14 53 14 C64 14 70 20 70 30 C70 34 68 35 66 33 C64 28 60 22 52 22 C43 22 36 26 34 35 Z" fill="#1e293b"/>
                                <path d="M36 24 C44 19 54 20 62 26 C57 26 50 25 43 28 C39 30 37 32 35 34 Z" fill="#334155"/>
                                <circle cx="42" cy="37" r="7.5" fill="none" stroke="#1e3a8a" stroke-width="2.2"/>
                                <circle cx="58" cy="37" r="7.5" fill="none" stroke="#1e3a8a" stroke-width="2.2"/>
                                <line x1="49.5" y1="37" x2="50.5" y2="37" stroke="#1e3a8a" stroke-width="2.2"/>
                                <path d="M38 34 Q42 32 45 35" stroke="#bae6fd" stroke-width="1.5" stroke-linecap="round" fill="none"/>
                                <path d="M54 34 Q58 32 61 35" stroke="#bae6fd" stroke-width="1.5" stroke-linecap="round" fill="none"/>
                                <circle cx="42" cy="37" r="2.8" fill="#0f172a"/>
                                <circle cx="43" cy="36" r="1.2" fill="#ffffff"/>
                                <circle cx="58" cy="37" r="2.8" fill="#0f172a"/>
                                <circle cx="59" cy="36" r="1.2" fill="#ffffff"/>
                                <circle cx="36" cy="42" r="3.5" fill="#f472b6" opacity="0.6"/>
                                <circle cx="64" cy="42" r="3.5" fill="#f472b6" opacity="0.6"/>
                                <circle cx="50" cy="41" r="1.5" fill="#f97316"/>
                                <path d="M44 45 Q50 51 56 45" stroke="#0f172a" stroke-width="2" stroke-linecap="round" fill="#e11d48"/>
                                <path d="M46 46 Q50 48 54 46" fill="#ffffff"/>
                                <g transform="translate(68, 64)">
                                    <circle cx="6" cy="10" r="5" fill="#fed7aa" stroke="#ea580c" stroke-width="1"/>
                                    <path d="M4 10 L4 3 C4 1.5 7 1.5 7 3 L7 10" fill="#fed7aa" stroke="#ea580c" stroke-width="1"/>
                                    <polygon points="12,2 13,0 14,2 16,3 14,4 13,6 12,4 10,3" fill="#38bdf8"/>
                                </g>
                                <polygon points="16,30 18,26 20,30 24,31 20,32 18,36 16,32 12,31" fill="#38bdf8"/>
                            </svg>
                        </div>
                        <!-- Pallas Owl SVG (Burung Hantu Bijak Athena) -->
                        <div data-char-avatar="pallas" class="hidden">
                            <svg class="char-art" viewBox="0 0 100 100" width="86" height="86" xmlns="http://www.w3.org/2000/svg">
                                <defs>
                                    <radialGradient id="pallas-halo" cx="50%" cy="50%" r="50%">
                                        <stop offset="0%" stop-color="#fbbf24" stop-opacity="0.35"/>
                                        <stop offset="100%" stop-color="#fbbf24" stop-opacity="0"/>
                                    </radialGradient>
                                </defs>
                                <circle cx="50" cy="50" r="46" fill="url(#pallas-halo)"/>
                                <ellipse cx="50" cy="58" rx="26" ry="28" fill="#92400e" stroke="#78350f" stroke-width="2"/>
                                <ellipse cx="50" cy="62" rx="18" ry="20" fill="#fef3c7"/>
                                <path d="M45 54 L50 58 L55 54" stroke="#d97706" stroke-width="1.8" stroke-linecap="round" fill="none"/>
                                <path d="M43 62 L50 67 L57 62" stroke="#d97706" stroke-width="1.8" stroke-linecap="round" fill="none"/>
                                <path d="M45 70 L50 74 L55 70" stroke="#d97706" stroke-width="1.8" stroke-linecap="round" fill="none"/>
                                <ellipse cx="27" cy="58" rx="6" ry="16" transform="rotate(12 27 58)" fill="#78350f"/>
                                <ellipse cx="73" cy="58" rx="6" ry="16" transform="rotate(-12 73 58)" fill="#78350f"/>
                                <circle cx="41" cy="42" r="14" fill="#ffffff" stroke="#d97706" stroke-width="1.5"/>
                                <circle cx="59" cy="42" r="14" fill="#ffffff" stroke="#d97706" stroke-width="1.5"/>
                                <circle cx="41" cy="42" r="10.5" fill="none" stroke="#f59e0b" stroke-width="2.5"/>
                                <circle cx="59" cy="42" r="10.5" fill="none" stroke="#f59e0b" stroke-width="2.5"/>
                                <line x1="51.5" y1="42" x2="48.5" y2="42" stroke="#f59e0b" stroke-width="2.5"/>
                                <circle cx="41" cy="42" r="6" fill="#1e1b4b"/>
                                <circle cx="41" cy="42" r="4.5" fill="#f59e0b"/>
                                <circle cx="41" cy="42" r="2.8" fill="#0f172a"/>
                                <circle cx="42.5" cy="40.5" r="1.3" fill="#ffffff"/>
                                <circle cx="59" cy="42" r="6" fill="#1e1b4b"/>
                                <circle cx="59" cy="42" r="4.5" fill="#f59e0b"/>
                                <circle cx="59" cy="42" r="2.8" fill="#0f172a"/>
                                <circle cx="60.5" cy="40.5" r="1.3" fill="#ffffff"/>
                                <polygon points="50,44 46,52 50,56 54,52" fill="#ea580c"/>
                                <path d="M42 24 C42 22 58 22 58 24 L56 27 L44 27 Z" fill="#1e1b4b"/>
                                <polygon points="50,11 76,21 50,29 24,21" fill="#312e81" stroke="#4338ca" stroke-width="1.5"/>
                                <polygon points="50,13 72,21 50,27 28,21" fill="#1e1b4b"/>
                                <circle cx="50" cy="20" r="2.5" fill="#fbbf24"/>
                                <path d="M50 20 Q65 24 68 32 L68 39" fill="none" stroke="#f59e0b" stroke-width="1.8"/>
                                <rect x="66" y="37" width="4" height="6" rx="1.5" fill="#fbbf24"/>
                                <g transform="translate(64, 68) rotate(-15)">
                                    <rect x="0" y="0" width="16" height="12" rx="2" fill="#dc2626" stroke="#991b1b" stroke-width="1"/>
                                    <rect x="2" y="2" width="12" height="8" rx="1" fill="#fef2f2"/>
                                    <line x1="8" y1="2" x2="8" y2="10" stroke="#f59e0b" stroke-width="1.5"/>
                                </g>
                                <ellipse cx="43" cy="84" rx="4" ry="2.5" fill="#ea580c"/>
                                <ellipse cx="57" cy="84" rx="4" ry="2.5" fill="#ea580c"/>
                                <polygon points="20,24 22,19 24,24 28,26 24,28 22,33 20,28 16,26" fill="#fde047"/>
                                <polygon points="82,42 83,38 85,42 89,43 85,44 83,48 82,44 78,43" fill="#fde047"/>
                            </svg>
                        </div>
                    </div>
                    <div class="pixel-pedestal"></div>
                </div>

                <div class="pixel-speech-bubble" data-speech-bubble>
                    <span data-speech-text>{{ $isLocked ? 'Kuis selesai! Skor kamu ' . ($attempt->score ?? 0) . '/100 dan +' . ($attempt->xp_awarded ?? 0) . ' XP! 🎉' : 'Halo! Aku Socrates. Pikirkan setiap pertanyaan secara kritis, kamu pasti bisa! ✨' }}</span>
                </div>

                <div class="pixel-char-select-row">
                    <button type="button" class="pixel-char-btn is-active" data-select-char="socrates" title="Pilih Socrates">🧙‍♂️ Socrates</button>
                    <button type="button" class="pixel-char-btn" data-select-char="arya" title="Pilih Arya">🧑‍🎓 Arya</button>
                    <button type="button" class="pixel-char-btn" data-select-char="pallas" title="Pilih Pallas">🦉 Pallas</button>
                </div>
            </div>

            <article class="panel quiz-card-panel" aria-labelledby="question-title">
                <div class="quiz-toolbar">
                    <div class="flex items-center gap-2.5">
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider" data-q-counter>SOAL 1 DARI {{ $questionsPayload->count() }}</span>
                        <span class="rounded bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700 border border-blue-200/60">{{ mb_strtoupper($document->category) }}</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <!-- Sound FX Toggle -->
                        <button type="button" class="sound-toggle-btn" data-sound-toggle aria-label="Pengaturan suara game">
                            <span data-sound-icon>🔊</span> <span data-sound-label class="hidden sm:inline">Suara ON</span>
                        </button>

                        @if ($isTimed && ! $isLocked)
                            <!-- Overall Quiz Countdown Timer (Berdasarkan Waktu Guru) -->
                            <div class="quiz-timer-overall" data-timer-overall data-timer-seconds aria-label="Sisa waktu pengerjaan kuis keseluruhan">
                                <x-icon name="timer" class="w-4 h-4 text-blue-600 animate-spin-slow" />
                                <span data-timer-overall-display data-timer-seconds>15:00</span>
                            </div>
                        @endif
                    </div>
                </div>

                @if ($isTimed && ! $isLocked)
                    <!-- Timer Progress Strip -->
                    <div class="timer-progress-track" aria-hidden="true">
                        <div class="timer-progress-bar" data-timer-bar style="width: 100%;"></div>
                    </div>
                @endif

                <div class="quiz-content-area" data-quiz-body>
                    <p class="section-overline">BACAAN: {{ mb_strtoupper($document->title) }}</p>
                    <h2 id="question-title" class="quiz-question-text" data-q-text>{{ $questionsPayload->first()['question'] ?? '' }}</h2>

                    <!-- 2x2 ZEP Chunky Options Grid -->
                    <div class="zep-options-grid" data-options-list role="radiogroup" aria-labelledby="question-title">
                        <!-- Populated dynamically via JS -->
                    </div>

                    <!-- Explanation Box (Appears on review mode) -->
                    <div class="quiz-explanation-box hidden rounded-xl p-3.5 text-xs border" data-explanation-box>
                        <div class="flex items-center gap-1.5 font-bold mb-1 text-blue-800">
                            <x-icon name="lightbulb" class="w-4 h-4 text-blue-600" />
                            <span>Pembahasan Kunci Jawaban:</span>
                        </div>
                        <p class="text-slate-700 leading-relaxed" data-explanation-text></p>
                    </div>
                </div>

                <!-- Footer Navigation: Tombol Back & Next di Setiap Soal -->
                <div class="quiz-card-footer flex items-center justify-between gap-3 pt-4 border-t border-slate-100">
                    <button type="button" class="outline-button" data-btn-prev disabled>
                        <x-icon name="arrow-left" /> <span>Sebelumnya</span>
                    </button>

                    <div class="flex items-center gap-2">
                        @if ($isLocked)
                            <a class="outline-button !border-purple-200 !text-purple-700 bg-purple-50/50 hover:bg-purple-100" href="{{ route('progress') }}">
                                <x-icon name="trending-up" /> <span>Lihat Progress</span>
                            </a>
                            <a class="outline-button" href="{{ route('quiz.index') }}">
                                <x-icon name="gamepad-2" /> <span>Challenge Socrates</span>
                            </a>
                        @endif
                        <button type="button" class="challenge-button" data-btn-next>
                            <span data-next-label>Lanjut</span>
                            <x-icon name="arrow-right" />
                        </button>
                    </div>
                </div>

                <!-- Scorecard Overlay (Shown upon completion) -->
                <div class="quiz-complete-card hidden p-8 text-center" data-scorecard>
                    <div class="w-16 h-16 rounded-2xl bg-emerald-100 text-emerald-600 grid place-items-center mx-auto mb-4">
                        <x-icon name="award" class="w-8 h-8" />
                    </div>
                    <p class="section-overline text-emerald-700 font-bold">KUIS SELESAI & TERSIMPAN</p>
                    <h2 class="text-2xl font-bold text-slate-900 mb-1" data-score-heading>Skor Anda: {{ $attempt->score ?? 0 }} / 100</h2>
                    <p class="text-xs text-slate-600 mb-6" data-score-sub>Jawaban Anda telah terkunci secara permanen untuk penilaian integritas.</p>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 max-w-md mx-auto mb-6 text-left">
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                            <small class="text-[10px] text-slate-400 font-bold uppercase">Jawaban Benar</small>
                            <div class="text-lg font-bold text-emerald-600" data-stat-correct>{{ $attempt->correct_count ?? 0 }} / {{ $attempt->total_questions ?? 10 }}</div>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                            <small class="text-[10px] text-slate-400 font-bold uppercase">XP Diperoleh</small>
                            <div class="text-lg font-bold text-blue-600" data-stat-xp>+{{ $attempt->xp_awarded ?? 0 }} XP</div>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-100 col-span-2 sm:col-span-1">
                            <small class="text-[10px] text-slate-400 font-bold uppercase">Status Tugas</small>
                            <div class="text-xs font-bold text-slate-700 mt-1 flex items-center gap-1">
                                <x-icon name="lock" class="w-3.5 h-3.5 text-amber-500" /> Terkunci
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-center gap-3">
                        <button type="button" class="outline-button" data-btn-review-scorecard>
                            <x-icon name="eye" /> <span>Tinjau Pembahasan Soal</span>
                        </button>
                        <a href="{{ route('quiz.index') }}" class="outline-button">
                            <x-icon name="gamepad-2" /> <span>Challenge Socrates</span>
                        </a>
                        <a href="{{ route('progress') }}" class="challenge-button">
                            <span>Lihat Progress</span> <x-icon name="arrow-right" />
                        </a>
                    </div>
                </div>
            </article>
        </div>

        <!-- Right Column: Number Grid (Only Question Grid, Tanpa Panel Tantangan Lain) -->
        <aside class="quiz-side-column" aria-label="Navigasi nomor soal">
            <section class="panel number-grid-card">
                <div class="section-heading compact-heading">
                    <div>
                        <p class="section-overline">DAFTAR SOAL</p>
                        <h2>Nomor Soal (1–{{ $questionsPayload->count() }})</h2>
                    </div>
                    <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">
                        {{ $isTimed ? "Waktu {$durationMinutes}m" : 'Mandiri' }}
                    </span>
                </div>

                <div class="number-grid-boxes grid grid-cols-5 gap-2 my-3" data-number-grid>
                    @foreach ($questionsPayload as $idx => $q)
                        <button type="button" class="num-box" data-num-idx="{{ $idx }}" aria-label="Buka nomor {{ $idx + 1 }}">
                            {{ $idx + 1 }}
                        </button>
                    @endforeach
                </div>

                <div class="text-[11px] text-slate-500 border-t border-slate-100 pt-3 mt-3 flex items-center justify-between">
                    <span class="flex items-center gap-1.5"><i class="w-2.5 h-2.5 rounded-full bg-blue-600 inline-block"></i> Dijawab</span>
                    <span class="flex items-center gap-1.5"><i class="w-2.5 h-2.5 rounded-full bg-slate-200 inline-block"></i> Belum</span>
                    @if ($isLocked)
                        <span class="flex items-center gap-1.5"><i class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></i> Benar</span>
                    @endif
                </div>
            </section>
        </aside>
    </div>

    <!-- Modal Konfirmasi & Peringatan Pengumpulan Jawaban -->
    <div id="quizSubmitModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity" role="dialog" aria-modal="true">
        <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl border border-slate-100 text-center transform transition-all scale-100">
            <!-- Dynamic Icon Container -->
            <div id="modalIconContainer" class="w-16 h-16 rounded-2xl mx-auto mb-4 grid place-items-center bg-amber-100 text-amber-600">
                <x-icon name="alert-triangle" class="w-8 h-8" />
            </div>

            <!-- Dynamic Title & Message -->
            <h3 id="modalTitle" class="text-xl font-bold text-slate-900 mb-2">Ada Soal yang Belum Dijawab!</h3>
            <p id="modalMessage" class="text-xs text-slate-600 leading-relaxed mb-6">
                Kamu masih memiliki beberapa soal yang belum dipilih jawabannya. Apakah kamu yakin ingin tetap mengumpulkan kuis sekarang?
            </p>

            <!-- Buttons -->
            <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-center gap-3">
                <button type="button" id="btnModalCheckAgain" class="outline-button w-full sm:w-1/2 justify-center py-2.5 text-xs font-semibold">
                    Periksa Kembali
                </button>
                <button type="button" id="btnModalConfirmSubmit" class="challenge-button !bg-emerald-600 hover:!bg-emerald-700 !border-emerald-600 w-full sm:w-1/2 justify-center py-2.5 text-xs font-bold">
                    Kumpulkan Sekarang
                </button>
            </div>
        </div>
    </div>

    <!-- Confetti Canvas for Victory Effects -->
    <canvas id="confetti-canvas"></canvas>
@endsection

@push('scripts')
<script>
(() => {
    const questions = @json($questionsPayload);
    let savedAnswers = @json($savedAnswers ?: new stdClass());
    let isLocked = Boolean(@json($isLocked));
    const isTimed = Boolean(@json($isTimed));
    let current = 0;

    // Total Overall Timer dari opsi Guru (dalam detik)
    const initialTotalSeconds = Number({{ $durationSeconds }}) || 900;
    let totalSecondsLeft = initialTotalSeconds;
    let overallTimerInterval = null;
    let totalTimeSpent = 0;

    // Element Selectors
    const appShell = document.querySelector('.app-shell');
    const root = document.querySelector('[data-quiz]');
    const qCounter = root.querySelector('[data-q-counter]');
    const qText = root.querySelector('[data-q-text]');
    const optionsList = root.querySelector('[data-options-list]');
    const explanationBox = root.querySelector('[data-explanation-box]');
    const explanationText = root.querySelector('[data-explanation-text]');
    const btnPrev = root.querySelector('[data-btn-prev]');
    const btnNext = root.querySelector('[data-btn-next]');
    const nextLabel = btnNext.querySelector('[data-next-label]');
    const numberBoxes = root.querySelectorAll('[data-num-idx]');

    const timerBadge = root.querySelector('[data-timer-overall]');
    const timerDisplay = root.querySelector('[data-timer-overall-display]');
    const timerBar = root.querySelector('[data-timer-bar]');

    const scorecard = root.querySelector('[data-scorecard]');
    const quizContentArea = root.querySelector('[data-quiz-body]');
    const quizFooter = root.querySelector('.quiz-card-footer');

    // Pixel Stage Elements
    const spriteWrapper = root.querySelector('[data-sprite-wrapper]');
    const speechText = root.querySelector('[data-speech-text]');
    const soundToggle = root.querySelector('[data-sound-toggle]');
    const soundIcon = root.querySelector('[data-sound-icon]');
    const soundLabel = root.querySelector('[data-sound-label]');
    const charButtons = root.querySelectorAll('[data-select-char]');
    const charAvatars = root.querySelectorAll('[data-char-avatar]');

    // Modal Elements
    const submitModal = document.getElementById('quizSubmitModal');
    const modalIconContainer = document.getElementById('modalIconContainer');
    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');
    const btnModalCheckAgain = document.getElementById('btnModalCheckAgain');
    const btnModalConfirmSubmit = document.getElementById('btnModalConfirmSubmit');

    // 1. Focus Mode: Hilangkan sidebar saat quiz berlangsung
    const updateFocusMode = () => {
        if (!isLocked && appShell) {
            appShell.classList.add('quiz-fullscreen-mode');
        } else if (appShell) {
            appShell.classList.remove('quiz-fullscreen-mode');
        }
    };
    updateFocusMode();

    // 2. Sound Effects
    let soundEnabled = localStorage.getItem('SR_QUIZ_SOUND') !== 'false';
    let audioCtx = null;
    const initAudio = () => {
        if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        if (audioCtx.state === 'suspended') audioCtx.resume();
    };

    const playSfx = (type) => {
        if (!soundEnabled) return;
        try {
            initAudio();
            const now = audioCtx.currentTime;
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.connect(gain);
            gain.connect(audioCtx.destination);

            if (type === 'click') {
                osc.type = 'sine';
                osc.frequency.setValueAtTime(480, now);
                osc.frequency.exponentialRampToValueAtTime(720, now + 0.08);
                gain.gain.setValueAtTime(0.15, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.08);
                osc.start(now);
                osc.stop(now + 0.08);
            } else if (type === 'cancel') {
                osc.type = 'sine';
                osc.frequency.setValueAtTime(500, now);
                osc.frequency.linearRampToValueAtTime(320, now + 0.1);
                gain.gain.setValueAtTime(0.12, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.1);
                osc.start(now);
                osc.stop(now + 0.1);
            } else if (type === 'victory') {
                [523.25, 659.25, 783.99, 1046.50].forEach((freq, idx) => {
                    const noteOsc = audioCtx.createOscillator();
                    const noteGain = audioCtx.createGain();
                    noteOsc.connect(noteGain);
                    noteGain.connect(audioCtx.destination);
                    noteOsc.type = 'triangle';
                    noteOsc.frequency.setValueAtTime(freq, now + idx * 0.12);
                    noteGain.gain.setValueAtTime(0.2, now + idx * 0.12);
                    noteGain.gain.exponentialRampToValueAtTime(0.001, now + idx * 0.12 + 0.3);
                    noteOsc.start(now + idx * 0.12);
                    noteOsc.stop(now + idx * 0.12 + 0.3);
                });
            }
        } catch (_) {}
    };

    // 3. Overall Timer Management
    const formatTime = (secs) => {
        const m = Math.floor(secs / 60);
        const s = secs % 60;
        return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    };

    const startOverallTimer = () => {
        if (!isTimed || isLocked) return;
        if (timerDisplay) timerDisplay.textContent = formatTime(totalSecondsLeft);

        overallTimerInterval = setInterval(() => {
            totalSecondsLeft--;
            totalTimeSpent++;

            if (timerDisplay) timerDisplay.textContent = formatTime(Math.max(0, totalSecondsLeft));
            if (timerBar) {
                const pct = Math.max(0, Math.round((totalSecondsLeft / initialTotalSeconds) * 100));
                timerBar.style.width = `${pct}%`;
                if (totalSecondsLeft <= 120) {
                    timerBadge?.classList.add('is-urgent');
                    timerBar.classList.add('is-urgent');
                }
            }

            if (totalSecondsLeft <= 0) {
                clearInterval(overallTimerInterval);
                alert('Waktu kuis telah berakhir! Jawaban Anda akan otomatis dikumpulkan.');
                submitQuiz();
            }
        }, 1000);
    };

    if (isTimed && !isLocked) {
        startOverallTimer();
    }

    // 4. Update Number Grid UI
    const updateNumberGridUI = () => {
        numberBoxes.forEach((box, idx) => {
            const q = questions[idx];
            const hasAnswer = Boolean(savedAnswers[String(q.id)]);
            box.classList.toggle('is-current', idx === current);
            box.classList.toggle('is-answered', hasAnswer);

            if (isLocked) {
                const isCorrect = q.correct_answer && String(savedAnswers[String(q.id)]).toUpperCase() === String(q.correct_answer).toUpperCase();
                box.classList.toggle('is-correct', isCorrect);
                box.classList.toggle('is-incorrect', hasAnswer && !isCorrect);
            }
        });
    };

    const zepCardStyles = [
        { colorClass: 'zep-card-a', shape: '▲' },
        { colorClass: 'zep-card-b', shape: '◆' },
        { colorClass: 'zep-card-c', shape: '●' },
        { colorClass: 'zep-card-d', shape: '■' },
    ];

    // 5. Render Question
    const renderQuestion = (idx) => {
        current = idx;
        const q = questions[current];
        if (!q) return;

        qCounter.textContent = `SOAL ${current + 1} DARI ${questions.length}`;
        qText.textContent = q.question;

        // Render 2x2 ZEP Chunky 3D Options
        optionsList.innerHTML = '';
        const currentAnswer = savedAnswers[String(q.id)] || '';
        const opts = Array.isArray(q.options) ? q.options : [];

        opts.forEach((opt, optIndex) => {
            const isSelected = String(opt.key).toUpperCase() === String(currentAnswer).toUpperCase();
            const zepStyle = zepCardStyles[optIndex % zepCardStyles.length];

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `zep-card ${zepStyle.colorClass} ${isSelected ? 'is-selected' : ''}`;
            btn.setAttribute('role', 'radio');
            btn.setAttribute('aria-checked', String(isSelected));
            btn.dataset.key = opt.key;

            let badgeHtml = `<span class="zep-shape-badge">${zepStyle.shape} ${opt.key}</span>`;

            if (isLocked) {
                const isCorrect = q.correct_answer && String(opt.key).toUpperCase() === String(q.correct_answer).toUpperCase();
                if (isCorrect) {
                    btn.classList.add('is-correct-answer');
                    badgeHtml = `<span class="zep-shape-badge bg-emerald-700">✓ ${opt.key}</span>`;
                } else if (isSelected) {
                    btn.classList.add('is-wrong-answer');
                    badgeHtml = `<span class="zep-shape-badge bg-red-800">✗ ${opt.key}</span>`;
                }
            }

            let checkHtml = isSelected && !isLocked ? `<span class="zep-selected-badge">✓ Dipilih</span>` : '';

            btn.innerHTML = `
                ${badgeHtml}
                <span class="zep-card-text">${opt.text}</span>
                ${checkHtml}
            `;

            // Toggle Interaksi: Klik 1x untuk memilih, klik 2x untuk membatalkan pilihan
            if (!isLocked) {
                btn.addEventListener('click', () => {
                    const alreadySelected = String(savedAnswers[String(q.id)] || '').toUpperCase() === String(opt.key).toUpperCase();

                    if (alreadySelected) {
                        // BATALKAN PILIHAN (Deselect)
                        playSfx('cancel');
                        delete savedAnswers[String(q.id)];
                        btn.classList.remove('is-selected');
                        btn.setAttribute('aria-checked', 'false');
                        const checkEl = btn.querySelector('.zep-selected-badge');
                        if (checkEl) checkEl.remove();

                        speechText.textContent = `Pilihan dibatalkan. Silakan pilih opsi lain atau lanjutkan.`;
                        updateNumberGridUI();
                    } else {
                        // PILIH OPSI INI
                        playSfx('click');
                        optionsList.querySelectorAll('.zep-card').forEach(el => {
                            el.classList.remove('is-selected');
                            el.setAttribute('aria-checked', 'false');
                            const chk = el.querySelector('.zep-selected-badge');
                            if (chk) chk.remove();
                        });

                        btn.classList.add('is-selected');
                        btn.setAttribute('aria-checked', 'true');
                        const newBadge = document.createElement('span');
                        newBadge.className = 'zep-selected-badge';
                        newBadge.innerHTML = '✓ Dipilih';
                        btn.appendChild(newBadge);

                        savedAnswers[String(q.id)] = opt.key;
                        speechText.textContent = `Kamu memilih opsi ${opt.key}! (Klik lagi untuk membatalkan).`;
                        updateNumberGridUI();
                    }
                });
            }

            optionsList.appendChild(btn);
        });

        // Explanation Box (review mode)
        if (isLocked && q.explanation) {
            explanationBox.classList.remove('hidden');
            explanationText.textContent = q.explanation;
        } else {
            explanationBox.classList.add('hidden');
        }

        // Tombol Back & Next
        // Tombol Back aktif di semua nomor > 0
        btnPrev.disabled = current === 0;
        btnPrev.style.opacity = current === 0 ? '0.4' : '1';

        // Tombol Next / Submit
        if (isLocked) {
            nextLabel.textContent = current === questions.length - 1 ? 'Selesai Meninjau' : 'Selanjutnya';
            btnNext.classList.remove('!bg-emerald-600', 'hover:!bg-emerald-700', '!border-emerald-600');
        } else {
            if (current === questions.length - 1) {
                nextLabel.textContent = 'Kirim Jawaban';
                btnNext.classList.add('!bg-emerald-600', 'hover:!bg-emerald-700', '!border-emerald-600');
            } else {
                nextLabel.textContent = 'Lanjut';
                btnNext.classList.remove('!bg-emerald-600', 'hover:!bg-emerald-700', '!border-emerald-600');
            }
        }

        updateNumberGridUI();
    };

    // 6. Navigation Button Handlers
    btnPrev.addEventListener('click', () => {
        if (current > 0) {
            renderQuestion(current - 1);
        }
    });

    btnNext.addEventListener('click', () => {
        if (isLocked) {
            if (current >= questions.length - 1) {
                window.location.href = "{{ route('quiz.index') }}";
                return;
            }
            renderQuestion(current + 1);
            return;
        }

        // Jika soal terakhir: Tampilkan modal konfirmasi & peringatan
        if (current === questions.length - 1) {
            openSubmitModal();
            return;
        }

        // Soal biasa: Maju ke nomor berikutnya
        renderQuestion(current + 1);
    });

    // 7. Modal Konfirmasi & Peringatan Pengumpulan
    const openSubmitModal = () => {
        const unanswered = [];
        questions.forEach((q, idx) => {
            if (!savedAnswers[String(q.id)]) {
                unanswered.push(idx + 1);
            }
        });

        if (unanswered.length > 0) {
            // Ada soal yang belum dijawab -> Tampilkan Warning
            modalIconContainer.className = 'w-16 h-16 rounded-2xl mx-auto mb-4 grid place-items-center bg-amber-100 text-amber-600';
            modalIconContainer.innerHTML = `<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>`;
            modalTitle.textContent = `Ada ${unanswered.length} Soal Belum Dijawab!`;
            modalMessage.innerHTML = `Kamu belum memilih jawaban untuk soal nomor: <strong class="text-amber-700 underline font-bold">${unanswered.join(', ')}</strong>.<br><br>Apakah kamu yakin ingin tetap mengumpulkan sekarang?`;
            btnModalCheckAgain.textContent = `Periksa Soal No. ${unanswered[0]}`;
            btnModalCheckAgain.onclick = () => {
                submitModal.classList.add('hidden');
                renderQuestion(unanswered[0] - 1);
            };
        } else {
            // Semua soal sudah dijawab -> Tampilkan Konfirmasi Sukses
            modalIconContainer.className = 'w-16 h-16 rounded-2xl mx-auto mb-4 grid place-items-center bg-emerald-100 text-emerald-600';
            modalIconContainer.innerHTML = `<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>`;
            modalTitle.textContent = 'Semua Soal Sudah Dijawab!';
            modalMessage.innerHTML = `Hebat! Semua 10 soal sudah kamu selesaikan.<br>Apakah kamu yakin ingin mengumpulkan kuis ini sekarang? Jawaban akan tersimpan dan dinilai secara permanen.`;
            btnModalCheckAgain.textContent = 'Periksa Lagi';
            btnModalCheckAgain.onclick = () => {
                submitModal.classList.add('hidden');
            };
        }

        btnModalConfirmSubmit.onclick = () => {
            submitModal.classList.add('hidden');
            submitQuiz();
        };

        submitModal.classList.remove('hidden');
    };

    // 8. Submit Quiz
    const submitQuiz = async () => {
        clearInterval(overallTimerInterval);
        btnNext.disabled = true;
        nextLabel.textContent = 'Mengumpulkan…';

        try {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const response = await fetch(root.dataset.submitUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({
                    attempt_id: Number(root.dataset.attemptId),
                    answers: savedAnswers,
                    time_spent_seconds: totalTimeSpent,
                }),
            });

            if (!response.ok) {
                const err = await response.json().catch(() => ({}));
                throw new Error(err.message || 'Gagal mengumpulkan kuis. Coba lagi.');
            }

            const result = await response.json();
            isLocked = true;
            root.dataset.isLocked = '1';

            // Kuis selesai -> kembalikan sidebar aplikasi
            updateFocusMode();

            // Pasang feedback ke questions lokal
            if (result.feedback) {
                questions.forEach(q => {
                    const fb = result.feedback[q.id];
                    if (fb) {
                        q.correct_answer = fb.correct_answer;
                        q.explanation = fb.explanation;
                    }
                });
            }

            // Sembunyikan area soal, tampilkan scorecard
            quizContentArea.classList.add('hidden');
            quizFooter.classList.add('hidden');
            if (timerBadge) timerBadge.classList.add('hidden');
            if (timerBar) timerBar.parentElement.classList.add('hidden');

            scorecard.classList.remove('hidden');
            scorecard.querySelector('[data-score-heading]').textContent = `Skor Anda: ${result.score} / 100`;
            scorecard.querySelector('[data-stat-correct]').textContent = `${result.correct_count} / ${result.total_questions}`;
            scorecard.querySelector('[data-stat-xp]').textContent = `+${result.xp_awarded} XP`;

            playSfx('victory');
            speechText.textContent = `Luar biasa! Kamu menyelesaikan kuis dengan skor ${result.score}! 🎉`;

            updateNumberGridUI();
        } catch (error) {
            alert(error.message);
            btnNext.disabled = false;
            nextLabel.textContent = 'Kirim Jawaban';
        }
    };

    // 9. Number Box Clicks
    numberBoxes.forEach((box, idx) => {
        box.addEventListener('click', () => {
            renderQuestion(idx);
        });
    });

    // 10. Review Scorecard Button
    const btnReviewScorecard = root.querySelector('[data-btn-review-scorecard]');
    if (btnReviewScorecard) {
        btnReviewScorecard.addEventListener('click', () => {
            scorecard.classList.add('hidden');
            quizContentArea.classList.remove('hidden');
            quizFooter.classList.remove('hidden');
            renderQuestion(0);
        });
    }

    // 11. Karakter & Sound Controls
    const companionGreetings = {
        socrates: 'Halo! Aku Socrates. Gunakan akal sehat dan analisis bacaan dengan cermat! 📜✨',
        arya: 'Semangat! Aku Arya, teman belajarmu. Yuk kita tuntaskan kuis ini bersama! 🚀',
        pallas: 'Hoo-hoo! Aku Pallas si burung hantu bijak. Perhatikan detail bacaan dengan teliti! 🦉',
    };

    charButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            playSfx('click');
            const charName = btn.dataset.selectChar;
            charButtons.forEach(b => b.classList.toggle('is-active', b === btn));
            charAvatars.forEach(av => av.classList.toggle('hidden', av.dataset.charAvatar !== charName));
            speechText.textContent = companionGreetings[charName] || `Karakter ${btn.textContent.trim()} siap menemanimu! 🚀`;
            if (spriteWrapper) {
                spriteWrapper.classList.remove('is-idle');
                spriteWrapper.classList.add('is-cheering');
                setTimeout(() => {
                    spriteWrapper.classList.remove('is-cheering');
                    spriteWrapper.classList.add('is-idle');
                }, 600);
            }
        });
    });

    if (soundToggle) {
        soundToggle.addEventListener('click', () => {
            soundEnabled = !soundEnabled;
            localStorage.setItem('SR_QUIZ_SOUND', String(soundEnabled));
            soundIcon.textContent = soundEnabled ? '🔊' : '🔇';
            soundLabel.textContent = soundEnabled ? 'Suara ON' : 'Bisu';
            if (soundEnabled) playSfx('click');
        });
    }

    // Initial render ke soal nomor 1
    renderQuestion(0);
})();
</script>
@endpush
