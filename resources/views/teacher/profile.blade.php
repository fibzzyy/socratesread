@extends('layouts.teacher')

@section('title', 'Profil Guru')
@section('breadcrumb', 'Profil')

@section('content')
    <section class="stage-heading" aria-labelledby="profile-title">
        <div>
            <p class="eyebrow">AKUN PRIBADI</p>
            <h1 id="profile-title">Profil Guru</h1>
            <p class="welcome-subtitle">Informasi profesional dan aktivitas mengajar Anda.</p>
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

    <section class="teacher-profile-overview mt-4 space-y-6" aria-labelledby="teacher-name">
        {{-- Profile Header Card (Clean & Perfectly Aligned) --}}
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
            {{-- Decorative Gradient Banner --}}
            <div class="h-28 sm:h-36 w-full bg-slate-200 bg-cover bg-center relative overflow-hidden" @if($user->coverUrl()) style="background-image:linear-gradient(rgb(0 0 0 / 8%),rgb(0 0 0 / 8%)),url('{{ $user->coverUrl() }}')" @endif>
                @if (!$user->coverUrl())
                    <div class="absolute inset-0 bg-[linear-gradient(120deg,rgba(255,255,255,0.28),transparent_55%)]"></div>
                @endif
            </div>

            {{-- Profile Details Bar --}}
            <div class="px-6 pb-6 sm:px-8 sm:pb-8 pt-0">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-5">
                    {{-- Avatar & Name Group --}}
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4 sm:gap-6">
                        {{-- Only the avatar has negative margin to overlap banner cleanly --}}
                        <div class="-mt-12 sm:-mt-14 relative shrink-0">
                            <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-3xl overflow-hidden ring-4 ring-white shadow-lg bg-slate-100 text-slate-400 flex items-center justify-center">
                                @if ($user->avatarUrl())
                                    <img src="{{ $user->avatarUrl() }}" alt="Foto profil {{ $user->name }}" class="w-full h-full object-cover">
                                @else
                                    <x-icon name="user" class="w-10 h-10" />
                                @endif
                            </div>
                        </div>

                        {{-- Name, Role Badge, and Email (Sits 100% on the white card, perfectly legible) --}}
                        <div class="teacher-profile-name">
                            <div class="flex items-center gap-2.5 flex-wrap">
                                <h2 id="teacher-name" class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight m-0 leading-tight">{{ $user->name }}</h2>
                                <span class="role-badge inline-flex items-center gap-1.5 text-xs font-medium text-slate-500">
                                    <x-icon name="graduation-cap" class="w-3.5 h-3.5 text-slate-400" />
                                    Guru
                                </span>
                            </div>
                            <p class="text-xs sm:text-sm text-slate-500 mt-1 m-0">{{ $user->email }}</p>
                        </div>
                    </div>

                    {{-- Action Button --}}
                    <div class="flex items-center gap-2.5 self-start sm:self-center">
                        <a href="{{ route('settings.profile') }}" class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 hover:text-slate-900 rounded-xl transition-colors no-underline">
                            <x-icon name="settings" class="w-4 h-4 text-slate-500" />
                            <span>Pengaturan Akun</span>
                        </a>
                    </div>
                </div>

                {{-- Bio Callout if Available --}}
                @if ($user->bio)
                    <div class="mt-6 p-4 rounded-2xl bg-slate-50/80 border border-slate-100 flex items-start gap-3">
                        <div class="w-8 h-8 text-slate-400 flex items-center justify-center shrink-0 mt-0.5">
                            <x-icon name="quote" class="w-4 h-4" />
                        </div>
                        <div class="flex-1">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Bio Pengajar</span>
                            <p class="text-sm text-slate-700 mt-0.5 m-0 leading-relaxed">{{ $user->bio }}</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Statistics Row: Ringkasan Aktivitas Mengajar --}}
        <div class="teacher-profile-activity grid grid-cols-1 sm:grid-cols-3 gap-4" aria-label="Ringkasan aktivitas mengajar">
            <div class="p-5 rounded-3xl bg-white border border-slate-200/80 shadow-xs flex items-center gap-4">
                <span class="w-12 h-12 text-slate-500 flex items-center justify-center shrink-0">
                    <x-icon name="users" class="w-6 h-6" />
                </span>
                <div>
                    <strong class="text-2xl font-bold text-slate-900 leading-tight block">{{ $teacherStats['classes'] }}</strong>
                    <span class="text-xs font-medium text-slate-500">Kelas Aktif</span>
                </div>
            </div>

            <div class="p-5 rounded-3xl bg-white border border-slate-200/80 shadow-xs flex items-center gap-4">
                <span class="w-12 h-12 text-slate-500 flex items-center justify-center shrink-0">
                    <x-icon name="graduation-cap" class="w-6 h-6" />
                </span>
                <div>
                    <strong class="text-2xl font-bold text-slate-900 leading-tight block">{{ $teacherStats['students'] }}</strong>
                    <span class="text-xs font-medium text-slate-500">Siswa Terdaftar</span>
                </div>
            </div>

            <div class="p-5 rounded-3xl bg-white border border-slate-200/80 shadow-xs flex items-center gap-4">
                <span class="w-12 h-12 text-slate-500 flex items-center justify-center shrink-0">
                    <x-icon name="book-open-text" class="w-6 h-6" />
                </span>
                <div>
                    <strong class="text-2xl font-bold text-slate-900 leading-tight block">{{ $teacherStats['materials'] }}</strong>
                    <span class="text-xs font-medium text-slate-500">Materi Bacaan</span>
                </div>
            </div>
        </div>

        {{-- Grid Content: Detail Data Diri & Keamanan (Tanpa duplikasi menu sidebar) --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Kolom Kiri: Detail Profesional Pendidik --}}
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-6 sm:p-8">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div class="flex items-center gap-2.5">
                        <span class="w-8 h-8 text-slate-400 flex items-center justify-center">
                            <x-icon name="shield-check" class="w-4 h-4" />
                        </span>
                        <div>
                            <h3 class="text-base font-bold text-slate-900 m-0">Informasi Pendidik</h3>
                            <p class="text-xs text-slate-500 m-0">Data resmi pendidik dan instansi mengajar</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500">
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                        Terverifikasi
                    </span>
                </div>

                <dl class="teacher-profile-details space-y-3 mt-5">
                    <div class="p-3.5 rounded-2xl bg-slate-50/70 border border-slate-100 flex items-center justify-between">
                        <div class="flex-1">
                            <dt>Peran</dt>
                            <dd class="text-sm font-bold text-slate-800 mt-0.5 m-0">Guru / Pendidik</dd>
                        </div>
                        <span class="w-9 h-9 rounded-xl bg-white border border-slate-200/60 flex items-center justify-center text-slate-400">
                            <x-icon name="graduation-cap" class="w-4 h-4" />
                        </span>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-slate-50/70 border border-slate-100 flex items-center justify-between">
                        <div class="flex-1">
                            <dt>NUPTK</dt>
                            <dd class="text-sm font-bold text-slate-800 mt-0.5 m-0 font-mono">{{ $user->nip_nuptk ?: '-' }}</dd>
                        </div>
                        <span class="w-9 h-9 rounded-xl bg-white border border-slate-200/60 flex items-center justify-center text-slate-400">
                            <x-icon name="shield-check" class="w-4 h-4" />
                        </span>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-slate-50/70 border border-slate-100 flex items-center justify-between">
                        <div class="flex-1">
                            <dt>Asal Sekolah / Instansi</dt>
                            <dd class="text-sm font-bold text-slate-800 mt-0.5 m-0">{{ $user->school_origin ?: '-' }}</dd>
                        </div>
                        <span class="w-9 h-9 rounded-xl bg-white border border-slate-200/60 flex items-center justify-center text-slate-400">
                            <x-icon name="building" class="w-4 h-4" />
                        </span>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-slate-50/70 border border-slate-100 flex items-center justify-between">
                        <div class="flex-1">
                            <dt>Bidang Studi / Mata Pelajaran</dt>
                            <dd class="text-sm font-bold text-slate-800 mt-0.5 m-0">{{ $user->subject_area ?: '-' }}</dd>
                        </div>
                        <span class="w-9 h-9 rounded-xl bg-white border border-slate-200/60 flex items-center justify-center text-slate-400">
                            <x-icon name="book-open-text" class="w-4 h-4" />
                        </span>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-slate-50/70 border border-slate-100 flex items-center justify-between">
                        <div class="flex-1">
                            <dt>Bergabung sejak</dt>
                            <dd class="text-sm font-bold text-slate-800 mt-0.5 m-0">{{ $user->created_at?->locale('id')->translatedFormat('d F Y') ?? '-' }}</dd>
                        </div>
                        <span class="w-9 h-9 rounded-xl bg-white border border-slate-200/60 flex items-center justify-center text-slate-400">
                            <x-icon name="calendar-days" class="w-4 h-4" />
                        </span>
                    </div>
                </dl>
            </div>

            {{-- Kolom Kanan: Status Akun & Keamanan (Bukan menu sidebar) --}}
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-6 sm:p-8 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 text-slate-400 flex items-center justify-center">
                                <x-icon name="shield-check" class="w-4 h-4" />
                            </span>
                            <div>
                                <h3 class="text-base font-bold text-slate-900 m-0">Keamanan & Akses Akun</h3>
                                <p class="text-xs text-slate-500 m-0">Kredensial dan akses pengajar Anda</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4 mt-5">
                        {{-- Email Status --}}
                        <div class="p-4 rounded-2xl bg-slate-50/70 border border-slate-100">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-slate-500">Alamat Email Guru</span>
                                <span class="inline-flex items-center gap-1 text-[11px] font-medium text-slate-500">
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
                                <span class="inline-flex items-center gap-1 text-[11px] font-medium text-slate-500">
                                    <x-icon name="shield-check" class="w-3.5 h-3.5" />
                                    Terlindungi
                                </span>
                            </div>
                            <p class="text-sm text-slate-600 mt-1 m-0">••••••••••••</p>
                        </div>

                        {{-- Tips Keamanan --}}
                        <div class="p-4 border-t border-slate-100">
                            <div class="flex items-start gap-2.5">
                                <x-icon name="info" class="w-4 h-4 text-slate-400 shrink-0 mt-0.5" />
                                <p class="text-xs text-slate-600 m-0 leading-relaxed">
                                    Pastikan email dan kata sandi Anda selalu aman untuk melindungi data dan penilaian siswa di kelas Anda.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between">
                    <span class="text-xs text-slate-500">Ingin memperbarui kata sandi atau email?</span>
                    <a href="{{ route('settings.profile') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-600 hover:text-blue-700 hover:underline">
                        <span>Buka Pengaturan</span>
                        <x-icon name="arrow-right" class="w-3.5 h-3.5" />
                    </a>
                </div>
            </div>
        </div>
    </section>

    <style>
        .teacher-profile-details dt {
            font-size: 0.75rem;
            line-height: 1rem;
            color: #64748b;
            font-weight: 500;
            margin: 0;
        }
    </style>
@endsection
