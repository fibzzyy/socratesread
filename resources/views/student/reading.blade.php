@extends('layouts.student')

@section('title', 'Reading Room')
@section('breadcrumb', 'Reading Room')

@php
    $formatter = app(\App\Services\ReadingMaterialFormatter::class);
    $formattedBody = $formatter->format((string) $document->body, (string) $document->title);
    $formattedBodyHtml = $formattedBodyHtml ?? $formatter->toHtml($formattedBody);
    $bookPagesHtml = $bookPagesHtml ?? $formatter->splitIntoPages($formattedBody);
    $coachHistory = $coachHistory ?? collect();

    $hasTeacherChallenges = $document->teacherChallenges()->exists();
    $readingElapsedSeconds = $session->started_at ? (int) $session->started_at->diffInSeconds(now()) : 0;
    $isChallengesUnlocked = $session->isCompleted() || $readingElapsedSeconds >= 120;
    $remainingLockSeconds = max(0, 120 - $readingElapsedSeconds);
    $initialProgress = $session->isCompleted() ? 100 : (int) $session->progress_percent;
@endphp

@section('content')
    <div class="reading-book-page">
    @if (session('notice')) <div class="auth-notice" role="status">{{ session('notice') }}</div> @endif
    <section class="stage-heading reading-book-header" aria-labelledby="reading-title">
        <div>
            <p class="eyebrow">RUANG FOKUSMU</p>
            <h1 id="reading-title">Reading Room</h1>
            <p class="welcome-subtitle">Baca dengan tenang. Temukan makna di balik setiap paragraf.</p>
        </div>
        <a class="back-link" href="{{ route('dashboard') }}">
            <x-icon name="arrow-left" /> <span>Kembali ke dashboard</span>
        </a>
    </section>

    <div class="reading-room-grid reading-workspace-grid">
        <article class="panel passage-panel reading-document-workspace" aria-labelledby="passage-title">
            <div class="passage-toolbar flex-wrap">
                <div class="passage-tags">
                    <span class="category-tag tag-blue">{{ mb_strtoupper($document->category) }}</span>
                    <span class="reading-time"><x-icon name="clock" /> {{ $document->estimated_minutes }} menit membaca</span>
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <div class="reading-mode-switch" role="group" aria-label="Mode tampilan membaca">
                        <button type="button" class="reading-mode-btn is-active" data-reading-mode-btn="scroll" aria-pressed="true">
                            <svg class="reading-mode-glyph" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="2" /><path d="M9 8h6M9 12h6M9 16h4" /></svg>
                            <span>Gulir</span>
                        </button>
                        <button type="button" class="reading-mode-btn" data-reading-mode-btn="book" aria-pressed="false">
                            <x-icon name="book-open-text" /> <span>Halaman</span>
                        </button>
                    </div>
                    <div class="text-controls" aria-label="Pengaturan teks">
                        <span>Ukuran teks</span>
                        <button type="button" data-font-step="-1" aria-label="Kecilkan ukuran teks">A−</button>
                        <button type="button" data-font-step="1" aria-label="Besarkan ukuran teks">A+</button>
                    </div>
                </div>
            </div>

            <div class="passage-content reading-canvas" data-reading-text>
                <p class="section-overline">{{ mb_strtoupper($document->category) }} · BACAAN PILIHAN</p>
                <h2 id="passage-title">{{ $document->title }}</h2>
                @php
                    $excerptClean = trim((string) $document->excerpt);
                    $plainBodySample = mb_strtolower(preg_replace('/\s+/u', ' ', strip_tags($formattedBodyHtml)) ?? '');
                    $excerptSample = mb_strtolower(mb_substr(preg_replace('/\s+/u', ' ', $excerptClean) ?? '', 0, 40));
                    $isDuplicateLede = $excerptClean === ''
                        || mb_strtolower($excerptClean) === mb_strtolower(trim((string) $document->title))
                        || ($excerptSample !== '' && str_contains($plainBodySample, $excerptSample));
                @endphp
                @if (! $isDuplicateLede)
                    <p class="passage-lede">{!! \Illuminate\Support\Str::inlineMarkdown(e($excerptClean)) !!}</p>
                @endif

                    <!-- Versi 1: Mode Gulir Vertikal (Seluruh materi terstruktur utuh) -->
                    <div class="passage-markdown" data-scroll-reader>
                        {!! $formattedBodyHtml !!}
                    </div>

                    <!-- Versi 2: Mode Buka Halaman Kertas (Paginasi Buku + Swipe Mobile) -->
                    <div class="book-page-stage hidden" data-page-reader>
                        <div class="book-page-sheet passage-markdown" data-book-sheet></div>
                        <div class="book-page-nav">
                            <button type="button" class="outline-button" data-book-prev disabled>
                                <x-icon name="arrow-left" /> <span>Halaman Sebelumnya</span>
                            </button>
                            <span class="text-xs font-bold text-slate-600" data-book-indicator>Halaman 1 / {{ max(1, count($bookPagesHtml)) }}</span>
                            <button type="button" class="outline-button" data-book-next>
                                <span>Halaman Berikutnya</span> <x-icon name="arrow-right" />
                            </button>
                        </div>
                    </div>

                    <div class="passage-note">
                        <x-icon name="lightbulb" />
                        <span>Saat membaca, cari kalimat yang menjadi <strong>bukti</strong> untuk setiap gagasan penting.</span>
                    </div>
                </div>
            </div>

            <div class="passage-bottom">
                <span class="read-progress-label">
                    <span class="read-progress-dot"></span> Progres membaca <strong data-read-progress>{{ $initialProgress }}%</strong>
                </span>
                <div class="progress-track">
                    <span data-bottom-progress-bar style="width:{{ $initialProgress }}%"></span>
                </div>
                @if ($session->isCompleted())
                    <button type="button" class="outline-button is-complete" disabled data-complete-reading data-session-id="{{ $session->id }}" data-progress-url="{{ route('reading.progress') }}">
                        <span data-complete-label>Tugas Selesai & Terkunci</span>
                        <x-icon name="check" />
                    </button>
                @else
                    <button type="button" class="outline-button" data-complete-reading data-session-id="{{ $session->id }}" data-progress-url="{{ route('reading.progress') }}">
                        <span data-complete-label>Selesai membaca</span>
                        <x-icon name="check" />
                    </button>
                @endif
            </div>
        </article>

        <aside class="reading-side-column reading-companion-panel" aria-label="Panduan membaca">
            <section class="panel session-card" data-session-card>
                <div class="session-head">
                    <div class="session-icon"><x-icon name="book-open-text" /></div>
                    <div>
                        <p class="section-overline">DISKUSI SOCRATIC</p>
                        <h2 id="coach-title">Tanya Socrates</h2>
                    </div>
                    <span class="coach-status"><i></i> Tutor AI</span>
                </div>

                <!-- Tips Membaca Aktif Terintegrasi -->
                <div class="bg-indigo-50/70 rounded-xl p-2.5 border border-indigo-100/80 mb-3 text-[11px] text-indigo-950 flex items-start gap-2">
                    <x-icon name="lightbulb" class="w-4 h-4 text-indigo-600 shrink-0 mt-0.5" />
                    <div class="space-y-0.5">
                        <span class="font-bold text-indigo-900">Tips Membaca Aktif:</span>
                        <p class="text-indigo-800/90 text-[10.5px] leading-tight">Tanyakan gagasan utama, cari kalimat buktinya, dan uji sudut pandang baru dengan Socrates.</p>
                    </div>
                </div>

                <div class="coach-transcript" data-coach-transcript aria-live="polite" aria-label="Percakapan dengan tutor">
                    <p class="coach-message coach-message-assistant">Halo! Bagian mana dari bacaan ini yang ingin kamu pahami lebih dalam?</p>
                    @foreach ($coachHistory as $msg)
                        <p class="coach-message coach-message-{{ $msg->role === 'user' ? 'user' : 'assistant' }}">{{ $msg->content }}</p>
                    @endforeach
                </div>
                <div class="coach-prompts" aria-label="Contoh pertanyaan">
                    <button type="button" data-coach-prompt="Apa gagasan utama bacaan ini?">Gagasan utama</button>
                    <button type="button" data-coach-prompt="Bukti apa yang mendukung ide penting dalam bacaan?">Cari bukti</button>
                    <button type="button" data-coach-prompt="Adakah sudut pandang lain atau makna mendalam dari bacaan ini?">Sudut pandang</button>
                </div>
                <form class="coach-form" data-coach-form>
                    <label class="sr-only" for="coach-message">Tulis pertanyaan atau pemikiranmu</label>
                    <textarea id="coach-message" name="message" rows="2" maxlength="{{ config('ai.limits.message_characters', 1200) }}" placeholder="Tulis pertanyaan atau pemikiranmu…" required data-coach-input></textarea>
                    <div class="coach-form-footer">
                        <span data-coach-count>0 / {{ config('ai.limits.message_characters', 1200) }}</span>
                        <button type="submit" class="challenge-button" data-coach-send>
                            <span data-coach-send-label>Kirim</span><x-icon name="arrow-right" />
                        </button>
                    </div>
                </form>
                <p class="coach-error" role="alert" data-coach-error hidden></p>
                <p class="coach-footnote"><x-icon name="shield-check" /> AI membantu kamu berpikir; periksa kembali jawabannya dengan bacaan.</p>
            </section>

            <!-- 3. TANTANGAN BACAAN (Langkah Evaluasi Setelah Membaca) -->
            <section class="panel session-card" data-challenge-card>
                <div class="session-head">
                    <div class="session-icon text-amber-600 bg-amber-50"><x-icon name="award" /></div>
                    <div>
                        <p class="section-overline">EVALUASI PEMAHAMAN</p>
                        <h2>Tantangan Bacaan</h2>
                    </div>
                </div>

                <!-- Primary CTA -->
                <div data-primary-cta-wrapper class="mt-3">
                    @if ($session->isCompleted())
                        <a class="challenge-button session-cta !bg-emerald-600 hover:!bg-emerald-700" href="{{ $hasTeacherChallenges ? route('student.teacher-challenges.show', ['document' => $document->id]) : route('quiz.timed', ['document' => $document->id]) }}">
                            <x-icon name="lock" class="size-3.5" /> <span>Lihat hasil jawaban</span>
                        </a>
                    @elseif ($isChallengesUnlocked)
                        @if ($hasTeacherChallenges)
                            <a class="challenge-button session-cta" href="{{ route('student.teacher-challenges.show', ['document' => $document->id]) }}" id="btnTeacherChallengeCta">
                                Lanjut ke Tantangan Esai Guru <x-icon name="arrow-right" />
                            </a>
                        @else
                            <a class="challenge-button session-cta" href="{{ route('quiz.timed', ['document' => $document->id]) }}">
                                <span>Buka Tantangan Socrates</span> <x-icon name="arrow-right" />
                            </a>
                        @endif
                    @else
                        <div id="tcLockedBox"
                             class="p-3.5 rounded-2xl bg-amber-50/90 border border-amber-200 text-xs text-amber-950 space-y-1.5 shadow-2xs"
                             data-challenges-lock
                             data-remaining="{{ $remainingLockSeconds }}"
                             data-has-teacher-challenges="{{ $hasTeacherChallenges ? '1' : '0' }}"
                             data-quiz-url="{{ route('quiz.timed', ['document' => $document->id]) }}"
                             data-quiz-count="{{ $document->quiz_question_count ?? 10 }}"
                             data-quiz-minutes="{{ $document->quiz_duration_minutes ?? 15 }}"
                             data-tc-url="{{ route('student.teacher-challenges.show', ['document' => $document->id]) }}">
                            <div class="flex items-center gap-2 font-bold text-amber-900">
                                <x-icon name="lock" class="w-4 h-4 text-amber-600 shrink-0" />
                                <span>Tantangan Bacaan Terkunci</span>
                            </div>
                            <p class="text-[11px] text-amber-800 leading-normal">
                                Tantangan Socrates & Tantangan Guru terbuka otomatis setelah membaca minimal 2 menit atau menekan Selesai Membaca.
                            </p>
                            <div class="font-mono font-extrabold text-xs text-amber-800 bg-white/90 py-1.5 px-2 rounded-xl border border-amber-200 text-center">
                                ⏱️ Sisa waktu membaca: <span id="tcCountdownDisplay">02:00</span>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Daftar Tantangan Bacaan (Keduanya terkunci 2 menit hingga timer habis / bacaan selesai) -->
                <div class="mt-4 pt-3 border-t border-slate-100 space-y-2" data-challenge-items-box>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">DAFTAR TANTANGAN</p>

                    <div data-quiz-item-slot>
                        @if ($isChallengesUnlocked)
                            <a class="flex items-center justify-between p-2.5 rounded-xl border border-indigo-200 bg-indigo-50/80 hover:bg-indigo-100 text-indigo-950 text-xs font-bold transition shadow-xs" href="{{ route('quiz.timed', ['document' => $document->id]) }}">
                                <div class="flex items-center gap-2">
                                    <x-icon name="gamepad-2" class="w-4 h-4 text-indigo-600 shrink-0" />
                                    <span>1. Tantangan Socrates (PG · {{ $document->quiz_question_count ?? 10 }} Soal · {{ $document->quiz_duration_minutes ?? 15 }}m)</span>
                                </div>
                                <x-icon name="arrow-right" class="w-3.5 h-3.5 text-indigo-500 shrink-0" />
                            </a>
                        @else
                            <div id="quizItemLocked" class="flex items-center justify-between p-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-500 text-xs font-semibold">
                                <div class="flex items-center gap-2">
                                    <x-icon name="lock" class="w-4 h-4 text-slate-400 shrink-0" />
                                    <span>1. Tantangan Socrates (Terbuka setelah 2m)</span>
                                </div>
                                <span id="quizItemCountdown" class="text-[10px] font-mono font-bold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded">02:00</span>
                            </div>
                        @endif
                    </div>

                    <div data-tc-item-slot>
                        @if (! $hasTeacherChallenges)
                            <div class="flex items-center justify-between p-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-500 text-xs font-semibold">
                                <div class="flex items-center gap-2">
                                    <x-icon name="info" class="w-4 h-4 text-slate-400 shrink-0" />
                                    <span>2. Tantangan Guru (Tidak ada tantangan dari guru)</span>
                                </div>
                            </div>
                        @elseif ($isChallengesUnlocked)
                            <a id="tcItemLink" class="flex items-center justify-between p-2.5 rounded-xl border border-amber-300 bg-amber-50/90 hover:bg-amber-100 text-amber-950 text-xs font-bold transition shadow-xs" href="{{ route('student.teacher-challenges.show', ['document' => $document->id]) }}">
                                <div class="flex items-center gap-2">
                                    <x-icon name="award" class="w-4 h-4 text-amber-600 shrink-0" />
                                    <span>2. Tantangan Guru (Analisis · Total 100 Poin)</span>
                                </div>
                                <x-icon name="arrow-right" class="w-3.5 h-3.5 text-amber-600 shrink-0" />
                            </a>
                        @else
                            <div id="tcItemLocked" class="flex items-center justify-between p-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-500 text-xs font-semibold">
                                <div class="flex items-center gap-2">
                                    <x-icon name="lock" class="w-4 h-4 text-slate-400 shrink-0" />
                                    <span>2. Tantangan Guru (Terbuka setelah 2m)</span>
                                </div>
                                <span id="tcItemCountdown" class="text-[10px] font-mono font-bold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded">02:00</span>
                            </div>
                        @endif
                    </div>
                </div>
            </section>
            <div class="privacy-note"><x-icon name="shield-check" /><span>Waktumu untuk membaca. Fokusmu yang utama.</span></div>
        </aside>
    </div>
    <div class="toast-message" role="status" aria-live="polite" data-reading-toast></div>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const text = document.querySelector('[data-reading-text]');
    let fontSize = 15;
    document.querySelectorAll('[data-font-step]').forEach((button) => button.addEventListener('click', () => {
        fontSize = Math.min(20, Math.max(13, fontSize + Number(button.dataset.fontStep)));
        text.style.setProperty('--passage-font-size', `${fontSize}px`);
    }));

    const completeBtn = document.querySelector('[data-complete-reading]');
    const sessionId = Number(completeBtn.dataset.sessionId);
    const progressUrl = completeBtn.dataset.progressUrl;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    const readProgressEl = document.querySelector('[data-read-progress]');
    const bottomProgressBar = document.querySelector('[data-bottom-progress-bar]');
    const sessionStatusLabel = document.querySelector('[data-session-status-label]');
    const sessionCompletedRow = document.querySelector('[data-session-completed-row]');
    const sessionCompletedTime = document.querySelector('[data-session-completed-time]');
    const sessionProgressBar = document.querySelector('[data-session-progress-bar]');
    const sessionProgressText = document.querySelector('[data-session-progress-text]');
    const toastEl = document.querySelector('[data-reading-toast]');

    let isSessionCompleted = {{ $session->isCompleted() ? 'true' : 'false' }};
    let highestProgress = {{ $initialProgress }};
    let lastSyncedProgress = highestProgress;
    let syncTimeout = null;

    const updateProgressUI = (pct) => {
        const clamped = Math.max(0, Math.min(100, Math.round(pct)));
        if (clamped < highestProgress && !isSessionCompleted) return;
        highestProgress = isSessionCompleted ? 100 : Math.max(highestProgress, clamped);

        if (readProgressEl) readProgressEl.textContent = `${highestProgress}%`;
        if (bottomProgressBar) bottomProgressBar.style.width = `${highestProgress}%`;
        if (sessionProgressBar) sessionProgressBar.style.width = `${highestProgress}%`;
        if (sessionProgressText) sessionProgressText.textContent = `${highestProgress}%`;
        if (sessionStatusLabel && !isSessionCompleted) {
            sessionStatusLabel.textContent = `${highestProgress}% dibaca`;
        }
    };

    const syncProgressToServer = async (targetPct) => {
        if (isSessionCompleted) return;
        const safePct = Math.min(99, Math.max(0, Math.round(targetPct)));
        if (safePct <= lastSyncedProgress) return;
        lastSyncedProgress = safePct;
        try {
            await fetch(progressUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ session_id: sessionId, progress: safePct }),
            });
        } catch (_) {}
    };

    const scheduleSyncProgress = (pct) => {
        if (isSessionCompleted) return;
        const capped = Math.min(99, Math.round(pct));
        if (capped - lastSyncedProgress >= 5) {
            clearTimeout(syncTimeout);
            syncTimeout = setTimeout(() => syncProgressToServer(capped), 400);
        }
    };

    // 2 Mode Baca: Gulir Vertikal vs Buka Halaman Kertas
    const bookPages = @json($bookPagesHtml);
    const modeButtons = document.querySelectorAll('[data-reading-mode-btn]');
    const scrollContainer = document.querySelector('[data-reading-scroll-container]');
    const scrollReader = document.querySelector('[data-scroll-reader]');
    const pageReader = document.querySelector('[data-page-reader]');
    const bookSheet = document.querySelector('[data-book-sheet]');
    const bookPrevBtn = document.querySelector('[data-book-prev]');
    const bookNextBtn = document.querySelector('[data-book-next]');
    const bookIndicator = document.querySelector('[data-book-indicator]');

    let readingMode = localStorage.getItem('SR_READING_MODE') || 'scroll';
    let currentBookPage = 0;

    const renderBookPage = (pageIdx, animate = true) => {
        if (!bookPages.length) return;
        currentBookPage = Math.max(0, Math.min(bookPages.length - 1, pageIdx));
        if (animate && bookSheet) {
            bookSheet.classList.add('is-turning');
            setTimeout(() => bookSheet.classList.remove('is-turning'), 160);
        }
        bookSheet.innerHTML = `
            ${bookPages[currentBookPage]}
            <div class="book-page-footer-num">— Halaman ${currentBookPage + 1} dari ${bookPages.length} —</div>
        `;
        bookIndicator.textContent = `Halaman ${currentBookPage + 1} / ${bookPages.length}`;
        bookPrevBtn.disabled = currentBookPage === 0;
        bookNextBtn.disabled = currentBookPage >= bookPages.length - 1;

        if (!isSessionCompleted) {
            const pagePct = bookPages.length <= 1
                ? 99
                : Math.min(99, Math.max(10, Math.round(((currentBookPage + 1) / bookPages.length) * 99)));
            updateProgressUI(pagePct);
            scheduleSyncProgress(pagePct);
        }
    };

    const setReadingMode = (mode) => {
        readingMode = mode === 'book' ? 'book' : 'scroll';
        localStorage.setItem('SR_READING_MODE', readingMode);
        modeButtons.forEach((btn) => {
            const active = btn.dataset.readingModeBtn === readingMode;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', String(active));
        });
        if (readingMode === 'book') {
            scrollReader.classList.add('hidden');
            pageReader.classList.remove('hidden');
            scrollContainer?.classList.remove('is-mode-scroll');
            scrollContainer?.classList.add('is-mode-book');
            renderBookPage(currentBookPage, false);
        } else {
            pageReader.classList.add('hidden');
            scrollReader.classList.remove('hidden');
            scrollContainer?.classList.remove('is-mode-book');
            scrollContainer?.classList.add('is-mode-scroll');
        }
    };

    window.SocratesReading = {
        getMode: () => readingMode,
        setMode: setReadingMode,
        getPage: () => currentBookPage,
        setPage: renderBookPage,
        findPageForParagraph: (paraNum) => {
            const pInt = parseInt(paraNum, 10);
            if (isNaN(pInt) || !bookPages || !bookPages.length) return 0;
            const targetPattern = new RegExp(`id=["']para-${pInt}["']|data-paragraph-index=["']${pInt}["']`, 'i');
            const foundIdx = bookPages.findIndex((html) => targetPattern.test(html));
            return foundIdx >= 0 ? foundIdx : 0;
        }
    };

    modeButtons.forEach((btn) => btn.addEventListener('click', () => setReadingMode(btn.dataset.readingModeBtn)));
    bookPrevBtn?.addEventListener('click', () => {
        if (currentBookPage > 0) renderBookPage(currentBookPage - 1);
    });
    bookNextBtn?.addEventListener('click', () => {
        if (currentBookPage < bookPages.length - 1) renderBookPage(currentBookPage + 1);
    });

    // Touch Swipe pada layar HP untuk Mode Halaman Kertas
    if (bookSheet) {
        let touchStartX = 0;
        let touchStartY = 0;
        bookSheet.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
            touchStartY = e.changedTouches[0].screenY;
        }, { passive: true });
        bookSheet.addEventListener('touchend', (e) => {
            const dx = e.changedTouches[0].screenX - touchStartX;
            const dy = e.changedTouches[0].screenY - touchStartY;
            if (Math.abs(dx) > 48 && Math.abs(dx) > Math.abs(dy) * 1.3) {
                if (dx < 0 && currentBookPage < bookPages.length - 1) renderBookPage(currentBookPage + 1);
                if (dx > 0 && currentBookPage > 0) renderBookPage(currentBookPage - 1);
            }
        }, { passive: true });
    }

    // Real-time Scroll Progress Tracker pada Mode Gulir (mendukung scroll container internal & window)
    const handleScrollProgress = () => {
        if (isSessionCompleted || readingMode !== 'scroll') return;
        if (scrollContainer && scrollContainer.scrollHeight > scrollContainer.clientHeight) {
            const maxScroll = scrollContainer.scrollHeight - scrollContainer.clientHeight;
            const pct = Math.min(99, Math.max(5, Math.round((scrollContainer.scrollTop / maxScroll) * 99)));
            if (pct > highestProgress) {
                updateProgressUI(pct);
                scheduleSyncProgress(pct);
            }
        } else if (scrollReader) {
            const rect = scrollReader.getBoundingClientRect();
            const scrollTop = window.scrollY || document.documentElement.scrollTop;
            const startY = rect.top + scrollTop - window.innerHeight * 0.45;
            const totalReadable = Math.max(200, scrollReader.offsetHeight - window.innerHeight * 0.45);
            const pct = Math.min(99, Math.max(5, Math.round(((scrollTop - startY) / totalReadable) * 99)));
            if (pct > highestProgress) {
                updateProgressUI(pct);
                scheduleSyncProgress(pct);
            }
        }
    };
    scrollContainer?.addEventListener('scroll', handleScrollProgress, { passive: true });
    window.addEventListener('scroll', handleScrollProgress, { passive: true });
    setReadingMode(readingMode);

    // 2-Minute Lock Countdown & Auto-Unlock untuk Challenge Socrates DAN Tantangan Guru
    const lockBox = document.querySelector('[data-challenges-lock]');
    let lockTimer = null;

    const unlockReadingChallenges = () => {
        if (lockTimer) clearInterval(lockTimer);
        const primaryWrapper = document.querySelector('[data-primary-cta-wrapper]');
        const quizSlot = document.querySelector('[data-quiz-item-slot]');
        const tcSlot = document.querySelector('[data-tc-item-slot]');

        const lockDataEl = document.querySelector('[data-challenges-lock]');
        const hasTeacher = lockDataEl ? lockDataEl.dataset.hasTeacherChallenges === '1' : {{ $hasTeacherChallenges ? 'true' : 'false' }};
        const quizUrl = lockDataEl ? lockDataEl.dataset.quizUrl : "{{ route('quiz.timed', ['document' => $document->id]) }}";
        const quizCount = lockDataEl ? lockDataEl.dataset.quizCount : "{{ $document->quiz_question_count ?? 10 }}";
        const quizMinutes = lockDataEl ? lockDataEl.dataset.quizMinutes : "{{ $document->quiz_duration_minutes ?? 15 }}";
        const tcUrl = lockDataEl ? lockDataEl.dataset.tcUrl : "{{ route('student.teacher-challenges.show', ['document' => $document->id]) }}";

        if (primaryWrapper) {
            if (isSessionCompleted) {
                primaryWrapper.innerHTML = `
                    <a class="challenge-button session-cta !bg-emerald-600 hover:!bg-emerald-700" href="${hasTeacher ? tcUrl : quizUrl}">
                        <span>Lihat hasil jawaban</span>
                    </a>
                `;
            } else if (hasTeacher) {
                primaryWrapper.innerHTML = `
                    <a class="challenge-button session-cta animate-scale-in" href="${tcUrl}" id="btnTeacherChallengeCta">
                        Lanjut ke Tantangan Esai Guru <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                `;
            } else {
                primaryWrapper.innerHTML = `
                    <a class="challenge-button session-cta animate-scale-in" href="${quizUrl}">
                        <span>Buka Tantangan Socrates</span> <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                `;
            }
        }

        if (quizSlot) {
            quizSlot.innerHTML = `
                <a class="flex items-center justify-between p-2.5 rounded-xl border border-indigo-200 bg-indigo-50/80 hover:bg-indigo-100 text-indigo-950 text-xs font-bold transition shadow-xs animate-scale-in" href="${quizUrl}">
                    <div class="flex items-center gap-2">
                        <span>🎮</span>
                        <span>1. Tantangan Socrates (PG · ${quizCount} Soal · ${quizMinutes}m)</span>
                    </div>
                    <span class="text-indigo-500 font-bold">→</span>
                </a>
            `;
        }

        if (tcSlot && hasTeacher) {
            tcSlot.innerHTML = `
                <a class="flex items-center justify-between p-2.5 rounded-xl border border-amber-300 bg-amber-50/90 hover:bg-amber-100 text-amber-950 text-xs font-bold transition shadow-xs animate-scale-in" href="${tcUrl}">
                    <div class="flex items-center gap-2">
                        <span>🏆</span>
                        <span>2. Tantangan Guru (Analisis · Total 100 Poin)</span>
                    </div>
                    <span class="text-amber-600 font-bold">→</span>
                </a>
            `;
        }
    };

    if (lockBox && !isSessionCompleted) {
        let remaining = parseInt(lockBox.dataset.remaining || '120', 10);
        const countdownEl = document.getElementById('tcCountdownDisplay');
        const quizCountdownPill = document.getElementById('quizItemCountdown');
        const tcCountdownPill = document.getElementById('tcItemCountdown');

        const formatMinutes = (secs) => {
            const m = Math.floor(secs / 60);
            const s = secs % 60;
            return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
        };

        const tick = () => {
            const formatted = formatMinutes(Math.max(0, remaining));
            if (countdownEl) countdownEl.textContent = formatted;
            if (quizCountdownPill) quizCountdownPill.textContent = formatted;
            if (tcCountdownPill) tcCountdownPill.textContent = formatted;

            if (remaining <= 0) {
                unlockReadingChallenges();
                return;
            }
            remaining--;
        };

        tick();
        lockTimer = setInterval(tick, 1000);
    }

    // Tombol Selesai Membaca -> Tugas Selesai & Terkunci (Update DOM Real-time Tanpa Refresh)
    completeBtn?.addEventListener('click', async () => {
        if (completeBtn.disabled) return;
        completeBtn.disabled = true;
        let result = null;
        try {
            const response = await fetch(progressUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ session_id: sessionId, progress: 100 }),
            });
            if (!response.ok) throw new Error('Progres belum berhasil disimpan. Coba lagi.');
            result = await response.json();
        } catch (error) {
            if (toastEl) toastEl.textContent = error.message;
            completeBtn.disabled = false;
            return;
        }

        isSessionCompleted = true;
        highestProgress = 100;
        completeBtn.querySelector('[data-complete-label]').textContent = 'Tugas Selesai & Terkunci';
        completeBtn.classList.add('is-complete');
        updateProgressUI(100);

        if (sessionStatusLabel) sessionStatusLabel.textContent = 'Selesai & Terkunci';
        if (sessionCompletedRow && sessionCompletedTime) {
            const nowTime = result?.completed_at || new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false });
            sessionCompletedTime.textContent = nowTime;
            sessionCompletedRow.classList.remove('hidden');
        }

        unlockReadingChallenges();

        if (toastEl) {
            toastEl.textContent = result?.xp_awarded
                ? `Bacaan selesai. +${result.xp_awarded} XP untukmu!`
                : 'Bacaan selesai & terkunci. Tantangan sudah terbuka!';
            window.setTimeout(() => { toastEl.textContent = ''; }, 3500);
        }
    });
})();
</script>
@endpush
