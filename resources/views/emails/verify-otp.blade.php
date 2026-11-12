<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kode OTP Verifikasi SocratesRead</title>
</head>
<body style="font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 24px;">
    <div style="max-width: 520px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
        <div style="background-color: #1e293b; padding: 24px; text-align: center;">
            <h1 style="color: #ffffff; font-size: 22px; font-weight: 700; margin: 0; letter-spacing: -0.5px;">
                socrates<span style="color: #3b82f6;">read</span>
            </h1>
            <p style="color: #94a3b8; font-size: 11px; margin: 4px 0 0; letter-spacing: 1px; text-transform: uppercase;">Learn Through Questions</p>
        </div>

        <div style="padding: 32px 28px;">
            <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0 0 12px;">Halo, {{ $user->name }}!</h2>
            <p style="font-size: 14px; line-height: 1.6; color: #475569; margin: 0 0 20px;">
                Terima kasih telah mendaftar di <strong>SocratesRead</strong> sebagai <strong>{{ $user->role === 'teacher' ? 'Guru / Pengajar' : 'Siswa' }}</strong>.
                Untuk menyelesaikan proses pendaftaran akun Anda, masukkan kode verifikasi 6 digit berikut:
            </p>

            <div style="background-color: #eff6ff; border: 2px dashed #93c5fd; border-radius: 10px; padding: 18px; text-align: center; margin: 24px 0;">
                <span style="font-size: 32px; font-weight: 800; letter-spacing: 6px; color: #1d4ed8; font-family: monospace;">
                    {{ $otp }}
                </span>
                <p style="font-size: 12px; color: #64748b; margin: 8px 0 0;">Kode ini berlaku selama <strong>15 menit</strong>.</p>
            </div>

            <p style="font-size: 13px; line-height: 1.6; color: #64748b; margin: 0 0 8px;">
                Jika Anda tidak merasa mendaftar di SocratesRead, abaikan email ini. Jangan pernah membagikan kode OTP ini kepada siapa pun.
            </p>
        </div>

        <div style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 16px 28px; text-align: center;">
            <p style="font-size: 11px; color: #94a3b8; margin: 0;">&copy; 2026 SocratesRead. Ruang literasi berpikir kritis.</p>
        </div>
    </div>
</body>
</html>
