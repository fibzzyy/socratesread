<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\VerifyEmailOtpMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (! auth()->check()) {
            return view('auth.register');
        }

        return redirect()->route(auth()->user()->role === 'teacher' ? 'teacher.dashboard' : 'dashboard');
    }

    public function store(Request $request): RedirectResponse
    {
        $role = $request->input('role', 'student');
        if (! in_array($role, ['student', 'teacher'], true)) {
            $role = 'student';
        }

        $rules = [
            'role' => ['required', 'in:student,teacher'],
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users')->whereNotNull('email_verified_at'),
            ],
            'school_origin' => ['required', 'string', 'max:160'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];

        if ($role === 'teacher') {
            $rules['nip_nuptk'] = ['required', 'string', 'max:50'];
        } else {
            $rules['nisn'] = ['required', 'string', 'max:50'];
            $rules['entry_grade'] = ['required', 'integer', 'in:10,11,12'];
        }

        $messages = [
            'role.required' => 'Pilih jenis akun (Siswa atau Guru).',
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar dan aktif. Silakan masuk dengan kata sandi Anda.',
            'school_origin.required' => 'Asal sekolah wajib diisi.',
            'nip_nuptk.required' => 'NUPTK wajib diisi untuk akun guru.',
            'nisn.required' => 'NISN wajib diisi untuk akun siswa.',
            'entry_grade.required' => 'Pilih tingkat kelas Anda saat ini.',
            'entry_grade.in' => 'Tingkat kelas harus Kelas 10, 11, atau 12.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
        ];

        $validated = $request->validate($rules, $messages);

        $otp = sprintf('%06d', random_int(100000, 999999));

        $enrollmentYear = (int) now()->year;
        $entryGrade = $validated['role'] === 'student' ? (int) ($validated['entry_grade'] ?? 10) : null;
        $graduationYear = $entryGrade ? User::calculateGraduationYear($enrollmentYear, $entryGrade) : null;

        $user = User::updateOrCreate(
            ['email' => trim($validated['email'])],
            [
                'name' => trim($validated['name']),
                'role' => $validated['role'],
                'school_origin' => trim($validated['school_origin']),
                'nip_nuptk' => $validated['role'] === 'teacher' ? trim($validated['nip_nuptk']) : null,
                'teacher_identifier_type' => $validated['role'] === 'teacher' ? 'nuptk' : null,
                'nisn' => $validated['role'] === 'student' ? trim($validated['nisn']) : null,
                'entry_grade' => $entryGrade,
                'enrollment_year' => $validated['role'] === 'student' ? $enrollmentYear : null,
                'graduation_year' => $graduationYear,
                'password' => Hash::make($validated['password']),
                'otp_code' => $otp,
                'otp_expires_at' => now()->addMinutes(15),
                'email_verified_at' => null,
            ]
        );

        try {
            Mail::to($user->email)->send(new VerifyEmailOtpMail($user, $otp));
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim email OTP pendaftaran: ' . $e->getMessage());
        }

        $request->session()->put('pending_user_id', $user->id);

        return redirect()->route('verification.otp')->with('status', 'Kode OTP telah dikirimkan ke email Anda (' . $user->email . ').');
    }
}
