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
        <a href="{{ route('settings.profile') }}" class="challenge-button profile-edit-link">Edit Profil</a>
    </section>

    @if (session('status'))
        <div class="auth-notice" role="status">
            <x-icon name="check" />
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <section class="panel student-profile-overview" aria-labelledby="student-name">
        <div class="student-profile-identity">
            <div class="student-avatar-wrapper">
                <div class="avatar student-avatar">
                    @if ($user->avatarUrl())
                        <img src="{{ $user->avatarUrl() }}" alt="Foto profil {{ $user->name }}" class="avatar-image" width="110" height="110">
                    @else
                        <span class="student-avatar-placeholder">
                            {{ mb_strtoupper(collect(explode(' ', $user->name))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('')) }}
                        </span>
                    @endif
                </div>
                <span class="student-role-badge">Siswa</span>
            </div>

            <div class="student-profile-name">
                <h2 id="student-name">{{ $user->name }}</h2>
                <p>{{ $user->email }}</p>
            </div>
        </div>

        <dl class="student-profile-details">
            <div>
                <dt>Peran</dt>
                <dd>Siswa SocratesRead</dd>
            </div>
            <div>
                <dt>Bergabung sejak</dt>
                <dd>{{ $user->created_at?->locale('id')->translatedFormat('d F Y') ?? '-' }}</dd>
            </div>
            <div>
                <dt>Sekolah</dt>
                <dd>{{ $user->school_origin ?: '-' }}</dd>
            </div>
            <div>
                <dt>NISN</dt>
                <dd>{{ $user->nisn ?: '-' }}</dd>
            </div>
            <div>
                <dt>Kelas saat ini</dt>
                <dd>{{ $user->currentGrade() ? 'Kelas '.$user->currentGrade() : '-' }}</dd>
            </div>
            @if ($user->bio)
                <div class="student-profile-bio">
                    <dt>Bio</dt>
                    <dd>{{ $user->bio }}</dd>
                </div>
            @endif
        </dl>
    </section>
@endsection

<style>
    .profile-edit-link {
        min-height: 40px;
        align-self: center;
        padding-inline: 30px;
        font-size: 16px;
        text-decoration: none;
    }

    .student-profile-overview {
        width: 100%;
        margin-top: 10px;
        padding: 32px;
    }

    .student-profile-identity {
        display: flex;
        align-items: center;
        gap: 24px;
        padding-bottom: 24px;
        border-bottom: 1px solid #e2e8f0;
    }

    .student-avatar-wrapper {
        position: relative;
        flex: 0 0 auto;
    }

    .student-avatar {
        width: 110px;
        height: 110px;
        overflow: hidden;
        border: 4px solid #e0e7ff;
        border-radius: 50%;
    }

    .student-avatar-placeholder {
        color: #2563eb;
        font-size: 34px;
        font-weight: 700;
    }

    .student-role-badge {
        position: absolute;
        right: 2px;
        bottom: 2px;
        padding: 4px 10px;
        border: 2px solid #fff;
        border-radius: 9999px;
        background: #2563eb;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
    }

    .student-profile-name h2 {
        margin: 0 0 6px;
        color: #1e293b;
        font-size: 22px;
    }

    .student-profile-name p {
        margin: 0;
        color: #64748b;
        overflow-wrap: anywhere;
    }

    .student-profile-details {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0 32px;
        margin: 8px 0 0;
    }

    .student-profile-details > div {
        display: flex;
        justify-content: space-between;
        gap: 20px;
        padding: 16px 0;
        border-bottom: 1px solid #e2e8f0;
    }

    .student-profile-details dt {
        color: #64748b;
    }

    .student-profile-details dd {
        margin: 0;
        color: #1e293b;
        font-weight: 600;
        text-align: right;
        overflow-wrap: anywhere;
    }

    .student-profile-details .student-profile-bio {
        grid-column: 1 / -1;
        display: block;
    }

    .student-profile-bio dd {
        margin-top: 8px;
        font-weight: 400;
        line-height: 1.6;
        text-align: left;
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

        .student-profile-overview {
            padding: 22px 18px;
        }

        .student-profile-identity {
            align-items: flex-start;
            flex-direction: column;
            gap: 14px;
        }

        .student-profile-details {
            grid-template-columns: 1fr;
        }
    }
</style>
