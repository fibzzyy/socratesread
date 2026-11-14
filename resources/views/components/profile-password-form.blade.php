<section class="password-edit-panel bg-white rounded-3xl border border-slate-200/80 shadow-xs p-6 sm:p-8 mt-6" aria-labelledby="change-password-heading">
    <div class="flex items-center justify-between pb-5 border-b border-slate-100">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 m-0">KEAMANAN AKUN</p>
            <h2 id="change-password-heading" class="text-xl font-bold text-slate-900 mt-1 m-0">Kelola Akses Akun</h2>
            <p class="text-xs text-slate-500 mt-0.5 m-0">Perbarui email dan kata sandi untuk melindungi privasi akun Anda.</p>
        </div>
        <span class="w-10 h-10 text-slate-400 flex items-center justify-center">
            <x-icon name="shield-check" class="w-5 h-5" />
        </span>
    </div>

    {{-- Form 1: Alamat Email --}}
    <div class="account-email-section mt-6 pt-2">
        <div class="flex items-center justify-between flex-wrap gap-2">
            <div>
                <h3 class="text-base font-bold text-slate-800 m-0">Alamat Email</h3>
                <p class="text-xs text-slate-500 mt-0.5 m-0">
                    Email aktif saat ini: <strong class="text-slate-800 font-semibold">{{ $user->email }}</strong>
                </p>
            </div>
            <span class="inline-flex items-center gap-1 text-[11px] font-medium text-slate-500">
                <x-icon name="check" class="w-3 h-3 text-slate-500" />
                Terverifikasi
            </span>
        </div>

        <form method="POST" action="{{ route('settings.email.update') }}" class="profile-form mt-4">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="email" class="block text-xs font-semibold text-slate-700 mb-1.5">Alamat Email Baru</label>
                    <input id="email" 
                           name="email" 
                           type="email" 
                           class="w-full px-4 py-2.5 rounded-xl border @error('email') border-rose-300 ring-2 ring-rose-100 @else border-slate-200 @enderror bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all" 
                           value="{{ old('email', $user->email) }}" 
                           autocomplete="email" 
                           required>
                    @error('email')
                        <small class="text-xs text-rose-600 font-medium mt-1 block">{{ $message }}</small>
                    @enderror
                </div>

                <div>
                    <label for="email_current_password" class="block text-xs font-semibold text-slate-700 mb-1.5">Konfirmasi Kata Sandi Saat Ini</label>
                    <input id="email_current_password" 
                           name="email_current_password" 
                           type="password" 
                           class="w-full px-4 py-2.5 rounded-xl border @error('email_current_password') border-rose-300 ring-2 ring-rose-100 @else border-slate-200 @enderror bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all" 
                           placeholder="Masukkan sandi saat ini"
                           autocomplete="current-password" 
                           required>
                    @error('email_current_password')
                        <small class="text-xs text-rose-600 font-medium mt-1 block">{{ $message }}</small>
                    @enderror
                </div>
            </div>

            <div class="mt-4 flex justify-end">
                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 text-xs font-bold text-white bg-slate-800 hover:bg-slate-900 active:scale-98 rounded-xl shadow-xs transition-all cursor-pointer">
                    <x-icon name="check" class="w-3.5 h-3.5" />
                    <span>Simpan Alamat Email</span>
                </button>
            </div>
        </form>
    </div>

    <div class="h-px bg-slate-100 my-6" role="separator"></div>

    {{-- Form 2: Ubah Kata Sandi --}}
    <div class="account-password-section">
        <div>
            <h3 class="text-base font-bold text-slate-800 m-0">Ubah Kata Sandi</h3>
            <p class="text-xs text-slate-500 mt-0.5 m-0">Gunakan minimal 8 karakter dengan kombinasi huruf dan angka.</p>
        </div>

        <form method="POST" action="{{ route('settings.password.update') }}" class="profile-form mt-4">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="current_password" class="block text-xs font-semibold text-slate-700 mb-1.5">Kata Sandi Saat Ini</label>
                    <input id="current_password" 
                           name="current_password" 
                           type="password" 
                           class="w-full px-4 py-2.5 rounded-xl border @error('current_password') border-rose-300 ring-2 ring-rose-100 @else border-slate-200 @enderror bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all" 
                           placeholder="Sandi saat ini"
                           autocomplete="current-password" 
                           required>
                    @error('current_password')
                        <small class="text-xs text-rose-600 font-medium mt-1 block">{{ $message }}</small>
                    @enderror
                </div>

                <div>
                    <label for="new_password" class="block text-xs font-semibold text-slate-700 mb-1.5">Kata Sandi Baru</label>
                    <input id="new_password" 
                           name="new_password" 
                           type="password" 
                           class="w-full px-4 py-2.5 rounded-xl border @error('new_password') border-rose-300 ring-2 ring-rose-100 @else border-slate-200 @enderror bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all" 
                           placeholder="Minimal 8 karakter"
                           autocomplete="new-password" 
                           minlength="8" 
                           required>
                    @error('new_password')
                        <small class="text-xs text-rose-600 font-medium mt-1 block">{{ $message }}</small>
                    @enderror
                </div>

                <div>
                    <label for="new_password_confirmation" class="block text-xs font-semibold text-slate-700 mb-1.5">Konfirmasi Kata Sandi Baru</label>
                    <input id="new_password_confirmation" 
                           name="new_password_confirmation" 
                           type="password" 
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all" 
                           placeholder="Ulangi kata sandi baru"
                           autocomplete="new-password" 
                           minlength="8" 
                           required>
                </div>
            </div>

            <div class="mt-4 flex justify-end">
                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 text-xs font-bold text-white bg-slate-800 hover:bg-slate-900 active:scale-98 rounded-xl shadow-xs transition-all cursor-pointer">
                    <x-icon name="shield-check" class="w-3.5 h-3.5" />
                    <span>Simpan Kata Sandi</span>
                </button>
            </div>
        </form>
    </div>
</section>
