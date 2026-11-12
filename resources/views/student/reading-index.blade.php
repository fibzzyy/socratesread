@extends('layouts.student')

@section('title', $selectedGroup['category'] ?? 'Reading Room')
@section('breadcrumb', $selectedGroup ? 'Reading Room / '.$selectedGroup['category'] : 'Reading Room')
@section('head')
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
@endsection

@php
    $readingUserName = auth()->user()->name ?? 'Pengguna';
    $readingInitials = mb_strtoupper(collect(preg_split('/\s+/', trim($readingUserName)))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode(''));
@endphp

@section('content')
    <header class="reading-topbar">
        <button class="icon-button menu-toggle reading-menu-toggle" type="button" aria-label="Buka navigasi" aria-controls="primary-navigation" aria-expanded="false" data-menu-toggle><x-icon name="menu" /></button>
        <span class="reading-topbar-date">{{ now()->locale('id')->translatedFormat('l, j F Y') }}</span>
        <div class="reading-topbar-actions">
            <span class="reading-stat-chip"><x-icon name="star" /> 0 XP</span>
            <span class="reading-stat-chip"><x-icon name="flame" /> Rutinitas 0 hari</span>
            <button class="reading-notification" type="button" aria-label="Notifikasi"><x-icon name="bell" /><i aria-hidden="true"></i></button>
            <a href="{{ route('profile') }}" class="reading-user" aria-label="Profil {{ $readingUserName }}" title="{{ $readingUserName }}">
                <span class="reading-user-avatar">@if (auth()->user()->avatarUrl())<img src="{{ auth()->user()->avatarUrl() }}" alt="">@else{{ $readingInitials }}@endif</span>
                <x-icon name="chevron-down" />
            </a>
        </div>
    </header>
    <div class="reading-layout-container">
    <section class="reading-hero" aria-labelledby="reading-library-title">
        <div class="rh-body">
            <h1 id="reading-library-title">Reading Room</h1>
            <p>Temukan pengetahuan baru, jelajahi berbagai materi, dan tingkatkan wawasanmu di sini.</p>
            <form class="rh-search" id="reading-material-search-form" role="search" aria-controls="reading-search-results">
                <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" />
                </svg>
                <input type="search" id="reading-material-search" aria-label="Cari materi" autocomplete="off" placeholder="Cari materi atau topik">
                <button type="submit" aria-label="Cari bacaan">
                    <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                </button>
            </form>
        </div>
    </section>

    <div class="reading-content">
    @if (session('notice'))
        <div class="auth-notice" role="status">{{ session('notice') }}</div>
    @endif

    <section class="panel reading-panel reading-search-results" id="reading-search-results" aria-labelledby="reading-search-title" aria-live="polite" hidden>
        <div class="section-heading">
            <div>
                <p class="section-overline">HASIL PENCARIAN</p>
                <h2 id="reading-search-title">Bacaan ditemukan</h2>
            </div>
            <span class="document-count" id="reading-search-count"></span>
        </div>
        <div class="reading-search-list">
            @foreach ($groups as $group)
                @foreach ($group['documents'] as $document)
                    <article class="reading-item" data-search-item>
                        <div class="book-icon" aria-hidden="true"><x-icon name="book-open-text" /></div>
                        <div class="reading-details">
                            <div class="reading-meta">
                                <span class="category-tag">{{ mb_strtoupper($document->category) }}</span>
                                <span class="reading-time"><x-icon name="clock" /> {{ $document->estimated_minutes }} menit membaca</span>
                            </div>
                            <h3>{{ $document->title }}</h3>
                            <p>{{ $document->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($document->body), 120) }}</p>
                            <p class="reading-search-class">Kelas {{ $group['category'] }} · {{ $group['teacher_name'] }}</p>
                        </div>
                        <a class="challenge-button" href="{{ route('reading', ['document' => $document->id]) }}" aria-label="Buka bacaan {{ $document->title }}"><span>Baca</span><x-icon name="arrow-right" /></a>
                    </article>
                @endforeach
            @endforeach
        </div>
        <div class="teacher-empty-state reading-search-empty" id="reading-search-empty" hidden>
            <span class="session-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></svg></span>
            <strong>Bacaan tidak ditemukan</strong>
            <p>Coba kata kunci lain. Pencarian mencakup judul bacaan, kelas, dan nama guru.</p>
        </div>
    </section>

    @if (! $selectedGroup)
        <div class="reading-filters" role="group" aria-label="Filter status kelas">
            <button class="reading-filter is-active" type="button" data-reading-filter="semua" aria-pressed="true">Semua</button>
            <button class="reading-filter" type="button" data-reading-filter="belum-dibaca" aria-pressed="false">Belum dibaca</button>
            <button class="reading-filter" type="button" data-reading-filter="sedang-dibaca" aria-pressed="false">Sedang dibaca</button>
            <button class="reading-filter" type="button" data-reading-filter="selesai" aria-pressed="false">Selesai</button>
        </div>
    @endif

    <div data-reading-default-content>
    @if (! $selectedGroup)
        <section class="panel reading-panel" aria-labelledby="reading-groups-title">
            <div class="section-heading">
                <div class="reading-class-heading">
                    <p class="section-overline">MATERI UNTUKMU</p>
                    <h2 id="reading-groups-title">Kelas dan materi</h2>
                    <span class="document-count" id="reading-class-count">{{ $groups->count() }} kelas</span>
                </div>
            </div>

            @if ($groups->isNotEmpty())
                <div class="reading-class-grid">
                    @foreach ($groups as $group)
                        @php
                            $classProgress = (int) $group['progress'];
                            $classStatus = $classProgress === 0 ? 'belum-dibaca' : ($classProgress >= 100 ? 'selesai' : 'sedang-dibaca');
                        @endphp
                        <a class="reading-class-card" href="{{ route('reading', ['teacher' => $group['teacher_id'], 'category' => $group['category']]) }}" data-search-item data-status="{{ $classStatus }}">
                            <div class="reading-class-top">
                                <span class="reading-class-icon"><x-icon name="book-open-text" /></span>
                                <span class="reading-class-progress">{{ $classProgress }}%</span>
                            </div>
                            <div class="reading-class-title">
                                <h3>{{ $group['category'] }}</h3>
                                <p>Pengajar: {{ mb_convert_case($group['teacher_name'], MB_CASE_TITLE, 'UTF-8') }}</p>
                            </div>
                            <div class="reading-class-track" role="progressbar" aria-label="Progres {{ $group['category'] }}" aria-valuenow="{{ $classProgress }}" aria-valuemin="0" aria-valuemax="100">
                                <span style="width:{{ max(2, $classProgress) }}%"></span>
                            </div>
                            <div class="reading-class-footer">
                                <span>{{ $group['completed_count'] }} dari {{ $group['materials_count'] }} materi selesai</span>
                                <span class="reading-class-action">Buka kelas</span>
                            </div>
                        </a>
                    @endforeach
                </div>
                <p class="reading-filter-empty" id="reading-filter-empty" hidden>Belum ada kelas dengan status ini.</p>
            @else
                <div class="reading-empty-state">
                    <strong>Kamu belum bergabung ke kelas mana pun</strong>
                    <p>Gabung kelas lewat menu Kelas saya untuk mulai membaca.</p>
                    <a href="{{ route('classrooms.index') }}">Buka Kelas saya</a>
                </div>
            @endif
        </section>
    @else
        <section class="panel mb-6" aria-label="Ringkasan progres kelas">
            <div class="section-heading">
                <div>
                    <p class="section-overline">PROGRES KELAS</p>
                    <h2>{{ $completedCount }} dari {{ $selectedDocuments->count() }} materi selesai</h2>
                </div>
                <span class="document-count">{{ $selectedGroup['progress'] }}%</span>
            </div>
            <div class="progress-track" role="progressbar" aria-label="Progres kelas {{ $selectedGroup['category'] }}" aria-valuenow="{{ $selectedGroup['progress'] }}" aria-valuemin="0" aria-valuemax="100"><span style="width:{{ $selectedGroup['progress'] }}%"></span></div>
        </section>

        <section class="panel reading-panel" aria-labelledby="reading-materials-title">
            <div class="section-heading">
                <div>
                    <p class="section-overline">MATERI DARI GURUMU</p>
                    <h2 id="reading-materials-title">Daftar materi</h2>
                </div>
                <span class="document-count">{{ $selectedDocuments->count() }} materi</span>
            </div>

            @foreach ($selectedDocuments as $document)
                @php($session = $document->student_session)
                @php($progress = $document->is_completed ? 100 : ($session?->progress_percent ?? 0))
                <article class="reading-item" data-search-item>
                    <div class="book-icon {{ $document->is_completed ? '!text-emerald-700 !bg-emerald-50' : '' }}" aria-hidden="true"><x-icon name="{{ $document->is_completed ? 'check-circle' : 'book-open-text' }}" /></div>
                    <div class="reading-details">
                        <div class="reading-meta">
                            <span class="category-tag">{{ mb_strtoupper($document->category) }}</span>
                            <span class="reading-time"><x-icon name="clock" /> {{ $document->estimated_minutes }} menit membaca</span>
                            @if ($document->is_completed)
                                <span class="category-tag border-emerald-200 bg-emerald-50 text-emerald-700">Selesai</span>
                            @endif
                        </div>
                        <h3>{{ $document->title }}</h3>
                        <p>{{ $document->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($document->body), 120) }}</p>
                        <div class="reading-progress">
                            <div class="progress-track"><span style="width:{{ $progress }}%"></span></div>
                            <span>{{ $document->is_completed ? 'Tuntas' : ($progress ? $progress.'%' : 'Belum dikerjakan') }}</span>
                        </div>
                    </div>
                    @if ($document->is_completed)
                        <a class="challenge-button" href="{{ route('reading', ['document' => $document->id]) }}" aria-label="Buka ulang {{ $document->title }}"><span>Buka</span><x-icon name="arrow-right" /></a>
                    @else
                        <a class="challenge-button" href="{{ route('reading', ['document' => $document->id]) }}" aria-label="{{ $session ? 'Lanjutkan membaca ' : 'Mulai membaca ' }}{{ $document->title }}"><span>{{ $session ? 'Lanjutkan' : 'Mulai' }}</span><x-icon name="arrow-right" /></a>
                    @endif
                </article>
            @endforeach
        </section>
    @endif
    </div>
    </div>

    @push('scripts')
        <script>
            (() => {
                const search = document.getElementById('reading-material-search');
                const form = document.getElementById('reading-material-search-form');
                const results = document.getElementById('reading-search-results');
                const defaultContent = document.querySelector('[data-reading-default-content]');
                const emptyState = document.getElementById('reading-search-empty');
                const resultCount = document.getElementById('reading-search-count');
                if (!search || !form || !results || !defaultContent) return;

                const updateResults = () => {
                    const query = search.value.trim().toLocaleLowerCase('id');
                    const items = [...results.querySelectorAll('[data-search-item]')];

                    if (!query) {
                        results.hidden = true;
                        defaultContent.hidden = false;
                        return;
                    }

                    let matches = 0;
                    items.forEach((item) => {
                        const isMatch = item.textContent.toLocaleLowerCase('id').includes(query);
                        item.hidden = !isMatch;
                        if (isMatch) matches += 1;
                    });

                    results.hidden = false;
                    defaultContent.hidden = true;
                    emptyState.hidden = matches > 0;
                    resultCount.textContent = `${matches} bacaan`;
                };

                search.addEventListener('input', updateResults);
                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    updateResults();
                    search.focus();
                });

                const filterButtons = [...document.querySelectorAll('[data-reading-filter]')];
                const classCards = [...document.querySelectorAll('.reading-class-card[data-status]')];
                const classCount = document.getElementById('reading-class-count');
                const filterEmpty = document.getElementById('reading-filter-empty');
                filterButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        const status = button.dataset.readingFilter;
                        let visibleCount = 0;

                        filterButtons.forEach((filterButton) => {
                            const isActive = filterButton === button;
                            filterButton.classList.toggle('is-active', isActive);
                            filterButton.setAttribute('aria-pressed', String(isActive));
                        });

                        classCards.forEach((card) => {
                            const isVisible = status === 'semua' || card.dataset.status === status;
                            card.hidden = !isVisible;
                            if (isVisible) visibleCount += 1;
                        });

                        if (classCount) classCount.textContent = `${visibleCount} kelas`;
                        if (filterEmpty) filterEmpty.hidden = visibleCount !== 0;
                        if (filterEmpty) filterEmpty.setAttribute('aria-live', 'polite');
                    });
                });
            })();
        </script>
    @endpush
@endsection
