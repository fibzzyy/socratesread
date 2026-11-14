@extends('layouts.student')

@section('title', $selectedGroup['category'] ?? 'Reading Room')
@section('breadcrumb', $selectedGroup ? 'Reading Room / '.$selectedGroup['category'] : 'Reading Room')
@php
    $readingUserName = auth()->user()->name ?? 'Pengguna';
@endphp

@section('content')
    <header class="reading-topbar">
        <button class="icon-button menu-toggle reading-menu-toggle" type="button" aria-label="Buka navigasi" aria-controls="primary-navigation" aria-expanded="false" data-menu-toggle><x-icon name="menu" /></button>
        <span class="reading-topbar-date">{{ now()->locale('id')->translatedFormat('l, j F Y') }}</span>
        <div class="reading-topbar-actions">
            <span class="reading-stat-chip"><x-icon name="star" /> 0 XP</span>
            <span class="reading-stat-chip"><x-icon name="flame" /> Rutinitas 0 hari</span>
            <x-notification-center />
            <a href="{{ route('profile') }}" class="reading-user" aria-label="Profil {{ $readingUserName }}" title="{{ $readingUserName }}">
                <span class="reading-user-avatar {{ auth()->user()->avatarUrl() ? '' : 'is-empty' }}">@if (auth()->user()->avatarUrl())<img src="{{ auth()->user()->avatarUrl() }}" alt="">@else<x-icon name="user" class="avatar-placeholder-icon" />@endif</span>
                <x-icon name="chevron-down" />
            </a>
        </div>
    </header>
    @if ($selectedGroup)
        @php
            $detailGrade = $selectedDocuments->first()?->target_grade ?? $grade;
            $detailMaterialCount = $selectedDocuments->count();
            $detailProgress = (int) $selectedGroup['progress'];
        @endphp
    @endif
    <div class="reading-layout-container {{ $selectedGroup ? 'reading-detail-layout' : '' }}">
    @if (! $selectedGroup)
        <section class="reading-hero" aria-labelledby="reading-library-title">
            <div class="rh-body">
                <span class="rh-eyebrow">RUANG LITERASI DIGITAL</span>
                <h1 id="reading-library-title">Reading Room</h1>
                <p>Temukan pengetahuan baru dan bangun pemahaman melalui membaca.</p>
                <form class="rh-search" id="reading-material-search-form" role="search" aria-controls="reading-search-results">
                    <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" />
                    </svg>
                    <input type="search" id="reading-material-search" aria-label="Cari materi" autocomplete="off" placeholder="Cari materi atau topik...">
                    <button type="submit" aria-label="Cari materi"><x-icon name="arrow-right" /></button>
                </form>
            </div>
        </section>
    @else
        <a class="reading-detail-back" href="{{ route('reading') }}" data-reading-back>
            <x-icon name="arrow-left" />
            <span>Kembali ke Daftar Kelas</span>
        </a>
        <section class="reading-detail-hero" aria-labelledby="reading-detail-title">
            <span class="reading-detail-eyebrow">DETAIL KELAS</span>
            <div class="reading-detail-hero-main">
                <span class="reading-detail-subject-icon" aria-hidden="true"><x-icon name="book-open-text" /></span>
                <div class="reading-detail-hero-copy">
                    <h2 id="reading-detail-title">{{ $selectedGroup['category'] }}</h2>
                    <div class="reading-detail-hero-meta">
                        <span><x-icon name="user" /> Pengajar: {{ mb_convert_case($selectedGroup['teacher_name'], MB_CASE_TITLE, 'UTF-8') }}</span>
                        <span><x-icon name="graduation-cap" /> {{ $detailGrade === 'all' ? 'Semua kelas' : 'Kelas '.$detailGrade }}</span>
                        <span><x-icon name="library" /> {{ $detailMaterialCount }} materi</span>
                    </div>
                    @if (! empty($selectedGroup['description'] ?? null))
                        <p>{{ $selectedGroup['description'] }}</p>
                    @endif
                </div>
            </div>
            <form class="rh-search reading-detail-search" id="reading-material-search-form" role="search" aria-controls="reading-search-results">
                <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" />
                </svg>
                <input type="search" id="reading-material-search" aria-label="Cari materi" autocomplete="off" placeholder="Cari materi atau topik...">
                <button type="submit" aria-label="Cari materi"><x-icon name="arrow-right" /></button>
            </form>
        </section>
    @endif
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
        @php
            $readingTotal = $groups->count();
            $readingUnread = $groups->where('progress', 0)->count();
            $readingComplete = $groups->filter(fn ($group) => (int) $group['progress'] >= 100)->count();
            $readingInProgress = $readingTotal - $readingUnread - $readingComplete;
        @endphp
        <div class="reading-filter-toolbar">
            <div class="reading-filters" role="group" aria-label="Filter status kelas">
                <button class="reading-filter is-active" type="button" data-reading-filter="semua" aria-pressed="true">Semua <span>{{ $readingTotal }}</span></button>
                <button class="reading-filter" type="button" data-reading-filter="belum-dibaca" aria-pressed="false"><i class="status-dot status-unread"></i>Belum dibaca <span>{{ $readingUnread }}</span></button>
                <button class="reading-filter" type="button" data-reading-filter="sedang-dibaca" aria-pressed="false"><i class="status-dot status-reading"></i>Sedang dibaca <span>{{ $readingInProgress }}</span></button>
                <button class="reading-filter" type="button" data-reading-filter="selesai" aria-pressed="false"><i class="status-dot status-done"></i>Selesai <span>{{ $readingComplete }}</span></button>
            </div>
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
                            $subject = mb_strtolower($group['category']);
                            $subjectIcon = match (true) {
                                str_contains($subject, 'matematika') => 'calculator',
                                str_contains($subject, 'kimia'), str_contains($subject, 'ipa'), str_contains($subject, 'sains') => 'flask-conical',
                                str_contains($subject, 'fisika') => 'atom',
                                str_contains($subject, 'biologi') => 'leaf',
                                str_contains($subject, 'ips'), str_contains($subject, 'geografi') => 'globe',
                                str_contains($subject, 'inggris'), str_contains($subject, 'bahasa') && str_contains($subject, 'asing') => 'languages',
                                str_contains($subject, 'informatika'), str_contains($subject, 'komputer'), str_contains($subject, 'teknologi') => 'monitor',
                                default => 'book-open-text',
                            };
                            $classGrade = $group['documents']->first()?->target_grade ?? $grade;
                        @endphp
                        <a class="reading-class-card" href="{{ route('reading', ['teacher' => $group['teacher_id'], 'category' => $group['category']]) }}" data-search-item data-status="{{ $classStatus }}">
                            <div class="reading-class-top">
                                <span class="reading-class-icon"><x-icon name="{{ $subjectIcon }}" /></span>
                                <span class="reading-status-pill"><i class="status-dot {{ $classStatus === 'belum-dibaca' ? 'status-unread' : ($classStatus === 'selesai' ? 'status-done' : 'status-reading') }}"></i>{{ $classStatus === 'belum-dibaca' ? 'Belum dibaca' : ($classStatus === 'selesai' ? 'Selesai' : 'Sedang dibaca') }}</span>
                            </div>
                            <div class="reading-class-title">
                                <h3>{{ $group['category'] }}</h3>
                                <p><span class="teacher-initial">{{ mb_strtoupper(mb_substr($group['teacher_name'], 0, 1)) }}</span> Pengajar: {{ mb_convert_case($group['teacher_name'], MB_CASE_TITLE, 'UTF-8') }}</p>
                                <div class="reading-class-tags"><span>{{ $classGrade === 'all' ? 'Semua kelas' : 'Kelas '.$classGrade }}</span><span>{{ $group['materials_count'] }} materi</span></div>
                            </div>
                            <div class="reading-class-track" role="progressbar" aria-label="Progres {{ $group['category'] }}" aria-valuenow="{{ $classProgress }}" aria-valuemin="0" aria-valuemax="100">
                                <span style="width:{{ max(2, $classProgress) }}%"></span>
                            </div>
                            <div class="reading-class-footer">
                                <span>{{ $group['completed_count'] }} dari {{ $group['materials_count'] }} materi selesai</span>
                                <span class="reading-class-action" aria-label="Buka kelas"><x-icon name="arrow-right" /></span>
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
        <section class="reading-detail-progress" aria-label="Ringkasan progres kelas">
            <div class="reading-detail-progress-heading">
                <div>
                    <p>PROGRES MEMBACA</p>
                    <h2>{{ $completedCount }} dari {{ $detailMaterialCount }} materi selesai</h2>
                </div>
                <strong>{{ $detailProgress }}%</strong>
            </div>
            <div class="reading-detail-progress-stats">
                <span><strong>{{ $detailMaterialCount }}</strong> Total materi</span>
                <span><strong>{{ $completedCount }}</strong> Selesai</span>
                <span><strong>{{ $detailProgress }}%</strong> Progres</span>
            </div>
            <div class="reading-detail-progress-track" role="progressbar" aria-label="Progres kelas {{ $selectedGroup['category'] }}" aria-valuenow="{{ $detailProgress }}" aria-valuemin="0" aria-valuemax="100"><span style="width:{{ $detailProgress }}%"></span></div>
        </section>

        <section class="reading-detail-materials" aria-labelledby="reading-materials-title">
            <div class="reading-detail-section-heading">
                <div>
                    <p>MATERI DARI GURUMU</p>
                    <h2 id="reading-materials-title">Materi Pembelajaran</h2>
                </div>
                <span>{{ $detailMaterialCount }} materi</span>
            </div>

            <div class="reading-detail-list">
                @forelse ($selectedDocuments as $document)
                    @php($session = $document->student_session)
                    @php($progress = $document->is_completed ? 100 : ($session?->progress_percent ?? 0))
                    @php($readingStatus = $document->is_completed ? 'Selesai' : ($progress > 0 ? 'Sedang dibaca' : 'Belum dibaca'))
                    <article class="reading-item reading-detail-item" data-search-item>
                        <span class="reading-detail-order">{{ $loop->iteration }}</span>
                        <span class="reading-detail-file-icon" aria-hidden="true"><x-icon name="book-open-text" /></span>
                        <div class="reading-detail-copy">
                            <div class="reading-detail-title-row">
                                <span class="reading-detail-order-label">Materi {{ $loop->iteration }}</span>
                                <span class="reading-detail-status {{ $document->is_completed ? 'is-complete' : ($progress > 0 ? 'is-reading' : 'is-unread') }}">@if ($document->is_completed)<x-icon name="check" />@endif{{ $readingStatus }}</span>
                            </div>
                            <h3>{{ $document->title }}</h3>
                            <div class="reading-detail-meta">
                                <span class="reading-detail-category">{{ mb_strtoupper($document->category) }}</span>
                                <span><x-icon name="clock" /> {{ $document->estimated_minutes }} menit baca</span>
                            </div>
                            @if ($document->excerpt || $document->body)
                                <p>{{ $document->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($document->body), 120) }}</p>
                            @endif
                            <div class="reading-detail-item-progress">
                                <div class="reading-detail-progress-track"><span style="width:{{ $progress }}%"></span></div>
                                <span>{{ $document->is_completed ? 'Tuntas' : ($progress ? $progress.'%' : 'Belum dimulai') }}</span>
                            </div>
                        </div>
                        @if ($document->is_completed)
                            <a class="reading-detail-action is-secondary" href="{{ route('reading', ['document' => $document->id]) }}" aria-label="Buka ulang {{ $document->title }}"><span>Buka Materi</span><x-icon name="arrow-right" /></a>
                        @else
                            <a class="reading-detail-action" href="{{ route('reading', ['document' => $document->id]) }}" aria-label="{{ $session ? 'Lanjutkan membaca ' : 'Mulai membaca ' }}{{ $document->title }}"><span>{{ $session ? 'Lanjutkan' : 'Mulai Membaca' }}</span><x-icon name="arrow-right" /></a>
                        @endif
                    </article>
                @empty
                    <div class="reading-detail-empty">
                        <span aria-hidden="true"><x-icon name="book-open-text" /></span>
                        <strong>Belum ada materi tersedia.</strong>
                        <p>Materi pembelajaran akan muncul di sini setelah guru mengunggahnya.</p>
                    </div>
                @endforelse
            </div>
        </section>
    @endif
    </div>
    </div>

    @push('scripts')
        <script>
            (() => {
                const search = document.getElementById('reading-material-search');
                const form = document.getElementById('reading-material-search-form');
                const backLink = document.querySelector('[data-reading-back]');
                backLink?.addEventListener('click', (event) => {
                    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                    if (!document.referrer) return;
                    try {
                        const previous = new URL(document.referrer);
                        if (previous.origin === window.location.origin && previous.pathname === window.location.pathname) {
                            event.preventDefault();
                            window.history.back();
                        }
                    } catch (_) {
                        // Keep the Reading Room list link as a safe fallback.
                    }
                });
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
