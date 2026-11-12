@extends('layouts.student')

@section('title', $selectedGroup['category'] ?? 'Reading Room')
@section('breadcrumb', $selectedGroup ? 'Reading Room / '.$selectedGroup['category'] : 'Reading Room')

@section('content')
    <section class="stage-heading" aria-labelledby="reading-library-title">
        <div>
            <p class="eyebrow">RUANG FOKUSMU · KELAS {{ $grade }}</p>
            <h1 id="reading-library-title">{{ $selectedGroup['category'] ?? 'Reading Room' }}</h1>
            <p class="welcome-subtitle">{{ $selectedGroup ? 'Materi yang dibagikan '.$selectedGroup['teacher_name'].'.' : 'Pilih kelas untuk melihat materi yang dibagikan dan progres belajarmu.' }}</p>
        </div>
        @if ($selectedGroup)
            <a class="back-link" href="{{ route('reading') }}"><x-icon name="arrow-left" /> <span>Kembali ke Reading Room</span></a>
        @else
            <a class="back-link" href="{{ route('dashboard') }}"><x-icon name="arrow-left" /> <span>Kembali ke dashboard</span></a>
        @endif
    </section>

    @if (session('notice'))
        <div class="auth-notice" role="status">{{ session('notice') }}</div>
    @endif

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
                        <article class="panel grid content-start gap-4">
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
                <article class="reading-item">
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
@endsection
