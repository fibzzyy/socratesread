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
                        <div class="flex min-h-6 items-center gap-2">
                            <span class="inline-flex min-h-6 items-center rounded-md bg-blue-50 px-2 text-xs font-semibold leading-none text-blue-700">{{ $classroom->targetGradeLabel() }}</span>
                            @if ($classroom->is_active)
                                <span class="inline-flex min-h-6 items-center rounded-md border border-emerald-200/60 bg-emerald-50 px-2 text-xs font-semibold leading-none text-emerald-700">Aktif</span>
                            @else
                                <span class="inline-flex min-h-6 items-center rounded-md bg-slate-100 px-2 text-xs font-semibold leading-none text-slate-600">Diarsipkan</span>
                            @endif
                        </div>
                        <h2 class="mt-1 text-lg font-bold text-slate-900">{{ $classroom->name }}</h2>
                    </div>
                    <span class="text-sm font-semibold text-slate-500">{{ $classroom->students_count }} siswa</span>
                </div>
                <div class="flex items-center justify-between border-y border-slate-100 py-3 text-sm"><span class="text-slate-600">Pembaca materi Anda</span><strong class="text-slate-900">{{ $classroom->active_readers_count }}</strong></div>
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Kode gabung</p>
                    <div class="flex flex-wrap sm:flex-nowrap items-center justify-between gap-2.5 rounded-xl border border-blue-100 bg-blue-50/40 px-3.5 py-3">
                        <strong class="min-w-0 truncate font-mono text-lg font-bold tracking-[.18em] {{ $classroom->is_active ? 'text-slate-900' : 'text-slate-400 line-through' }}">{{ $classroom->join_code }}</strong>
                        @if ($classroom->is_active)
                            <button type="button" class="outline-button inline-flex min-h-11 min-w-0 shrink-0 items-center justify-center gap-2 px-3" data-copy-join-link="{{ url('/classrooms?join_code='.$classroom->join_code) }}" aria-label="Salin tautan untuk bergabung ke kelas {{ $classroom->name }}">
                                <x-icon name="copy" class="size-4 shrink-0" />
                                <span data-copy-label>Salin tautan</span>
                            </button>
                        @else
                            <span class="text-xs font-medium text-amber-600">Kelas nonaktif</span>
                        @endif
                    </div>
                </div>
                <div class="border-t border-slate-100 pt-4">
                    <a href="{{ route('teacher.classrooms.members', $classroom) }}" class="challenge-button flex min-h-12 w-full min-w-0 justify-center px-4 text-sm" aria-label="Lihat {{ $classroom->students_count }} anggota kelas {{ $classroom->name }}">
                        <x-icon name="users" class="size-4" />
                        <span>Anggota kelas</span>
                    </a>
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

@push('scripts')
<script>
(() => {
    document.querySelectorAll('[data-copy-join-link]').forEach((button) => {
        button.addEventListener('click', async () => {
            const label = button.querySelector('[data-copy-label]');
            const originalLabel = label.textContent;
            const link = button.dataset.copyJoinLink;

            try {
                await navigator.clipboard.writeText(link);
            } catch {
                const input = document.createElement('textarea');
                input.value = link;
                input.setAttribute('readonly', '');
                input.style.position = 'fixed';
                input.style.opacity = '0';
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                input.remove();
            }

            label.textContent = 'Tautan tersalin';
            button.classList.add('!border-emerald-300', '!text-emerald-700', '!bg-emerald-50');
            window.setTimeout(() => {
                label.textContent = originalLabel;
                button.classList.remove('!border-emerald-300', '!text-emerald-700', '!bg-emerald-50');
            }, 2200);
        });
    });
})();
</script>
@endpush
