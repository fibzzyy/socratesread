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
        <a href="{{ route('settings.profile') }}" class="challenge-button profile-edit-link">Edit Profil</a>
    </section>

    @if (session('status'))
        <div class="auth-notice" role="status">
            <x-icon name="check" />
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <section class="panel teacher-profile-overview" aria-labelledby="teacher-name">
        <div class="teacher-profile-identity">
            <div class="summary-avatar-wrapper">
                <div class="avatar summary-avatar">
                    @if ($user->avatarUrl())
                        <img src="{{ $user->avatarUrl() }}" alt="Foto profil {{ $user->name }}" class="avatar-image" width="110"
                            height="110">
                    @else
                        <span class="summary-avatar-placeholder">
                            {{ mb_strtoupper(collect(explode(' ', $user->name))->filter()->take(2)->map(fn($part) => mb_substr($part, 0, 1))->implode('')) }}
                        </span>
                    @endif
                </div>
                <span class="role-badge role-badge-teacher">Guru</span>
            </div>

            <div class="teacher-profile-name">
                <h2 id="teacher-name">{{ $user->name }}</h2>
                <p>{{ $user->email }}</p>
            </div>
        </div>

        <dl class="teacher-profile-details">
            <div>
                <dt>Peran</dt>
                <dd>Guru / Pendidik</dd>
            </div>
            <div>
                <dt>Bergabung sejak</dt>
                <dd>{{ $user->created_at?->locale('id')->translatedFormat('d F Y') ?? '-' }}</dd>
            </div>
            <div>
                <dt>Sekolah / Instansi</dt>
                <dd>{{ $user->school_origin ?: '-' }}</dd>
            </div>
            <div>
                <dt>NUPTK</dt>
                <dd>{{ $user->nip_nuptk ?: '-' }}</dd>
            </div>
            <div>
                <dt>Bidang ajar</dt>
                <dd>{{ $user->subject_area ?: '-' }}</dd>
            </div>
            @if ($user->bio)
                <div class="teacher-profile-bio">
                    <dt>Bio</dt>
                    <dd>{{ $user->bio }}</dd>
                </div>
            @endif
        </dl>

        <div class="teacher-profile-activity" aria-label="Ringkasan aktivitas mengajar">
            <div>
                <strong>{{ $teacherStats['classes'] }}</strong>
                <span>Kelas</span>
            </div>
            <div>
                <strong>{{ $teacherStats['students'] }}</strong>
                <span>Siswa</span>
            </div>
            <div>
                <strong>{{ $teacherStats['materials'] }}</strong>
                <span>Materi</span>
            </div>
        </div>
    </section>
@endsection

<style>
    .profile-edit-link {
        font-size: 16px;
        min-height: 40px;
        padding-inline: 30px;
        align-self: center;
        transform: translateY(30px);
        text-decoration: none;
    }

    .teacher-profile-overview {
        width: 100%;
        margin-top: 10px;
        padding: 32px;
    }

    .teacher-profile-identity {
        display: flex;
        align-items: center;
        gap: 24px;
        padding-bottom: 24px;
        border-bottom: 1px solid #e2e8f0;
    }

    .summary-avatar-wrapper {
        position: relative;
        flex: 0 0 auto;
    }

    .summary-avatar {
        width: 110px;
        height: 110px;
        overflow: hidden;
        border: 4px solid #d1fae5;
        border-radius: 50%;
    }

    .summary-avatar-placeholder {
        color: #059669;
        font-size: 34px;
        font-weight: 700;
    }

    .role-badge {
        position: absolute;
        right: 2px;
        bottom: 2px;
        padding: 4px 10px;
        border: 2px solid #fff;
        border-radius: 9999px;
        background: #059669;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
    }

    .teacher-profile-name h2 {
        margin: 0 0 6px;
        color: #1e293b;
        font-size: 22px;
    }

    .teacher-profile-name p {
        margin: 0;
        color: #64748b;
        overflow-wrap: anywhere;
    }

    .teacher-profile-details {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0 32px;
        margin: 8px 0 0;
    }

    .teacher-profile-details>div {
        display: flex;
        justify-content: space-between;
        gap: 20px;
        padding: 16px 0;
        border-bottom: 1px solid #e2e8f0;
    }

    .teacher-profile-details dt {
        color: #64748b;
    }

    .teacher-profile-details dd {
        margin: 0;
        color: #1e293b;
        font-weight: 600;
        text-align: right;
        overflow-wrap: anywhere;
    }

    .teacher-profile-details .teacher-profile-bio {
        grid-column: 1 / -1;
        display: block;
    }

    .teacher-profile-bio dd {
        margin-top: 8px;
        font-weight: 400;
        line-height: 1.6;
        text-align: left;
    }

    .teacher-profile-activity {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        margin-top: 24px;
    }

    .teacher-profile-activity>div {
        display: grid;
        gap: 4px;
        justify-items: center;
        padding: 16px 10px;
        border-radius: 10px;
        background: #f0fdf4;
    }

    .teacher-profile-activity strong {
        color: #047857;
        font-size: 20px;
    }

    .teacher-profile-activity span {
        color: #475569;
        font-size: 13px;
    }

    @media (max-width: 640px) {
        .stage-heading {
            align-items: flex-start;
            flex-direction: column;
            gap: 16px;
        }

        .profile-edit-link {
            align-self: flex-start;
        }

        .teacher-profile-overview {
            padding: 22px 18px;
        }

        .teacher-profile-identity {
            align-items: flex-start;
            flex-direction: column;
            gap: 14px;
        }

        .teacher-profile-details {
            grid-template-columns: 1fr;
        }
    }
</style>