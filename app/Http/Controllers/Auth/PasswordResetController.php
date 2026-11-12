<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordOtpMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function sendOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'exists:users,email'],
        ], [
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'email.exists' => 'Alamat email tersebut tidak terdaftar pada sistem kami.',
        ]);

        $user = User::where('email', trim($validated['email']))->firstOrFail();

        $otp = sprintf('%06d', random_int(100000, 999999));
        $user->update([
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(15),
        ]);

        try {
            Mail::to($user->email)->send(new ResetPasswordOtpMail($user, $otp));
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim email OTP reset password: ' . $e->getMessage());
        }

        $request->session()->put('password_reset_user_id', $user->id);
        $request->session()->forget('password_reset_otp_verified');

        return redirect()->route('password.otp')->with('status', 'Kode OTP 6 digit telah dikirimkan ke email Anda (' . $user->email . ').');
    }

    public function showVerifyOtpForm(Request $request): View|RedirectResponse
    {
        $userId = $request->session()->get('password_reset_user_id');
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            return redirect()->route('password.request')->withErrors([
                'email' => 'Silakan masukkan email Anda terlebih dahulu untuk meminta kode OTP.',
            ]);
        }

        if ($request->session()->get('password_reset_otp_verified') === true) {
            return redirect()->route('password.reset');
        }

        return view('auth.forgot-password-otp', [
            'user' => $user,
        ]);
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('password_reset_user_id');
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            return redirect()->route('password.request')->withErrors([
                'email' => 'Sesi pemulihan kata sandi telah berakhir. Silakan minta kode OTP baru.',
            ]);
        }

        $validated = $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ], [
            'otp.required' => 'Kode OTP wajib diisi.',
            'otp.size' => 'Kode OTP harus berupa 6 digit angka.',
        ]);

        if ($user->otp_code !== $validated['otp']) {
            return back()->withErrors(['otp' => 'Kode OTP yang Anda masukkan salah.'])->withInput();
        }

        if (! $user->otp_expires_at || $user->otp_expires_at->isPast()) {
            return back()->withErrors(['otp' => 'Kode OTP telah kedaluwarsa. Silakan minta kode baru.'])->withInput();
        }

        $user->update([
            'otp_code' => null,
            'otp_expires_at' => null,
        ]);

        $request->session()->put('password_reset_otp_verified', true);

        return redirect()->route('password.reset')->with('status', 'Kode OTP berhasil diverifikasi. Silakan buat kata sandi baru Anda.');
    }

    public function showResetForm(Request $request): View|RedirectResponse
    {
        $userId = $request->session()->get('password_reset_user_id');
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            return redirect()->route('password.request')->withErrors([
                'email' => 'Silakan masukkan email Anda terlebih dahulu untuk meminta kode OTP.',
            ]);
        }

        if ($request->session()->get('password_reset_otp_verified') !== true) {
            return redirect()->route('password.otp')->withErrors([
                'otp' => 'Silakan verifikasi kode OTP terlebih dahulu sebelum memperbarui kata sandi.',
            ]);
        }

        return view('auth.reset-password', [
            'user' => $user,
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('password_reset_user_id');
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            return redirect()->route('password.request')->withErrors([
                'email' => 'Sesi pemulihan kata sandi telah berakhir. Silakan minta kode OTP baru.',
            ]);
        }

        if ($request->session()->get('password_reset_otp_verified') !== true) {
            return redirect()->route('password.otp')->withErrors([
                'otp' => 'Silakan verifikasi kode OTP terlebih dahulu sebelum memperbarui kata sandi.',
            ]);
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
            'password.min' => 'Kata sandi baru minimal 8 karakter.',
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
            'otp_code' => null,
            'otp_expires_at' => null,
        ]);

        $request->session()->forget(['password_reset_user_id', 'password_reset_otp_verified']);

        return redirect()->route('login')->with('status', 'Kata sandi berhasil diperbarui! Silakan masuk dengan kata sandi baru Anda.');
    }

    public function resendOtp(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('password_reset_user_id');
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            return redirect()->route('password.request');
        }

        $otp = sprintf('%06d', random_int(100000, 999999));
        $user->update([
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(15),
        ]);

        $request->session()->forget('password_reset_otp_verified');

        try {
            Mail::to($user->email)->send(new ResetPasswordOtpMail($user, $otp));
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim ulang email OTP reset password: ' . $e->getMessage());
        }

        return redirect()->route('password.otp')->with('status', 'Kode OTP baru telah dikirimkan ke email Anda.');
    }
}
