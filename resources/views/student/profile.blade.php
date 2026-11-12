@extends('layouts.student')

@section('title', 'Profil Saya')
@section('breadcrumb', 'Profil')

@section('content')
    <section class="stage-heading" aria-labelledby="profile-title">
        <div>
            <p class="eyebrow">AKUN PRIBADI</p>
            <h1 id="profile-title">Profil Saya</h1>
            <p class="welcome-subtitle">Informasi akun dan identitas belajar Anda.</p>
        </div>
        <a href="{{ route('settings.profile') }}" class="profile-edit-link inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 active:scale-98 rounded-xl shadow-xs transition-all no-underline">
            <x-icon name="pencil" class="w-4 h-4" />
            <span>Edit Profil</span>
        </a>
    </section>

    @if (session('status'))
        <div class="auth-notice" role="status">
            <x-icon name="check" />
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <section class="student-profile-overview mt-4 space-y-6" aria-labelledby="student-name">
        {{-- Profile Header Card (Clean & Perfectly Aligned) --}}
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
            {{-- Decorative Banner (Subtle & Proportional) --}}
            <div class="h-28 sm:h-36 w-full bg-gradient-to-r from-blue-600 via-indigo-600 to-sky-500 relative overflow-hidden">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.22),transparent_65%)]"></div>
                <div class="absolute -right-8 -bottom-8 w-40 h-40 bg-white/10 rounded-full blur-xl"></div>
                <div class="absolute left-1/4 top-2 w-28 h-28 bg-sky-300/20 rounded-full blur-lg"></div>
            </div>

            {{-- Profile Details Bar --}}
            <div class="px-6 pb-6 sm:px-8 sm:pb-8 pt-0">
                <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-5">
                    {{-- Avatar & Name Group --}}
                    <div class="flex flex-col sm:flex-row sm:items-end gap-5">
                        {{-- Only the avatar has negative top margin to overlap the banner cleanly --}}
                        <div class="-mt-14 sm:-mt-16 relative shrink-0">
                            <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-3xl overflow-hidden ring-4 ring-white shadow-lg bg-gradient-to-tr from-blue-600 to-indigo-600 text-white flex items-center justify-center font-bold text-2xl sm:text-3xl">
                                @if ($user->avatarUrl())
                                    <img src="{{ $user->avatarUrl() }}" alt="Foto profil {{ $user->name }}" class="w-full h-full object-cover">
                                @else
                                    <span>{{ mb_strtoupper(collect(explode(' ', $user->name))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('')) }}</span>
                                @endif
                            </div>
                        </div>

                        {{-- Name, Role Badge, and Email (Sits 100% on the white card, perfectly legible) --}}
                        <div class="student-profile-name pt-1 sm:pb-1">
                            <div class="flex items-center gap-2.5 flex-wrap">
                                <h2 id="student-name" class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight m-0 leading-tight">{{ $user->name }}</h2>
                                <span class="student-role-badge inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200/80 shadow-2xs">
                                    <x-icon name="book-open-text" class="w-3.5 h-3.5" />
                                    Siswa
                                </span>
                            </div>
                            <p class="text-xs sm:text-sm text-slate-500 mt-1 m-0">{{ $user->email }}</p>
                        </div>
                    </div>

                    {{-- Action Button --}}
                    <div class="sm:pb-1 flex items-center gap-2.5">
                        <a href="{{ route('settings.profile') }}" class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 hover:text-slate-900 rounded-xl transition-colors no-underline">
                            <x-icon name="settings" class="w-4 h-4 text-slate-500" />
                            <span>Pengaturan Akun</span>
                        </a>
                    </div>
                </div>

                {{-- Bio Callout if Available --}}
                @if ($user->bio)
                    <div class="mt-6 p-4 rounded-2xl bg-slate-50/80 border border-slate-100 flex items-start gap-3">
                        <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 mt-0.5 shadow-2xs">
                            <x-icon name="quote" class="w-4 h-4" />
                        </div>
                        <div class="flex-1">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Bio Belajar</span>
                            <p class="text-sm text-slate-700 mt-0.5 m-0 leading-relaxed">{{ $user->bio }}</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Grid Content: Detail Akademik & Status Akun (Tanpa duplikasi menu sidebar) --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Card 1: Informasi Akademik Siswa --}}
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-6 sm:p-8">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div class="flex items-center gap-2.5">
                        <span class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shadow-2xs">
                            <x-icon name="graduation-cap" class="w-4 h-4" />
                        </span>
                        <div>
                            <h3 class="text-base font-bold text-slate-900 m-0">Informasi Akademik</h3>
                            <p class="text-xs text-slate-500 m-0">Identitas sekolah dan jenjang belajar resmi</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        Terdaftar
                    </span>
                </div>

                <dl class="student-profile-details space-y-3 mt-5">
                    <div class="p-3.5 rounded-2xl bg-slate-50/70 border border-slate-100 flex items-center justify-between">
                        <div>
                            <dt class="text-xs text-slate-500 font-medium">Peran Pengguna</dt>
                            <dd class="text-sm font-bold text-slate-800 mt-0.5 m-0">Siswa SocratesRead</dd>
                        </div>
                        <span class="w-8 h-8 rounded-xl bg-white border border-slate-200/60 flex items-center justify-center text-slate-400">
                            <x-icon name="user" class="w-4 h-4" />
                        </span>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-slate-50/70 border border-slate-100 flex items-center justify-between">
                        <div>
                            <dt class="text-xs text-slate-500 font-medium">NISN</dt>
                            <dd class="text-sm font-bold text-slate-800 mt-0.5 m-0 font-mono">{{ $user->nisn ?: '-' }}</dd>
                        </div>
                        <span class="w-8 h-8 rounded-xl bg-white border border-slate-200/60 flex items-center justify-center text-slate-400">
                            <x-icon name="shield-check" class="w-4 h-4" />
                        </span>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-slate-50/70 border border-slate-100 flex items-center justify-between">
                        <div>
                            <dt class="text-xs text-slate-500 font-medium">Sekolah</dt>
                            <dd class="text-sm font-bold text-slate-800 mt-0.5 m-0 truncate max-w-[200px]" title="{{ $user->school_origin }}">{{ $user->school_origin ?: '-' }}</dd>
                        </div>
                        <span class="w-8 h-8 rounded-xl bg-white border border-slate-200/60 flex items-center justify-center text-slate-400">
                            <x-icon name="graduation-cap" class="w-4 h-4" />
                        </span>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-slate-50/70 border border-slate-100 flex items-center justify-between">
                        <div>
                            <dt class="text-xs text-slate-500 font-medium">Kelas saat ini</dt>
                            <dd class="text-sm font-bold text-slate-800 mt-0.5 m-0">{{ $user->currentGrade() ? 'Kelas '.$user->currentGrade() : '-' }}</dd>
                        </div>
                        <span class="w-8 h-8 rounded-xl bg-white border border-slate-200/60 flex items-center justify-center text-slate-400">
                            <x-icon name="book-open-text" class="w-4 h-4" />
                        </span>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-slate-50/70 border border-slate-100 flex items-center justify-between">
                        <div>
                            <dt class="text-xs text-slate-500 font-medium">Bergabung sejak</dt>
                            <dd class="text-sm font-bold text-slate-800 mt-0.5 m-0">{{ $user->created_at?->locale('id')->translatedFormat('d F Y') ?? '-' }}</dd>
                        </div>
                        <span class="w-8 h-8 rounded-xl bg-white border border-slate-200/60 flex items-center justify-center text-slate-400">
                            <x-icon name="calendar-days" class="w-4 h-4" />
                        </span>
                    </div>
                </dl>
            </div>

            {{-- Card 2: Keamanan & Status Akun (Bukan menu sidebar) --}}
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-6 sm:p-8 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center shadow-2xs">
                                <x-icon name="shield-check" class="w-4 h-4" />
                            </span>
                            <div>
                                <h3 class="text-base font-bold text-slate-900 m-0">Keamanan & Akses Akun</h3>
                                <p class="text-xs text-slate-500 m-0">Kredensial dan perlindungan akun Anda</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4 mt-5">
                        {{-- Email Status --}}
                        <div class="p-4 rounded-2xl bg-slate-50/70 border border-slate-100">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-slate-500">Alamat Email</span>
                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600">
                                    <x-icon name="check" class="w-3.5 h-3.5" />
                                    Terverifikasi
                                </span>
                            </div>
                            <p class="text-sm font-bold text-slate-800 mt-1 m-0 break-all">{{ $user->email }}</p>
                        </div>

                        {{-- Password Status --}}
                        <div class="p-4 rounded-2xl bg-slate-50/70 border border-slate-100">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-slate-500">Kata Sandi</span>
                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-blue-600">
                                    <x-icon name="shield-check" class="w-3.5 h-3.5" />
                                    Terlindungi
                                </span>
                            </div>
                            <p class="text-sm text-slate-600 mt-1 m-0">••••••••••••</p>
                        </div>

                        {{-- Tips Keamanan --}}
                        <div class="p-4 rounded-2xl bg-blue-50/50 border border-blue-100/60">
                            <div class="flex items-start gap-2.5">
                                <x-icon name="info" class="w-4 h-4 text-blue-600 shrink-0 mt-0.5" />
                                <p class="text-xs text-blue-900 m-0 leading-relaxed">
                                    Jaga kerahasiaan kata sandi Anda dan jangan bagikan kepada siapa pun. Anda dapat memperbarui kata sandi secara berkala di menu pengaturan.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between">
                    <span class="text-xs text-slate-500">Ingin mengubah sandi atau email?</span>
                    <a href="{{ route('settings.profile') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 hover:text-blue-700 hover:underline">
                        <span>Buka Pengaturan</span>
                        <x-icon name="arrow-right" class="w-3.5 h-3.5" />
                    </a>
                </div>
            </div>
        </div>
    </section>
@endsection
