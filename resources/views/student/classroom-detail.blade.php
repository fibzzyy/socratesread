@extends('layouts.student')

@section('title', $classroom->name)
@section('breadcrumb', 'Kelas saya / '.$classroom->name)

@section('content')
    <section class="stage-heading" aria-labelledby="classroom-title">
        <div>
            <p class="eyebrow">{{ $classroom->targetGradeLabel() }} · KELAS SAYA</p>
            <h1 id="classroom-title">{{ $classroom->name }}</h1>
            <p class="welcome-subtitle">Materi dari {{ $classroom->teacher?->name ?? 'guru kelasmu' }} dan progres belajarmu.</p>
        </div>
        <a class="back-link" href="{{ route('classrooms.index') }}"><x-icon name="arrow-left" /> <span>Kembali ke kelas</span></a>
    </section>

    @if (session('notice'))
        <div class="auth-notice" role="status">{{ session('notice') }}</div>
    @endif

    <section class="panel mb-6" aria-label="Ringkasan progres kelas">
        <div class="section-heading">
            <div>
                <p class="section-overline">PROGRES KELAS</p>
                <h2>{{ $completedCount }} dari {{ $documents->count() }} materi selesai</h2>
            </div>
            <span class="document-count">{{ $progress }}%</span>
        </div>
        <div class="progress-track" role="progressbar" aria-label="Progres materi kelas" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"><span style="width:{{ $progress }}%"></span></div>
    </section>

    <section aria-labelledby="classroom-materials-title">
        <div class="section-heading mb-4">
            <div>
                <p class="section-overline">MATERI DARI GURUMU</p>
                <h2 id="classroom-materials-title">Daftar materi</h2>
            </div>
            <span class="document-count">{{ $documents->count() }} materi</span>
        </div>

        <div class="grid gap-3">
            @forelse ($documents as $document)
                <article class="panel flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-start gap-3">
                        <span class="book-icon {{ $document->is_completed ? '!text-emerald-700 !bg-emerald-50' : '' }} shrink-0" aria-hidden="true"><x-icon name="{{ $document->is_completed ? 'check-circle' : 'book-open-text' }}" /></span>
                        <div class="min-w-0">
                            <div class="reading-meta">
                                <span class="category-tag">{{ mb_strtoupper($document->category) }}</span>
                                <span class="reading-time"><x-icon name="clock" /> {{ $document->estimated_minutes }} menit</span>
                            </div>
                            <h3 class="mt-1 font-bold text-slate-900">{{ $document->title }}</h3>
                            <p class="mt-1 text-xs leading-5 text-slate-500">{{ $document->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($document->body), 140) }}</p>
                            @if ($document->is_completed)
                                <span class="mt-2 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700"><x-icon name="check-circle" class="size-3.5" /> Selesai · {{ $document->student_session?->completed_at?->locale('id')->translatedFormat('d M Y') ?? 'kuis selesai' }}</span>
                            @elseif ($document->student_session)
                                <span class="mt-2 inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-semibold text-blue-700"><x-icon name="clock" class="size-3.5" /> Siap dilanjutkan</span>
                            @else
                                <span class="mt-2 inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">Belum dikerjakan</span>
                            @endif
                        </div>
                    </div>
                    @if ($document->is_completed)
                        <a class="outline-button shrink-0" href="{{ route('reading', ['document' => $document->id]) }}"><span>Buka</span><x-icon name="arrow-right" /></a>
                    @else
                        <form method="POST" action="{{ route('classrooms.materials.start', [$classroom, $document]) }}" class="shrink-0">
                            @csrf
                            <button class="challenge-button" type="submit"><span>{{ $document->student_session ? 'Lanjutkan' : 'Mulai' }}</span><x-icon name="arrow-right" /></button>
                        </form>
                    @endif
                </article>
            @empty
                <div class="panel">
                    <div class="teacher-empty-state">
                        <span class="session-icon"><x-icon name="book-open-text" /></span>
                        <strong>Belum ada materi untuk kelas ini</strong>
                        <p>Materi akan muncul setelah gurumu menerbitkan bacaan untuk kelas {{ $classroom->name }}.</p>
                    </div>
                </div>
            @endforelse
        </div>
    </section>
@endsection