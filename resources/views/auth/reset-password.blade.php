<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2563eb">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Perbarui Kata Sandi — SocratesRead</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('auth.partials.anywhere-styles')
</head>
<body class="min-h-screen bg-white font-sans text-[#0f172a] antialiased overflow-x-hidden">
    <div class="relative min-h-screen w-full bg-white flex">
        <div data-slot="auth" class="sr-split-card animate-auth-enter">
                <!-- LEFT: 21st.dev Auth Reset Password Form -->
                <div data-slot="auth-reset-password" class="sr-card-left">
                    <div class="mx-auto w-full max-w-[380px]">
                        <div class="mb-7">
                            <a href="{{ route('login') }}" class="mb-5 inline-flex items-center gap-2.5 text-decoration-none">
                                <img src="{{ asset('images/logo.png') }}" alt="Logo SocratesRead" class="h-9 w-9 object-contain">
                                <span class="font-display text-xl font-extrabold tracking-tight text-slate-900">
                                    Socrates<span class="text-blue-600">Read</span>
                                </span>
                            </a>
                            <h1 class="text-3xl font-semibold tracking-tight text-slate-900">Perbarui kata sandi</h1>
                            <p class="mt-2 text-sm text-slate-500">
                                Buat kata sandi baru untuk <strong class="text-slate-800">{{ $user->email }}</strong>
                            </p>
                        </div>

                        @if (session('status'))
                            <div class="mb-6 rounded-lg border border-emerald-500/20 bg-emerald-500/10 p-4 text-sm text-emerald-700" role="status">
                                {{ session('status') }}
                            </div>
                        @endif

                        @if ($errors->any())
                            <div data-slot="auth-error" class="mb-6 rounded-lg border border-red-500/20 bg-red-500/10 p-4 text-sm text-red-600" role="alert">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        <form action="{{ route('password.update') }}" method="POST" data-slot="auth-form" class="space-y-5">
                            @csrf
                            <div class="space-y-2">
                                <label for="password" class="text-sm font-medium leading-none text-slate-900">Kata Sandi Baru</label>
                                <div class="relative">
                                    <input
                                        id="password"
                                        name="password"
                                        type="password"
                                        placeholder="Minimal 8 karakter"
                                        autocomplete="new-password"
                                        required
                                        autofocus
                                        class="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 pr-10 text-sm text-slate-900 placeholder:text-slate-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2 @error('password') border-red-500 @enderror"
                                    >
                                    <button
                                        type="button"
                                        id="togglePasswordBtn"
                                        aria-label="Tampilkan kata sandi"
                                        class="absolute right-0 top-0 inline-flex h-10 w-10 items-center justify-center rounded-md text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900"
                                    >
                                        <x-icon name="eye" class="h-4 w-4 eye-open" />
                                        <x-icon name="eye-off" class="h-4 w-4 eye-closed hidden" />
                                    </button>
                                </div>
                                @error('password')
                                    <p class="text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-2">
                                <label for="password_confirmation" class="text-sm font-medium leading-none text-slate-900">Ulangi Kata Sandi Baru</label>
                                <div class="relative">
                                    <input
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        type="password"
                                        placeholder="••••••••"
                                        autocomplete="new-password"
                                        required
                                        class="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 pr-10 text-sm text-slate-900 placeholder:text-slate-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2"
                                    >
                                    <button
                                        type="button"
                                        id="toggleConfirmBtn"
                                        aria-label="Tampilkan konfirmasi kata sandi"
                                        class="absolute right-0 top-0 inline-flex h-10 w-10 items-center justify-center rounded-md text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900"
                                    >
                                        <x-icon name="eye" class="h-4 w-4 eye-open" />
                                        <x-icon name="eye-off" class="h-4 w-4 eye-closed hidden" />
                                    </button>
                                </div>
                            </div>

                            <button
                                type="submit"
                                class="inline-flex h-10 w-full items-center justify-center whitespace-nowrap rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-600/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2"
                            >
                                Simpan kata sandi
                            </button>
                        </form>
                    </div>
                </div>

                @include('auth.partials.anywhere-right-panel')
            </div>
    </div>

    <script>
        (() => {
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
