<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ffffff">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Ruang belajar') SocratesRead</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    @php($userInitials = collect(explode(' ', auth()->user()->name))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode(''))
    <div class="app-shell">
        <button class="scrim" type="button" aria-label="Tutup navigasi" data-menu-close></button>
        <aside class="sidebar" id="primary-navigation" aria-label="Navigasi utama">
            <a class="brand" href="{{ route('dashboard') }}" aria-label="SocratesRead, beranda"><span class="brand-name">socrates<span>read</span><small>LEARN THROUGH QUESTIONS</small></span></a>
            <div class="nav-label">MENU UTAMA</div>
            <nav class="nav-list" aria-label="Menu utama">
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}" href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif><x-icon name="layout-dashboard" /><span>Dashboard</span></a>
                <a class="nav-link {{ request()->routeIs('reading') ? 'is-active' : '' }}" href="{{ route('reading') }}" @if(request()->routeIs('reading')) aria-current="page" @endif><x-icon name="book-open-text" /><span>Reading Room</span></a>
                <a class="nav-link {{ request()->routeIs('quiz.*') ? 'is-active' : '' }}" href="{{ route('quiz.index') }}" @if(request()->routeIs('quiz.*')) aria-current="page" @endif><x-icon name="gamepad-2" /><span>Challenge Socrates</span></a>
                <a class="nav-link {{ request()->routeIs('progress') ? 'is-active' : '' }}" href="{{ route('progress') }}" @if(request()->routeIs('progress')) aria-current="page" @endif><x-icon name="chart-no-axes-combined" /><span>Progress</span></a>
                <a class="nav-link {{ request()->routeIs('classrooms.*') ? 'is-active' : '' }}" href="{{ route('classrooms.index') }}" @if(request()->routeIs('classrooms.*')) aria-current="page" @endif><x-icon name="users" /><span>My Class</span></a>
                <a class="nav-link {{ request()->routeIs('settings.profile', 'profile') ? 'is-active' : '' }}" href="{{ route('profile') }}" @if(request()->routeIs('settings.profile', 'profile')) aria-current="page" @endif><x-icon name="user" /><span>Profil</span></a>
            </nav>
            <div class="nav-label nav-label-spaced">BELAJAR</div>
            <nav class="nav-list" aria-label="Materi belajar">
                <a class="nav-link {{ request()->routeIs('achievements*') ? 'is-active' : '' }}" href="{{ route('achievements') }}" @if(request()->routeIs('achievements*')) aria-current="page" @endif><x-icon name="award" /><span>Pencapaian</span></a>
            </nav>
            <div class="sidebar-spacer"></div>
            <div class="sidebar-tip"><span class="tip-icon" aria-hidden="true"><x-icon name="lightbulb" /></span><strong>Berpikir lebih dalam</strong><p>Setiap kuis dan tantangan adalah langkah menuju pemahaman baru.</p><a href="{{ route('quiz.index') }}">Challenge Socrates <x-icon name="arrow-right" /></a></div>
            <div class="profile-button">
                <a href="{{ route('profile') }}" class="avatar avatar-sidebar avatar-link" title="Profil">
                    @if (auth()->user()->avatarUrl())
                        <img src="{{ auth()->user()->avatarUrl() }}" alt="{{ auth()->user()->name }}" class="avatar-image">
                    @else
                        {{ mb_strtoupper($userInitials) }}
                    @endif
                </a>
                <span class="profile-copy">
                    <strong>{{ auth()->user()->name }}</strong>
                    <small>Siswa SocratesRead</small>
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
                <button class="icon-button menu-toggle" type="button" aria-label="Buka navigasi" aria-controls="primary-navigation" aria-expanded="false" data-menu-toggle><x-icon name="menu" /></button>
                <div class="breadcrumb"><a href="{{ route('dashboard') }}">Ruang belajar</a><span>/</span><strong>@yield('breadcrumb', 'Halaman')</strong></div>
                <div class="topbar-actions">
                    <span class="today-label">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span>
                    <button class="icon-button notification-button" type="button" aria-label="Notifikasi"><x-icon name="bell" /><i></i></button>
                    <a href="{{ route('profile') }}" class="avatar avatar-top avatar-link" aria-label="Profil {{ auth()->user()->name }}" title="Profil">
                        @if (auth()->user()->avatarUrl())
                            <img src="{{ auth()->user()->avatarUrl() }}" alt="{{ auth()->user()->name }}" class="avatar-image">
                        @else
                            {{ mb_strtoupper($userInitials) }}
                        @endif
                    </a>
                </div>
            </header>
            <div class="page-content stage-two-content">
                @yield('content')
                <footer class="page-footer"><span>Terus bertanya, terus bertumbuh.</span><span>© 2026 SocratesRead</span></footer>
            </div>
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
    @stack('scripts')
</body>
</html>
