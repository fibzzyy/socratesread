<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\VerifyEmailOtpMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class OtpVerificationController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $userId = $request->session()->get('pending_user_id') ?? auth()->id();
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->email_verified_at !== null) {
            if (! auth()->check()) {
                Auth::login($user);
            }
            return redirect()->route($user->role === 'teacher' ? 'teacher.dashboard' : 'dashboard');
        }

        return view('auth.verify-otp', [
            'user' => $user,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ], [
            'otp.required' => 'Kode OTP wajib diisi.',
            'otp.size' => 'Kode OTP harus berupa 6 digit angka.',
        ]);

        $userId = $request->session()->get('pending_user_id') ?? auth()->id();
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'Sesi verifikasi telah kedaluwarsa. Silakan masuk kembali.']);
        }

        if ($user->otp_code !== $request->otp) {
            return back()->withErrors(['otp' => 'Kode OTP yang Anda masukkan salah.']);
        }

        if (! $user->otp_expires_at || $user->otp_expires_at->isPast()) {
            return back()->withErrors(['otp' => 'Kode OTP telah kedaluwarsa. Silakan minta kode baru.']);
        }

        $user->update([
            'email_verified_at' => now(),
            'otp_code' => null,
            'otp_expires_at' => null,
        ]);

        $request->session()->forget('pending_user_id');
        Auth::login($user);
        $request->session()->regenerate();

        $destination = $user->role === 'teacher' ? 'teacher.dashboard' : 'dashboard';
        return redirect()->route($destination)->with('status', 'Email berhasil diverifikasi! Selamat datang di SocratesRead.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('pending_user_id') ?? auth()->id();
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            return redirect()->route('login');
        }

        $otp = sprintf('%06d', random_int(100000, 999999));
        $user->update([
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(15),
        ]);

        try {
            Mail::to($user->email)->send(new VerifyEmailOtpMail($user, $otp));
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim ulang OTP: ' . $e->getMessage());
        }

        return back()->with('status', 'Kode OTP baru telah dikirimkan ke email Anda.');
    }
}
