<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ffffff">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Verifikasi Email — SocratesRead</title>
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

        <section class="w-full max-w-[460px] rounded-xl border border-[#e9edf4] bg-white p-[34px] max-[520px]:px-5 max-[520px]:py-[25px]" aria-labelledby="otp-title">
            <div class="mb-4 text-center">
                <div class="mx-auto mb-3 flex size-14 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                    <x-icon name="shield-check" class="size-7" />
                </div>
                <p class="text-[10px] font-bold tracking-[1px] text-[#667085] uppercase">VERIFIKASI KEAMANAN</p>
                <h1 id="otp-title" class="text-[23px] font-bold leading-tight text-[#172033] font-display">Verifikasi Kode OTP</h1>
                <p class="mt-2 text-[13px] leading-relaxed text-[#667085]">
                    Kode 6 digit telah dikirimkan ke alamat email:<br>
                    <strong class="text-[#172033]">{{ $user->email }}</strong>
                </p>
            </div>

            @if (session('status'))
                <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-3 text-xs leading-5 text-green-800" role="status">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('notice'))
                <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-800" role="status">
                    {{ session('notice') }}
                </div>
            @endif

            <form class="grid gap-4" method="POST" action="{{ route('verification.otp.submit') }}">
                @csrf
                <div>
                    <label class="block text-center text-xs font-semibold text-[#344054] mb-2" for="otp">
                        Masukkan 6 Digit Kode OTP
                    </label>
                    <input
                        class="min-h-[52px] w-full rounded-lg border border-[#d0d5dd] bg-white text-center font-mono text-2xl font-bold tracking-[8px] text-[#172033] outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-600/10 @error('otp') border-red-500 @enderror"
                        id="otp"
                        name="otp"
                        type="text"
                        maxlength="6"
                        pattern="[0-9]{6}"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        placeholder="••••••"
                        required
                        autofocus
                    >
                    @error('otp')
                        <span class="mt-1.5 block text-center text-xs text-red-600">{{ $message }}</span>
                    @enderror
                </div>

                <button class="flex min-h-11 items-center justify-center gap-2 rounded-lg border border-blue-600 bg-blue-600 text-sm font-bold text-white hover:bg-blue-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 transition-colors" type="submit">
                    <span>Verifikasi Akun</span>
                    <x-icon name="arrow-right" class="size-4" />
                </button>
            </form>

            <div class="mt-6 border-t border-[#f0f2f5] pt-4 text-center">
                <p class="text-xs text-[#667085]">
                    Tidak menerima email kode?
                </p>
                <form method="POST" action="{{ route('verification.otp.resend') }}" class="mt-2">
                    @csrf
                    <button type="submit" id="resendBtn" class="text-xs font-semibold text-blue-600 hover:text-blue-800 disabled:opacity-50">
                        Kirim Ulang Kode OTP
                    </button>
                </form>
            </div>
        </section>

        <p class="m-0 text-[11px] text-[#667085]">Terus bertanya, terus bertumbuh.</p>
    </main>
</body>
</html>
