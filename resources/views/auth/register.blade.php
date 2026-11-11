<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ffffff">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Daftar Akun — SocratesRead</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f7f8fa] font-sans text-[#172033] antialiased">
    <main class="grid min-h-screen content-center justify-items-center gap-[22px] px-[18px] py-[38px] max-[520px]:gap-[18px] max-[520px]:px-[14px] max-[520px]:py-[22px]">
        <a class="inline-flex items-center gap-2.5 font-display text-[20px] font-bold leading-none tracking-[-.5px] text-[#172033]" href="{{ route('register') }}">
            <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-white shadow-xs p-1 border border-slate-200">
                <img src="{{ asset('images/logo.png') }}" alt="Logo SocratesRead" class="w-full h-full object-contain">
            </span>
            <span>socrates<strong class="text-blue-600">read</strong></span>
        </a>

        <section class="w-full max-w-[480px] rounded-xl border border-[#e9edf4] bg-white p-[34px] max-[520px]:px-5 max-[520px]:py-[25px]" aria-labelledby="auth-title">
            <p class="mb-[6px] text-[10px] font-bold tracking-[1px] text-[#667085] uppercase">PENDAFTARAN AKUN</p>
            <h1 id="auth-title" class="mb-1 text-[24px] font-bold leading-[1.25] tracking-[-.7px] text-[#172033] font-display">
                Buat Akun Baru
            </h1>
            <p class="mb-5 text-[13px] leading-[1.5] text-[#667085]">
                Pilih jenis akun Anda untuk memulai ruang belajar dan literasi berpikir kritis.
            </p>

            <form class="grid gap-3" method="POST" action="{{ route('register') }}" id="registerForm">
                @csrf
                <input type="hidden" name="role" id="roleInput" value="{{ old('role', 'student') }}">

                <!-- Selector Peran: Siswa atau Guru -->
                <div>
                    <label class="block text-xs font-semibold text-[#344054] mb-2">Daftar Sebagai:</label>
                    <div class="grid grid-cols-2 gap-2.5">
                        <button
                            type="button"
                            id="btnRoleStudent"
                            class="role-select-btn flex items-center justify-center gap-2 rounded-lg border-2 p-2.5 text-xs font-bold transition-all {{ old('role', 'student') === 'student' ? 'border-blue-600 bg-blue-50/70 text-blue-700 shadow-sm' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300' }}"
                        >
                            <x-icon name="graduation-cap" class="size-4 shrink-0" />
                            <span>Akun Siswa</span>
                        </button>
                        <button
                            type="button"
                            id="btnRoleTeacher"
                            class="role-select-btn flex items-center justify-center gap-2 rounded-lg border-2 p-2.5 text-xs font-bold transition-all {{ old('role') === 'teacher' ? 'border-blue-600 bg-blue-50/70 text-blue-700 shadow-sm' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300' }}"
                        >
                            <x-icon name="library" class="size-4 shrink-0" />
                            <span>Akun Guru</span>
                        </button>
                    </div>
                    @error('role') <span class="text-[11px] text-red-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Nama Lengkap -->
                <div>
                    <label class="block text-xs font-semibold text-[#344054] mb-1" for="name">Nama Lengkap</label>
                    <input class="min-h-[42px] w-full rounded-lg border border-[#d0d5dd] bg-white px-3 text-[13px] text-[#172033] outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-600/10 @error('name') border-red-500 @enderror" id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" maxlength="120" placeholder="contoh: Ahmad Pratama" required autofocus>
                    @error('name') <span class="text-[11px] text-red-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-xs font-semibold text-[#344054] mb-1" for="email">Alamat Email</label>
                    <input class="min-h-[42px] w-full rounded-lg border border-[#d0d5dd] bg-white px-3 text-[13px] text-[#172033] outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-600/10 @error('email') border-red-500 @enderror" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" placeholder="nama@email.com" required>
                    <small class="text-[11px] text-slate-500 mt-0.5 block">Kode verifikasi OTP akan dikirimkan ke email ini.</small>
                    @error('email') <span class="text-[11px] text-red-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Asal Sekolah -->
                <div>
                    <label class="block text-xs font-semibold text-[#344054] mb-1" for="school_origin">Asal Sekolah</label>
                    <input class="min-h-[42px] w-full rounded-lg border border-[#d0d5dd] bg-white px-3 text-[13px] text-[#172033] outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-600/10 @error('school_origin') border-red-500 @enderror" id="school_origin" name="school_origin" type="text" value="{{ old('school_origin') }}" maxlength="160" placeholder="contoh: SMA Negeri 1 Jakarta" required>
                    @error('school_origin') <span class="text-[11px] text-red-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Field NISN (Hanya untuk Siswa) -->
                <div id="fieldNisn" class="{{ old('role', 'student') === 'student' ? 'block' : 'hidden' }}">
                    <label class="block text-xs font-semibold text-[#344054] mb-1" for="nisn">
                        NISN <span class="text-[11px] font-normal text-slate-500">(Nomor Induk Siswa Nasional)</span>
                    </label>
                    <input class="min-h-[42px] w-full rounded-lg border border-[#d0d5dd] bg-white px-3 text-[13px] text-[#172033] outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-600/10 @error('nisn') border-red-500 @enderror" id="nisn" name="nisn" type="text" value="{{ old('nisn') }}" maxlength="50" placeholder="10 digit NISN">
                    @error('nisn') <span class="text-[11px] text-red-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Field Tingkat Kelas Saat Masuk (Hanya untuk Siswa) -->
                <div id="fieldGrade" class="{{ old('role', 'student') === 'student' ? 'block' : 'hidden' }}">
                    <label class="block text-xs font-semibold text-[#344054] mb-1.5">
                        Tingkat Kelas Saat Ini
                    </label>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach ([10 => 'Kelas 10', 11 => 'Kelas 11', 12 => 'Kelas 12'] as $gVal => $gName)
                            <label class="flex items-center justify-center rounded-lg border border-slate-300 p-2 text-xs font-semibold text-slate-700 cursor-pointer hover:border-blue-500 hover:bg-blue-50/50 has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50 has-[:checked]:text-blue-700 has-[:checked]:shadow-sm transition">
                                <input type="radio" name="entry_grade" value="{{ $gVal }}" class="sr-only" {{ (string) old('entry_grade', '10') === (string) $gVal ? 'checked' : '' }}>
                                <span>{{ $gName }}</span>
                            </label>
                        @endforeach
                    </div>
                    <small class="text-[11px] text-slate-500 mt-1 block">Tahun akses modul & bacaan akan disesuaikan otomatis sampai Anda lulus.</small>
                    @error('entry_grade') <span class="text-[11px] text-red-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Field NUPTK (Hanya untuk Guru) -->
                <div id="fieldNuptk" class="{{ old('role') === 'teacher' ? 'block' : 'hidden' }}">
                    <label class="block text-xs font-semibold text-[#344054] mb-1" for="nip_nuptk">NUPTK</label>
                    <input class="min-h-[42px] w-full rounded-lg border border-[#d0d5dd] bg-white px-3 text-[13px] text-[#172033] outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-600/10 @error('nip_nuptk') border-red-500 @enderror" id="nip_nuptk" name="nip_nuptk" type="text" value="{{ old('nip_nuptk') }}" maxlength="50" placeholder="Masukkan NUPTK">
                    @error('nip_nuptk') <span class="text-[11px] text-red-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Kata Sandi -->
                <div>
                    <label class="block text-xs font-semibold text-[#344054] mb-1" for="password">Kata Sandi</label>
                    <div class="relative">
                        <input class="min-h-[42px] w-full rounded-lg border border-[#d0d5dd] bg-white px-3 pr-10 text-[13px] text-[#172033] outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-600/10 @error('password') border-red-500 @enderror" id="password" name="password" type="password" autocomplete="new-password" minlength="8" placeholder="Minimal 8 karakter" required>
                        <button type="button" class="password-toggle-btn absolute right-3 top-1/2 -translate-y-1/2 text-[#667085] hover:text-[#172033]" data-target="password" aria-label="Tampilkan kata sandi">
                            <x-icon name="eye" class="h-4 w-4 eye-open" />
                            <x-icon name="eye-off" class="h-4 w-4 eye-closed hidden" />
                        </button>
                    </div>
                    @error('password') <span class="text-[11px] text-red-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Konfirmasi Kata Sandi -->
                <div>
                    <label class="block text-xs font-semibold text-[#344054] mb-1" for="password_confirmation">Ulangi Kata Sandi</label>
                    <div class="relative">
                        <input class="min-h-[42px] w-full rounded-lg border border-[#d0d5dd] bg-white px-3 pr-10 text-[13px] text-[#172033] outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-600/10" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" placeholder="Masukkan kembali kata sandi" required>
                        <button type="button" class="password-toggle-btn absolute right-3 top-1/2 -translate-y-1/2 text-[#667085] hover:text-[#172033]" data-target="password_confirmation" aria-label="Tampilkan kata sandi">
                            <x-icon name="eye" class="h-4 w-4 eye-open" />
                            <x-icon name="eye-off" class="h-4 w-4 eye-closed hidden" />
                        </button>
                    </div>
                </div>

                <button class="mt-2 flex min-h-11 items-center justify-center gap-2 rounded-lg border border-blue-600 bg-blue-600 text-[13px] font-bold text-white hover:bg-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 transition-colors" type="submit">
                    <span>Lanjutkan</span>
                    <x-icon name="arrow-right" class="size-4" />
                </button>
            </form>

            <p class="mt-5 text-center text-xs text-[#667085]">
                Sudah memiliki akun? <a class="font-bold text-blue-700 hover:underline" href="{{ route('login') }}">Masuk di sini</a>
            </p>
        </section>

        <p class="m-0 text-[11px] text-[#667085]">Terus bertanya, terus bertumbuh.</p>
    </main>

    <script>
        (() => {
            const roleInput = document.getElementById('roleInput');
            const btnStudent = document.getElementById('btnRoleStudent');
            const btnTeacher = document.getElementById('btnRoleTeacher');
            const fieldNisn = document.getElementById('fieldNisn');
            const fieldGrade = document.getElementById('fieldGrade');
            const fieldNuptk = document.getElementById('fieldNuptk');
            const inputNisn = document.getElementById('nisn');
            const inputNuptk = document.getElementById('nip_nuptk');

            function setRole(role) {
                roleInput.value = role;
                if (role === 'teacher') {
                    btnTeacher.classList.add('border-blue-600', 'bg-blue-50/70', 'text-blue-700', 'shadow-sm');
                    btnTeacher.classList.remove('border-slate-200', 'bg-white', 'text-slate-600');

                    btnStudent.classList.remove('border-blue-600', 'bg-blue-50/70', 'text-blue-700', 'shadow-sm');
                    btnStudent.classList.add('border-slate-200', 'bg-white', 'text-slate-600');

                    fieldNuptk.classList.remove('hidden');
                    fieldNuptk.classList.add('block');
                    inputNuptk.setAttribute('required', 'required');

                    fieldNisn.classList.add('hidden');
                    fieldNisn.classList.remove('block');
                    inputNisn.removeAttribute('required');

                    if (fieldGrade) {
                        fieldGrade.classList.add('hidden');
                        fieldGrade.classList.remove('block');
                    }
                } else {
                    btnStudent.classList.add('border-blue-600', 'bg-blue-50/70', 'text-blue-700', 'shadow-sm');
                    btnStudent.classList.remove('border-slate-200', 'bg-white', 'text-slate-600');

                    btnTeacher.classList.remove('border-blue-600', 'bg-blue-50/70', 'text-blue-700', 'shadow-sm');
                    btnTeacher.classList.add('border-slate-200', 'bg-white', 'text-slate-600');

                    fieldNisn.classList.remove('hidden');
                    fieldNisn.classList.add('block');
                    inputNisn.setAttribute('required', 'required');

                    fieldNuptk.classList.add('hidden');
                    fieldNuptk.classList.remove('block');
                    inputNuptk.removeAttribute('required');

                    if (fieldGrade) {
                        fieldGrade.classList.remove('hidden');
                        fieldGrade.classList.add('block');
                    }
                }
            }

            btnStudent.addEventListener('click', () => setRole('student'));
            btnTeacher.addEventListener('click', () => setRole('teacher'));

            // Set initial state
            setRole(roleInput.value || 'student');

            // Password Toggles
            const passwordFields = [
                { input: document.getElementById('password') },
                { input: document.getElementById('password_confirmation') }
            ];

            passwordFields.forEach((field) => {
                const button = field.input.parentElement.querySelector('button');
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    field.input.type = field.input.type === 'password' ? 'text' : 'password';
                    const shown = field.input.type === 'text';
                    button.setAttribute('aria-label', `${shown ? 'Sembunyikan' : 'Tampilkan'} kata sandi`);
                    const openIcon = button.querySelector('.eye-open');
                    const closedIcon = button.querySelector('.eye-closed');
                    if (openIcon) openIcon.classList.toggle('hidden', shown);
                    if (closedIcon) closedIcon.classList.toggle('hidden', !shown);
                });
            });
        })();
    </script>
</body>
</html>
