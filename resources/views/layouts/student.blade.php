<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ffffff">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Ruang belajar') SocratesRead</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @if (request()->routeIs('reading') && ! request()->route('document'))
        <link rel="preload" href="{{ asset('fonts/plus-jakarta-sans-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&display=swap" rel="stylesheet">
    @else
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    @endif
    <!-- KaTeX Math Rendering Support for Mathematics & Sciences -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css" crossorigin="anonymous">
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js" crossorigin="anonymous"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/contrib/auto-render.min.js" crossorigin="anonymous"
        onload="renderMathInElement(document.body, {delimiters: [{left: '$$', right: '$$', display: true}, {left: '$', right: '$', display: false}, {left: '\\(', right: '\\)', display: false}, {left: '\\[', right: '\\]', display: true}], throwOnError: false});"></script>
    @yield('head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
    @php($userInitials = collect(explode(' ', auth()->user()->name))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode(''))
    @php($isReadingIndex = request()->routeIs('reading') && ! request()->route('document'))
    @php($isProgressOverview = request()->routeIs('progress') && ! request()->query('classroom'))
    <div class="app-shell">
        <button class="scrim" type="button" aria-label="Tutup navigasi" data-menu-close></button>
        <aside class="sidebar sidebar-theme" id="primary-navigation" aria-label="Navigasi utama">
            <a class="brand" href="{{ route('dashboard') }}" aria-label="SocratesRead, beranda"><img class="brand-logo" src="{{ asset('images/logo.png') }}" alt="" style="width: 36px; height: 36px; object-fit: contain;"><span class="brand-name">Socrates<span>Read</span></span></a>
            <nav class="nav-list" aria-label="Menu utama">
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}" href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif><x-icon name="layout-dashboard" /><span>Beranda</span></a>
                <a class="nav-link {{ request()->routeIs('reading') ? 'is-active' : '' }}" href="{{ route('reading') }}" @if(request()->routeIs('reading')) aria-current="page" @endif><x-icon name="book-open-text" /><span>Reading Room</span></a>
                <a class="nav-link {{ request()->routeIs('classrooms.*') ? 'is-active' : '' }}" href="{{ route('classrooms.index') }}" @if(request()->routeIs('classrooms.*')) aria-current="page" @endif><x-icon name="users" /><span>Kelas saya</span></a>
                <a class="nav-link {{ request()->routeIs('settings.profile', 'profile') ? 'is-active' : '' }}" href="{{ route('profile') }}" @if(request()->routeIs('settings.profile', 'profile')) aria-current="page" @endif><x-icon name="user" /><span>Profil</span></a>
            </nav>
            <div class="nav-label nav-label-spaced">BELAJAR</div>
            <nav class="nav-list" aria-label="Materi belajar">
                <a class="nav-link {{ request()->routeIs('quiz.*') ? 'is-active' : '' }}" href="{{ route('quiz.index') }}" @if(request()->routeIs('quiz.*')) aria-current="page" @endif><x-icon name="brain-circuit" /><span>Challenge Socrates</span></a>
                <a class="nav-link {{ request()->routeIs('progress') ? 'is-active' : '' }}" href="{{ route('progress') }}" @if(request()->routeIs('progress')) aria-current="page" @endif><x-icon name="chart-no-axes-combined" /><span>Kemajuan</span></a>
                <a class="nav-link {{ request()->routeIs('achievements*') ? 'is-active' : '' }}" href="{{ route('achievements') }}" @if(request()->routeIs('achievements*')) aria-current="page" @endif><x-icon name="award" /><span>Pencapaian</span></a>
            </nav>
            <div class="sidebar-spacer"></div>
            <details class="profile-menu">
                <summary class="profile-button">
                <span class="avatar avatar-sidebar {{ auth()->user()->avatarUrl() ? '' : 'avatar-empty' }}">
                    @if (auth()->user()->avatarUrl())
                        <img src="{{ auth()->user()->avatarUrl() }}" alt="{{ auth()->user()->name }}" class="avatar-image">
                    @else
                        <x-icon name="user" class="h-4 w-4 text-slate-400" />
                    @endif
                </span>
                <span class="profile-copy">
                    <strong>{{ auth()->user()->name }}</strong>
                    <small>Siswa SocratesRead</small>
                </span>
                <x-icon name="chevron-down" class="profile-chevron" />
                </summary>
                <div class="profile-dropdown">
                    <a href="{{ route('profile') }}">Profil</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Keluar</button></form>
                </div>
            </details>
        </aside>
        <main class="main-content {{ $isReadingIndex ? 'reading-room-shell' : '' }}">
            @unless($isReadingIndex)
                <header class="topbar {{ request()->routeIs('quiz.index') ? 'quiz-topbar' : '' }} {{ $isProgressOverview ? 'progress-topbar' : '' }}">
                    <button class="icon-button menu-toggle" type="button" aria-label="Buka navigasi" aria-controls="primary-navigation" aria-expanded="false" data-menu-toggle><x-icon name="menu" /></button>
                    <div class="breadcrumb">
                        @if($isProgressOverview)
                            <strong>Dashboard Siswa</strong>
                        @else
                        @if(request()->routeIs('quiz.index'))
                            <a href="{{ route('dashboard') }}" class="quiz-home-link" aria-label="Beranda"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7M5 9v12h14V9M9 21v-7h6v7" /></svg></a>
                        @endif
                        <a href="{{ route('dashboard') }}">Ruang belajar</a><span>/</span><strong>@yield('breadcrumb', 'Halaman')</strong>
                        @endif
                    </div>
                    <div class="topbar-actions">
                        <span class="today-label">@if(request()->routeIs('quiz.index'))<x-icon name="calendar-days" />@endif{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span>
                        <x-notification-center />
                        <x-profile-dropdown />
                    </div>
                </header>
            @endunless
            <div class="page-content stage-two-content {{ $isReadingIndex ? 'reading-page-content' : '' }}">
                @yield('content')
                <footer class="page-footer"><span>Terus bertanya, terus bertumbuh.</span><span>© 2026
                        SocratesRead</span></footer>
            </div>
        </main>
    </div>
    <script>
        (() => {
            const shell = document.querySelector('.app-shell');
            const sidebar = shell?.querySelector('.sidebar');
            const toggle = document.querySelector('[data-menu-toggle]');
            const close = document.querySelector('[data-menu-close]');
            if (!shell || !sidebar || !toggle || !close) return;
            const mobileNavigation = window.matchMedia('(max-width: 1024px)');
            const setOpen = (open, restoreFocus = true) => {
                const isOpen = mobileNavigation.matches && open;
                shell.classList.toggle('menu-open', isOpen);
                toggle.setAttribute('aria-expanded', String(isOpen));
                document.body.classList.toggle('menu-locked', isOpen);
                sidebar.inert = mobileNavigation.matches && !isOpen;
                if (isOpen && restoreFocus) sidebar.querySelector('.nav-link')?.focus({ preventScroll: true });
                else if (restoreFocus && sidebar.contains(document.activeElement) && toggle.getClientRects().length) toggle.focus({ preventScroll: true });
            };
            setOpen(false, false);
            toggle.addEventListener('click', () => setOpen(!shell.classList.contains('menu-open')));
            close.addEventListener('click', () => setOpen(false));
            shell.querySelectorAll('.sidebar .nav-link, .sidebar .brand').forEach((link) => link.addEventListener('click', () => setOpen(false)));
            document.addEventListener('keydown', (event) => { if (event.key === 'Escape') setOpen(false); });
            mobileNavigation.addEventListener('change', () => setOpen(false, false));
        })();
    </script>
    @stack('scripts')
</body>

</html>
