@extends('layouts.student')

@php
    $isTimed = $mode === 'timed_quiz';
    $modeTitle = $isTimed ? 'Kuis Timer Pilihan Ganda' : 'Latihan Pilihan Ganda';
    $modeSubtitle = $isTimed
        ? '10 Soal berbasis bacaan dengan batas waktu 60 detik per nomor. Uji ketepatan & kecepatan pemikiranmu.'
        : '10 Soal evaluasi pemahaman bacaan tanpa batas waktu. Baca teks dengan teliti dan temukan jawaban terbaik.';
@endphp

@section('title', $modeTitle)
@section('breadcrumb', $modeTitle)

@section('content')
    <section class="stage-heading" aria-labelledby="quiz-heading-title">
        <div>
            <p class="eyebrow">{{ $isTimed ? 'EVALUASI DENGAN WAKTU · 60 DETIK / SOAL' : 'LATIHAN MANDIRI · PILIHAN GANDA' }}</p>
            <h1 id="quiz-heading-title">{{ $modeTitle }}</h1>
            <p class="welcome-subtitle">{{ $modeSubtitle }}</p>
        </div>
        <a class="back-link" href="{{ route('reading', ['document' => $document->id]) }}">
            <x-icon name="arrow-left" /> <span>Kembali ke bacaan</span>
        </a>
    </section>

    <div class="mb-5 flex flex-wrap items-center gap-2 border-b border-slate-200/80 pb-3" role="tablist" aria-label="Mode Tantangan">
        <a href="{{ route('challenge') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition bg-slate-100 text-slate-700 hover:bg-slate-200/80" role="tab" aria-selected="false">
            <x-icon name="circle-help" class="w-4 h-4" />
            <span>1. Esai Sokratik (5 Soal)</span>
        </a>
        <a href="{{ route('quiz.timed', ['document' => $document->id]) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition bg-indigo-600 text-white shadow-xs" role="tab" aria-selected="true">
            <x-icon name="gamepad-2" class="w-4 h-4" />
            <span>2. Kuis Interaktif ZEP (10 Soal Timer)</span>
            <span class="ml-0.5 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] bg-indigo-700/90 text-indigo-100">60s/soal</span>
            @if ($isLocked)
                <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] bg-indigo-900 text-indigo-100">
                    <x-icon name="lock" class="w-2.5 h-2.5 mr-0.5" /> Selesai
                </span>
            @endif
        </a>
    </div>

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

    <div class="quiz-room-grid" data-quiz data-attempt-id="{{ $attempt->id }}" data-mode="{{ $mode }}" data-is-locked="{{ $isLocked ? '1' : '0' }}" data-submit-url="{{ route('quiz.submit') }}">
        <!-- Left Column: Active Question -->
        <div class="quiz-main-column">
            <!-- Retro Pixel Character Stage (ZEP Quiz Style) -->
            <div class="pixel-stage-banner" data-pixel-stage>
                <div class="pixel-character-box">
                    <div class="pixel-sprite-wrapper is-idle" data-sprite-wrapper>
                        <!-- Socrates SVG -->
                        <div data-char-avatar="socrates">
                            <svg class="pixel-art" viewBox="0 0 24 24" width="60" height="60" xmlns="http://www.w3.org/2000/svg">
                                <rect x="7" y="3" width="2" height="1" fill="#f59e0b" />
                                <rect x="15" y="3" width="2" height="1" fill="#f59e0b" />
                                <rect x="6" y="4" width="2" height="1" fill="#fbbf24" />
                                <rect x="16" y="4" width="2" height="1" fill="#fbbf24" />
                                <rect x="8" y="4" width="8" height="2" fill="#fed7aa" />
                                <rect x="5" y="6" width="2" height="4" fill="#cbd5e1" />
                                <rect x="17" y="6" width="2" height="4" fill="#cbd5e1" />
                                <rect x="7" y="6" width="10" height="5" fill="#fed7aa" />
                                <rect x="9" y="8" width="1.5" height="1.5" fill="#1e293b" />
                                <rect x="13.5" y="8" width="1.5" height="1.5" fill="#1e293b" />
                                <rect x="7.5" y="9.5" width="1.5" height="1" fill="#fca5a5" />
                                <rect x="15" y="9.5" width="1.5" height="1" fill="#fca5a5" />
                                <rect x="8" y="11" width="8" height="3" fill="#f1f5f9" />
                                <rect x="9" y="14" width="6" height="2" fill="#e2e8f0" />
                                <rect x="10" y="16" width="4" height="1" fill="#cbd5e1" />
                                <rect x="11" y="11.5" width="2" height="1" fill="#dc2626" />
                                <rect x="7" y="14" width="10" height="7" fill="#ffffff" />
                                <rect x="9" y="14" width="3" height="7" fill="#f59e0b" />
                                <rect x="6" y="15" width="2" height="4" fill="#ffffff" />
                                <rect x="16" y="15" width="2" height="4" fill="#ffffff" />
                                <rect x="5" y="17" width="2" height="2" fill="#fed7aa" />
                                <rect x="17" y="17" width="2" height="2" fill="#fed7aa" />
                                <rect x="18" y="15" width="3" height="5" fill="#fef08a" />
                                <rect x="19" y="16" width="1" height="3" fill="#ca8a04" />
                                <rect x="8" y="21" width="3" height="2" fill="#d97706" />
                                <rect x="13" y="21" width="3" height="2" fill="#d97706" />
                            </svg>
                        </div>
                        <!-- Arya SVG -->
                        <div data-char-avatar="arya" class="hidden">
                            <svg class="pixel-art" viewBox="0 0 24 24" width="60" height="60" xmlns="http://www.w3.org/2000/svg">
                                <rect x="7" y="3" width="10" height="3" fill="#2563eb" />
                                <rect x="6" y="5" width="12" height="2" fill="#1d4ed8" />
                                <rect x="15" y="6" width="4" height="1" fill="#60a5fa" />
                                <rect x="7" y="6" width="3" height="2" fill="#78350f" />
                                <rect x="7" y="7" width="10" height="5" fill="#fed7aa" />
                                <rect x="9" y="8.5" width="1.5" height="1.5" fill="#0f172a" />
                                <rect x="13.5" y="8.5" width="1.5" height="1.5" fill="#0f172a" />
                                <rect x="10.5" y="10.5" width="3" height="1" fill="#dc2626" />
                                <rect x="7.5" y="9.5" width="1.5" height="1" fill="#f87171" />
                                <rect x="15" y="9.5" width="1.5" height="1" fill="#f87171" />
                                <rect x="6" y="12" width="12" height="8" fill="#3b82f6" />
                                <rect x="10.5" y="12" width="3" height="8" fill="#f97316" />
                                <rect x="4" y="13" width="2" height="5" fill="#2563eb" />
                                <rect x="18" y="13" width="2" height="5" fill="#2563eb" />
                                <rect x="4" y="17" width="2" height="2" fill="#fed7aa" />
                                <rect x="18" y="17" width="2" height="2" fill="#fed7aa" />
                                <rect x="7" y="20" width="4" height="3" fill="#1e293b" />
                                <rect x="13" y="20" width="4" height="3" fill="#1e293b" />
                            </svg>
                        </div>
                        <!-- Pallas Owl SVG -->
                        <div data-char-avatar="pallas" class="hidden">
                            <svg class="pixel-art" viewBox="0 0 24 24" width="60" height="60" xmlns="http://www.w3.org/2000/svg">
                                <polygon points="12,2 20,5 12,8 4,5" fill="#1e1b4b" />
                                <rect x="10" y="6" width="4" height="2" fill="#312e81" />
                                <rect x="19" y="5" width="1" height="4" fill="#fbbf24" />
                                <rect x="5" y="8" width="14" height="12" fill="#92400e" rx="3" />
                                <rect x="4" y="11" width="2" height="7" fill="#78350f" />
                                <rect x="18" y="11" width="2" height="7" fill="#78350f" />
                                <rect x="8" y="13" width="8" height="7" fill="#fef3c7" />
                                <circle cx="8.5" cy="11.5" r="3" fill="#ffffff" stroke="#475569" stroke-width="0.8" />
                                <circle cx="15.5" cy="11.5" r="3" fill="#ffffff" stroke="#475569" stroke-width="0.8" />
                                <circle cx="8.5" cy="11.5" r="1.3" fill="#0f172a" />
                                <circle cx="15.5" cy="11.5" r="1.3" fill="#0f172a" />
                                <polygon points="12,12 10.5,14 13.5,14" fill="#f59e0b" />
                                <rect x="8" y="20" width="3" height="2" fill="#f59e0b" />
                                <rect x="13" y="20" width="3" height="2" fill="#f59e0b" />
                            </svg>
                        </div>
                    </div>
                    <div class="pixel-pedestal"></div>
                </div>

                <div class="pixel-speech-bubble" data-speech-bubble>
                    <span data-speech-text>Halo! Baca setiap pertanyaan dengan cermat ya, kamu pasti bisa! ✨</span>
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
                        <!-- Streak Combo Counter Badge -->
                        <div class="zep-streak-badge hidden" data-streak-badge>
                            <span>🔥</span> <span data-streak-text>STREAK x0</span>
                        </div>

                        <!-- 8-Bit Retro Sound FX Toggle -->
                        <button type="button" class="sound-toggle-btn" data-sound-toggle aria-label="Pengaturan suara game">
                            <span data-sound-icon>🔊</span> <span data-sound-label class="hidden sm:inline">Suara ON</span>
                        </button>

                        @if ($isTimed && ! $isLocked)
                            <!-- 60-Second Question Timer -->
                            <div class="quiz-timer-badge" data-timer-badge aria-label="Sisa waktu soal">
                                <x-icon name="timer" class="w-4 h-4 text-blue-600" />
                                <span class="text-xs font-bold text-blue-700 font-mono"><span data-timer-seconds>60</span>s</span>
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

                    <!-- ZEP 2x2 Chunky 3D Options Container -->
                    <div class="zep-options-grid" data-options-list role="radiogroup" aria-labelledby="question-title">
                        <!-- Populated by JS -->
                    </div>

                    <!-- Explanation Box (Appears on review mode or after answering) -->
                    <div class="quiz-explanation-box hidden rounded-xl p-3.5 text-xs border" data-explanation-box>
                        <div class="flex items-center gap-1.5 font-bold mb-1 text-blue-800">
                            <x-icon name="lightbulb" class="w-4 h-4 text-blue-600" />
                            <span>Pembahasan Kunci Jawaban:</span>
                        </div>
                        <p class="text-slate-700 leading-relaxed" data-explanation-text></p>
                    </div>
                </div>

                <!-- Footer Navigation -->
                <div class="quiz-card-footer">
                    <div class="flex items-center gap-2">
                        <button type="button" class="outline-button" data-btn-prev hidden>
                            <x-icon name="arrow-left" /> <span>Sebelumnya</span>
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($isLocked)
                            <a class="outline-button" href="{{ route('dashboard') }}">Dashboard</a>
                        @endif
                        <button type="button" class="challenge-button" data-btn-next>
                            <span data-next-label>{{ $isLocked ? 'Selanjutnya' : ($questionsPayload->count() > 1 ? 'Lanjutkan' : 'Kumpulkan Jawaban') }}</span>
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
                    <h2 class="text-2xl font-bold text-slate-900 mb-1" data-score-heading>Skor Anda: 0 / 100</h2>
                    <p class="text-xs text-slate-600 mb-6" data-score-sub>Jawaban Anda telah terkunci secara permanen untuk penilaian integritas.</p>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 max-w-md mx-auto mb-6 text-left">
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                            <small class="text-[10px] text-slate-400 font-bold uppercase">Jawaban Benar</small>
                            <div class="text-lg font-bold text-emerald-600" data-stat-correct>0 / 10</div>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                            <small class="text-[10px] text-slate-400 font-bold uppercase">XP Diperoleh</small>
                            <div class="text-lg font-bold text-blue-600" data-stat-xp>+0 XP</div>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-100 col-span-2 sm:col-span-1">
                            <small class="text-[10px] text-slate-400 font-bold uppercase">Status Tugas</small>
                            <div class="text-xs font-bold text-slate-700 mt-1 flex items-center gap-1">
                                <x-icon name="lock" class="w-3.5 h-3.5 text-amber-500" /> Terkunci
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-center gap-3">
                        <button type="button" class="outline-button" data-btn-review-scorecard>
                            <x-icon name="eye" /> <span>Tinjau Pembahasan Soal</span>
                        </button>
                        <a href="{{ route('challenge') }}" class="challenge-button">
                            <span>Lanjut ke Esai Sokratik</span> <x-icon name="arrow-right" />
                        </a>
                    </div>
                </div>
            </article>
        </div>

        <!-- Right Column: Number Grid & Mode Switcher -->
        <aside class="quiz-side-column" aria-label="Navigasi soal dan materi">
            <section class="panel number-grid-card">
                <div class="section-heading compact-heading">
                    <div>
                        <p class="section-overline">DAFTAR SOAL</p>
                        <h2>Nomor Soal (1–{{ $questionsPayload->count() }})</h2>
                    </div>
                    <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">
                        {{ $isTimed ? 'Timer 60s' : 'Mandiri' }}
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

            <!-- Mode Switcher Card -->
            <section class="panel focus-card">
                <p class="section-overline">2 TANTANGAN BACAAN INI</p>
                <h2 class="text-xs font-bold text-slate-800 mb-2.5">{{ $document->title }}</h2>

                <div class="space-y-2 text-xs">
                    <a href="{{ route('quiz.timed', ['document' => $document->id]) }}" class="flex items-center justify-between p-2.5 rounded-xl border border-indigo-300 bg-indigo-50/80 text-indigo-900 font-bold shadow-xs">
                        <div class="flex items-center gap-2">
                            <x-icon name="gamepad-2" class="w-4 h-4 text-indigo-600" />
                            <span>1. Kuis Interaktif ZEP (10 Soal)</span>
                        </div>
                        <x-icon name="chevron-right" class="w-3.5 h-3.5 text-indigo-500" />
                    </a>

                    <a href="{{ route('challenge') }}" class="flex items-center justify-between p-2.5 rounded-xl border border-slate-100 bg-slate-50/60 text-slate-700 hover:bg-slate-100 font-medium">
                        <div class="flex items-center gap-2">
                            <x-icon name="circle-help" class="w-4 h-4 text-purple-500" />
                            <span>2. Esai Sokratik (5 Soal)</span>
                        </div>
                        <x-icon name="chevron-right" class="w-3.5 h-3.5 text-slate-400" />
                    </a>
                </div>
            </section>
        </aside>
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
    let timerSeconds = 60;
    let timerInterval = null;
    let totalTimeSpent = 0;
    let totalTimeInterval = null;

    // Gamification & Streak State
    let currentStreak = 0;
    let soundEnabled = localStorage.getItem('SR_QUIZ_SOUND') !== 'false';
    let selectedCharacter = localStorage.getItem('SR_QUIZ_CHAR') || 'socrates';

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

    const timerBadge = root.querySelector('[data-timer-badge]');
    const timerSecEl = root.querySelector('[data-timer-seconds]');
    const timerBar = root.querySelector('[data-timer-bar]');

    const scorecard = root.querySelector('[data-scorecard]');
    const quizContentArea = root.querySelector('[data-quiz-body]');
    const quizFooter = root.querySelector('.quiz-card-footer');

    // Pixel Stage Elements
    const spriteWrapper = root.querySelector('[data-sprite-wrapper]');
    const speechText = root.querySelector('[data-speech-text]');
    const streakBadge = root.querySelector('[data-streak-badge]');
    const streakText = root.querySelector('[data-streak-text]');
    const soundToggle = root.querySelector('[data-sound-toggle]');
    const soundIcon = root.querySelector('[data-sound-icon]');
    const soundLabel = root.querySelector('[data-sound-label]');
    const charButtons = root.querySelectorAll('[data-select-char]');
    const charAvatars = root.querySelectorAll('[data-char-avatar]');

    // Sound Synthesizer via Web Audio API (Zero audio assets, ultra-lightweight)
    let audioCtx = null;
    const initAudio = () => {
        if (!audioCtx) {
            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        }
        if (audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
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
            } else if (type === 'correct') {
                // Cheerful ascending triad
                [523.25, 659.25, 783.99].forEach((freq, idx) => {
                    const noteOsc = audioCtx.createOscillator();
                    const noteGain = audioCtx.createGain();
                    noteOsc.connect(noteGain);
                    noteGain.connect(audioCtx.destination);
                    noteOsc.type = 'triangle';
                    noteOsc.frequency.setValueAtTime(freq, now + idx * 0.09);
                    noteGain.gain.setValueAtTime(0.2, now + idx * 0.09);
                    noteGain.gain.exponentialRampToValueAtTime(0.001, now + idx * 0.09 + 0.22);
                    noteOsc.start(now + idx * 0.09);
                    noteOsc.stop(now + idx * 0.09 + 0.22);
                });
            } else if (type === 'wrong') {
                // Gentle arcade buzz
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(220, now);
                osc.frequency.linearRampToValueAtTime(140, now + 0.25);
                gain.gain.setValueAtTime(0.18, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.25);
                osc.start(now);
                osc.stop(now + 0.25);
            } else if (type === 'tick') {
                osc.type = 'square';
                osc.frequency.setValueAtTime(800, now);
                gain.gain.setValueAtTime(0.08, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.04);
                osc.start(now);
                osc.stop(now + 0.04);
            } else if (type === 'victory') {
                // Fanfare melody
                const notes = [523.25, 523.25, 523.25, 659.25, 783.99, 1046.50];
                const times = [0, 0.1, 0.2, 0.35, 0.5, 0.7];
                const lens = [0.08, 0.08, 0.08, 0.12, 0.18, 0.45];
                notes.forEach((freq, idx) => {
                    const noteOsc = audioCtx.createOscillator();
                    const noteGain = audioCtx.createGain();
                    noteOsc.connect(noteGain);
                    noteGain.connect(audioCtx.destination);
                    noteOsc.type = 'triangle';
                    noteOsc.frequency.setValueAtTime(freq, now + times[idx]);
                    noteGain.gain.setValueAtTime(0.22, now + times[idx]);
                    noteGain.gain.exponentialRampToValueAtTime(0.001, now + times[idx] + lens[idx]);
                    noteOsc.start(now + times[idx]);
                    noteOsc.stop(now + times[idx] + lens[idx]);
                });
            }
        } catch (_) {}
    };

    // Confetti Engine (Vanilla Canvas)
    const confettiCanvas = document.getElementById('confetti-canvas');
    let confettiCtx = confettiCanvas.getContext('2d');
    let particles = [];
    let confettiAnimationId = null;

    const resizeCanvas = () => {
        confettiCanvas.width = window.innerWidth;
        confettiCanvas.height = window.innerHeight;
    };
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();

    const triggerConfetti = (count = 50) => {
        const colors = ['#ef4444', '#3b82f6', '#f59e0b', '#10b981', '#8b5cf6', '#ec4899'];
        for (let i = 0; i < count; i++) {
            particles.push({
                x: window.innerWidth * (0.3 + Math.random() * 0.4),
                y: window.innerHeight * 0.6,
                w: 8 + Math.random() * 8,
                h: 8 + Math.random() * 6,
                color: colors[Math.floor(Math.random() * colors.length)],
                vx: (Math.random() - 0.5) * 14,
                vy: -Math.random() * 16 - 6,
                rot: Math.random() * 360,
                rotSpeed: (Math.random() - 0.5) * 10,
                opacity: 1,
            });
        }
        if (!confettiAnimationId) {
            animateConfetti();
        }
    };

    const animateConfetti = () => {
        confettiCtx.clearRect(0, 0, confettiCanvas.width, confettiCanvas.height);
        particles.forEach((p, index) => {
            p.x += p.vx;
            p.y += p.vy;
            p.vy += 0.45; // gravity
            p.rot += p.rotSpeed;
            p.opacity -= 0.012;

            confettiCtx.save();
            confettiCtx.translate(p.x, p.y);
            confettiCtx.rotate((p.rot * Math.PI) / 180);
            confettiCtx.globalAlpha = Math.max(0, p.opacity);
            confettiCtx.fillStyle = p.color;
            confettiCtx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
            confettiCtx.restore();

            if (p.opacity <= 0 || p.y > window.innerHeight) {
                particles.splice(index, 1);
            }
        });

        if (particles.length > 0) {
            confettiAnimationId = requestAnimationFrame(animateConfetti);
        } else {
            confettiAnimationId = null;
            confettiCtx.clearRect(0, 0, confettiCanvas.width, confettiCanvas.height);
        }
    };

    // Pixel Character Dialogue & Animations
    const setSpriteState = (state) => {
        spriteWrapper.className = `pixel-sprite-wrapper is-${state}`;
        if (state !== 'idle') {
            setTimeout(() => {
                if (spriteWrapper.classList.contains(`is-${state}`)) {
                    spriteWrapper.className = 'pixel-sprite-wrapper is-idle';
                }
            }, 1400);
        }
    };

    const setSpeech = (text) => {
        speechText.textContent = text;
    };

    const idleQuotes = [
        "Baca soalnya dengan teliti ya, jangan terburu-buru!",
        "Gunakan nalarmu, jawaban yang tepat pasti ada!",
        "Pertanyaan ini menguji pemahaman konsep dasarmu ✨",
        "Kamu pasti bisa! Pilih jawaban yang paling logis.",
    ];

    const pickIdleQuote = () => {
        const quote = idleQuotes[Math.floor(Math.random() * idleQuotes.length)];
        setSpeech(quote);
    };

    // Character Selector
    const applyCharacter = (charName) => {
        selectedCharacter = charName;
        localStorage.setItem('SR_QUIZ_CHAR', charName);
        charButtons.forEach(btn => btn.classList.toggle('is-active', btn.dataset.selectChar === charName));
        charAvatars.forEach(av => av.classList.toggle('hidden', av.dataset.charAvatar !== charName));
    };

    charButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            playSfx('click');
            applyCharacter(btn.dataset.selectChar);
            setSpriteState('cheering');
            setSpeech(`Karakter ${btn.textContent.trim()} siap menemanimu belajar! 🚀`);
        });
    });
    applyCharacter(selectedCharacter);

    // Sound Toggle Button
    const updateSoundUI = () => {
        soundIcon.textContent = soundEnabled ? '🔊' : '🔇';
        soundLabel.textContent = soundEnabled ? 'Suara ON' : 'Bisu';
    };
    if (soundToggle) {
        soundToggle.addEventListener('click', () => {
            soundEnabled = !soundEnabled;
            localStorage.setItem('SR_QUIZ_SOUND', String(soundEnabled));
            updateSoundUI();
            if (soundEnabled) playSfx('click');
        });
        updateSoundUI();
    }

    // Start total session time tracker if active
    if (!isLocked) {
        totalTimeInterval = setInterval(() => { totalTimeSpent++; }, 1000);
    }

    const startQuestionTimer = () => {
        if (!isTimed || isLocked) return;
        clearInterval(timerInterval);
        timerSeconds = 60;
        if (timerSecEl) timerSecEl.textContent = '60';
        if (timerBar) {
            timerBar.style.width = '100%';
            timerBar.classList.remove('is-urgent');
        }

        timerInterval = setInterval(() => {
            timerSeconds--;
            if (timerSecEl) timerSecEl.textContent = String(Math.max(0, timerSeconds));

            if (timerBar) {
                const pct = Math.max(0, Math.round((timerSeconds / 60) * 100));
                timerBar.style.width = `${pct}%`;
                if (timerSeconds <= 10) {
                    timerBar.classList.add('is-urgent');
                    setSpriteState('hurry');
                    setSpeech("Waktu tinggal 10 detik lagi! Segera tentukan pilihanmu! ⏰");
                }
            }

            if (timerSeconds <= 5 && timerSeconds > 0) {
                playSfx('tick');
            }

            if (timerSeconds <= 0) {
                clearInterval(timerInterval);
                playSfx('wrong');
                setSpriteState('shaking');
                setSpeech("Waktu habis untuk nomor ini! Otomatis beralih ke soal berikutnya...");
                handleNextOrSubmit(true);
            }
        }, 1000);
    };

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

    const renderQuestion = (idx) => {
        current = idx;
        const q = questions[current];
        if (!q) return;

        qCounter.textContent = `SOAL ${current + 1} DARI ${questions.length}`;
        qText.textContent = q.question;
        pickIdleQuote();

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

            btn.innerHTML = `
                ${badgeHtml}
                <span class="zep-card-text">${opt.text}</span>
            `;

            if (!isLocked) {
                btn.addEventListener('click', () => {
                    playSfx('click');
                    optionsList.querySelectorAll('.zep-card').forEach(el => {
                        el.classList.remove('is-selected');
                        el.setAttribute('aria-checked', 'false');
                    });
                    btn.classList.add('is-selected');
                    btn.setAttribute('aria-checked', 'true');
                    savedAnswers[String(q.id)] = opt.key;

                    setSpriteState('cheering');
                    setSpeech(`Kamu memilih opsi ${opt.key}! Klik tombol 'Lanjutkan' untuk nomor berikutnya.`);
                    updateNumberGridUI();
                });
            }

            optionsList.appendChild(btn);
        });

        // Explanation in locked / review mode
        if (isLocked && q.explanation) {
            explanationBox.classList.remove('hidden');
            explanationText.textContent = q.explanation;
        } else {
            explanationBox.classList.add('hidden');
        }

        // Prev & Next Buttons
        btnPrev.hidden = (!isLocked && isTimed) || current === 0;

        if (isLocked) {
            nextLabel.textContent = current === questions.length - 1 ? 'Selesai Meninjau' : 'Selanjutnya';
        } else {
            nextLabel.textContent = current === questions.length - 1 ? 'Kumpulkan Jawaban' : 'Lanjutkan';
        }

        updateNumberGridUI();

        // Restart timer if active timed test
        if (isTimed && !isLocked) {
            startQuestionTimer();
        }
    };

    const handleNextOrSubmit = async (forcedByTimer = false) => {
        if (isLocked) {
            if (current >= questions.length - 1) {
                window.location.href = "{{ route('dashboard') }}";
                return;
            }
            renderQuestion(current + 1);
            return;
        }

        if (current >= questions.length - 1) {
            // Submit quiz
            clearInterval(timerInterval);
            clearInterval(totalTimeInterval);
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

                // Attach feedback to local questions array
                if (result.feedback) {
                    questions.forEach(q => {
                        const fb = result.feedback[q.id];
                        if (fb) {
                            q.correct_answer = fb.correct_answer;
                            q.explanation = fb.explanation;
                        }
                    });
                }

                // Show scorecard
                quizContentArea.classList.add('hidden');
                quizFooter.classList.add('hidden');
                if (timerBadge) timerBadge.classList.add('hidden');
                if (timerBar) timerBar.parentElement.classList.add('hidden');

                scorecard.classList.remove('hidden');
                scorecard.querySelector('[data-score-heading]').textContent = `Skor Anda: ${result.score} / 100`;
                scorecard.querySelector('[data-stat-correct]').textContent = `${result.correct_count} / ${result.total_questions}`;
                scorecard.querySelector('[data-stat-xp]').textContent = `+${result.xp_awarded} XP`;

                // Victory Celebrations
                playSfx('victory');
                triggerConfetti(90);
                setSpriteState('cheering');
                setSpeech(`Luar biasa! Kamu menyelesaikan kuis dengan skor ${result.score}! 🎉`);

                updateNumberGridUI();
            } catch (error) {
                alert(error.message);
                btnNext.disabled = false;
                nextLabel.textContent = 'Kumpulkan Jawaban';
            }
            return;
        }

        // Advance to next question
        renderQuestion(current + 1);
    };

    btnNext.addEventListener('click', () => handleNextOrSubmit(false));
    btnPrev.addEventListener('click', () => {
        if (current > 0) {
            renderQuestion(current - 1);
        }
    });

    numberBoxes.forEach((box, idx) => {
        box.addEventListener('click', () => {
            if (!isTimed || isLocked) {
                renderQuestion(idx);
            }
        });
    });

    const btnReviewScorecard = root.querySelector('[data-btn-review-scorecard]');
    if (btnReviewScorecard) {
        btnReviewScorecard.addEventListener('click', () => {
            scorecard.classList.add('hidden');
            quizContentArea.classList.remove('hidden');
            quizFooter.classList.remove('hidden');
            renderQuestion(0);
        });
    }

    // Initial render
    renderQuestion(0);
})();
</script>
@endpush
