@extends('layouts.student')

@section('title', $selectedGroup['category'] ?? 'Reading Room')
@section('breadcrumb', $selectedGroup ? 'Reading Room / '.$selectedGroup['category'] : 'Reading Room')

@php
    $readingUserName = auth()->user()->name ?? 'Pengguna';
    $readingInitials = mb_strtoupper(collect(preg_split('/\s+/', trim($readingUserName)))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode(''));
@endphp

@section('content')
    <section class="reading-hero" aria-labelledby="reading-library-title">
        <div class="rh-top">
            <div class="rh-crumb-group">
                <button class="icon-button menu-toggle rh-menu-toggle" type="button" aria-label="Buka navigasi" aria-controls="primary-navigation" aria-expanded="false" data-menu-toggle><x-icon name="menu" /></button>
                <nav class="rh-crumb" aria-label="Breadcrumb">
                    <a href="{{ route('dashboard') }}">Ruang belajar</a>
                    <span class="sep">/</span>
                    <strong>Reading Room</strong>
                </nav>
            </div>
            <div class="rh-right">
                <span class="rh-date">{{ now()->locale('id')->translatedFormat('l, j F Y') }}</span>
                <button class="rh-bell" type="button" aria-label="Notifikasi">
                    <x-icon name="bell" />
                    <span class="dot" aria-hidden="true"></span>
                </button>
                <x-profile-dropdown />
            </div>
        </div>
        <div class="rh-body">
            <h1 id="reading-library-title">Reading Room</h1>
            <p>Temukan pengetahuan baru, jelajahi berbagai materi, dan tingkatkan wawasanmu di sini.</p>
            <form class="rh-search" id="reading-material-search-form" role="search" aria-controls="reading-search-results">
                <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" />
                </svg>
                <input type="search" id="reading-material-search" aria-label="Cari materi" autocomplete="off" placeholder="Cari materi, topik, atau kategori...">
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

    <div data-reading-default-content>
    @if (! $selectedGroup)
        <section class="panel reading-panel" aria-labelledby="reading-groups-title">
            <div class="section-heading">
                <div>
                    <p class="section-overline">MATERI UNTUKMU</p>
                    <h2 id="reading-groups-title">Kelas dan materi</h2>
                </div>
                <span class="document-count">{{ $groups->count() }} kelas</span>
            </div>

            @if ($groups->isNotEmpty())
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($groups as $group)
                        <article class="panel grid content-start gap-4" data-search-item>
                            <div class="flex items-start justify-between gap-3">
                                <span class="book-icon icon-blue"><x-icon name="book-open-text" /></span>
                                <span class="category-tag">Progres {{ $group['progress'] }}%</span>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-slate-900">{{ $group['category'] }}</h3>
                                <p class="mt-1 text-xs text-slate-500">{{ $group['teacher_name'] }}</p>
                            </div>
                            <div class="flex items-center justify-between text-xs text-slate-500">
                                <span>{{ $group['materials_count'] }} materi</span>
                                <span>{{ $group['completed_count'] }} selesai</span>
                            </div>
                            <div class="progress-track" role="progressbar" aria-label="Progres {{ $group['category'] }}" aria-valuenow="{{ $group['progress'] }}" aria-valuemin="0" aria-valuemax="100"><span style="width:{{ $group['progress'] }}%"></span></div>
                            <a class="challenge-button justify-center" href="{{ route('reading', ['teacher' => $group['teacher_id'], 'category' => $group['category']]) }}"><span>Buka kelas</span><x-icon name="arrow-right" /></a>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="teacher-empty-state">
                    <span class="session-icon"><x-icon name="book-open-text" /></span>
                    <strong>Belum ada materi tersedia</strong>
                    <p>Materi untuk Kelas {{ $grade }} akan muncul di sini setelah guru menerbitkannya.</p>
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
            })();
        </script>
    @endpush
@endsection
