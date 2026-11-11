<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#102f50">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard SocratesRead</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="dashboard-page">
    @php($userInitials = collect(explode(' ', auth()->user()->name))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode(''))
    <div class="app-shell">
        <button class="scrim" type="button" aria-label="Tutup navigasi" data-menu-close></button>
        <aside class="sidebar sidebar-theme" id="primary-navigation" aria-label="Navigasi utama">
            <a class="brand" href="{{ route('dashboard') }}" aria-label="SocratesRead, beranda"><img class="brand-logo" src="{{ asset('images/socratesread-logo.jpeg') }}" alt=""><span class="brand-name">socrates<span>read</span><small>LEARN · THINK · GROW</small></span></a>

            <div class="nav-label">MENU UTAMA</div>
            <nav class="nav-list" aria-label="Menu utama">
                <a class="nav-link is-active" href="{{ route('dashboard') }}" aria-current="page"><x-icon name="layout-dashboard" /><span>Dashboard</span></a>
                <a class="nav-link" href="{{ route('reading') }}"><x-icon name="book-open-text" /><span>Reading Room</span></a>
                <a class="nav-link {{ request()->routeIs('classrooms.*') ? 'is-active' : '' }}" href="{{ route('classrooms.index') }}"><x-icon name="users" /><span>My Class</span></a>
                <a class="nav-link {{ request()->routeIs('settings.profile', 'profile') ? 'is-active' : '' }}" href="{{ route('profile') }}"><x-icon name="user" /><span>Profil</span></a>
            </nav>

            <div class="nav-label nav-label-spaced">BELAJAR</div>
            <nav class="nav-list" aria-label="Materi belajar">
                <a class="nav-link" href="{{ route('quiz.index') }}"><x-icon name="gamepad-2" /><span>Challenge Socrates</span></a>
                <a class="nav-link" href="{{ route('progress') }}"><x-icon name="chart-no-axes-combined" /><span>Progress</span></a>
                <a class="nav-link" href="{{ route('achievements') }}"><x-icon name="award" /><span>Pencapaian</span></a>
            </nav>

            <div class="sidebar-spacer"></div>
            <div class="sidebar-tip">
                <span class="tip-icon" aria-hidden="true"><x-icon name="lightbulb" /></span>
                <strong>Berpikir lebih dalam</strong>
                <p>Setiap pertanyaan adalah langkah menuju pemahaman baru.</p>
                <a href="{{ $activeSession ? route('challenge') : route('reading') }}">{{ $activeSession ? 'Mulai tantangan' : 'Pilih bacaan' }} <x-icon name="arrow-right" /></a>
            </div>
            <div class="profile-button"><a href="{{ route('profile') }}" class="avatar avatar-sidebar avatar-link" title="Profil">@if (auth()->user()->avatarUrl())<img src="{{ auth()->user()->avatarUrl() }}" alt="{{ auth()->user()->name }}" class="avatar-image">@else{{ mb_strtoupper($userInitials) }}@endif</a><span class="profile-copy"><strong>{{ auth()->user()->name }}</strong><small>Siswa SocratesRead</small></span><form method="POST" action="{{ route('logout') }}">@csrf<button class="logout-button" type="submit">Keluar</button></form></div>
        </aside>

        <main class="main-content" id="dashboard">
            <header class="topbar">
                <button class="icon-button menu-toggle" type="button" aria-label="Buka navigasi" aria-controls="primary-navigation" aria-expanded="false" data-menu-toggle><x-icon name="menu" /></button>
                <div class="breadcrumb"><strong>Dashboard Siswa</strong></div>
                <div class="topbar-actions">
                    <span class="today-label">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span>
                    <button class="icon-button notification-button" type="button" aria-label="Notifikasi"><x-icon name="bell" /><i></i></button>
                    <a href="{{ route('profile') }}" class="avatar avatar-top avatar-link" aria-label="Profil {{ auth()->user()->name }}" title="Profil">@if (auth()->user()->avatarUrl())<img src="{{ auth()->user()->avatarUrl() }}" alt="{{ auth()->user()->name }}" class="avatar-image">@else{{ mb_strtoupper($userInitials) }}@endif</a>
                </div>
            </header>

            <div class="page-content">
                @if (session('notice')) <div class="auth-notice" role="status">{{ session('notice') }}</div> @endif
                <section class="welcome-row" aria-labelledby="welcome-title">
                    <div><p class="eyebrow">{{ now()->locale('id')->translatedFormat('l, d F Y') }} <span class="eyebrow-dot">·</span> SIAP BELAJAR?</p><h1 id="welcome-title"><span id="greetingText" class="text-blue-600">{{ $greeting ?? 'Selamat malam' }}</span>, {{ auth()->user()->name }} <span class="welcome-dot" aria-hidden="true">·</span></h1><p class="welcome-subtitle">Pikiran hebat tumbuh dari pertanyaan yang tepat.</p></div>
                    <div class="welcome-actions flex items-center gap-2 flex-wrap">
                        <!-- Dropdown Pilihan Tingkat Kelas -->
                        <div class="relative filter-dropdown-container" id="classFilterContainer">
                            <button class="date-filter" id="classFilterButton" type="button" aria-haspopup="true" aria-expanded="false" title="Pilih Tingkat Kelas">
                                <x-icon name="graduation-cap" />
                                <span id="classFilterLabel">{{ $selectedGrade === 'all' ? 'Semua Kelas' : 'Kelas ' . $selectedGrade }}</span>
                                <x-icon name="chevron-down" class="filter-chevron" />
                            </button>

                            <div id="classFilterMenu" class="filter-dropdown-menu hidden absolute right-0 sm:right-auto sm:left-0 top-[calc(100%+6px)] z-50 w-52 rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl transition-all" role="menu">
                                <div class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                    Pilih Tingkat Kelas
                                </div>

                                <a href="{{ route('dashboard', ['grade' => 'all']) }}" class="flex w-full items-center justify-between rounded-lg px-2.5 py-2 text-left text-xs font-medium text-slate-700 hover:bg-slate-50 transition {{ $selectedGrade === 'all' ? 'bg-blue-50/70 text-blue-700 font-semibold' : '' }}">
                                    <span class="flex items-center gap-2">
                                        <span class="size-1.5 rounded-full bg-slate-300"></span>
                                        <span>Semua Kelas</span>
                                    </span>
                                    @if ($selectedGrade === 'all')
                                        <span class="text-blue-600 text-xs font-bold">✓</span>
                                    @endif
                                </a>

                                <div class="my-1 border-t border-slate-100"></div>

                                @foreach ([10, 11, 12] as $g)
                                    @php($isPrior = $user->isPriorGrade($g))
                                    @php($isSelected = (string) $selectedGrade === (string) $g)
                                    <a href="{{ route('dashboard', ['grade' => $g]) }}" class="flex w-full items-center justify-between rounded-lg px-2.5 py-2 text-left text-xs font-medium text-slate-700 hover:bg-blue-50/70 hover:text-blue-700 transition {{ $isSelected ? 'bg-blue-50/70 text-blue-700 font-semibold' : '' }}">
                                        <span class="flex items-center gap-2">
                                            <span class="size-1.5 rounded-full {{ $g === 10 ? 'bg-blue-500' : ($g === 11 ? 'bg-indigo-500' : 'bg-violet-500') }}"></span>
                                            <span>Kelas {{ $g }}</span>
                                            @if ($isPrior)
                                                <span class="text-[10px] text-slate-400 font-normal">(Kosong)</span>
                                            @endif
                                        </span>
                                        @if ($isSelected)
                                            <span class="text-blue-600 text-xs font-bold">✓</span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>

                        <!-- Dropdown Periode (Minggu ini) -->
                        <div class="relative filter-dropdown-container" id="periodFilterContainer">
                            <button class="date-filter" id="periodFilterButton" type="button" aria-haspopup="true" aria-expanded="false" title="Pilih Rentang Waktu">
                                <x-icon name="calendar-days" />
                                <span id="periodFilterLabel">Minggu ini</span>
                                <x-icon name="chevron-down" class="filter-chevron" />
                            </button>

                            <div id="periodFilterMenu" class="filter-dropdown-menu hidden absolute right-0 top-[calc(100%+6px)] z-50 w-44 rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl transition-all" role="menu">
                                <div class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                    Rentang Waktu
                                </div>
                                <button type="button" class="period-option flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-left text-xs font-medium text-slate-700 hover:bg-blue-50/70 hover:text-blue-700 transition" data-value="today" data-label="Hari ini">
                                    <span>Hari ini</span>
                                    <span class="check-mark hidden text-blue-600 text-xs font-bold">✓</span>
                                </button>
                                <button type="button" class="period-option flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-left text-xs font-medium text-slate-700 hover:bg-blue-50/70 hover:text-blue-700 transition bg-blue-50/70 text-blue-700" data-value="week" data-label="Minggu ini">
                                    <span>Minggu ini</span>
                                    <span class="check-mark text-blue-600 text-xs font-bold">✓</span>
                                </button>
                                <button type="button" class="period-option flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-left text-xs font-medium text-slate-700 hover:bg-blue-50/70 hover:text-blue-700 transition" data-value="month" data-label="Bulan ini">
                                    <span>Bulan ini</span>
                                    <span class="check-mark hidden text-blue-600 text-xs font-bold">✓</span>
                                </button>
                                <button type="button" class="period-option flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-left text-xs font-medium text-slate-700 hover:bg-blue-50/70 hover:text-blue-700 transition" data-value="semester" data-label="Semester ini">
                                    <span>Semester ini</span>
                                    <span class="check-mark hidden text-blue-600 text-xs font-bold">✓</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                @if ($isPriorGrade)
                    <section class="panel mb-6 border-2 border-dashed border-slate-200 bg-white/90 p-7 text-center rounded-2xl shadow-sm" aria-label="Informasi tingkat kelas">
                        <div class="mx-auto mb-3 flex size-12 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                            <x-icon name="book-open-text" class="size-6" />
                        </div>
                        <h2 class="text-base font-bold text-slate-800 font-display">
                            Belum Ada Riwayat di Kelas {{ $selectedGrade }}
                        </h2>
                        <p class="mx-auto mt-1.5 max-w-md text-xs leading-5 text-slate-600">
                            Akun Anda mulai terdaftar di SocratesRead pada jenjang <strong>Kelas {{ $user->entry_grade ?? 10 }}</strong>. Seluruh aktivitas membaca dan materi belajar Anda dimulai dari jenjang tersebut.
                        </p>
                        <div class="mt-4 flex items-center justify-center gap-2">
                            <a href="{{ route('dashboard', ['grade' => $user->currentGrade() ?? ($user->entry_grade ?? 10)]) }}" class="challenge-button inline-flex items-center gap-2 text-xs font-semibold">
                                <span>Beralih ke Kelas {{ $user->currentGrade() ?? ($user->entry_grade ?? 10) }} (Tingkat Aktifmu)</span>
                                <x-icon name="arrow-right" class="size-3.5" />
                            </a>
                        </div>
                    </section>
                @endif

                <section class="hero-card" aria-label="Tantangan membaca hari ini">
                    <div class="hero-copy"><span class="hero-kicker"><span class="sparkle" aria-hidden="true"><x-icon name="target" /></span> MISI HARI INI</span><h2>Baca dengan rasa ingin tahu.<br><span>Pikirkan lebih jauh.</span></h2><p>Satu bacaan, satu pertanyaan hebat, satu langkah lebih dekat pada pemahaman.</p><a class="hero-cta" href="#bacaan">Lanjutkan perjalanan <x-icon name="arrow-right" /></a></div>
                    <div class="hero-art" aria-hidden="true"><img class="burhan-owl" src="{{ asset('images/burhan.png') }}" alt=""></div>
                </section>

                <section class="stats-grid" aria-label="Ringkasan belajar">
                    <article class="stat-card"><div class="stat-top"><span class="stat-icon icon-blue"><x-icon name="book-open-text" /></span><span class="stat-trend trend-neutral">{{ $weeklyReadingCount }} minggu ini</span></div><p class="stat-label">Bacaan diselesaikan</p><div class="stat-value">{{ $completedReadings }} <span>bacaan</span></div><div class="mini-progress"><span style="width:{{ min(100, $weeklyReadingCount * 20) }}%"></span></div><p class="stat-foot">Target mingguan: {{ min(5, $weeklyReadingCount) }} dari 5 bacaan</p></article>
                    <article class="stat-card"><div class="stat-top"><span class="stat-icon icon-violet"><x-icon name="zap" /></span><span class="stat-trend trend-up">Level {{ $summary['level'] }}</span></div><p class="stat-label">Total pengalaman</p><div class="stat-value">{{ number_format($summary['xp']) }} <span>XP</span></div><div class="mini-progress violet-progress"><span style="width:{{ $summary['level_progress'] }}%"></span></div><p class="stat-foot">{{ $summary['xp_to_next_level'] }} XP menuju Level {{ $summary['level'] + 1 }}</p></article>
                    <article class="stat-card"><div class="stat-top"><span class="stat-icon icon-orange"><x-icon name="flame" /></span><span class="stat-trend trend-neutral">Terpanjang {{ $summary['longest_streak'] }} hari</span></div><p class="stat-label">Streak membaca</p><div class="stat-value">{{ $summary['current_streak'] }} <span>hari</span></div><div class="streak-dots" aria-label="Aktivitas membaca tujuh hari terakhir">@foreach ($weeklyActivity as $day)<i class="{{ $day['events'] ? 'filled' : '' }}" title="{{ $day['date']->locale('id')->translatedFormat('l') }}: {{ $day['events'] }} aktivitas">{{ mb_substr($day['label'], 0, 1) }}</i>@endforeach</div><p class="stat-foot">Satu hari membaca menjaga ritmemu</p></article>
                    <article class="stat-card"><div class="stat-top"><span class="stat-icon icon-teal"><x-icon name="award" /></span><span class="stat-trend trend-neutral">{{ $completedChallenges }} selesai</span></div><p class="stat-label">Tantangan dituntaskan</p><div class="stat-value">{{ $completedChallenges }} <span>tantangan</span></div><div class="mini-progress"><span style="width:{{ min(100, $completedChallenges * 10) }}%"></span></div><p class="stat-foot">Setiap jawaban melatih cara berpikir</p></article>
                </section>

                <div class="content-grid">
                    <section class="panel reading-panel scroll-mt-6" id="bacaan" aria-labelledby="reading-heading">
                        <div class="section-heading"><div><p class="section-overline">LANJUTKAN DARI SINI</p><h2 id="reading-heading">Bacaan untukmu</h2></div><a class="text-link" href="{{ route('reading') }}">Buka Reading Room <x-icon name="arrow-right" /></a></div>
                        @forelse ($readingDocuments as $document)
                            @php($session = $document->readingSessions->first())
                            @php($isSessionCompleted = $session?->status === 'completed')
                            @php($readingPercent = $isSessionCompleted ? 100 : ($session?->progress_percent ?? 0))
                            <article class="reading-item"><div class="book-icon {{ $isSessionCompleted ? '!text-emerald-700 !bg-emerald-50' : '' }}" aria-hidden="true"><x-icon name="{{ $isSessionCompleted ? 'check-circle' : 'book-open-text' }}" /></div><div class="reading-details"><div class="reading-meta"><span class="category-tag">{{ mb_strtoupper($document->category) }}</span>@if ($document->target_grade && $document->target_grade !== 'all')<span class="category-tag border-blue-200 bg-blue-50 text-blue-700">Kelas {{ $document->target_grade }}</span>@endif @if ($isSessionCompleted)<span class="category-tag border-emerald-200 bg-emerald-50 text-emerald-700">✓ Selesai & Terkunci</span>@endif<span class="reading-time"><x-icon name="clock" /> {{ $document->estimated_minutes }} menit</span></div><h3>{{ $document->title }}</h3><p>{{ $document->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($document->body), 110) }}</p><div class="reading-progress"><div class="progress-track"><span style="width:{{ $readingPercent }}%"></span></div><span>{{ $isSessionCompleted ? 'Tuntas' : ($readingPercent ? $readingPercent.'%' : 'Baru') }}</span></div></div><a class="continue-button {{ $isSessionCompleted ? '!text-emerald-700 !bg-emerald-50 !border-emerald-200' : '' }}" href="{{ route('reading', ['document' => $document->id]) }}" aria-label="{{ $isSessionCompleted ? 'Lihat bacaan ' : ($readingPercent ? 'Lanjutkan membaca ' : 'Mulai membaca ') }}{{ $document->title }}"><x-icon name="{{ $isSessionCompleted ? 'check' : 'chevron-right' }}" /></a></article>
                        @empty
                            <div class="teacher-empty-state">
                                <span class="session-icon"><x-icon name="book-open-text" /></span>
                                @if ($isPriorGrade)
                                    <strong>Tidak ada materi di Kelas {{ $selectedGrade }}</strong>
                                    <p>Anda terdaftar mulai Kelas {{ $user->entry_grade ?? 10 }}. Beralihlah ke tingkat aktif Anda untuk melanjutkan membaca.</p>
                                @elseif (! $hasEnrolledClass)
                                    <strong>Belum bergabung ke Kelas {{ $selectedGrade }}</strong>
                                    <p>Materi dan progres kelas akan muncul setelah kamu bergabung kembali ke kelas.</p>
                                @else
                                    <strong>Belum ada bacaan tersedia</strong>
                                    <p>Bacaan baru akan muncul di sini setelah guru menerbitkan materi.</p>
                                @endif
                            </div>
                        @endforelse
                    </section>

                    <div class="right-column">
                        <section class="panel challenge-panel scroll-mt-6" id="tantangan" aria-labelledby="challenge-heading"><div class="section-heading compact-heading"><div><p class="section-overline">ASAH RASA INGIN TAHU</p><h2 id="challenge-heading">Tantanganmu</h2></div><span class="challenge-mark" aria-hidden="true"><x-icon name="circle-help" /></span></div>
                            @if ($isPriorGrade)
                                <div class="challenge-question"><span class="question-label">TINGKAT SEBELUM PENDAFTARAN</span><p>Tantangan Socratic Anda aktif mulai Kelas {{ $user->entry_grade ?? 10 }}.</p></div><div class="challenge-footer"><span class="challenge-reward"><strong>Halaman Kosong</strong></span><a class="challenge-button" href="{{ route('dashboard', ['grade' => $user->currentGrade() ?? ($user->entry_grade ?? 10)]) }}">Beralih ke Kelas {{ $user->currentGrade() ?? ($user->entry_grade ?? 10) }} <x-icon name="arrow-right" /></a></div>
                            @elseif ($challengeQuestion && $activeSession)
                                <div class="challenge-question"><span class="question-label">{{ $activeSession->document->title }}</span><p>{{ $challengeQuestion->prompt }}</p></div>
                                <div class="grid grid-cols-2 gap-2 mt-3 pt-2.5 border-t border-slate-100">
                                    <a class="flex items-center justify-center gap-1.5 p-2 rounded-lg bg-blue-50 text-blue-700 font-bold text-[11px] hover:bg-blue-100 transition" href="{{ route('quiz.timed', ['document' => $activeSession->document_id]) }}">
                                        <x-icon name="timer" class="w-3.5 h-3.5" /> <span>Kuis Timer (PG)</span>
                                    </a>
                                    <a class="flex items-center justify-center gap-1.5 p-2 rounded-lg bg-purple-50 text-purple-700 font-bold text-[11px] hover:bg-purple-100 transition" href="{{ route('challenge') }}">
                                        <x-icon name="circle-help" class="w-3.5 h-3.5" /> <span>Esai Sokratik</span>
                                    </a>
                                </div>
                                <div class="challenge-footer"><div class="challenge-reward"><span class="reward-icon"><x-icon name="zap" /></span><span><strong>+25 XP</strong><small>Kuis & tantangan</small></span></div><a class="challenge-button" href="{{ route('quiz.timed', ['document' => $activeSession->document_id]) }}">Mulai Kuis <x-icon name="arrow-right" /></a></div>
                            @elseif ($activeSession)
                                <div class="challenge-question"><span class="question-label">PERTANYAAN BELUM TERSEDIA</span><p>Bacaan ini belum memiliki pertanyaan Socratic. Pilih bacaan lain atau tanyakan kepada gurumu.</p></div><div class="challenge-footer"><span class="challenge-reward"><strong>Materi sedang disiapkan</strong></span><a class="challenge-button" href="#bacaan">Pilih bacaan <x-icon name="arrow-right" /></a></div>
                            @elseif (! $hasEnrolledClass)
                                <div class="challenge-question"><span class="question-label">BELUM BERGABUNG KELAS</span><p>Tantangan dan progres akan tersedia setelah kamu bergabung ke kelas.</p></div><div class="challenge-footer"><span class="challenge-reward"><strong>Kelas diperlukan</strong></span><a class="challenge-button" href="{{ route('classrooms.index') }}">Gabung kelas <x-icon name="arrow-right" /></a></div>
                            @else
                                <div class="challenge-question"><span class="question-label">MULAI DARI BACAAN</span><p>Pilih bacaan dan temukan pertanyaan yang membantumu melihat isi teks lebih dalam.</p></div><div class="challenge-footer"><span class="challenge-reward"><strong>Pertanyaan Socratic</strong></span><a class="challenge-button" href="{{ route('reading') }}">Buka bacaan <x-icon name="arrow-right" /></a></div>
                            @endif
                        </section>

                        <section class="panel activity-panel scroll-mt-6" id="progress" aria-labelledby="activity-heading"><div class="section-heading compact-heading"><div><p class="section-overline">JEJAK PERJALANANMU</p><h2 id="activity-heading">Aktivitas terbaru</h2></div><a class="more-button" href="{{ route('progress') }}" aria-label="Lihat semua aktivitas"><x-icon name="arrow-right" /></a></div><div class="recent-activity-list">@forelse ($recentActivities as $activity)<article class="recent-activity-row"><span class="recent-activity-icon"><x-icon name="{{ $activity->event_type === 'reading_completed' ? 'book-open-text' : 'award' }}" /></span><div><strong>{{ $activity->summary }}</strong><small>{{ $activity->readingSession?->document?->title ?? 'Sesi membaca' }} · {{ $activity->created_at->locale('id')->diffForHumans() }}</small></div><span class="recent-activity-xp">{{ $activity->xp_awarded ? '+'.$activity->xp_awarded.' XP' : 'Tercatat' }}</span></article>@empty<div class="activity-empty">Selesaikan bacaan atau tantangan untuk mulai mencatat perjalanan belajarmu.</div>@endforelse</div></section>
                    </div>
                </div>
                <footer class="page-footer"><span>Terus bertanya, terus bertumbuh.</span><span>© 2026 SocratesRead</span></footer>
            </div>
        </main>
    </div>
    <script>
        (() => {
            const shell = document.querySelector('.app-shell');
            const toggle = document.querySelector('[data-menu-toggle]');
            const close = document.querySelector('[data-menu-close]');
            const setOpen = (open) => {
                shell.classList.toggle('menu-open', open);
                toggle.setAttribute('aria-expanded', String(open));
                document.body.classList.toggle('menu-locked', open);
            };
            toggle.addEventListener('click', () => setOpen(!shell.classList.contains('menu-open')));
            close.addEventListener('click', () => setOpen(false));
            document.querySelectorAll('.nav-link').forEach((link) => link.addEventListener('click', () => {
                document.querySelector('.nav-link.is-active')?.classList.remove('is-active');
                link.classList.add('is-active');
                document.querySelector('.nav-link[aria-current="page"]')?.removeAttribute('aria-current');
                link.setAttribute('aria-current', 'page');
                setOpen(false);
            }));
            document.addEventListener('keydown', (event) => { if (event.key === 'Escape') setOpen(false); });
        })();

        // Sinkronisasi ucapan salam berdasarkan waktu lokal perangkat
        (() => {
            const greetingEl = document.getElementById('greetingText');
            if (!greetingEl) return;
            const hour = new Date().getHours();
            let greeting = 'Selamat malam';
            if (hour >= 4 && hour < 11) greeting = 'Selamat pagi';
            else if (hour >= 11 && hour < 15) greeting = 'Selamat siang';
            else if (hour >= 15 && hour < 18) greeting = 'Selamat sore';
            greetingEl.textContent = greeting;
        })();

        (() => {
            const initFilterDropdown = ({ containerId, buttonId, menuId, labelId, storageKey, optionSelector }) => {
                const container = document.getElementById(containerId);
                const button = document.getElementById(buttonId);
                const menu = document.getElementById(menuId);
                const label = document.getElementById(labelId);
                if (!container || !button || !menu || !label) return;

                const toggleMenu = (forceState) => {
                    const isOpen = forceState !== undefined ? forceState : menu.classList.contains('hidden');
                    if (isOpen) {
                        document.querySelectorAll('.filter-dropdown-menu').forEach((otherMenu) => {
                            if (otherMenu !== menu) {
                                otherMenu.classList.add('hidden');
                                otherMenu.closest('.filter-dropdown-container')?.querySelector('.date-filter')?.setAttribute('aria-expanded', 'false');
                            }
                        });
                        menu.classList.remove('hidden');
                        button.setAttribute('aria-expanded', 'true');
                    } else {
                        menu.classList.add('hidden');
                        button.setAttribute('aria-expanded', 'false');
                    }
                };

                button.addEventListener('click', (e) => {
                    e.stopPropagation();
                    toggleMenu();
                });

                const selectOption = (optLabel, optVal) => {
                    label.textContent = optLabel;
                    if (storageKey) {
                        try {
                            localStorage.setItem(storageKey, JSON.stringify({ label: optLabel, value: optVal }));
                        } catch (_) {}
                    }
                    menu.querySelectorAll(optionSelector).forEach((opt) => {
                        const isMatch = (opt.dataset.label === optLabel) || (opt.dataset.value === optVal);
                        const check = opt.querySelector('.check-mark');
                        if (check) check.classList.toggle('hidden', !isMatch);
                        if (isMatch) {
                            opt.classList.add('bg-blue-50/70', 'text-blue-700');
                        } else {
                            opt.classList.remove('bg-blue-50/70', 'text-blue-700');
                        }
                    });
                };

                menu.querySelectorAll(optionSelector).forEach((opt) => {
                    opt.addEventListener('click', (e) => {
                        e.stopPropagation();
                        selectOption(opt.dataset.label, opt.dataset.value);
                        toggleMenu(false);
                    });
                });

                if (storageKey) {
                    try {
                        const saved = JSON.parse(localStorage.getItem(storageKey));
                        if (saved && (saved.label || saved.value)) {
                            const exists = Array.from(menu.querySelectorAll(optionSelector)).some(
                                (opt) => opt.dataset.label === saved.label || opt.dataset.value === saved.value
                            );
                            if (exists) {
                                selectOption(saved.label, saved.value);
                            }
                        }
                    } catch (_) {}
                }

                document.addEventListener('click', (e) => {
                    if (!container.contains(e.target)) {
                        toggleMenu(false);
                    }
                });

                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') toggleMenu(false);
                });
            };

            // Dropdown Navigasi Tingkat Kelas
            const classBtn = document.getElementById('classFilterButton');
            const classMenu = document.getElementById('classFilterMenu');
            const classContainer = document.getElementById('classFilterContainer');
            if (classBtn && classMenu && classContainer) {
                classBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isOpen = !classMenu.classList.contains('hidden');
                    if (isOpen) {
                        classMenu.classList.add('hidden');
                        classBtn.setAttribute('aria-expanded', 'false');
                    } else {
                        document.querySelectorAll('.filter-dropdown-menu').forEach((m) => m.classList.add('hidden'));
                        document.querySelectorAll('.date-filter').forEach((b) => b.setAttribute('aria-expanded', 'false'));
                        classMenu.classList.remove('hidden');
                        classBtn.setAttribute('aria-expanded', 'true');
                    }
                });

                document.addEventListener('click', (e) => {
                    if (!classContainer.contains(e.target)) {
                        classMenu.classList.add('hidden');
                        classBtn.setAttribute('aria-expanded', 'false');
                    }
                });

                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        classMenu.classList.add('hidden');
                        classBtn.setAttribute('aria-expanded', 'false');
                    }
                });
            }

            // Dropdown Filter Periode Waktu
            initFilterDropdown({
                containerId: 'periodFilterContainer',
                buttonId: 'periodFilterButton',
                menuId: 'periodFilterMenu',
                labelId: 'periodFilterLabel',
                storageKey: 'socrates_selected_period',
                optionSelector: '.period-option'
            });
        })();
    </script>
</body>
</html>
