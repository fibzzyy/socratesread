<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ffffff">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk — SocratesRead</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f7f8fa] font-sans text-[#172033] antialiased">
    <main class="grid min-h-screen content-center justify-items-center gap-[22px] px-[18px] py-[38px] max-[520px]:gap-[18px] max-[520px]:px-[14px] max-[520px]:py-[22px]">
        <a class="font-display text-[19px] font-bold leading-none tracking-[-.5px] text-[#172033]" href="{{ route('login') }}">
            socrates<strong class="text-blue-600">read</strong>
        </a>

        <section class="w-full max-w-[450px] rounded-xl border border-[#e9edf4] bg-white p-[34px] max-[520px]:px-5 max-[520px]:py-[25px]" aria-labelledby="auth-title">
            <p class="mb-[6px] text-[10px] font-bold tracking-[1px] text-[#667085] uppercase">RUANG BELAJAR PRIBADIMU</p>
            <h1 id="auth-title" class="mb-1 text-[24px] font-bold leading-[1.25] tracking-[-.7px] text-[#172033] font-display">
                Selamat Datang Kembali
            </h1>
            <p class="mb-5 text-[13px] leading-[1.5] text-[#667085]">
                Masuk untuk melanjutkan bacaan dan latihan berpikir kritis Anda.
            </p>

            @if (session('status'))
                <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-3 text-xs leading-5 text-green-800" role="status">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('notice'))
                <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-3 text-xs leading-5 text-blue-800" role="status">
                    {{ session('notice') }}
                </div>
            @endif

            <form class="grid gap-3" method="POST" action="{{ route('login') }}" id="loginForm">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-[#344054] mb-1" for="email">Alamat Email</label>
                    <input
                        class="min-h-[42px] w-full rounded-lg border border-[#d0d5dd] bg-white px-3 text-[13px] text-[#172033] outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-600/10 @error('email') border-red-500 @enderror"
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        placeholder="nama@email.com"
                        required
                        autofocus
                    >
                    @error('email') <span class="text-[11px] text-red-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs font-semibold text-[#344054]" for="password">Kata Sandi</label>
                    </div>
                    <div class="relative">
                        <input
                            class="min-h-[42px] w-full rounded-lg border border-[#d0d5dd] bg-white px-3 pr-10 text-[13px] text-[#172033] outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-600/10 @error('password') border-red-500 @enderror"
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            placeholder="Masukkan kata sandi"
                            required
                        >
                        <button type="button" id="togglePasswordBtn" class="absolute right-3 top-1/2 -translate-y-1/2 text-[#667085] hover:text-[#172033]" aria-label="Tampilkan kata sandi">
                            <x-icon name="eye" class="h-4 w-4 eye-open" />
                            <x-icon name="eye-off" class="h-4 w-4 eye-closed hidden" />
                        </button>
                    </div>
                    @error('password') <span class="text-[11px] text-red-600 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <label class="my-1 flex items-center gap-2 text-xs text-[#667085]">
                    <input class="size-4 accent-blue-600 rounded" type="checkbox" name="remember">
                    <span>Ingat saya di perangkat ini</span>
                </label>

                <button class="mt-1 flex min-h-11 items-center justify-center gap-2 rounded-lg border border-blue-600 bg-blue-600 text-[13px] font-bold text-white hover:bg-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 transition-colors" type="submit">
                    <span>Masuk ke Ruang Belajar</span>
                    <x-icon name="arrow-right" class="size-4" />
                </button>
            </form>

            <p class="mt-5 text-center text-xs text-[#667085]">
                Belum memiliki akun? <a class="font-bold text-blue-700 hover:underline" href="{{ route('register') }}">Daftar akun baru</a>
            </p>
        </section>

        <p class="m-0 text-[11px] text-[#667085]">Terus bertanya, terus bertumbuh.</p>
    </main>

    <script>
        (() => {
            const password = document.getElementById('password');
            const toggle = document.getElementById('togglePasswordBtn');

            toggle.addEventListener('click', (e) => {
                e.preventDefault();
                password.type = password.type === 'password' ? 'text' : 'password';
                const shown = password.type === 'text';
                toggle.setAttribute('aria-label', `${shown ? 'Sembunyikan' : 'Tampilkan'} kata sandi`);
                const openIcon = toggle.querySelector('.eye-open');
                const closedIcon = toggle.querySelector('.eye-closed');
                if (openIcon) openIcon.classList.toggle('hidden', shown);
                if (closedIcon) closedIcon.classList.toggle('hidden', !shown);
            });
        })();
    </script>
</body>
</html>
