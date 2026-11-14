@extends('layouts.teacher')

@section('title', 'Kelas Saya')
@section('breadcrumb', 'Kelas saya')

@section('content')
    <div class="teacher-classrooms-page">
    <section class="stage-heading classrooms-page-heading" aria-labelledby="classrooms-title">
        <div><p class="eyebrow">KOMUNITAS BELAJAR</p><h1 id="classrooms-title">Kelas saya</h1><p class="welcome-subtitle">Kelola kode gabung dan pantau siswa yang membaca materi Anda.</p></div>
    </section>

    @if (session('status')) <div class="auth-notice" role="status">{{ session('status') }}</div> @endif

    <form method="POST" action="{{ route('teacher.classrooms.store') }}" class="panel classroom-create-card classroom-create-form" aria-labelledby="create-class-title">
        @csrf
        <div class="classroom-create-icon" aria-hidden="true">
            <x-icon name="users-round" class="classroom-create-users-icon" />
            <span class="classroom-create-icon-plus">+</span>
        </div>
        <div class="classroom-create-copy">
            <p class="section-overline">MULAI BERSAMA</p>
            <h2 id="create-class-title">Buat kelas baru</h2>
            <p class="classroom-create-description">Mulai kelas baru dan ajak siswa membaca serta berdiskusi bersama.</p>
        </div>
        <div class="classroom-form-fields">
            <div class="classroom-form-field classroom-name-field">
                <label for="class-name">Nama kelas</label>
                <input id="class-name" name="name" value="{{ old('name') }}" maxlength="120" required class="classroom-form-control" placeholder="Contoh: X IPA 1">
                @error('name')<span class="classroom-field-error">{{ $message }}</span>@enderror
            </div>
            <div class="classroom-form-field classroom-grade-field">
                <label for="target_grade">Target Jenjang</label>
                <select id="target_grade" name="target_grade" class="classroom-form-control">
                    <option value="">Semua Tingkat</option>
                    <option value="10" {{ old('target_grade') == '10' ? 'selected' : '' }}>Kelas 10</option>
                    <option value="11" {{ old('target_grade') == '11' ? 'selected' : '' }}>Kelas 11</option>
                    <option value="12" {{ old('target_grade') == '12' ? 'selected' : '' }}>Kelas 12</option>
                </select>
                @error('target_grade')<span class="classroom-field-error">{{ $message }}</span>@enderror
            </div>
        </div>
        <button type="submit" class="challenge-button classroom-submit"><x-icon name="plus" />Buat kelas <x-icon name="arrow-right" /></button>
    </form>

    <!-- Tab Filter Status Kelas -->
    <div class="classroom-tabs">
        <div class="classroom-tab-list">
            <a href="{{ route('teacher.classrooms', ['status' => 'active']) }}" class="classroom-tab {{ $status === 'active' ? 'is-active' : '' }}">
                <span>Kelas Aktif</span>
                <span class="classroom-count-badge">{{ $activeCount }}</span>
            </a>
            <a href="{{ route('teacher.classrooms', ['status' => 'archived']) }}" class="classroom-tab {{ $status === 'archived' ? 'is-active' : '' }}">
                <span>Diarsipkan</span>
                <span class="classroom-count-badge">{{ $archivedCount }}</span>
            </a>
        </div>
        <span class="classroom-count">{{ $classrooms->count() }} kelas ditampilkan</span>
    </div>

    <section class="classroom-grid" aria-label="Daftar kelas">
        @forelse ($classrooms as $classroom)
            <article class="panel classroom-card">
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
                @if ($classroom->is_active)
                    <details class="border-t border-slate-100 pt-3">
                        <summary class="w-fit cursor-pointer text-sm text-slate-600 hover:text-slate-900">Tampilkan QR untuk bergabung</summary>
                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <canvas width="224" height="224" class="size-48 rounded border border-slate-200 bg-white p-1" data-class-qr-code data-join-url="{{ route('classrooms.index', ['join_code' => $classroom->join_code, 'scan' => 1], false) }}" aria-label="QR untuk bergabung ke kelas {{ $classroom->name }}"></canvas>
                            <p class="max-w-[190px] text-sm leading-5 text-slate-500" data-qr-status>Scan dengan kamera ponsel untuk membuka halaman gabung kelas.</p>
                        </div>
                        <button type="button" class="mt-3 inline-flex min-h-9 items-center rounded-md border border-slate-200 px-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50" data-open-qr-presentation aria-controls="class-qr-presentation-{{ $classroom->id }}" data-join-url="{{ route('classrooms.index', ['join_code' => $classroom->join_code, 'scan' => 1], false) }}" data-class-name="{{ $classroom->name }}" data-grade="{{ $classroom->targetGradeLabel() }}" data-join-code="{{ $classroom->join_code }}">
                            Besarkan QR
                        </button>
                    </details>
                    <dialog id="class-qr-presentation-{{ $classroom->id }}" class="m-auto h-[96vh] w-[96vw] max-w-none border-0 bg-white p-0 text-slate-900 backdrop:bg-slate-950/80" data-qr-presentation>
                        <div class="relative flex h-full flex-col items-center justify-center gap-3 px-5 py-8 text-center sm:gap-5">
                            <div class="absolute right-4 top-4 flex items-center gap-2 sm:right-6 sm:top-6">
                                <button type="button" class="rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50" data-qr-fullscreen>Layar penuh</button>
                                <button type="button" class="rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50" data-close-qr-presentation>Tutup</button>
                            </div>
                            <p class="text-sm text-slate-500" data-presentation-grade></p>
                            <h2 class="text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl" data-presentation-title></h2>
                            <canvas width="680" height="680" class="max-h-[62vh] max-w-[78vw] rounded-lg border border-slate-200 bg-white p-2 sm:max-h-[68vh]" style="width:min(68vmin,680px);height:min(68vmin,680px)" data-presentation-qr aria-label="QR kelas untuk dipindai siswa"></canvas>
                            <p class="text-sm text-slate-600">Arahkan kamera ponsel ke QR untuk bergabung</p>
                            <p class="font-mono text-sm tracking-[.16em] text-slate-500" data-presentation-code></p>
                        </div>
                    </dialog>
                @endif
                <div class="border-t border-slate-100 pt-4">
                    <a href="{{ route('teacher.classrooms.members', $classroom) }}" class="challenge-button flex min-h-12 w-full min-w-0 justify-center px-4 text-sm" aria-label="Lihat {{ $classroom->students_count }} anggota kelas {{ $classroom->name }}">
                        <x-icon name="users" class="size-4" />
                        <span>Anggota kelas</span>
                    </a>
                </div>
                <div class="border-t border-slate-100 pt-3 flex items-center justify-between">
                    @if ($classroom->is_active)
                        <form method="POST" action="{{ route('teacher.classrooms.toggle-status', $classroom) }}"
                              data-confirm-title="Arsipkan Kelas?"
                              data-confirm-subject="{{ $classroom->name }}"
                              data-confirm-message="Siswa tidak akan bisa bergabung selama kelas diarsipkan, namun seluruh data riwayat belajar tetap tersimpan aman."
                              data-confirm-ok="Ya, Arsipkan"
                              data-confirm-variant="warning">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-amber-600 transition cursor-pointer">
                                <x-icon name="archive" class="w-3.5 h-3.5" />
                                <span>Arsipkan kelas</span>
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('teacher.classrooms.toggle-status', $classroom) }}"
                              data-confirm-title="Aktifkan Kembali Kelas?"
                              data-confirm-subject="{{ $classroom->name }}"
                              data-confirm-message="Siswa akan dapat bergabung kembali ke kelas ini menggunakan kode kelas."
                              data-confirm-ok="Ya, Aktifkan"
                              data-confirm-variant="success">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600 hover:text-emerald-700 transition cursor-pointer">
                                <x-icon name="rotate-ccw" class="w-3.5 h-3.5" />
                                <span>Aktifkan kembali</span>
                            </button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <div class="panel classroom-empty-panel">
                <div class="teacher-empty-state classroom-empty">
                    <svg class="classroom-empty-illustration" viewBox="0 0 240 150" fill="none" aria-hidden="true">
                        <circle cx="36" cy="96" r="7" fill="#C9DCFF"/>
                        <circle cx="204" cy="34" r="10" fill="#7EA9F5"/>
                        <circle cx="220" cy="88" r="4" fill="#D5E3FA"/>
                        <circle cx="18" cy="48" r="4" fill="#4388F5"/>
                        <g class="classroom-empty-card classroom-empty-card-back" transform="rotate(-10 100 72)">
                            <rect x="54" y="22" width="116" height="88" rx="14" fill="#E7F0FF" stroke="#C4D8FA" stroke-width="2"/>
                            <path d="M72 41h56" stroke="#A8C5F5" stroke-width="6" stroke-linecap="round"/>
                            <path d="M72 56h40" stroke="#D1E0F8" stroke-width="5" stroke-linecap="round"/>
                        </g>
                        <g class="classroom-empty-card classroom-empty-card-front" transform="rotate(7 134 84)">
                            <rect x="83" y="35" width="112" height="86" rx="14" fill="#F8FBFF" stroke="#C7D9F7" stroke-width="2"/>
                            <circle cx="139" cy="68" r="20" fill="#E1ECFF"/>
                            <path d="M130 71c0-5 4-9 9-9s9 4 9 9M125 83c1-6 6-10 14-10s13 4 14 10" stroke="#2871F2" stroke-width="4" stroke-linecap="round"/>
                            <path d="M103 101h67" stroke="#D6E2F5" stroke-width="6" stroke-linecap="round"/>
                        </g>
                        <circle cx="190" cy="113" r="18" fill="#2469EE"/>
                        <path d="M190 105v16M182 113h16" stroke="white" stroke-width="3" stroke-linecap="round"/>
                    </svg>
                    <span class="session-icon"><x-icon name="users" /></span>
                    @if ($status === 'archived')
                        <strong>Belum ada kelas yang diarsipkan</strong>
                        <p>Kelas yang telah selesai semester dapat diarsipkan dari tab Kelas Aktif.</p>
                    @else
                        <strong>Belum ada kelas aktif</strong>
                        <p>Buat kelas baru pada form di atas untuk mendapatkan kode gabung bagi siswa Anda.</p>
                        <span class="classroom-empty-hint">Mulai dengan membuat kelas pertama Anda.</span>
                    @endif
                </div>
            </div>
        @endforelse
    </section>
    </div>
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
