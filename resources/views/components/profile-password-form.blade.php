<section class="panel profile-edit-panel password-edit-panel" aria-labelledby="change-password-heading">
    <div class="section-heading">
        <div>
            <p class="section-overline">KEAMANAN AKUN</p>
            <h2 id="change-password-heading">Kelola Akses Akun</h2>
        </div>
        <span class="session-icon"><x-icon name="user" /></span>
    </div>

    <div class="account-email-section">
        <h3>Alamat Email</h3>
        <p class="upload-hint">Email aktif untuk masuk ke SocratesRead: <strong>{{ $user->email }}</strong></p>

        <form method="POST" action="{{ route('settings.email.update') }}" class="profile-form">
            @csrf
            @method('PUT')

            <div class="form-grid account-security-grid">
                <div class="form-group">
                    <label for="email">Alamat Email Baru</label>
                    <input id="email" name="email" type="email" class="form-input @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" autocomplete="email" required>
                    @error('email')
                        <small class="form-error">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="email_current_password">Kata Sandi Saat Ini</label>
                    <input id="email_current_password" name="email_current_password" type="password" class="form-input @error('email_current_password') is-invalid @enderror" autocomplete="current-password" required>
                    @error('email_current_password')
                        <small class="form-error">{{ $message }}</small>
                    @enderror
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="challenge-button">
                    <span>Simpan Alamat Email</span>
                </button>
            </div>
        </form>
    </div>

    <div class="account-password-section">
        <h3>Ubah Kata Sandi</h3>
        <p class="upload-hint">Masukkan kata sandi saat ini untuk mengonfirmasi perubahan.</p>

        <form method="POST" action="{{ route('settings.password.update') }}" class="profile-form">
            @csrf
            @method('PUT')

            <div class="form-grid account-security-grid">
                <div class="form-group">
                    <label for="current_password">Kata Sandi Saat Ini</label>
                    <input id="current_password" name="current_password" type="password" class="form-input @error('current_password') is-invalid @enderror" autocomplete="current-password" required>
                    @error('current_password')
                        <small class="form-error">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="new_password">Kata Sandi Baru</label>
                    <input id="new_password" name="new_password" type="password" class="form-input @error('new_password') is-invalid @enderror" autocomplete="new-password" minlength="8" required>
                    @error('new_password')
                        <small class="form-error">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="new_password_confirmation">Konfirmasi Kata Sandi Baru</label>
                    <input id="new_password_confirmation" name="new_password_confirmation" type="password" class="form-input" autocomplete="new-password" minlength="8" required>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="challenge-button">
                    <span>Simpan Kata Sandi</span>
                </button>
            </div>
        </form>
    </div>
</section>

<style>
    .account-email-section,
    .account-password-section {
        margin-top: 24px;
    }

    .account-email-section h3,
    .account-password-section h3 {
        margin: 0 0 8px;
        color: #1e293b;
        font-size: 16px;
    }

    .account-security-grid {
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr));
    }
</style>
