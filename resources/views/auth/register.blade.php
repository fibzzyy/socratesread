<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2563eb">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Buat Akun — SocratesRead</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('auth.partials.anywhere-styles')
    <style>
        .sr-register-page #registerForm label {
            color: #334155;
            font-size: 13px;
            letter-spacing: 0;
            text-transform: none;
        }

        .sr-register-page #registerForm .sr-input-field {
            height: 44px;
            border-radius: 10px;
            border-color: #d8dee8;
            background: #fff;
        }

        .sr-register-page #registerForm .relative {
            position: relative;
        }

        .sr-register-page #registerForm button[id^="toggle"] {
            position: absolute;
            top: 4px;
            right: 4px;
            z-index: 2;
            display: inline-flex;
            width: 32px;
            height: 32px;
            align-items: center;
            justify-content: center;
            padding: 0;
            border: 0;
            background: transparent;
            color: #64748b;
            cursor: pointer;
        }

        .sr-register-page #registerForm button[id^="toggle"]:hover {
            color: #334155;
        }

        .sr-register-page #registerForm .eye-open.hidden,
        .sr-register-page #registerForm .eye-closed.hidden {
            display: none !important;
        }
    </style>
</head>
<body class="min-h-screen bg-white font-sans text-[#0f172a] antialiased overflow-x-hidden">
    @php
        $initialRole = old('role', request('role', 'student'));
        if (!in_array($initialRole, ['student', 'teacher'], true)) {
            $initialRole = 'student';
        }
    @endphp

    <div class="sr-auth-layout sr-auth-wave sr-register-page">
        <!-- KIRI: Hero Visual & Inspirasi -->
        @include('auth.partials.anywhere-right-panel')

        <!-- KANAN: Form Pendaftaran -->
        <div data-slot="auth" class="sr-form-panel animate-auth-enter">
            <div class="mx-auto w-full max-w-[420px]">
                <div class="mb-6">
                    <div class="mb-4 flex items-center">
                        <a href="{{ route('login') }}" class="group inline-flex items-center gap-2.5 text-decoration-none">
                            <img src="{{ asset('images/logo.png') }}" alt="Logo SocratesRead" class="h-9 w-9 object-contain">
                            <span class="font-display text-lg font-extrabold tracking-tight text-slate-900">
                                Socrates<span class="text-blue-600">Read</span>
                            </span>
                        </a>
                    </div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900">Mulai langkah Anda</h1>
                    <p class="mt-1 text-xs text-slate-500">Daftar untuk mengasah kemampuan membaca kritis Anda.</p>
                </div>

                @if ($errors->any())
                    <div data-slot="auth-error" class="mb-4 rounded-xl border border-red-500/20 bg-red-500/10 px-3.5 py-2.5 text-xs text-red-600" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('register') }}" id="registerForm" data-slot="auth-form" class="space-y-3">
                    @csrf
                    <input type="hidden" name="role" id="roleInput" value="{{ $initialRole }}">

                    <!-- Role Switcher -->
                    <div class="space-y-1">
                        <label class="text-xs font-semibold uppercase tracking-wider text-slate-700">Daftar Sebagai</label>
                        <div class="grid grid-cols-2 gap-1.5 rounded-xl border border-slate-200 bg-slate-100/80 p-1" role="group">
                            <button
                                type="button"
                                data-role-btn="student"
                                aria-pressed="{{ $initialRole === 'student' ? 'true' : 'false' }}"
                                class="inline-flex h-8 items-center justify-center rounded-lg px-3 text-xs font-semibold transition-all {{ $initialRole === 'student' ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900' }}"
                            >
                                Siswa
                            </button>
                            <button
                                type="button"
                                data-role-btn="teacher"
                                aria-pressed="{{ $initialRole === 'teacher' ? 'true' : 'false' }}"
                                class="inline-flex h-8 items-center justify-center rounded-lg px-3 text-xs font-semibold transition-all {{ $initialRole === 'teacher' ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900' }}"
                            >
                                Guru
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="space-y-1">
                            <label for="name" class="text-xs font-semibold uppercase tracking-wider text-slate-700">Nama</label>
                            <input
                                id="name"
                                name="name"
                                type="text"
                                value="{{ old('name') }}"
                                placeholder="Nama lengkap"
                                autocomplete="name"
                                required
                                class="sr-input-field flex h-10 w-full rounded-xl border border-slate-200/90 bg-slate-50/70 px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none @error('name') border-red-500 @enderror"
                            >
                        </div>

                        <div class="space-y-1">
                            <label for="school_origin" class="text-xs font-semibold uppercase tracking-wider text-slate-700">Asal Sekolah</label>
                            <input
                                id="school_origin"
                                name="school_origin"
                                type="text"
                                value="{{ old('school_origin') }}"
                                placeholder="SMAN 1 Jakarta"
                                required
                                class="sr-input-field flex h-10 w-full rounded-xl border border-slate-200/90 bg-slate-50/70 px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none @error('school_origin') border-red-500 @enderror"
                            >
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label for="email" class="text-xs font-semibold uppercase tracking-wider text-slate-700">Email</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            placeholder="nama@email.com"
                            autocomplete="email"
                            required
                            class="sr-input-field flex h-10 w-full rounded-xl border border-slate-200/90 bg-slate-50/70 px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none @error('email') border-red-500 @enderror"
                        >
                    </div>

                    <div id="studentFieldGroup" class="grid grid-cols-1 gap-3 sm:grid-cols-2 {{ $initialRole === 'student' ? '' : 'hidden' }}">
                        <div class="space-y-1">
                            <label for="nisn" class="text-xs font-semibold uppercase tracking-wider text-slate-700">NISN</label>
                            <input
                                id="nisn"
                                name="nisn"
                                type="text"
                                value="{{ old('nisn') }}"
                                placeholder="10 digit"
                                {{ $initialRole === 'student' ? 'required' : '' }}
                                class="sr-input-field flex h-10 w-full rounded-xl border border-slate-200/90 bg-slate-50/70 px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none @error('nisn') border-red-500 @enderror"
                            >
                        </div>

                        <div class="space-y-1">
                            <label for="entry_grade" class="text-xs font-semibold uppercase tracking-wider text-slate-700">Tingkat Kelas</label>
                            <select
                                id="entry_grade"
                                name="entry_grade"
                                {{ $initialRole === 'student' ? 'required' : '' }}
                                class="sr-input-field flex h-10 w-full rounded-xl border border-slate-200/90 bg-slate-50/70 px-3 text-sm text-slate-900 focus:outline-none @error('entry_grade') border-red-500 @enderror"
                            >
                                @php $selectedGrade = (int) old('entry_grade', 10); @endphp
                                <option value="10" {{ $selectedGrade === 10 ? 'selected' : '' }}>Kelas 10</option>
                                <option value="11" {{ $selectedGrade === 11 ? 'selected' : '' }}>Kelas 11</option>
                                <option value="12" {{ $selectedGrade === 12 ? 'selected' : '' }}>Kelas 12</option>
                            </select>
                        </div>
                    </div>

                    <div id="teacherFieldGroup" class="space-y-1 {{ $initialRole === 'teacher' ? '' : 'hidden' }}">
                        <label class="text-xs font-semibold uppercase tracking-wider text-slate-700" for="nip_nuptk">NUPTK</label>
                        <input
                            id="nip_nuptk"
                            name="nip_nuptk"
                            type="text"
                            value="{{ old('nip_nuptk') }}"
                            placeholder="Nomor identitas pendidik"
                            {{ $initialRole === 'teacher' ? 'required' : '' }}
                            class="sr-input-field flex h-10 w-full rounded-xl border border-slate-200/90 bg-slate-50/70 px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none @error('nip_nuptk') border-red-500 @enderror"
                        >
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="space-y-1">
                            <label for="password" class="text-xs font-semibold uppercase tracking-wider text-slate-700">Sandi</label>
                            <div class="relative">
                                <input
                                    id="password"
                                    name="password"
                                    type="password"
                                    placeholder="••••••••"
                                    autocomplete="new-password"
                                    required
                                    class="sr-input-field flex h-10 w-full rounded-xl border border-slate-200/90 bg-slate-50/70 px-3 pr-9 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none @error('password') border-red-500 @enderror"
                                >
                                <button
                                    type="button"
                                    id="togglePasswordBtn"
                                    aria-label="Tampilkan kata sandi"
                                    class="absolute right-1 top-1 inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:text-slate-700"
                                >
                                    <span class="material-symbols-outlined text-[18px] leading-none eye-open" aria-hidden="true">visibility</span>
                                    <span class="material-symbols-outlined text-[18px] leading-none eye-closed hidden" aria-hidden="true">visibility_off</span>
                                </button>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label for="password_confirmation" class="text-xs font-semibold uppercase tracking-wider text-slate-700">Ulangi Sandi</label>
                            <div class="relative">
                                <input
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    type="password"
                                    placeholder="••••••••"
                                    autocomplete="new-password"
                                    required
                                    class="sr-input-field flex h-10 w-full rounded-xl border border-slate-200/90 bg-slate-50/70 px-3 pr-9 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none"
                                >
                                <button
                                    type="button"
                                    id="toggleConfirmBtn"
                                    aria-label="Tampilkan konfirmasi kata sandi"
                                    class="absolute right-1 top-1 inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:text-slate-700"
                                >
                                    <span class="material-symbols-outlined text-[18px] leading-none eye-open" aria-hidden="true">visibility</span>
                                    <span class="material-symbols-outlined text-[18px] leading-none eye-closed hidden" aria-hidden="true">visibility_off</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2 pt-0.5">
                        <input
                            id="terms"
                            name="terms"
                            type="checkbox"
                            required
                            class="h-4 w-4 shrink-0 rounded-md border-slate-300 text-blue-600 accent-blue-600 focus:ring-blue-600"
                        >
                        <label for="terms" class="cursor-pointer text-xs font-medium text-slate-600 select-none">
                            Saya menyetujui ketentuan layanan SocratesRead
                        </label>
                    </div>

                    <button
                        type="submit"
                        class="sr-btn-primary mt-1 inline-flex h-10 w-full items-center justify-center whitespace-nowrap rounded-xl text-sm font-semibold tracking-wide text-white"
                    >
                        Buat Akun Sekarang
                    </button>
                </form>

                <div class="mt-6 border-t border-slate-100 pt-4 text-center">
                    <p class="text-xs text-slate-500">
                        Sudah memiliki akun?
                        <a href="{{ route('login') }}" class="font-semibold text-blue-600 transition-colors hover:text-blue-700 hover:underline">
                            Masuk ke akun
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const roleInput = document.getElementById('roleInput');
            const roleButtons = document.querySelectorAll('[data-role-btn]');
            const studentGroup = document.getElementById('studentFieldGroup');
            const teacherGroup = document.getElementById('teacherFieldGroup');
            const nisnInput = document.getElementById('nisn');
            const entryGradeSelect = document.getElementById('entry_grade');
            const nuptkInput = document.getElementById('nip_nuptk');

            function setRole(role) {
                roleInput.value = role;

                roleButtons.forEach((btn) => {
                    const isSelected = btn.getAttribute('data-role-btn') === role;
                    btn.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
                    btn.className = isSelected
                        ? 'inline-flex h-8 items-center justify-center rounded-lg px-3 text-xs font-semibold transition-all bg-blue-600 text-white shadow-xs'
                        : 'inline-flex h-8 items-center justify-center rounded-lg px-3 text-xs font-semibold transition-all text-slate-600 hover:text-slate-900';
                });

                if (role === 'teacher') {
                    studentGroup.classList.add('hidden');
                    teacherGroup.classList.remove('hidden');
                    nisnInput.required = false;
                    if (entryGradeSelect) entryGradeSelect.required = false;
                    nuptkInput.required = true;
                } else {
                    teacherGroup.classList.add('hidden');
                    studentGroup.classList.remove('hidden');
                    nuptkInput.required = false;
                    nisnInput.required = true;
                    if (entryGradeSelect) entryGradeSelect.required = true;
                }
            }

            roleButtons.forEach((btn) => {
                btn.addEventListener('click', () => {
                    setRole(btn.getAttribute('data-role-btn'));
                });
            });

            const passwordFields = [
                {
                    input: document.getElementById('password'),
                    button: document.getElementById('togglePasswordBtn'),
                    showLabel: 'Tampilkan kata sandi',
                    hideLabel: 'Sembunyikan kata sandi',
                },
                {
                    input: document.getElementById('password_confirmation'),
                    button: document.getElementById('toggleConfirmBtn'),
                    showLabel: 'Tampilkan konfirmasi kata sandi',
                    hideLabel: 'Sembunyikan konfirmasi kata sandi',
                },
            ];

            passwordFields.forEach((field) => {
                field.button.addEventListener('click', (e) => {
                    e.preventDefault();
                    field.input.type = field.input.type === 'password' ? 'text' : 'password';
                    const shown = field.input.type === 'text';
                    field.button.setAttribute('aria-label', shown ? field.hideLabel : field.showLabel);
                    const openIcon = field.button.querySelector('.eye-open');
                    const closedIcon = field.button.querySelector('.eye-closed');
                    if (openIcon) openIcon.classList.toggle('hidden', shown);
                    if (closedIcon) closedIcon.classList.toggle('hidden', !shown);
                });
            });
        })();
    </script>
</body>
</html>
