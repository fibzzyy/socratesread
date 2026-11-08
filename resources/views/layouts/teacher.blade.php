<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Area Guru') SocratesRead</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    @php($userInitials = collect(explode(' ', auth()->user()->name))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode(''))
    <div class="app-shell">
        <button class="scrim" type="button" aria-label="Tutup navigasi" data-menu-close></button>
        <aside class="sidebar" id="teacher-navigation" aria-label="Area guru">
            <a class="brand" href="{{ route('teacher.dashboard') }}" aria-label="SocratesRead, area guru"><span class="brand-name">socrates<span>read</span><small>AREA GURU</small></span></a>
            <div class="nav-label">PEMANTAUAN BELAJAR</div>
            <nav class="nav-list" aria-label="Menu guru">
                <a class="nav-link {{ request()->routeIs('teacher.dashboard') ? 'is-active' : '' }}" href="{{ route('teacher.dashboard') }}" @if(request()->routeIs('teacher.dashboard')) aria-current="page" @endif><x-icon name="layout-dashboard"/><span>Ringkasan</span></a>
                <a class="nav-link {{ request()->routeIs('teacher.classrooms*') ? 'is-active' : '' }}" href="{{ route('teacher.classrooms') }}" @if(request()->routeIs('teacher.classrooms*')) aria-current="page" @endif><x-icon name="users"/><span>Kelas saya</span></a>
                <a class="nav-link {{ request()->routeIs('teacher.documents*') ? 'is-active' : '' }}" href="{{ route('teacher.documents') }}" @if(request()->routeIs('teacher.documents*')) aria-current="page" @endif><x-icon name="book-open-text"/><span>Kelola bacaan</span></a>
                <a class="nav-link {{ request()->routeIs('settings.profile', 'profile') ? 'is-active' : '' }}" href="{{ route('settings.profile') }}" @if(request()->routeIs('settings.profile', 'profile')) aria-current="page" @endif><x-icon name="user"/><span>Profil</span></a>
            </nav>
            <div class="sidebar-spacer"></div>
            <div class="profile-button">
                <a href="{{ route('settings.profile') }}" class="avatar avatar-sidebar avatar-link" title="Profil">
                    @if (auth()->user()->avatarUrl())
                        <img src="{{ auth()->user()->avatarUrl() }}" alt="{{ auth()->user()->name }}" class="avatar-image">
                    @else
                        {{ mb_strtoupper($userInitials) }}
                    @endif
                </a>
                <span class="profile-copy">
                    <strong>{{ auth()->user()->name }}</strong>
                    <small>Guru SocratesRead</small>
                </span>
                <form method="POST" action="{{ route('logout') }}" class="ml-auto">
                    @csrf
                    <button class="logout-button" type="submit" title="Keluar / Logout">
                        <x-icon name="log-out" class="w-3.5 h-3.5" />
                        <span>Keluar</span>
                    </button>
                </form>
            </div>
        </aside>
        <main class="main-content">
            <header class="topbar">
                <button class="icon-button menu-toggle" type="button" aria-label="Buka navigasi" aria-controls="teacher-navigation" aria-expanded="false" data-menu-toggle><x-icon name="menu" /></button>
                <div class="breadcrumb"><a href="{{ route('teacher.dashboard') }}">Area guru</a><span>/</span><strong>@yield('breadcrumb', 'Ringkasan')</strong></div>
                <div class="topbar-actions">
                    <span class="today-label">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span>
                    <button class="icon-button notification-button" type="button" aria-label="Notifikasi"><x-icon name="bell" /><i></i></button>
                    <a href="{{ route('settings.profile') }}" class="avatar avatar-top avatar-link" aria-label="Pengaturan profil {{ auth()->user()->name }}" title="Pengaturan profil">
                        @if (auth()->user()->avatarUrl())
                            <img src="{{ auth()->user()->avatarUrl() }}" alt="{{ auth()->user()->name }}" class="avatar-image">
                        @else
                            {{ mb_strtoupper($userInitials) }}
                        @endif
                    </a>
                </div>
            </header>
            <div class="page-content stage-two-content">@yield('content')<footer class="page-footer"><span>Materi bermakna dimulai dari bacaan yang baik.</span><span>© 2026 SocratesRead</span></footer></div>
        </main>
    </div>
    <script>
        (() => {
            const shell = document.querySelector('.app-shell');
            const toggle = document.querySelector('[data-menu-toggle]');
            const close = document.querySelector('[data-menu-close]');
            const setOpen = (open) => { shell.classList.toggle('menu-open', open); toggle.setAttribute('aria-expanded', String(open)); document.body.classList.toggle('menu-locked', open); };
            toggle.addEventListener('click', () => setOpen(!shell.classList.contains('menu-open')));
            close.addEventListener('click', () => setOpen(false));
            document.addEventListener('keydown', (event) => { if (event.key === 'Escape') setOpen(false); });
        })();
    </script>
</body>
</html>
