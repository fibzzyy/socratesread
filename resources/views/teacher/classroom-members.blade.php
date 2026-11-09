@extends('layouts.teacher')

@section('title', 'Anggota '.$classroom->name)
@section('breadcrumb', 'Kelas saya / Anggota')

@section('content')
    <section class="stage-heading" aria-labelledby="members-title">
        <div>
            <p class="eyebrow">{{ $classroom->targetGradeLabel() }} · ANGGOTA KELAS</p>
            <h1 id="members-title">{{ $classroom->name }}</h1>
            <p class="welcome-subtitle">Daftar siswa yang sudah bergabung ke kelas ini.</p>
        </div>
        <a class="back-link" href="{{ route('teacher.classrooms', ['status' => $classroom->is_active ? 'active' : 'archived']) }}"><x-icon name="arrow-left" /> <span>Kembali ke kelas</span></a>
    </section>

    <section class="panel teacher-students-panel" aria-labelledby="members-list-title">
        @if (session('status'))
            <div class="auth-notice mb-4" role="status">{{ session('status') }}</div>
        @endif
        <div class="section-heading">
            <div>
                <p class="section-overline">ANGGOTA KELAS</p>
                <h2 id="members-list-title">Siswa yang bergabung</h2>
            </div>
            <span class="document-count">{{ $students->total() }} siswa</span>
        </div>

        @if ($students->isNotEmpty())
            <div class="teacher-table-wrap">
                <table class="teacher-student-table">
                    <thead>
                        <tr>
                            <th scope="col">Nama siswa</th>
                            <th scope="col">Kelas</th>
                            <th scope="col">Bergabung sejak</th>
                            <th scope="col" class="text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($students as $student)
                            <tr>
                                <td>
                                    <span class="teacher-student-name">
                                        <span class="avatar">
                                            @if ($student->avatarUrl())
                                                <img src="{{ $student->avatarUrl() }}" alt="Foto profil {{ $student->name }}" class="avatar-image">
                                            @else
                                                {{ mb_strtoupper(mb_substr($student->name, 0, 1)) }}
                                            @endif
                                        </span>
                                        {{ $student->name }}
                                    </span>
                                </td>
                                <td>{{ $classroom->targetGradeLabel() }}</td>
                                <td>{{ $student->pivot->joined_at ? \Illuminate\Support\Carbon::parse($student->pivot->joined_at)->locale('id')->translatedFormat('d M Y') : '—' }}</td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('teacher.classrooms.members.remove', [$classroom, $student]) }}" data-remove-student-form data-student-name="{{ $student->name }}" data-class-name="{{ $classroom->name }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ml-auto inline-flex min-h-10 items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3 text-xs font-semibold text-red-700 transition hover:border-red-300 hover:bg-red-100">
                                            <x-icon name="trash-2" class="size-4" />
                                            <span>Keluarkan dari kelas</span>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($students->hasPages())
                <div class="teacher-pagination">{{ $students->links() }}</div>
            @endif
        @else
            <div class="teacher-empty-state">
                <span class="session-icon"><x-icon name="users" /></span>
                <strong>Belum ada siswa bergabung</strong>
                <p>Salin tautan gabung dari halaman Kelas Saya dan bagikan kepada siswa.</p>
            </div>
        @endif
    </section>

    <div id="remove-student-dialog" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/50 p-4" data-remove-dialog aria-hidden="true">
        <section class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" role="alertdialog" aria-modal="true" aria-labelledby="remove-student-title" aria-describedby="remove-student-message">
            <div class="remove-student-dialog-icon mb-4 flex size-12 items-center justify-center rounded-full bg-red-50 text-red-600">
                <x-icon name="trash-2" class="size-6" />
            </div>
            <h2 id="remove-student-title" class="text-lg font-bold text-slate-900">Keluarkan siswa dari kelas?</h2>
            <p id="remove-student-message" class="mt-2 text-sm leading-6 text-slate-600">
                Siswa <strong data-dialog-student class="text-slate-900"></strong> akan dikeluarkan dari kelas <strong data-dialog-class class="text-slate-900"></strong>. Progres kelas tidak akan ditampilkan selama siswa keluar. Jika akun yang sama bergabung kembali, progres sebelumnya akan muncul lagi. Akun siswa tidak dihapus.
            </p>
            <div class="remove-student-dialog-actions">
                <button type="button" data-dialog-cancel class="remove-student-dialog-cancel">Batal</button>
                <button type="button" data-dialog-confirm class="remove-student-dialog-confirm">Ya, keluarkan siswa</button>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const dialog = document.querySelector('[data-remove-dialog]');
    if (!dialog) return;

    const studentName = dialog.querySelector('[data-dialog-student]');
    const className = dialog.querySelector('[data-dialog-class]');
    const cancelButton = dialog.querySelector('[data-dialog-cancel]');
    const confirmButton = dialog.querySelector('[data-dialog-confirm]');
    let pendingForm = null;
    let previousFocus = null;

    const closeDialog = () => {
        dialog.classList.add('hidden');
        dialog.classList.remove('flex');
        dialog.setAttribute('aria-hidden', 'true');
        pendingForm = null;
        previousFocus?.focus();
    };

    document.querySelectorAll('[data-remove-student-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === 'true') {
                delete form.dataset.confirmed;
                return;
            }

            event.preventDefault();
            pendingForm = form;
            previousFocus = document.activeElement;
            studentName.textContent = form.dataset.studentName;
            className.textContent = form.dataset.className;
            dialog.classList.remove('hidden');
            dialog.classList.add('flex');
            dialog.setAttribute('aria-hidden', 'false');
            cancelButton.focus();
        });
    });

    cancelButton.addEventListener('click', closeDialog);
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) closeDialog();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !dialog.classList.contains('hidden')) closeDialog();
    });
    confirmButton.addEventListener('click', () => {
        if (!pendingForm) return;
        const form = pendingForm;
        form.dataset.confirmed = 'true';
        form.requestSubmit();
    });
})();
</script>
@endpush
