<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();
        $isTeacher = $user->role === 'teacher';
        $view = $isTeacher
            ? 'teacher.settings.profile'
            : 'settings.profile';

        return view($view, ['user' => $user]);
    }

    public function show(Request $request): View
    {
        $user = $request->user();

        if ($user->role !== 'teacher') {
            return view('student.profile', ['user' => $user]);
        }

        return view('teacher.profile', [
            'user' => $user,
            'teacherStats' => $this->teacherStats($user),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'avatar_base64' => ['nullable', 'string'],
        ];

        if ($user->role === 'teacher') {
            $rules += [
                'school_origin' => ['nullable', 'string', 'max:160'],
                'nip_nuptk' => ['nullable', 'string', 'max:50'],
                'subject_area' => ['nullable', 'string', 'max:120'],
                'bio' => ['nullable', 'string', 'max:1000'],
            ];
        } else {
            $rules += [
                'nisn' => ['nullable', 'string', 'max:50'],
                'bio' => ['nullable', 'string', 'max:1000'],
            ];
        }

        $validated = $request->validate($rules);

        if (! empty($validated['avatar_base64']) && preg_match('/^data:image\/(\w+);base64,/', $validated['avatar_base64'], $type)) {
            $data = substr($validated['avatar_base64'], strpos($validated['avatar_base64'], ',') + 1);
            $decoded = base64_decode($data);
            if ($decoded !== false) {
                $ext = strtolower($type[1]);
                if ($ext === 'jpeg') {
                    $ext = 'jpg';
                }
                if (! in_array($ext, ['jpg', 'png', 'webp'])) {
                    $ext = 'png';
                }
                $filename = 'avatars/'.Str::random(40).'.'.$ext;
                Storage::disk('public')->put($filename, $decoded);

                if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
                    Storage::disk('public')->delete($user->avatar_path);
                }
                $validated['avatar_path'] = $filename;
            }
        } elseif ($request->hasFile('avatar')) {
            $newAvatarPath = $request->file('avatar')->store('avatars', 'public');
            if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $validated['avatar_path'] = $newAvatarPath;
        }

        unset($validated['avatar'], $validated['avatar_base64']);
        if ($user->role === 'teacher') {
            $validated['teacher_identifier_type'] = 'nuptk';
        }
        $user->update($validated);

        return redirect()->route('settings.profile')->with('status', 'Profil berhasil diperbarui.');
    }

    public function updateEmail(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'email_current_password' => ['required', 'current_password'],
        ], [
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Alamat email ini sudah digunakan.',
            'email_current_password.required' => 'Kata sandi saat ini wajib diisi.',
            'email_current_password.current_password' => 'Kata sandi saat ini tidak sesuai.',
        ]);

        $user->update(['email' => $validated['email']]);

        return redirect()->route('settings.profile')->with('status', 'Alamat email berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'new_password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'current_password.required' => 'Kata sandi saat ini wajib diisi.',
            'current_password.current_password' => 'Kata sandi saat ini tidak sesuai.',
            'new_password.required' => 'Kata sandi baru wajib diisi.',
            'new_password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
            'new_password.min' => 'Kata sandi baru minimal 8 karakter.',
        ]);

        $request->user()->update(['password' => $validated['new_password']]);

        return redirect()->route('settings.profile')->with('status', 'Kata sandi berhasil diperbarui.');
    }

    private function teacherStats(User $user): array
    {
        return [
            'classes' => $user->teacherClasses()->count(),
            'students' => DB::table('classroom_memberships')
                ->join('teacher_classes', 'teacher_classes.id', '=', 'classroom_memberships.classroom_id')
                ->where('teacher_classes.teacher_id', $user->id)
                ->distinct()
                ->count('classroom_memberships.student_id'),
            'materials' => $user->uploadedDocuments()->count(),
        ];
    }
}
