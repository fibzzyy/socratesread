<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2563eb">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Reset Kata Sandi — SocratesRead</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('auth.partials.anywhere-styles')
    <style>
        .sr-forgot-page .sr-forgot-email-wrap {
            position: relative;
        }

        .sr-forgot-page .sr-forgot-email-icon {
            position: absolute;
            z-index: 1;
            top: 50%;
            left: 12px;
            display: flex;
            align-items: center;
            color: #94a3b8;
            pointer-events: none;
            transform: translateY(-50%);
        }

        .sr-forgot-page #email {
            height: 44px;
            padding-left: 42px;
            border-radius: 10px;
            background: #fff;
        }
    </style>
</head>
<body class="min-h-screen bg-white font-sans text-[#0f172a] antialiased overflow-x-hidden">
    <div class="sr-auth-layout sr-auth-wave sr-forgot-page">
        <!-- KIRI: Hero Visual & Inspirasi -->
        @include('auth.partials.anywhere-right-panel')

        <!-- KANAN: Form Forgot Password -->
        <div data-slot="auth" class="sr-form-panel animate-auth-enter">
            <div class="mx-auto w-full max-w-[400px]">
                <div class="mb-7">
                    <div class="mb-5 flex items-center">
                        <a href="{{ route('login') }}" class="group inline-flex items-center gap-2.5 text-decoration-none">
                            <img src="{{ asset('images/logo.png') }}" alt="Logo SocratesRead" class="h-10 w-10 object-contain">
                            <span class="font-display text-xl font-extrabold tracking-tight text-slate-900">
                                Socrates<span class="text-blue-600">Read</span>
                            </span>
                        </a>
                    </div>
                    <h1 class="text-[1.8rem] font-bold tracking-tight text-slate-900">Lupa kata sandi?</h1>
                    <p class="mt-2 text-sm text-slate-500">Masukkan email Anda untuk menerima 6 digit kode OTP verifikasi.</p>
                </div>

                @if (session('status'))
                    <div class="mb-5 rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-sm text-emerald-700" role="status">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div data-slot="auth-error" class="mb-5 rounded-xl border border-red-500/20 bg-red-500/10 p-4 text-sm text-red-600" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form action="{{ route('password.email') }}" method="POST" data-slot="auth-form" class="space-y-4">
                    @csrf
                    <div class="space-y-1.5">
                        <label for="email" class="text-xs font-semibold text-slate-700">Email</label>
                        <div class="sr-forgot-email-wrap">
                            <span class="sr-forgot-email-icon material-symbols-outlined text-[18px] leading-none" aria-hidden="true">mail</span>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                placeholder="nama@email.com"
                                autocomplete="email"
                                required
                                autofocus
                                class="sr-input-field flex h-11 w-full rounded-xl border border-slate-200/90 bg-white pl-10 pr-3.5 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none @error('email') border-red-500 bg-red-50/20 @enderror"
                            >
                        </div>
                        @error('email')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button
                        type="submit"
                        class="sr-btn-primary mt-2 inline-flex h-11 w-full items-center justify-center whitespace-nowrap rounded-xl text-sm font-semibold tracking-wide text-white"
                    >
                        Kirim Kode OTP
                    </button>
                </form>

                <div class="mt-8 border-t border-slate-100 pt-6 text-center">
                    <p class="text-xs text-slate-500">
                        Ingat kata sandi Anda?
                        <a href="{{ route('login') }}" class="font-semibold text-blue-600 transition-colors hover:text-blue-700 hover:underline">
                            Masuk kembali
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
