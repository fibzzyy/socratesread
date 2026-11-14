<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2563eb">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk — SocratesRead</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('auth.partials.anywhere-styles')
    <style>
        .sr-login-page .sr-hero-panel {
            background: #172033;
        }

        .sr-login-page .sr-form-panel > div {
            width: 100%;
            max-width: 420px;
        }

        .sr-login-page .sr-hero-image {
            filter: grayscale(35%) contrast(102%) brightness(0.58);
        }

        .sr-login-page .sr-hero-overlay {
            background: linear-gradient(180deg, rgba(15, 23, 42, .48), rgba(15, 23, 42, .46) 48%, rgba(15, 23, 42, .82));
        }

        .sr-login-page .sr-hero-content {
            justify-content: center;
        }

        .sr-login-page .sr-hero-title {
            max-width: 620px;
            font-size: clamp(2rem, 3.1vw, 3.25rem);
            line-height: 1.12;
            letter-spacing: -.045em;
            margin-bottom: 16px;
        }

        .sr-login-page .sr-hero-title span {
            background: none;
            color: #bfdbfe;
            -webkit-text-fill-color: #bfdbfe;
        }

        .sr-login-page .sr-hero-desc {
            max-width: 440px;
            font-size: 1rem;
            line-height: 1.75;
        }

        .sr-login-page .sr-input-field {
            display: block;
            width: 100%;
            height: 44px;
            padding: 0 12px 0 36px;
            border-radius: 10px;
            background: #fff;
            border-color: #d8dee8;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .025);
            color: #0f172a;
            font: 400 14px/1.4 'DM Sans', sans-serif;
            outline: none;
            transition: border-color .16s ease, box-shadow .16s ease;
        }

        .sr-login-page .sr-input-field:focus {
            border-color: #7aa2f7;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
        }

        .sr-login-page .sr-login-input-wrap {
            position: relative;
        }

        .sr-login-page .sr-login-input-icon {
            position: absolute;
            inset-block: 0;
            left: 10px;
            z-index: 1;
            display: flex;
            align-items: center;
            color: #94a3b8;
            pointer-events: none;
        }

        .sr-login-page .sr-login-input-icon .sr-field-icon {
            font-size: 17px;
        }

        .sr-login-page .sr-password-input {
            padding-right: 44px;
        }

        .sr-login-page .sr-login-remember-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding-top: 4px;
        }

        .sr-login-page .sr-login-remember-row input[type="checkbox"] {
            flex: 0 0 16px;
            width: 16px;
            height: 16px;
            margin: 0;
        }

        .sr-login-page .sr-login-remember-row label {
            color: #475569;
            font-size: 13px;
            line-height: 20px;
        }

        .sr-login-page .sr-field-icon {
            font-family: 'Material Symbols Outlined';
            font-size: 19px;
            font-weight: 400;
            font-style: normal;
            line-height: 1;
            letter-spacing: normal;
            text-transform: none;
            white-space: nowrap;
            word-wrap: normal;
            direction: ltr;
            -webkit-font-feature-settings: 'liga';
            -webkit-font-smoothing: antialiased;
            font-feature-settings: 'liga';
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 20;
        }

        .sr-login-page .sr-password-wrap {
            position: relative;
        }

        .sr-login-page #togglePasswordBtn {
            position: absolute;
            top: 4px;
            right: 4px;
            display: grid;
            place-items: center;
            width: 36px;
            height: 36px;
            padding: 0;
            border: 0;
            border-radius: 8px;
            color: #64748b;
            background: transparent;
            cursor: pointer;
        }

        .sr-login-page #togglePasswordBtn:hover {
            color: #334155;
            background: #f1f5f9;
        }

        .sr-login-page input:-webkit-autofill,
        .sr-login-page input:-webkit-autofill:hover,
        .sr-login-page input:-webkit-autofill:focus {
            -webkit-text-fill-color: #0f172a;
            -webkit-box-shadow: 0 0 0 1000px #fff inset;
            caret-color: #0f172a;
        }

        .sr-login-page .sr-btn-primary {
            border-radius: 10px;
            background: #2457c5;
            box-shadow: none;
        }

        .sr-login-page .sr-btn-primary:hover {
            background: #1e4daF;
            box-shadow: 0 5px 14px rgba(30, 77, 175, .18);
            transform: none;
        }

        .sr-login-page .sr-btn-primary:active {
            background: #193f91;
            box-shadow: none;
        }

        @media (prefers-reduced-motion: reduce) {
            .sr-login-page *,
            .sr-login-page *::before,
            .sr-login-page *::after {
                animation-duration: .01ms !important;
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-white font-sans text-[#0f172a] antialiased overflow-x-hidden">
    <div class="sr-auth-layout sr-auth-wave sr-login-page">
        <div class="sr-hero-panel" aria-hidden="true">
            <!-- Photo by Jason Hu on Unsplash, free under the Unsplash License. -->
            <img
                src="https://images.unsplash.com/photo-1776571661811-c311d42634d9?auto=format&amp;fit=crop&amp;w=2400&amp;q=85"
                alt=""
                class="sr-hero-image"
            >
            <div class="sr-hero-overlay"></div>
            <div class="sr-hero-content">
                <div class="sr-hero-body">
                    <h2 class="sr-hero-title">
                        Jangan berhenti<br>
                        <span>di halaman terakhir.</span>
                    </h2>
                    <p class="sr-hero-desc">
                        Temukan gagasan utama, periksa alasan, dan dukung jawabanmu dengan bukti dari bacaan.
                    </p>
                </div>
            </div>
        </div>

        <!-- KANAN: Form Masuk Bersih & Elegan -->
        <div data-slot="auth" class="sr-form-panel animate-auth-enter">
            <div class="mx-auto w-full max-w-[400px]">
                <!-- Brand Header -->
                <div class="mb-8">
                    <div class="mb-5 flex items-center justify-between">
                        <a href="{{ route('login') }}" class="group inline-flex items-center gap-2.5 text-decoration-none">
                            <img src="{{ asset('images/logo.png') }}" alt="Logo SocratesRead" class="h-10 w-10 object-contain">
                            <span class="font-display text-xl font-extrabold tracking-tight text-slate-900">
                                Socrates<span class="text-blue-600">Read</span>
                            </span>
                        </a>
                    </div>
                    <h1 class="text-[1.8rem] font-bold tracking-tight text-slate-900">Masuk ke akunmu</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-500">Lanjutkan membaca dan menguji pemahamanmu.</p>
                </div>

                @if (session('status'))
                    <div class="mb-5 rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-sm text-emerald-700" role="status">
                        {{ session('status') }}
                    </div>
                @endif

                @if (session('notice'))
                    <div class="mb-5 rounded-xl border border-blue-500/20 bg-blue-500/10 p-4 text-sm text-blue-700" role="status">
                        {{ session('notice') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div data-slot="auth-error" class="mb-5 rounded-xl border border-red-500/20 bg-red-500/10 p-4 text-sm text-red-600" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" id="loginForm" data-slot="auth-form" class="space-y-4">
                    @csrf

                    <!-- Email -->
                    <div class="space-y-1.5">
                        <label for="email" class="text-sm font-medium text-slate-700">Email</label>
                        <div class="sr-login-input-wrap">
                            <span class="sr-login-input-icon">
                                <span class="sr-field-icon" aria-hidden="true">mail</span>
                            </span>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                placeholder="nama@email.com"
                                autocomplete="email"
                                required
                                autofocus
                                class="sr-input-field border @error('email') border-red-500 bg-red-50/20 @enderror"
                            >
                        </div>
                        @error('email')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label for="password" class="text-sm font-medium text-slate-700">Kata sandi</label>
                            <a
                                href="{{ route('password.request') }}"
                                class="text-xs font-medium text-blue-600 transition-colors hover:text-blue-700 hover:underline"
                            >
                                Lupa kata sandi?
                            </a>
                        </div>
                        <div class="sr-password-wrap sr-login-input-wrap">
                            <span class="sr-login-input-icon">
                                <span class="sr-field-icon" aria-hidden="true">lock</span>
                            </span>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                placeholder="••••••••"
                                autocomplete="current-password"
                                required
                                class="sr-input-field border sr-password-input @error('password') border-red-500 bg-red-50/20 @enderror"
                            >
                            <button
                                type="button"
                                id="togglePasswordBtn"
                                aria-label="Tampilkan kata sandi"
                            >
                                <span class="sr-field-icon eye-open" aria-hidden="true">visibility</span>
                                <span class="sr-field-icon eye-closed hidden" aria-hidden="true">visibility_off</span>
                            </button>
                        </div>
                        @error('password')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Remember me -->
                    <div class="sr-login-remember-row">
                        <input
                            id="remember"
                            name="remember"
                            type="checkbox"
                            class="rounded accent-blue-600"
                        >
                        <label for="remember" class="cursor-pointer select-none">
                            Ingat saya
                        </label>
                    </div>

                    <!-- Submit -->
                    <button
                        type="submit"
                        class="sr-btn-primary mt-2 inline-flex h-11 w-full items-center justify-center whitespace-nowrap rounded-xl text-sm font-semibold tracking-wide text-white"
                    >
                        Masuk
                    </button>
                </form>

                <!-- Footer -->
                <div class="mt-8 border-t border-slate-100 pt-6 text-center">
                    <p class="text-xs text-slate-500">
                        Belum memiliki akun?
                        <a href="{{ route('register') }}" class="font-semibold text-blue-600 transition-colors hover:text-blue-700 hover:underline">
                            Daftar gratis
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

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
