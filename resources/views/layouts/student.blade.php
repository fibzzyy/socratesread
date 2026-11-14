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
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    @if (request()->routeIs('reading') && ! request()->route('document'))
        <link rel="preload" href="{{ asset('fonts/plus-jakarta-sans-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&display=swap" rel="stylesheet">
    @endif
    @yield('head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
    @php($isReadingIndex = request()->routeIs('reading') && ! request()->route('document'))
    @php($isProgressOverview = request()->routeIs('progress') && ! request()->query('classroom'))
    <div class="app-shell">
        <button class="scrim" type="button" aria-label="Tutup navigasi" data-menu-close></button>
        <aside class="sidebar sidebar-theme" id="primary-navigation" aria-label="Navigasi utama">
            <a class="brand" href="{{ route('dashboard') }}" aria-label="SocratesRead, beranda"><img class="brand-logo" src="{{ asset('images/socratesread-logo.jpeg') }}" alt=""><span class="brand-name">socrates<span>read</span><small>LEARN · THINK · GROW</small></span></a>
            <div class="nav-label">MENU UTAMA</div>
            <nav class="nav-list" aria-label="Menu utama">
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}" href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif><x-icon name="layout-dashboard" /><span>Beranda</span></a>
                <a class="nav-link {{ request()->routeIs('reading') ? 'is-active' : '' }}" href="{{ route('reading') }}" @if(request()->routeIs('reading')) aria-current="page" @endif><x-icon name="book-open-text" /><span>Reading Room</span></a>
                <a class="nav-link {{ request()->routeIs('classrooms.*') ? 'is-active' : '' }}" href="{{ route('classrooms.index') }}" @if(request()->routeIs('classrooms.*')) aria-current="page" @endif><x-icon name="users" /><span>Kelas saya</span></a>
            </nav>
            <div class="nav-label nav-label-spaced">BELAJAR</div>
            <nav class="nav-list" aria-label="Materi belajar">
                <a class="nav-link {{ request()->routeIs('quiz.*') ? 'is-active' : '' }}" href="{{ route('quiz.index') }}" @if(request()->routeIs('quiz.*')) aria-current="page" @endif><x-icon name="brain-circuit" /><span>Tantangan Socrates</span></a>
                <a class="nav-link {{ request()->routeIs('progress') ? 'is-active' : '' }}" href="{{ route('progress') }}" @if(request()->routeIs('progress')) aria-current="page" @endif><x-icon name="chart-no-axes-combined" /><span>Kemajuan</span></a>
                <a class="nav-link {{ request()->routeIs('achievements*') ? 'is-active' : '' }}" href="{{ route('achievements') }}" @if(request()->routeIs('achievements*')) aria-current="page" @endif><x-icon name="award" /><span>Pencapaian</span></a>
            </nav>
            <div class="sidebar-spacer"></div>
        </aside>
        <main class="main-content {{ $isReadingIndex ? 'reading-room-shell' : '' }}">
            @unless($isReadingIndex)
                <header class="topbar student-topbar-pill quiz-topbar">
                    <button class="icon-button menu-toggle" type="button" aria-label="Buka navigasi" aria-controls="primary-navigation" aria-expanded="false" data-menu-toggle><x-icon name="menu" /></button>
                    <div class="breadcrumb">
                        <a href="{{ route('dashboard') }}" class="quiz-home-link topbar-home-link" aria-label="Beranda"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7M5 9v12h14V9M9 21v-7h6v7" /></svg></a>
                        <a href="{{ route('dashboard') }}" class="hover:text-blue-600 transition-colors">Ruang belajar</a><span>/</span><strong>@yield('breadcrumb', 'Halaman')</strong>
                    </div>
                    <div class="topbar-actions">
                        <span class="today-label"><x-icon name="calendar-days" />{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span>
                        <button class="icon-button notification-button" type="button" aria-label="Notifikasi"><x-icon name="bell" /><i></i></button>
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