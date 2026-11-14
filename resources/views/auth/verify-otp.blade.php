<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2563eb">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Verifikasi OTP — SocratesRead</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800;900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('auth.partials.anywhere-styles')
</head>
<body class="min-h-screen bg-white font-sans text-[#0f172a] antialiased overflow-x-hidden">
    <div class="sr-auth-layout">
        <!-- KIRI: Hero Visual & Inspirasi -->
        @include('auth.partials.anywhere-right-panel')

        <!-- KANAN: Form OTP -->
        <div data-slot="otp-dialog" class="sr-form-panel animate-auth-enter">
            <div class="mx-auto w-full max-w-[400px]">
                <div class="mb-6 flex flex-col items-center text-center">
                    <a href="{{ route('login') }}" class="mb-4 inline-flex items-center gap-2.5 text-decoration-none">
                        <img src="{{ asset('images/logo.png') }}" alt="Logo SocratesRead" class="h-10 w-10 object-contain">
                        <span class="font-display text-xl font-extrabold tracking-tight text-slate-900">
                            Socrates<span class="text-blue-600">Read</span>
                        </span>
                    </a>

                    <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-600/10 text-blue-600">
                        <x-icon name="shield-check" class="h-6 w-6" />
                    </div>

                    <h1 class="text-2xl font-bold tracking-tight text-slate-900">Verifikasi Kode OTP</h1>
                    <p class="mt-2 text-sm text-slate-500">
                        Masukkan 6 digit kode yang dikirim ke<br>
                        <strong class="font-semibold text-slate-800">{{ $user->email }}</strong>
                    </p>
                </div>

                @if (session('status'))
                    <div class="mb-5 rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-3.5 text-center text-xs font-medium text-emerald-700" role="status">
                        {{ session('status') }}
                    </div>
                @endif

                @if (session('notice'))
                    <div class="mb-5 rounded-xl border border-blue-500/20 bg-blue-500/10 p-3.5 text-center text-xs font-medium text-blue-700" role="status">
                        {{ session('notice') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div data-slot="auth-error" class="mb-5 rounded-xl border border-red-500/20 bg-red-500/10 p-3.5 text-center text-xs font-medium text-red-600" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('verification.otp.submit') }}" id="otpForm" class="space-y-6">
                    @csrf
                    <input type="hidden" id="otp" name="otp" value="{{ old('otp') }}">

                    <div>
                        <label class="sr-only" for="otp-digit-0">Masukkan 6 Digit Kode OTP</label>
                        <div class="flex items-center justify-center gap-2 sm:gap-2.5" id="otpBoxes">
                            @for ($i = 0; $i < 6; $i++)
                                <input
                                    id="otp-digit-{{ $i }}"
                                    type="text"
                                    inputmode="numeric"
                                    maxlength="1"
                                    pattern="[0-9]"
                                    autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}"
                                    data-otp-index="{{ $i }}"
                                    class="h-12 w-11 rounded-xl border border-slate-200 bg-slate-50/70 text-center font-mono text-lg font-bold text-slate-900 shadow-xs transition-all focus:border-blue-600 focus:bg-white focus:outline-none focus:ring-4 focus:ring-blue-600/15 sm:h-13 sm:w-12 sm:text-xl @error('otp') border-red-500 bg-red-50/30 @enderror"
                                    {{ $i === 0 ? 'autofocus' : '' }}
                                >
                            @endfor
                        </div>
                    </div>

                    <button
                        type="submit"
                        id="verifySubmitBtn"
                        class="sr-btn-primary inline-flex h-11 w-full items-center justify-center whitespace-nowrap rounded-xl text-sm font-semibold tracking-wide text-white disabled:pointer-events-none disabled:opacity-50"
                    >
                        Verifikasi OTP
                    </button>
                </form>

                <div class="mt-6 flex flex-col items-center gap-3 border-t border-slate-100 pt-5 text-center">
                    <p id="countdownText" class="text-xs text-slate-500">
                        Kirim ulang kode dalam <span id="timerSeconds" class="font-semibold text-slate-800">60</span> detik
                    </p>

                    <div class="flex w-full items-center justify-between gap-3">
                        <form method="POST" action="{{ route('verification.otp.resend') }}" class="flex-1">
                            @csrf
                            <button
                                type="submit"
                                id="resendBtn"
                                disabled
                                class="inline-flex h-9 w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50 hover:text-slate-900 disabled:pointer-events-none disabled:opacity-50"
                            >
                                Kirim Ulang OTP
                            </button>
                        </form>
                        <a
                            href="{{ route('register') }}"
                            class="inline-flex h-9 items-center justify-center rounded-xl px-3 text-xs font-semibold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900"
                        >
                            Ganti Email
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const hiddenInput = document.getElementById('otp');
            const boxes = Array.from(document.querySelectorAll('[data-otp-index]'));
            const submitBtn = document.getElementById('verifySubmitBtn');
            const form = document.getElementById('otpForm');

            function syncOtpValue() {
                const code = boxes.map((b) => b.value.replace(/\D/g, '')).join('');
                hiddenInput.value = code;
                submitBtn.disabled = code.length !== 6;
            }

            if (hiddenInput.value) {
                hiddenInput.value.slice(0, 6).split('').forEach((char, idx) => {
                    if (boxes[idx]) boxes[idx].value = char;
                });
            }
            syncOtpValue();

            boxes.forEach((box, idx) => {
                box.addEventListener('input', (e) => {
                    const val = e.target.value.replace(/\D/g, '');
                    e.target.value = val.slice(-1);
                    syncOtpValue();

                    if (e.target.value && idx < boxes.length - 1) {
                        boxes[idx + 1].focus();
                        boxes[idx + 1].select();
                    }
                });

                box.addEventListener('keydown', (e) => {
                    if (e.key === 'Backspace' && !box.value && idx > 0) {
                        boxes[idx - 1].value = '';
                        boxes[idx - 1].focus();
                        syncOtpValue();
                    } else if (e.key === 'ArrowLeft' && idx > 0) {
                        boxes[idx - 1].focus();
                    } else if (e.key === 'ArrowRight' && idx < boxes.length - 1) {
                        boxes[idx + 1].focus();
                    }
                });

                box.addEventListener('paste', (e) => {
                    e.preventDefault();
                    const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
                    if (!pasted) return;
                    pasted.split('').forEach((ch, i) => {
                        if (boxes[i]) boxes[i].value = ch;
                    });
                    syncOtpValue();
                    const nextFocus = Math.min(pasted.length, boxes.length - 1);
                    boxes[nextFocus].focus();
                });
            });

            form.addEventListener('submit', () => {
                syncOtpValue();
            });

            let remaining = 60;
            const timerSpan = document.getElementById('timerSeconds');
            const countdownText = document.getElementById('countdownText');
            const resendBtn = document.getElementById('resendBtn');

            const interval = setInterval(() => {
                remaining -= 1;
                if (remaining <= 0) {
                    clearInterval(interval);
                    countdownText.textContent = 'Tidak menerima kode OTP?';
                    resendBtn.disabled = false;
                } else if (timerSpan) {
                    timerSpan.textContent = String(remaining);
                }
            }, 1000);
        })();
    </script>
</body>
</html>
