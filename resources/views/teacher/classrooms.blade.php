@extends('layouts.teacher')

@section('title', 'Kelas Saya')
@section('breadcrumb', 'Kelas saya')

@section('content')
    <section class="stage-heading" aria-labelledby="classrooms-title">
        <div><p class="eyebrow">KOMUNITAS BELAJAR</p><h1 id="classrooms-title">Kelas saya</h1><p class="welcome-subtitle">Kelola kode gabung dan pantau siswa yang membaca materi Anda.</p></div>
    </section>

    @if (session('status')) <div class="auth-notice" role="status">{{ session('status') }}</div> @endif

    <section class="panel" aria-labelledby="create-class-title">
        <div class="section-heading"><div><p class="section-overline">MULAI BERSAMA</p><h2 id="create-class-title">Buat kelas baru</h2></div></div>
        <form method="POST" action="{{ route('teacher.classrooms.store') }}" class="grid gap-3 sm:grid-cols-[1fr_180px_auto] sm:items-end">
            @csrf
            <div class="grid gap-1.5">
                <label for="class-name" class="text-xs font-semibold text-slate-700">Nama kelas</label>
                <input id="class-name" name="name" value="{{ old('name') }}" maxlength="120" required class="min-h-10 rounded-lg border border-slate-300 px-3 text-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100" placeholder="Contoh: X IPA 1">
                @error('name')<span class="text-xs text-red-700">{{ $message }}</span>@enderror
            </div>
            <div class="grid gap-1.5">
                <label for="target_grade" class="text-xs font-semibold text-slate-700">Target Jenjang</label>
                <select id="target_grade" name="target_grade" class="min-h-10 rounded-lg border border-slate-300 px-3 text-sm outline-none bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-100">
                    <option value="">Semua Tingkat</option>
                    <option value="10" {{ old('target_grade') == '10' ? 'selected' : '' }}>Kelas 10</option>
                    <option value="11" {{ old('target_grade') == '11' ? 'selected' : '' }}>Kelas 11</option>
                    <option value="12" {{ old('target_grade') == '12' ? 'selected' : '' }}>Kelas 12</option>
                </select>
                @error('target_grade')<span class="text-xs text-red-700">{{ $message }}</span>@enderror
            </div>
            <button type="submit" class="challenge-button h-10">Buat kelas <x-icon name="arrow-right" /></button>
        </form>
    </section>

    <!-- Tab Filter Status Kelas -->
    <div class="mt-8 mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-3">
        <div class="flex items-center gap-2">
            <a href="{{ route('teacher.classrooms', ['status' => 'active']) }}" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $status === 'active' ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                <span>Kelas Aktif</span>
                <span class="rounded-full px-1.5 py-0.2 text-[10px] {{ $status === 'active' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700' }}">{{ $activeCount }}</span>
            </a>
            <a href="{{ route('teacher.classrooms', ['status' => 'archived']) }}" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $status === 'archived' ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                <span>Diarsipkan</span>
                <span class="rounded-full px-1.5 py-0.2 text-[10px] {{ $status === 'archived' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700' }}">{{ $archivedCount }}</span>
            </a>
        </div>
        <span class="text-xs text-slate-500 font-medium">{{ $classrooms->count() }} kelas ditampilkan</span>
    </div>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3" aria-label="Daftar kelas">
        @forelse ($classrooms as $classroom)
            <article class="panel grid content-start gap-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="section-overline">KELAS</p>
                            <span class="rounded bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700">{{ $classroom->targetGradeLabel() }}</span>
                            @if ($classroom->is_active)
                                <span class="rounded bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 border border-emerald-200/60">Aktif</span>
                            @else
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">Diarsipkan</span>
                            @endif
                        </div>
                        <h2 class="mt-1 text-lg font-bold text-slate-900">{{ $classroom->name }}</h2>
                    </div>
                    <span class="text-sm font-semibold text-slate-500">{{ $classroom->students_count }} siswa</span>
                </div>
                <div class="flex items-center justify-between border-y border-slate-100 py-3 text-sm"><span class="text-slate-600">Pembaca materi Anda</span><strong class="text-slate-900">{{ $classroom->active_readers_count }}</strong></div>
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Kode gabung</p>
                    <div class="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3">
                        <strong class="font-mono text-lg tracking-[.18em] {{ $classroom->is_active ? 'text-slate-900' : 'text-slate-400 line-through' }}">{{ $classroom->join_code }}</strong>
                        <span class="text-xs {{ $classroom->is_active ? 'text-slate-500' : 'text-amber-600 font-medium' }}">{{ $classroom->is_active ? 'Bagikan ke siswa' : 'Kelas nonaktif' }}</span>
                    </div>
                </div>
                <div class="grid gap-3 border-t border-slate-100 pt-4"><div class="flex items-center justify-between"><h3 class="text-sm font-bold text-slate-900">Anggota kelas</h3><span class="text-xs text-slate-500">{{ $classroom->students_count }} siswa</span></div>
                    @forelse ($classroom->students as $student)
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <strong class="block truncate text-slate-800">{{ $student->name }}</strong>
                                    @if ($student->currentGrade())
                                        <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600">Kelas {{ $student->currentGrade() }}</span>
                                    @endif
                                </div>
                                <span class="text-xs text-slate-500">{{ $student->teacher_reading_sessions_count }} sesi pada bacaan Anda</span>
                            </div>
                            <span class="shrink-0 font-semibold text-slate-700">{{ (int) round($student->teacher_average_progress ?? 0) }}%</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Belum ada siswa bergabung.</p>
                    @endforelse
                </div>
                <div class="border-t border-slate-100 pt-3 flex items-center justify-between">
                    <form method="POST" action="{{ route('teacher.classrooms.toggle-status', $classroom) }}">
                        @csrf
                        @if ($classroom->is_active)
                            <button type="submit" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-amber-600 transition" onclick="return confirm('Arsipkan kelas ini? Siswa tidak akan bisa bergabung selama kelas diarsipkan, namun data riwayat belajar tetap tersimpan.')">
                                <x-icon name="archive" class="w-3.5 h-3.5" />
                                <span>Arsipkan kelas</span>
                            </button>
                        @else
                            <button type="submit" class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600 hover:text-emerald-700 transition" onclick="return confirm('Aktifkan kembali kelas ini? Siswa akan dapat bergabung kembali menggunakan kode kelas.')">
                                <x-icon name="rotate-ccw" class="w-3.5 h-3.5" />
                                <span>Aktifkan kembali</span>
                            </button>
                        @endif
                    </form>
                </div>
            </article>
        @empty
            <div class="panel md:col-span-2 xl:col-span-3">
                <div class="teacher-empty-state">
                    <span class="session-icon"><x-icon name="users" /></span>
                    @if ($status === 'archived')
                        <strong>Belum ada kelas yang diarsipkan</strong>
                        <p>Kelas yang telah selesai semester dapat diarsipkan dari tab Kelas Aktif.</p>
                    @else
                        <strong>Belum ada kelas aktif</strong>
                        <p>Buat kelas baru pada form di atas untuk mendapatkan kode gabung bagi siswa Anda.</p>
                    @endif
                </div>
            </div>
        @endforelse
    </section>
@endsection
