<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f7f8fa">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk — SocratesRead</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
</head>
<body class="login-scene">
    <main class="login-page">
        <a class="brand" href="{{ route('login') }}" aria-label="SocratesRead">
            socrates<strong>read</strong>
        </a>

        <section class="login-card" aria-labelledby="auth-title">
            <p class="eyebrow">RUANG BELAJAR PRIBADIMU</p>
            <h1 id="auth-title">Selamat Datang Kembali</h1>
            <p class="subtitle">Masuk untuk melanjutkan bacaan dan latihan berpikir kritis Anda.</p>

            @if (session('status'))
                <div class="notice notice-success" role="status">{{ session('status') }}</div>
            @endif

            @if (session('notice'))
                <div class="notice notice-info" role="status">{{ session('notice') }}</div>
            @endif

            <form class="login-form" method="POST" action="{{ route('login') }}" id="loginForm">
                @csrf
                <div class="field">
                    <label for="email">Alamat Email</label>
                    <input class="login-input @error('email') is-invalid @enderror" id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" placeholder="nama@email.com" required autofocus>
                    @error('email') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="password">Kata Sandi</label>
                    <div class="password-wrap">
                        <input class="login-input password-input @error('password') is-invalid @enderror" id="password" type="password" name="password" autocomplete="current-password" placeholder="Masukkan kata sandi" required>
                        <button type="button" id="togglePasswordBtn" class="toggle-password" aria-label="Tampilkan kata sandi">
                            <x-icon name="eye" class="eye-open" />
                            <x-icon name="eye-off" class="eye-closed hidden" />
                        </button>
                    </div>
                    @error('password') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <label class="remember" for="remember">
                    <input id="remember" type="checkbox" name="remember">
                    <span>Ingat saya di perangkat ini</span>
                </label>

                <button type="submit" class="btn-primary">
                    <span>Masuk ke Ruang Belajar</span>
                    <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </button>
            </form>

            <p class="signup">Belum memiliki akun? <a href="{{ route('register') }}">Daftar akun baru</a></p>
        </section>

        <p class="page-footer">Terus bertanya, terus bertumbuh.</p>
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
