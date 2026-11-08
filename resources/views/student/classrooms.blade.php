@extends('layouts.student')

@section('title', 'Kelas Saya')
@section('breadcrumb', 'Kelas saya')

@section('content')
    <section class="stage-heading" aria-labelledby="classrooms-title">
        <div>
            <p class="eyebrow">KOMUNITAS BELAJAR</p>
            <h1 id="classrooms-title">Kelas saya</h1>
            <p class="welcome-subtitle">Kelola kelas belajar yang kamu ikuti bersama guru dan teman sekelas.</p>
        </div>
        <a class="back-link" href="{{ route('dashboard') }}"><x-icon name="arrow-left" /> <span>Kembali ke dashboard</span></a>
    </section>

    @if (session('notice'))
        <div class="auth-notice" role="status">{{ session('notice') }}</div>
    @endif

    <section class="panel mb-6" aria-labelledby="join-class-heading">
        <div class="section-heading">
            <div>
                <p class="section-overline">GABUNG BERSAMA GURU</p>
                <h2 id="join-class-heading">Gabung kelas baru</h2>
            </div>
            <span class="session-icon"><x-icon name="users" /></span>
        </div>
        <form method="POST" action="{{ route('classrooms.join') }}" class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
            @csrf
            <div class="grid gap-1.5">
                <label for="join_code" class="text-xs font-semibold text-slate-700">Kode gabung kelas (8 karakter)</label>
                <input id="join_code" name="join_code" value="{{ old('join_code', $joinCode) }}" maxlength="8" minlength="8" required autocomplete="off" autocapitalize="characters" class="min-h-11 rounded-xl border border-slate-300 px-4 font-mono text-base uppercase tracking-widest outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100" placeholder="Contoh: BACA2026">
                @error('join_code')
                    <span class="text-xs text-red-600 font-medium">{{ $message }}</span>
                @enderror
            </div>
            <button type="submit" class="challenge-button h-11">
                <span>Gabung kelas</span>
                <x-icon name="arrow-right" />
            </button>
        </form>
        <p class="mt-3 text-xs leading-5 text-slate-500">
            Guru dapat melihat keterlibatan dan progres sesi pada bacaan yang mereka unggah, bukan isi jawaban pribadimu.
        </p>
    </section>

    <section class="mt-6" aria-labelledby="enrolled-classes-heading">
        <div class="section-heading mb-4">
            <div>
                <p class="section-overline">KELAS AKTIF</p>
                <h2 id="enrolled-classes-heading">Kelas yang kamu ikuti</h2>
            </div>
            <span class="document-count">{{ $classrooms->count() }} kelas</span>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($classrooms as $classroom)
                <article class="panel grid content-start gap-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="rounded bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700">
                                    {{ $classroom->targetGradeLabel() }}
                                </span>
                            </div>
                            <h3 class="mt-1 text-base font-bold text-slate-900">{{ $classroom->name }}</h3>
                            <p class="text-xs text-slate-500 mt-0.5">{{ $classroom->teacher?->name ?? 'Guru SocratesRead' }}</p>
                        </div>
                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 border border-emerald-200/60">
                            Aktif
                        </span>
                    </div>

                    <div class="border-t border-slate-100 pt-3 text-xs text-slate-500 flex items-center justify-between">
                        <span>Anggota kelas</span>
                        <strong class="text-slate-700">{{ $classroom->students_count }} siswa</strong>
                    </div>

                    <div class="border-t border-slate-100 pt-3 text-xs text-slate-500 flex items-center justify-between">
                        <span>Bergabung sejak</span>
                        <span class="font-medium text-slate-600">
                            {{ $classroom->pivot->joined_at ? \Illuminate\Support\Carbon::parse($classroom->pivot->joined_at)->locale('id')->translatedFormat('d M Y') : '-' }}
                        </span>
                    </div>
                </article>
            @empty
                <div class="panel md:col-span-2 xl:col-span-3">
                    <div class="teacher-empty-state">
                        <span class="session-icon"><x-icon name="users" /></span>
                        <strong>Belum ada kelas yang diikuti</strong>
                        <p>Masukkan kode kelas dari gurumu pada form di atas untuk mulai belajar bersama.</p>
                    </div>
                </div>
            @endforelse
        </div>
    </section>
@endsection
