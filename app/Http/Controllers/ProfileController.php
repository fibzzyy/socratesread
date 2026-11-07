<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $view = $request->user()->role === 'teacher' 
            ? 'teacher.settings.profile' 
            : 'settings.profile';
        
        return view($view, ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'avatar_base64' => ['nullable', 'string'],
        ]);

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
                $filename = 'avatars/' . Str::random(40) . '.' . $ext;
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
        $user->update($validated);

        return redirect()->route('settings.profile')->with('status', 'Profil berhasil diperbarui.');
    }
}

