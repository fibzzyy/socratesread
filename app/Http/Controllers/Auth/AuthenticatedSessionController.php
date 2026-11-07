<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\VerifyEmailOtpMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (! auth()->check()) {
            return view('auth.login');
        }

        return redirect()->route(auth()->user()->role === 'teacher' ? 'teacher.dashboard' : 'dashboard');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi tidak cocok.',
            ]);
        }

        // Cek apakah email sudah diverifikasi dengan OTP
        if ($user->email_verified_at === null) {
            $otp = sprintf('%06d', random_int(100000, 999999));
            $user->update([
                'otp_code' => $otp,
                'otp_expires_at' => now()->addMinutes(15),
            ]);

            try {
                Mail::to($user->email)->send(new VerifyEmailOtpMail($user, $otp));
            } catch (\Throwable $e) {
                Log::error('Gagal mengirim email OTP login: ' . $e->getMessage());
            }

            $request->session()->put('pending_user_id', $user->id);

            return redirect()->route('verification.otp')->with('notice', 'Email Anda belum diverifikasi. Silakan masukkan kode OTP yang telah dikirim ke ' . $user->email);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->route($user->role === 'teacher' ? 'teacher.dashboard' : 'dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
