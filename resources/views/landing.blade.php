<!DOCTYPE html>
<html lang="id" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0B132B">
    <meta name="description"
        content="SocratesRead: Platform membaca kritis dan penalaran reflektif berbasis dialektika Sokratik untuk siswa dan pendidik.">
    <title>SocratesRead | Belajar Membaca Kritis Melalui Pertanyaan</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">

    <!-- Google Fonts: Editorial Serif & Modern Clean Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Crimson+Pro:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .font-serif-reading {
            font-family: 'Crimson Pro', Georgia, serif;
        }

        .font-sans-ui {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
        }
    </style>
</head>

<body class="font-sans-ui bg-slate-50 text-slate-800 antialiased selection:bg-blue-600 selection:text-white"
    id="socratesread-landing">

    <!-- ====================================================================
         1. 3D BOOK OPENING INTRO OVERLAY
         ==================================================================== -->
    <div id="book-intro-overlay" class="book-intro-overlay" role="dialog" aria-modal="true"
        aria-label="Intro Buku Interaktif SocratesRead">
        <!-- Aurora Ambient Glow Background (Inspired by ReactBits.dev) -->
        <div class="aurora-ambient-glow" aria-hidden="true"></div>

        <!-- Celestial Starry Sparkles Canvas / Accents -->
        <div class="stars-canvas" aria-hidden="true">
            <div class="absolute top-1/4 left-1/5 w-1 h-1 bg-amber-300 rounded-full animate-ping"></div>
            <div class="absolute top-1/3 right-1/4 w-1.5 h-1.5 bg-blue-300 rounded-full animate-pulse"></div>
            <div class="absolute bottom-1/4 left-1/3 w-1 h-1 bg-indigo-200 rounded-full animate-pulse"></div>
            <div class="absolute top-2/3 right-1/5 w-2 h-2 bg-amber-200 rounded-full opacity-60"></div>
        </div>

        <button id="btn-skip-intro" class="btn-skip-intro" type="button"
            aria-label="Lewati intro dan langsung ke halaman utama">
            Lewati Intro (Esc)
        </button>

        <!-- 3D Book Stage -->
        <div id="book-stage" class="book-stage" tabindex="0" role="button"
            aria-label="Buku SocratesRead. Klik atau tekan Spasi untuk membuka buku.">
            <div class="book-light-burst" aria-hidden="true"></div>

            <div class="book-3d" id="book-3d">
                <!-- Book Spine (Left spine block with leather grain and raised bands) -->
                <div class="book-spine">
                    <div class="book-spine-rib rib-1" aria-hidden="true"></div>
                    <div class="book-spine-rib rib-2" aria-hidden="true"></div>
                    <span class="book-spine-text">SOCRATESREAD · 2026</span>
                    <div class="book-spine-rib rib-3" aria-hidden="true"></div>
                    <div class="book-spine-rib rib-4" aria-hidden="true"></div>
                </div>

                <!-- Book Headband Cloth (Top & Bottom of spine, authentic bookbinding) -->
                <div class="book-headband book-headband-top" aria-hidden="true"></div>
                <div class="book-headband book-headband-bottom" aria-hidden="true"></div>

                <!-- Silk Ribbon Bookmark draping across center gutter -->
                <div class="book-ribbon-bookmark" aria-hidden="true">
                    <div class="book-ribbon-tail"></div>
                </div>

                <!-- Back Cover -->
                <div class="book-cover-back" aria-hidden="true"></div>

                <!-- Right Page: Inisiasi Penalaran & Gerbang Dialektika -->
                <div class="book-sheet-stack" id="book-sheet-stack">
                    <div class="book-page-border-inner" aria-hidden="true"></div>
                    <div class="book-page-content p-5 sm:p-7 flex flex-col justify-between h-full relative z-10">
                        <div class="border-b border-amber-900/10 pb-2 flex items-center justify-between">
                            <span
                                class="text-[10px] font-bold tracking-widest text-amber-800 uppercase bg-amber-100/80 px-2 py-0.5 rounded">Inisiasi
                                Penalaran</span>
                            <span class="text-[10px] sm:text-[11px] font-semibold text-slate-400">SocratesRead</span>
                        </div>

                        <div class="py-2 sm:py-3 flex-1 flex flex-col justify-center">
                            <h2 class="font-serif-reading text-lg sm:text-xl font-bold text-slate-900 mb-1.5">Gerbang
                                Dialektika</h2>
                            <blockquote
                                class="font-serif-reading italic text-slate-700 text-xs sm:text-sm leading-relaxed border-l-2 border-amber-500/70 pl-2.5 my-1.5">
                                "Pikiran bukanlah bejana kosong yang perlu diisi, melainkan kayu bakar yang hanya
                                membutuhkan percikan api untuk menyala."
                            </blockquote>
                            <p class="text-right text-[11px] font-semibold text-slate-500 mt-0.5 mb-2">(Plutarkhos)</p>
                            <p class="text-[11px] sm:text-xs text-slate-600 leading-relaxed">
                                Membaca kritis bukan sekadar menelan kata-kata, melainkan keberanian mengajukan
                                pertanyaan mendalam pada setiap gagasan yang tersaji.
                            </p>
                        </div>

                        <div class="pt-2 sm:pt-2.5 border-t border-amber-900/10 flex items-center justify-between">
                            <span class="text-[10px] sm:text-[11px] font-serif-reading italic text-slate-400">Ars
                                Cogitandi</span>
                            <button type="button"
                                class="btn-sheet-finish shimmer-cta inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-md transition cursor-pointer">
                                <span>Mulai Sekarang</span>
                                <x-icon name="arrow-right" class="w-3.5 h-3.5 text-amber-300" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Cascading Flipping Paper Leaves (Realistic Book Flutter to Middle Spread) -->
                <div class="book-fanning-leaves" aria-hidden="true">
                    <div class="book-flip-leaf leaf-1">
                        <div class="leaf-face leaf-front">
                            <div class="leaf-lines"></div>
                        </div>
                        <div class="leaf-face leaf-back">
                            <div class="leaf-lines"></div>
                        </div>
                    </div>
                    <div class="book-flip-leaf leaf-2">
                        <div class="leaf-face leaf-front">
                            <div class="leaf-lines"></div>
                        </div>
                        <div class="leaf-face leaf-back">
                            <div class="leaf-lines"></div>
                        </div>
                    </div>
                    <div class="book-flip-leaf leaf-3">
                        <div class="leaf-face leaf-front">
                            <div class="leaf-lines"></div>
                        </div>
                        <div class="leaf-face leaf-back">
                            <div class="leaf-lines"></div>
                        </div>
                    </div>
                    <div class="book-flip-leaf leaf-4">
                        <div class="leaf-face leaf-front">
                            <div class="leaf-lines"></div>
                        </div>
                        <div class="leaf-face leaf-back">
                            <div class="leaf-lines"></div>
                        </div>
                    </div>
                </div>

                <!-- Front Cover (Rotates open) -->
                <div class="book-cover-front">
                    <!-- Front face of the cover -->
                    <div class="book-cover-front-face">
                        <!-- 4 Corner Gold Filigree Ornaments -->
                        <div class="book-corner book-corner-tl" aria-hidden="true"></div>
                        <div class="book-corner book-corner-tr" aria-hidden="true"></div>
                        <div class="book-corner book-corner-bl" aria-hidden="true"></div>
                        <div class="book-corner book-corner-br" aria-hidden="true"></div>

                        <div class="book-cover-border" aria-hidden="true"></div>

                        <!-- Header gold ornament -->
                        <div class="w-full text-center pt-2">
                            <span class="text-[10px] tracking-[0.3em] font-bold text-amber-400 uppercase">Ars
                                Cogitandi</span>
                            <div class="w-16 h-px bg-amber-400/40 mx-auto mt-1"></div>
                        </div>

                        <!-- Center Medallion with User's Uploaded Logo -->
                        <div class="flex flex-col items-center text-center my-auto px-2">
                            <div
                                class="w-24 h-24 sm:w-28 sm:h-28 rounded-full bg-white p-2.5 shadow-2xl border-2 border-amber-400/80 flex items-center justify-center mb-4 relative ring-4 ring-amber-400/20">
                                <img src="{{ asset('images/logo.png') }}" alt="Logo SocratesRead"
                                    class="w-full h-full object-contain">
                            </div>

                            <h1 class="font-serif-reading text-2xl sm:text-3xl font-bold text-amber-100 tracking-wide">
                                SocratesRead
                            </h1>
                            <p class="text-xs text-blue-200/80 mt-1 font-medium tracking-wider">
                                LEARN THROUGH QUESTIONS
                            </p>
                        </div>

                        <!-- Footer Gold Note -->
                        <div class="w-full text-center pb-2">
                            <div class="w-12 h-px bg-amber-400/30 mx-auto mb-2"></div>
                            <span class="text-[10px] tracking-widest text-amber-400/90 uppercase">Ketuk Untuk
                                Membuka</span>
                        </div>
                    </div>

                    <!-- Left Page: Dialektika Socrates & Ex Libris (Paper Page matching Right Page) -->
                    <div class="book-cover-back-face" aria-hidden="false">
                        <div class="book-page-border-inner" aria-hidden="true"></div>
                        <div
                            class="book-page-content p-5 sm:p-7 flex flex-col justify-between h-full relative z-10 w-full">
                            <!-- Header with Category Badge & Platform Name -->
                            <div class="border-b border-amber-900/10 pb-2 flex items-center justify-between">
                                <span
                                    class="text-[10px] font-bold tracking-widest text-amber-800 uppercase bg-amber-100/80 px-2 py-0.5 rounded">Ex
                                    Libris</span>
                                <span class="text-[10px] sm:text-[11px] font-semibold text-slate-400">Ars
                                    Cogitandi</span>
                            </div>

                            <!-- Body with Title, Socratic Quote & Pedagogical Note -->
                            <div class="py-2 sm:py-3 flex-1 flex flex-col justify-center">
                                <h2 class="font-serif-reading text-lg sm:text-xl font-bold text-slate-900 mb-1.5">
                                    Dialektika Socrates</h2>
                                <blockquote
                                    class="font-serif-reading italic text-slate-700 text-xs sm:text-sm leading-relaxed border-l-2 border-amber-500/70 pl-2.5 my-1.5">
                                    "Kebenaran tidak diajarkan secara dogmatis, melainkan dilahirkan kembali melalui
                                    dialog yang jujur."
                                </blockquote>
                                <p class="text-right text-[11px] font-semibold text-slate-500 mt-0.5 mb-2">(Socrates)
                                </p>
                                <p class="text-[11px] sm:text-xs text-slate-600 leading-relaxed">
                                    Seni bertanya membimbing akal melahirkan pemahaman sejati dari dalam diri, bukan
                                    sekadar menerima dogma tanpa uji nalar.
                                </p>
                            </div>

                            <!-- Footer with Action Button -->
                            <div class="pt-2 sm:pt-2.5 border-t border-amber-900/10 flex items-center justify-between">
                                <span class="text-[10px] sm:text-[11px] font-serif-reading italic text-slate-400">2026 ·
                                    Filosofi Baca</span>
                                <button type="button"
                                    class="btn-cover-close inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-300 hover:border-slate-400 bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs shadow-xs transition cursor-pointer"
                                    title="Tutup kembali buku ini">
                                    <x-icon name="book-minus" class="w-3.5 h-3.5 text-slate-500" />
                                    <span>Tutup Sampul</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Athena Owl Mascot (Airborne in overlay, non-blocking, draggable anywhere) -->
        <div id="intro-owl-popup" class="intro-owl-popup intro-owl-flight" role="region"
            aria-label="Maskot Burung Hantu Athena Interaktif (Dapat Digeser)">
            <!-- Drag Helper Handle / Badge -->
            <div class="owl-drag-badge" title="Klik dan seret Athena ke mana saja!">
                <x-icon name="grip-horizontal" class="w-3 h-3 text-amber-300" />
                <span>Geser Aku!</span>
            </div>

            <!-- Flight Stardust & Sparkling Particles -->
            <div class="flight-sparkles" aria-hidden="true">
                <span class="sparkle sp-1">✦</span>
                <span class="sparkle sp-2">★</span>
                <span class="sparkle sp-3">✦</span>
                <span class="sparkle sp-4">★</span>
                <span class="sparkle sp-5">✦</span>
                <span class="sparkle sp-6">★</span>
            </div>

            <!-- Speech Bubble above flying Athena -->
            <div id="intro-owl-bubble" class="intro-owl-bubble font-serif-reading">
                "Salam pembelajar! Mari buka cakrawala pikiranmu."
            </div>

            <div class="owl-interactive-svg-wrap">
                <x-athena-owl prefix="intro" :flying="true" class="w-64 h-48 sm:w-72 sm:h-52 filter drop-shadow-2xl" />
            </div>
        </div>

        <!-- Controls below the book -->
        <div id="intro-controls" class="book-intro-controls">
            <!-- Initial button before opening -->
            <button id="btn-open-book" class="btn-open-book shimmer-cta" type="button">
                <x-icon name="book-open-text" class="w-5 h-5 text-amber-300" />
                <span>Buka Lembaran Buku</span>
            </button>

            <!-- Interactive Bar when book is open (Sleek 2-button layout) -->
            <div id="intro-controls-open" class="book-intro-open-bar is-hidden">
                <!-- Close Book Button -->
                <button id="btn-close-book" class="btn-book-action btn-close-book" type="button"
                    title="Tutup kembali buku ini">
                    <x-icon name="book-minus" class="w-4 h-4 text-amber-300" />
                    <span class="hidden sm:inline">Tutup Buku</span>
                    <span class="sm:hidden">Tutup</span>
                </button>

                <!-- Get Started / Masuk Platform Button (Shimmer CTA) -->
                <button id="btn-get-started" class="btn-book-action btn-get-started-cta shimmer-cta" type="button">
                    <span>Mulai Sekarang</span>
                    <x-icon name="arrow-right" class="w-4 h-4 text-amber-300" />
                </button>
            </div>
        </div>
    </div>


    <!-- ====================================================================
         2. TOP NAVIGATION BAR
         ==================================================================== -->
    <header class="sticky top-0 z-40 w-full backdrop-blur-md bg-white/90 border-b border-slate-200/80 transition-all">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-18 flex items-center justify-between">
            <!-- Brand & Logo -->
            <a href="{{ route('home') }}"
                class="flex items-center gap-3 group focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 rounded-lg p-1"
                aria-label="SocratesRead Beranda">
                <div
                    class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-900 to-slate-950 p-1 border border-blue-400/30 shadow-sm flex items-center justify-center overflow-hidden">
                    <img src="{{ asset('images/logo.png') }}" alt="" class="w-full h-full object-contain">
                </div>
                <div class="flex flex-col">
                    <span
                        class="text-lg sm:text-xl font-bold tracking-tight text-slate-900 group-hover:text-blue-600 transition-colors">
                        socrates<span class="text-blue-600">read</span>
                    </span>
                    <span
                        class="text-[10px] font-semibold tracking-wider text-slate-500 uppercase -mt-1 hidden sm:inline-block">
                        Tanya & Pahami
                    </span>
                </div>
            </a>

            <!-- Desktop Nav Links -->
            <nav class="hidden md:flex items-center gap-8" aria-label="Menu Utama">
                <a href="#filosofi"
                    class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors focus-visible:ring-2 focus-visible:ring-blue-600 rounded">Filosofi</a>
                <a href="#fitur"
                    class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors focus-visible:ring-2 focus-visible:ring-blue-600 rounded">Fitur
                    Utama</a>
                <a href="#demo-sokratik"
                    class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors focus-visible:ring-2 focus-visible:ring-blue-600 rounded">Uji
                    Daya Nalar</a>
                <a href="#ruang-guru"
                    class="text-sm font-medium text-slate-600 hover:text-blue-600 transition-colors focus-visible:ring-2 focus-visible:ring-blue-600 rounded">Untuk
                    Guru</a>
            </nav>

            <!-- Actions & Auth -->
            <div class="flex items-center gap-2 sm:gap-3">
                <!-- Sound Toggle -->
                <button type="button" data-audio-toggle
                    class="p-2 rounded-xl text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition-colors focus-visible:ring-2 focus-visible:ring-blue-600"
                    aria-label="Aktifkan atau bisukan efek suara" title="Efek Suara">
                    <span class="icon-sound-on hidden"><x-icon name="volume-2" class="w-5 h-5 text-blue-600" /></span>
                    <span class="icon-sound-off"><x-icon name="volume-x" class="w-5 h-5" /></span>
                </button>

                @auth
                    <!-- Logged in state -->
                    <a href="{{ auth()->user()->role === 'teacher' ? route('teacher.dashboard') : route('dashboard') }}"
                        class="inline-flex items-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs sm:text-sm font-semibold shadow-sm transition-all focus-visible:ring-2 focus-visible:ring-blue-600">
                        <x-icon name="layout-dashboard" class="w-4 h-4" />
                        <span class="hidden sm:inline">Buka Dashboard</span>
                        <span class="sm:hidden">Dashboard</span>
                    </a>
                @else
                    <!-- Guest state -->
                    <a href="{{ route('login') }}"
                        class="hidden sm:inline-flex px-4 py-2 rounded-xl text-sm font-semibold text-slate-700 hover:text-blue-600 hover:bg-slate-100 transition-all focus-visible:ring-2 focus-visible:ring-blue-600">
                        Masuk
                    </a>
                    <a href="{{ route('register') }}"
                        class="inline-flex items-center gap-1.5 px-3 sm:px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs sm:text-sm font-semibold shadow-sm hover:shadow-md transition-all focus-visible:ring-2 focus-visible:ring-blue-600">
                        <span>Daftar</span>
                        <x-icon name="arrow-right" class="w-3.5 h-3.5" />
                    </a>
                @endauth

                <!-- Mobile Menu Button -->
                <button id="landing-mobile-menu-btn" type="button"
                    class="md:hidden p-2 rounded-xl text-slate-700 hover:bg-slate-100 transition"
                    aria-label="Buka menu navigasi" aria-expanded="false">
                    <x-icon name="menu" class="w-6 h-6" />
                </button>
            </div>
        </div>

        <!-- Mobile Drawer Navigation -->
        <div id="landing-mobile-nav"
            class="hidden md:hidden border-t border-slate-200 bg-white px-4 pt-3 pb-6 space-y-3">
            <a href="#filosofi"
                class="block px-3 py-2 rounded-lg text-base font-medium text-slate-700 hover:bg-slate-50">Filosofi</a>
            <a href="#fitur"
                class="block px-3 py-2 rounded-lg text-base font-medium text-slate-700 hover:bg-slate-50">Fitur
                Utama</a>
            <a href="#demo-sokratik"
                class="block px-3 py-2 rounded-lg text-base font-medium text-slate-700 hover:bg-slate-50">Uji Daya
                Nalar</a>
            <a href="#ruang-guru"
                class="block px-3 py-2 rounded-lg text-base font-medium text-slate-700 hover:bg-slate-50">Untuk Guru</a>
            <div class="pt-3 border-t border-slate-100 flex flex-col gap-2">
                @guest
                    <a href="{{ route('login') }}"
                        class="w-full text-center py-2.5 rounded-xl border border-slate-300 font-semibold text-slate-700">Masuk</a>
                    <a href="{{ route('register') }}"
                        class="w-full text-center py-2.5 rounded-xl bg-blue-600 text-white font-semibold shadow-sm">Daftar
                        Akun Baru</a>
                @endguest
            </div>
        </div>
    </header>


    <!-- ====================================================================
         3. HERO SECTION WITH ATHENA THE OWL MASCOT
         ==================================================================== -->
    <main>
        <section
            class="relative overflow-hidden pt-12 pb-20 lg:pt-20 lg:pb-32 bg-gradient-to-b from-slate-100/70 via-white to-slate-50">
            <!-- Subtle background aesthetic shapes -->
            <div
                class="absolute top-0 right-1/4 w-96 h-96 bg-blue-100/40 rounded-full blur-3xl pointer-events-none -z-10">
            </div>
            <div
                class="absolute bottom-10 left-10 w-80 h-80 bg-violet-100/40 rounded-full blur-3xl pointer-events-none -z-10">
            </div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">

                    <!-- Left: Hero Headline & Copy -->
                    <div class="lg:col-span-7 space-y-6 text-center lg:text-left">

                        <!-- Pedagogical Badge -->
                        <div
                            class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-blue-50 border border-blue-200/80 text-blue-700 text-xs font-semibold shadow-xs">
                            <span class="w-2 h-2 rounded-full bg-blue-600 animate-pulse"></span>
                            <span>Metode Dialektika Sokratik untuk SMA & SMK</span>
                        </div>

                        <!-- Main Heading -->
                        <h1 id="hero-main-title"
                            class="font-serif-reading text-4xl sm:text-5xl lg:text-6xl font-bold text-slate-900 tracking-tight leading-[1.15]">
                            Jangan Sekadar Membaca. <br>
                            <span
                                class="text-transparent bg-clip-text bg-gradient-to-r from-blue-700 via-indigo-600 to-violet-700">
                                Berdialoglah dengan Pengetahuan.
                            </span>
                        </h1>

                        <!-- Subtitle -->
                        <p class="text-base sm:text-lg text-slate-600 max-w-2xl mx-auto lg:mx-0 leading-relaxed">
                            Membaca bukan sekadar menghafal deretan kalimat. Di SocratesRead, setiap teks menjadi arena
                            tanya jawab kritis bersama <strong>Athena</strong>, melatihmu menguji asumsi, menemukan
                            premis tersembunyi, dan mengonstruksi pemikiran mandiri.
                        </p>

                        <!-- Action Buttons -->
                        <div class="pt-2 flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4">
                            <a href="#demo-sokratik"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-6 py-3.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-semibold shadow-md hover:shadow-lg transition-all focus-visible:ring-2 focus-visible:ring-blue-600">
                                <span>Coba Dialog Sokratik</span>
                                <x-icon name="arrow-right" class="w-4 h-4" />
                            </a>

                            <button type="button" data-reopen-book
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-3.5 rounded-xl bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 font-semibold shadow-xs transition-all focus-visible:ring-2 focus-visible:ring-slate-400">
                                <x-icon name="rotate-ccw" class="w-4 h-4 text-blue-600" />
                                <span>Buka Ulang Buku 3D</span>
                            </button>
                        </div>

                        <!-- Trust / Pedagogical points (Responsive cards: stacked on mobile, 3-col on tablet/desktop) -->
                        <div
                            class="pt-6 border-t border-slate-200/80 grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 text-left">
                            <div
                                class="p-3 sm:p-3.5 rounded-xl bg-white/80 border border-slate-200/70 shadow-xs flex sm:flex-col items-center sm:items-start gap-3 sm:gap-1">
                                <span
                                    class="block text-xl sm:text-2xl font-bold text-slate-900 font-serif-reading shrink-0">3
                                    Tingkat</span>
                                <span class="text-xs text-slate-500 font-medium">Bahan ajar kurasi Kelas 10, 11, dan
                                    12</span>
                            </div>
                            <div
                                class="p-3 sm:p-3.5 rounded-xl bg-blue-50/60 border border-blue-200/60 shadow-xs flex sm:flex-col items-center sm:items-start gap-3 sm:gap-1">
                                <span
                                    class="block text-xl sm:text-2xl font-bold text-blue-600 font-serif-reading shrink-0">Reflektif</span>
                                <span class="text-xs text-slate-500 font-medium">Melatih penalaran analitis
                                    mandiri</span>
                            </div>
                            <div
                                class="p-3 sm:p-3.5 rounded-xl bg-indigo-50/60 border border-indigo-200/60 shadow-xs flex sm:flex-col items-center sm:items-start gap-3 sm:gap-1">
                                <span
                                    class="block text-xl sm:text-2xl font-bold text-indigo-600 font-serif-reading shrink-0">Pendamping</span>
                                <span class="text-xs text-slate-500 font-medium">Dipandu Athena dengan pertanyaan
                                    bertingkat</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Athena the Owl Mascot Interactive Area -->
                    <div class="lg:col-span-5 flex flex-col items-center justify-center relative pt-4 lg:pt-0">
                        <div class="athena-hero-stage flex flex-col items-center w-full max-w-sm mx-auto relative">
                            <!-- Floating Speech Bubble above Athena (strictly centered directly above Athena) -->
                            <div id="athena-speech-bubble" class="athena-speech-bubble w-full max-w-xs sm:max-w-sm mb-3.5"
                                role="status" aria-live="polite">
                                <div class="flex items-center justify-between pb-1.5 border-b border-slate-100 mb-2">
                                    <div class="flex items-center gap-1.5 text-xs font-bold text-blue-700">
                                        <span class="w-2 h-2 rounded-full bg-blue-600 animate-pulse"></span>
                                        <span>Athena Menjawab</span>
                                    </div>
                                    <button id="btn-next-wisdom" type="button"
                                        class="text-[11px] text-slate-400 hover:text-blue-600 font-medium cursor-pointer"
                                        title="Kutipan Lain">
                                        Ganti &rarr;
                                    </button>
                                </div>
                                <p id="athena-quote-text"
                                    class="text-xs sm:text-sm text-slate-700 font-serif-reading italic leading-relaxed">
                                    "Pikiran yang terlatih mampu menimbang suatu gagasan tanpa harus langsung menelannya
                                    mentah-mentah."
                                </p>
                                <p id="athena-quote-author"
                                    class="text-[11px] text-slate-400 font-medium text-right mt-1.5">
                                    Aristoteles, Etika Nikomakea
                                </p>
                            </div>

                            <!-- Bespoke SVG Owl Mascot: "Athena" with Hinggap Perch Animation -->
                            <div id="athena-hero-mascot" class="athena-mascot-wrapper" role="button" tabindex="0"
                                aria-label="Athena sang Burung Hantu. Klik untuk mendengar nasihat membaca kritis."
                                title="Klik Athena untuk nasihat berpikir!">
                                <!-- Perch Touchdown Golden Dust Burst on Hinggap -->
                                <div class="athena-landing-sparkles" aria-hidden="true">
                                    <span class="sparkle lsp-1">✦</span>
                                    <span class="sparkle lsp-2">★</span>
                                    <span class="sparkle lsp-3">✦</span>
                                    <span class="sparkle lsp-4">★</span>
                                </div>
                                <x-athena-owl prefix="hero" class="w-48 h-56 sm:w-60 sm:h-68 lg:w-64 lg:h-72" />
                            </div>
                            <span class="text-xs text-slate-400 mt-2 font-medium text-center">Athena · Pendamping Membaca SocratesRead</span>
                        </div>
                    </div>

                </div>
            </div>
        </section>


        <!-- ====================================================================
         4. FILOSOFI: MEMBACA PASIF VS DIALEKTIKA SOKRATIK
         ==================================================================== -->
        <section id="filosofi" class="py-20 bg-white border-y border-slate-200/80">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="max-w-3xl mx-auto text-center space-y-4 mb-16">
                    <span
                        class="text-xs font-bold uppercase tracking-wider text-blue-600 bg-blue-50 px-3 py-1 rounded-full">
                        Paradigma Belajar
                    </span>
                    <h2 class="font-serif-reading text-3xl sm:text-4xl font-bold text-slate-900">
                        Dua Cara Menghadapi Sebuah Bacaan
                    </h2>
                    <p class="text-slate-600 text-base sm:text-lg">
                        Di era banjir informasi dan rangkuman instan kecerdasan buatan, keterampilan yang sesungguhnya
                        membedakan manusia adalah ketajaman dalam menguji apa yang dibaca.
                    </p>
                </div>

                <!-- Comparison Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-12">

                    <!-- Card 1: Membaca Pasif (Kelemahan cara lama) -->
                    <div class="p-8 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-6">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-10 h-10 rounded-xl bg-slate-200 text-slate-600 flex items-center justify-center font-bold">
                                &times;
                            </div>
                            <div>
                                <h3 class="font-serif-reading text-xl font-bold text-slate-900">Membaca Pasif & Hafalan
                                </h3>
                                <p class="text-xs text-slate-500">Menerima teks apa adanya tanpa pengujian</p>
                            </div>
                        </div>

                        <ul class="space-y-4 text-sm text-slate-600">
                            <li class="flex items-start gap-3">
                                <span class="text-red-500 font-bold">&bull;</span>
                                <span>Menelan premis penulis mentah-mentah tanpa memverifikasi validitas data.</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="text-red-500 font-bold">&bull;</span>
                                <span>Fokus hanya menghafal kata kunci untuk menjawab soal pilihan ganda standar.</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="text-red-500 font-bold">&bull;</span>
                                <span>Mudah terperdaya oleh kesesatan berpikir (logical fallacy) dan bias narasi.</span>
                            </li>
                        </ul>

                        <div class="p-4 rounded-xl bg-slate-100 text-xs text-slate-600 italic">
                            Hasil: Membaca banyak buku, namun tidak melatih kemandirian berpikir saat dihadapkan pada
                            masalah nyata.
                        </div>
                    </div>

                    <!-- Card 2: Membaca Sokratik (SocratesRead) -->
                    <div
                        class="p-8 rounded-2xl bg-gradient-to-br from-blue-50 to-indigo-50/40 border-2 border-blue-600/30 space-y-6 shadow-sm">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center font-bold">
                                &check;
                            </div>
                            <div>
                                <h3 class="font-serif-reading text-xl font-bold text-slate-900">Membaca Reflektif
                                    Sokratik</h3>
                                <p class="text-xs text-blue-700 font-medium">Berdialog dan menguji logika argumen</p>
                            </div>
                        </div>

                        <ul class="space-y-4 text-sm text-slate-700">
                            <li class="flex items-start gap-3">
                                <span class="text-blue-600 font-bold">&bull;</span>
                                <span>Mengidentifikasi asumsi yang tidak diungkapkan secara eksplisit oleh
                                    penulis.</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="text-blue-600 font-bold">&bull;</span>
                                <span>Menguji konsistensi penalaran dengan pertanyaan dialektis bertingkat.</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="text-blue-600 font-bold">&bull;</span>
                                <span>Mampu merumuskan argumen tandingan secara adil dan berbasis bukti kuat.</span>
                            </li>
                        </ul>

                        <div
                            class="p-4 rounded-xl bg-white border border-blue-200/80 text-xs text-blue-950 font-medium shadow-xs">
                            Hasil: Membangun pikiran berdaulat yang tangguh dalam menyaring misinformasi dan cerdas
                            memecahkan persoalan kompleks.
                        </div>
                    </div>

                </div>

            </div>
        </section>


        <!-- ====================================================================
         5. FITUR UTAMA PLATFORM SOCRATESREAD
         ==================================================================== -->
        <section id="fitur" class="py-20 lg:py-28 bg-slate-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="max-w-3xl mx-auto text-center space-y-4 mb-16">
                    <span
                        class="text-xs font-bold uppercase tracking-wider text-blue-600 bg-blue-100/70 px-3 py-1 rounded-full">
                        Ekosistem Belajar
                    </span>
                    <h2 class="font-serif-reading text-3xl sm:text-4xl font-bold text-slate-900">
                        Dirancang Khusus untuk Mengasah Ketajaman Intelektual
                    </h2>
                    <p class="text-slate-600 text-base sm:text-lg">
                        Setiap modul di SocratesRead tidak sekadar menyajikan teks, melainkan membimbing langkah demi
                        langkah proses penalaran mandiri.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">

                    <!-- Fitur 1: Reading Room -->
                    <div
                        class="p-8 rounded-2xl bg-white border border-slate-200 shadow-xs hover:shadow-md transition-shadow flex flex-col justify-between">
                        <div class="space-y-4">
                            <div
                                class="w-12 h-12 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center">
                                <x-icon name="book-open-text" class="w-6 h-6" />
                            </div>
                            <h3 class="font-serif-reading text-2xl font-bold text-slate-900">Reading Room</h3>
                            <p class="text-sm text-slate-600 leading-relaxed">
                                Kurasi bacaan esai, filsafat, sains sosial, dan sastra bertingkat (Kelas 10, 11, dan 12)
                                dengan tipografi nyaman dan anotasi nalar langsung di teks.
                            </p>
                        </div>
                        <div
                            class="mt-6 pt-4 border-t border-slate-100 text-xs font-semibold text-blue-600 flex items-center gap-1">
                            <span>Teks Kurasi Nasional</span>
                        </div>
                    </div>

                    <!-- Fitur 2: Socratic Challenge -->
                    <div
                        class="p-8 rounded-2xl bg-white border border-slate-200 shadow-xs hover:shadow-md transition-shadow flex flex-col justify-between">
                        <div class="space-y-4">
                            <div
                                class="w-12 h-12 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center">
                                <x-icon name="circle-help" class="w-6 h-6" />
                            </div>
                            <h3 class="font-serif-reading text-2xl font-bold text-slate-900">Socratic Challenge</h3>
                            <p class="text-sm text-slate-600 leading-relaxed">
                                Latihan dialog interaktif di mana setiap jawabanmu diuji kembali: Mengapa kamu
                                berkesimpulan demikian? Fakta apa yang mendukung asumsimu?
                            </p>
                        </div>
                        <div
                            class="mt-6 pt-4 border-t border-slate-100 text-xs font-semibold text-indigo-600 flex items-center gap-1">
                            <span>Uji Berpikir Kritis</span>
                        </div>
                    </div>

                    <!-- Fitur 3: Athena AI Coach -->
                    <div
                        class="p-8 rounded-2xl bg-white border border-slate-200 shadow-xs hover:shadow-md transition-shadow flex flex-col justify-between">
                        <div class="space-y-4">
                            <div
                                class="w-12 h-12 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center">
                                <x-icon name="lightbulb" class="w-6 h-6" />
                            </div>
                            <h3 class="font-serif-reading text-2xl font-bold text-slate-900">Athena Coach</h3>
                            <p class="text-sm text-slate-600 leading-relaxed">
                                Asisten pedagogis ramah yang menolak memberi jawaban instan. Athena memberi petunjuk
                                berupa pertanyaan pemantik agar kamu menemukan pencerahan sendiri.
                            </p>
                        </div>
                        <div
                            class="mt-6 pt-4 border-t border-slate-100 text-xs font-semibold text-amber-700 flex items-center gap-1">
                            <span>Pedagogi Humanis</span>
                        </div>
                    </div>

                </div>

            </div>
        </section>


        <!-- ====================================================================
         6. INTERACTIVE SOCRATIC SANDBOX (UJI DAYA NALAR)
         ==================================================================== -->
        <section id="demo-sokratik" class="py-20 lg:py-28 bg-white border-t border-slate-200/80">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="text-center space-y-4 mb-12">
                    <span
                        class="text-xs font-bold uppercase tracking-wider text-blue-600 bg-blue-50 px-3 py-1 rounded-full">
                        Simulasi Interaktif
                    </span>
                    <h2 class="font-serif-reading text-3xl sm:text-4xl font-bold text-slate-900">
                        Uji Daya Nalar Bersama Athena
                    </h2>
                    <p class="text-slate-600 text-base max-w-2xl mx-auto">
                        Bacalah cuplikan teks pemikiran berikut. Pilihlah satu pendekatan simpulan, dan amati bagaimana
                        Athena membimbing pemikiranmu secara Sokratik.
                    </p>
                </div>

                <!-- Interactive Sandbox Card (Responsive padding & spotlight card styling) -->
                <div class="socratic-sandbox-card p-4 sm:p-8 lg:p-10">

                    <!-- Reading Passage -->
                    <div class="space-y-3 mb-8">
                        <div
                            class="flex items-center justify-between text-xs font-semibold text-slate-500 uppercase tracking-wider">
                            <span>Cuplikan Teks Kajian</span>
                            <span>Topik: Etika Ilmu & Keberanian Berpikir</span>
                        </div>
                        <div class="socratic-passage">
                            <p>
                                "Seringkali masyarakat menyamakan kepintaran dengan banyaknya informasi yang mampu
                                diingat seseorang. Padahal, perpustakaan yang megah tidak berpikir; yang berpikir adalah
                                manusia yang berani menantang isi rak buku tersebut. Seseorang yang hanya mengulang
                                kata-kata orang bijak tanpa pernah menguji kebenarannya di hadapan kenyataan tidak
                                ubahnya seperti gema di dalam gua kosong."
                            </p>
                        </div>
                    </div>

                    <!-- Socratic Prompt Question -->
                    <div class="mb-6">
                        <h3 class="font-serif-reading text-lg font-bold text-slate-900 mb-2">
                            Pertanyaan Athena: "Bagaimana kamu menyikapi klaim teks di atas?"
                        </h3>
                        <p class="text-xs text-slate-500">Pilih salah satu perspektif di bawah untuk melihat tanggapan
                            dialektis:</p>
                    </div>

                    <!-- 3 Socratic Choice Options -->
                    <div class="grid grid-cols-1 gap-3.5 mb-8">
                        <button type="button" data-socratic-option="1"
                            class="socratic-option-btn flex items-start gap-3">
                            <span class="font-bold text-slate-400 mt-0.5">A.</span>
                            <span><strong>Pendekatan Hafalan:</strong> Teks ini benar karena ditulis oleh tokoh
                                terpelajar, jadi kita harus langsung menyepakatinya tanpa perlu diperdebatkan.</span>
                        </button>

                        <button type="button" data-socratic-option="2"
                            class="socratic-option-btn flex items-start gap-3">
                            <span class="font-bold text-slate-400 mt-0.5">B.</span>
                            <span><strong>Skeptisisme Total:</strong> Teks ini hanyalah opini subjektif penulis,
                                sehingga semua pengetahuan sama-sama tidak pasti dan tidak ada yang patut
                                dipercaya.</span>
                        </button>

                        <button type="button" data-socratic-option="3"
                            class="socratic-option-btn flex items-start gap-3">
                            <span class="font-bold text-slate-400 mt-0.5">C.</span>
                            <span><strong>Pendekatan Sokratik:</strong> Penulis membedakan 'daya ingat pasif' dari
                                'penalaran aktif'. Kita perlu menguji premis ini: Adakah kondisi di mana mengingat
                                informasi justru merupakan syarat mutlak sebelum seseorang bisa bernalar kritis?</span>
                        </button>
                    </div>

                    <!-- Athena Feedback Box (Dynamically updated) -->
                    <div id="sandbox-feedback-box"
                        class="hidden p-6 rounded-xl bg-blue-900 text-white space-y-3 shadow-lg border border-blue-700">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-blue-800 p-1 flex items-center justify-center">
                                <img src="{{ asset('images/logo.png') }}" alt="" class="w-full h-full object-contain">
                            </div>
                            <h4 id="sandbox-feedback-title" class="font-serif-reading text-lg font-bold text-amber-300">
                                Evaluasi Kritis Athena
                            </h4>
                        </div>
                        <p id="sandbox-feedback-text" class="text-sm text-blue-100 leading-relaxed"></p>
                        <div class="p-3.5 rounded-lg bg-blue-950/70 border border-blue-800/80">
                            <p id="sandbox-feedback-question"
                                class="text-xs sm:text-sm font-medium text-amber-200 italic"></p>
                        </div>
                    </div>

                </div>

            </div>
        </section>


        <!-- ====================================================================
         7. UNTUK GURU & SEKOLAH
         ==================================================================== -->
        <section id="ruang-guru" class="py-20 bg-slate-50 border-t border-slate-200/80">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">

                    <div class="lg:col-span-6 space-y-6">
                        <span
                            class="text-xs font-bold uppercase tracking-wider text-indigo-700 bg-indigo-100/70 px-3 py-1 rounded-full">
                            Pemberdayaan Pendidik
                        </span>
                        <h2 class="font-serif-reading text-3xl sm:text-4xl font-bold text-slate-900 leading-tight">
                            Ruang Guru: Pantau Keterlibatan Intelektual Siswa Secara Terukur
                        </h2>
                        <p class="text-slate-600 text-base leading-relaxed">
                            Bukan sekadar mencatat siapa yang sudah membaca, SocratesRead memberi guru wawasan mendalam
                            mengenai seberapa jauh siswa memahami premis teks, pola kesalahan nalar yang sering muncul,
                            serta kemajuan kohor akademik.
                        </p>

                        <div class="space-y-3">
                            <div class="flex items-start gap-3">
                                <x-icon name="shield-check" class="w-5 h-5 text-indigo-600 shrink-0 mt-0.5" />
                                <span class="text-sm text-slate-700 font-medium">Unggah dokumen materi mandiri (PDF,
                                    teks kurasi, bahan ajar sekolah).</span>
                            </div>
                            <div class="flex items-start gap-3">
                                <x-icon name="users" class="w-5 h-5 text-indigo-600 shrink-0 mt-0.5" />
                                <span class="text-sm text-slate-700 font-medium">Manajemen kelas terpadu dengan kode
                                    gabung sederhana.</span>
                            </div>
                            <div class="flex items-start gap-3">
                                <x-icon name="chart-no-axes-combined" class="w-5 h-5 text-indigo-600 shrink-0 mt-0.5" />
                                <span class="text-sm text-slate-700 font-medium">Laporan analitik kemajuan membaca dan
                                    ekspor rekapitulasi data.</span>
                            </div>
                        </div>

                        <div class="pt-2">
                            <a href="{{ route('register') }}"
                                class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold text-sm shadow-sm transition-all focus-visible:ring-2 focus-visible:ring-slate-700">
                                <span>Daftar Sebagai Pendidik</span>
                                <x-icon name="arrow-right" class="w-4 h-4" />
                            </a>
                        </div>
                    </div>

                    <!-- Teacher Feature Visual Showcase -->
                    <div class="lg:col-span-6">
                        <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200 space-y-4">
                            <div
                                class="flex items-center justify-between pb-3 border-b border-slate-100 text-xs font-semibold text-slate-500">
                                <span>SIMULASI DASBOR PENDIDIK</span>
                                <span class="text-indigo-600">Terpantau Real-time</span>
                            </div>

                            <div class="space-y-3 text-xs">
                                <div
                                    class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-2.5 h-2.5 rounded-full bg-emerald-500"></div>
                                        <div>
                                            <span class="font-bold text-slate-800 block">Kelas X-A · Penalaran Etika
                                                Sosial</span>
                                            <span class="text-slate-500">32 Siswa Aktif · Rerata Keterlibatan 88%</span>
                                        </div>
                                    </div>
                                    <span
                                        class="text-emerald-700 font-semibold bg-emerald-50 px-2 py-1 rounded">Optimal</span>
                                </div>

                                <div
                                    class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-2.5 h-2.5 rounded-full bg-blue-500"></div>
                                        <div>
                                            <span class="font-bold text-slate-800 block">Kelas XI-C · Analisis Teks
                                                Sastra</span>
                                            <span class="text-slate-500">28 Siswa Aktif · Socratic Challenge
                                                Selesai</span>
                                        </div>
                                    </div>
                                    <span class="text-blue-700 font-semibold bg-blue-50 px-2 py-1 rounded">Aktif</span>
                                </div>
                            </div>

                            <p class="text-[11px] text-slate-400 italic text-center pt-2">
                                Guru dapat langsung mengevaluasi bagian teks mana yang paling memicu keraguan nalar
                                siswa.
                            </p>
                        </div>
                    </div>

                </div>

            </div>
        </section>


        <!-- ====================================================================
         8. CALL TO ACTION (CTA)
         ==================================================================== -->
        <section
            class="py-20 lg:py-24 bg-gradient-to-br from-slate-900 via-blue-950 to-slate-950 text-white relative overflow-hidden">
            <div
                class="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(37,99,235,0.15),transparent_70%)] pointer-events-none">
            </div>

            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-6 relative z-10">
                <div
                    class="w-16 h-16 rounded-2xl bg-white/10 p-2 mx-auto border border-white/20 backdrop-blur-sm flex items-center justify-center">
                    <img src="{{ asset('images/logo.png') }}" alt="" class="w-full h-full object-contain">
                </div>

                <h2 class="font-serif-reading text-3xl sm:text-5xl font-bold tracking-tight">
                    Mulailah Petualangan Membaca yang Sebenarnya
                </h2>

                <p class="text-base sm:text-lg text-blue-200/90 max-w-2xl mx-auto leading-relaxed">
                    Tinggalkan kebiasaan membaca pasif. Bergabunglah dengan ribuan siswa dan pendidik yang mengasah
                    ketajaman nalar bersama SocratesRead.
                </p>

                <div class="pt-4 flex flex-col sm:flex-row items-center justify-center gap-4">
                    <a href="{{ route('register') }}"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-4 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold shadow-lg transition-all focus-visible:ring-2 focus-visible:ring-white">
                        <span>Buat Akun Siswa Sekarang</span>
                        <x-icon name="arrow-right" class="w-4 h-4" />
                    </a>

                    <a href="{{ route('login') }}"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-4 rounded-xl bg-white/10 hover:bg-white/20 text-white border border-white/20 font-semibold transition-all focus-visible:ring-2 focus-visible:ring-white">
                        <span>Sudah Punya Akun? Masuk</span>
                    </a>
                </div>
            </div>
        </section>
    </main>


    <!-- ====================================================================
         9. FOOTER
         ==================================================================== -->
    <footer class="bg-slate-950 text-slate-400 py-14 border-t border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-10 pb-12 border-b border-slate-800">

                <!-- Col 1: Brand Info -->
                <div class="md:col-span-2 space-y-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-9 h-9 rounded-xl bg-white/10 p-1.5 border border-white/20 flex items-center justify-center">
                            <img src="{{ asset('images/logo.png') }}" alt="" class="w-full h-full object-contain">
                        </div>
                        <span class="text-xl font-bold text-white tracking-tight">
                            socrates<span class="text-blue-500">read</span>
                        </span>
                    </div>
                    <p class="text-sm text-slate-400 max-w-sm leading-relaxed">
                        Platform dialektika membaca dan penalaran kritis untuk sekolah menengah atas dan kejuruan.
                        Membangun generasi yang gemar bertanya dan bernalar jernih.
                    </p>
                    <div class="text-xs text-slate-500">
                        &copy; 2026 SocratesRead. Dikembangkan dengan komitmen etika literasi dan pembelajaran mandiri.
                    </div>
                </div>

                <!-- Col 2: Navigasi Platform -->
                <div class="space-y-3">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-200">Navigasi Halaman</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#filosofi" class="hover:text-blue-400 transition">Filosofi Sokratik</a></li>
                        <li><a href="#fitur" class="hover:text-blue-400 transition">Fitur Utama</a></li>
                        <li><a href="#demo-sokratik" class="hover:text-blue-400 transition">Simulasi Nalar</a></li>
                        <li><a href="#ruang-guru" class="hover:text-blue-400 transition">Portal Pendidik</a></li>
                    </ul>
                </div>

                <!-- Col 3: Akses Cepat & Kontrol -->
                <div class="space-y-3">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-200">Akses & Suara</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ route('login') }}" class="hover:text-blue-400 transition">Masuk ke Ruang
                                Belajar</a></li>
                        <li><a href="{{ route('register') }}" class="hover:text-blue-400 transition">Pendaftaran
                                Pengguna</a></li>
                        <li>
                            <button type="button" data-audio-toggle
                                class="hover:text-blue-400 transition text-left flex items-center gap-1.5 cursor-pointer">
                                <span class="sound-label">Suara: Hening</span>
                            </button>
                        </li>
                        <li>
                            <button type="button" data-reopen-book
                                class="hover:text-blue-400 transition text-left cursor-pointer">
                                Animasi Buku 3D
                            </button>
                        </li>
                    </ul>
                </div>

            </div>

            <div class="pt-8 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 gap-4">
                <p>Navigasi keyboard didukung penuh: Gunakan tombol Tab untuk berpindah antar elemen.</p>
                <div class="flex items-center gap-4">
                    <span>SocratesRead Platform</span>
                    <span>&bull;</span>
                    <span>Inisiasi Nalar Kritis</span>
                </div>
            </div>
        </div>
    </footer>


    <!-- ====================================================================
         10. FLOATING ATHENA DOCK (BOTTOM-RIGHT)
         ==================================================================== -->
    <div id="athena-floating-dock" class="athena-floating-dock" aria-label="Widget Athena sang Burung Hantu">
        <button id="athena-floating-trigger" class="athena-mini-trigger" type="button"
            aria-label="Tanya Athena atau kembali ke simulasi nalar" title="Tanya Athena!">
            <div class="w-10 h-10 rounded-full overflow-hidden flex items-center justify-center p-0.5">
                <img src="{{ asset('images/logo.png') }}" alt="Athena Mascot Icon" class="w-full h-full object-contain">
            </div>
        </button>
    </div>

</body>

</html>