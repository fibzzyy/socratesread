@extends('layouts.teacher')

@section('title', 'Profil Guru')
@section('breadcrumb', 'Profil')

@section('content')
    <section class="stage-heading" aria-labelledby="profile-title">
        <div>
            <h1 id="profile-title">Profil Guru</h1>
        </div>
        <a href="{{ route('settings.profile') }}" class="challenge-button">
            <x-icon name="pencil" />
            Ubah profil
        </a>
    </section>

    @if (session('status'))
        <div class="auth-notice" role="status">
            <x-icon name="check" />
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <section class="panel mt-5 p-5 sm:p-7" aria-labelledby="teacher-name">
        <div class="flex items-center gap-4 sm:gap-5">
            <div class="grid h-16 w-16 shrink-0 place-items-center overflow-hidden rounded-full border border-slate-200 bg-slate-50 text-lg font-semibold text-slate-600">
                @if ($user->avatarUrl())
                    <img src="{{ $user->avatarUrl() }}" alt="Foto profil {{ $user->name }}" class="h-full w-full object-cover">
                @else
                    <x-icon name="user" class="h-8 w-8 text-slate-400" />
                @endif
            </div>
            <div class="min-w-0">
                <h2 id="teacher-name" class="m-0 truncate text-lg font-semibold tracking-tight text-slate-900 sm:text-xl">{{ $user->name }}</h2>
                <p class="mt-1 break-all text-sm text-slate-600">{{ $user->email }}</p>
                @if ($user->school_origin)
                    <p class="mt-1 text-sm text-slate-500">{{ $user->school_origin }}</p>
                @endif
            </div>
        </div>

        @if ($user->bio)
            <p class="mt-5 max-w-3xl border-t border-slate-100 pt-4 text-sm leading-relaxed text-slate-600">{{ $user->bio }}</p>
        @endif
    </section>

    @if ($user->nip_nuptk || $user->subject_area)
        <section class="panel mt-4 p-5 sm:p-7" aria-labelledby="teaching-info-title">
            <h2 id="teaching-info-title" class="m-0 text-base font-semibold text-slate-900">Informasi mengajar</h2>
            <dl class="mt-4 grid gap-x-8 sm:grid-cols-2">
                @if ($user->nip_nuptk)
                    <div class="border-t border-slate-100 py-3">
                        <dt class="text-xs text-slate-500">NUPTK</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-800">{{ $user->nip_nuptk }}</dd>
                    </div>
                @endif
                @if ($user->subject_area)
                    <div class="border-t border-slate-100 py-3">
                        <dt class="text-xs text-slate-500">Bidang ajar</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-800">{{ $user->subject_area }}</dd>
                    </div>
                @endif
            </dl>
        </section>
    @endif
@endsection
