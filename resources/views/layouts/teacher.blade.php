<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Area Guru') SocratesRead</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <!-- KaTeX Math Rendering Support for Mathematics & Sciences -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css" crossorigin="anonymous">
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js" crossorigin="anonymous"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/contrib/auto-render.min.js" crossorigin="anonymous"
        onload="renderMathInElement(document.body, {delimiters: [{left: '$$', right: '$$', display: true}, {left: '$', right: '$', display: false}, {left: '\\(', right: '\\)', display: false}, {left: '\\[', right: '\\]', display: true}], throwOnError: false});"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="app-shell">
        <button class="scrim" type="button" aria-label="Tutup navigasi" data-menu-close></button>
        <aside class="sidebar" id="teacher-navigation" aria-label="Area guru">
            <a class="brand" href="{{ route('teacher.dashboard') }}" aria-label="SocratesRead, area guru">
                <span class="brand-logo-wrap">
                    <img src="{{ asset('images/logo.png') }}" alt="Logo SocratesRead" class="brand-logo">
                </span>
                <span class="brand-name">socrates<span>read</span><small>AREA GURU</small></span>
            </a>
            <div class="nav-label">PEMANTAUAN BELAJAR</div>
            <nav class="nav-list" aria-label="Menu guru">
                <a class="nav-link {{ request()->routeIs('teacher.dashboard') ? 'is-active' : '' }}" href="{{ route('teacher.dashboard') }}" @if(request()->routeIs('teacher.dashboard')) aria-current="page" @endif><x-icon name="layout-dashboard"/><span>Ringkasan</span></a>
                <a class="nav-link {{ request()->routeIs('teacher.classrooms*') ? 'is-active' : '' }}" href="{{ route('teacher.classrooms') }}" @if(request()->routeIs('teacher.classrooms*')) aria-current="page" @endif><x-icon name="users"/><span>Kelas saya</span></a>
                <a class="nav-link {{ request()->routeIs('teacher.documents*') ? 'is-active' : '' }}" href="{{ route('teacher.documents') }}" @if(request()->routeIs('teacher.documents*')) aria-current="page" @endif><x-icon name="book-open-text"/><span>Kelola bacaan</span></a>
                <a class="nav-link {{ request()->routeIs('teacher.reports*') ? 'is-active' : '' }}" href="{{ route('teacher.reports') }}" @if(request()->routeIs('teacher.reports*')) aria-current="page" @endif><x-icon name="clipboard-check"/><span>Rekap & Laporan</span></a>
            </nav>
            <div class="sidebar-spacer"></div>
        </aside>
        <main class="main-content">
            <header class="topbar">
                <button class="icon-button menu-toggle" type="button" aria-label="Buka navigasi" aria-controls="teacher-navigation" aria-expanded="false" data-menu-toggle><x-icon name="menu" /></button>
                <div class="breadcrumb"><a href="{{ route('teacher.dashboard') }}">Area guru</a><span>/</span><strong>@yield('breadcrumb', 'Ringkasan')</strong></div>
                <div class="topbar-actions">
                    <span class="today-label">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span>
                    <x-notification-center />
                    <x-profile-dropdown />
                </div>
            </header>
            <div class="page-content stage-two-content">@yield('content')<footer class="page-footer"><span>Materi bermakna dimulai dari bacaan yang baik.</span><span>© 2026 SocratesRead</span></footer></div>
        </main>
    </div>
    <!-- Custom In-Web Confirmation Modal & Toast Notifications -->
    <div id="webToastContainer" class="fixed top-5 right-5 z-[70] flex flex-col gap-2.5 max-w-sm w-[calc(100%-2.5rem)] pointer-events-none" aria-live="polite" aria-atomic="true"></div>

    <div id="webConfirmModal" class="fixed inset-0 z-[60] hidden items-center justify-center p-4 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="webConfirmTitle">
        <div id="webConfirmBackdrop" class="fixed inset-0 bg-slate-900/55 backdrop-blur-xs transition-opacity"></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl border border-slate-200/90 overflow-hidden z-10 animate-scale-in">
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div id="webConfirmIconWrap" class="w-11 h-11 rounded-2xl bg-rose-50 text-rose-600 border border-rose-200 grid place-items-center shrink-0">
                        <svg id="webConfirmIconSvg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/>
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p id="webConfirmEyebrow" class="text-[10px] font-bold uppercase tracking-wider text-rose-600 mb-0.5">KONFIRMASI TINDAKAN</p>
                        <h3 id="webConfirmTitle" class="text-base font-bold text-slate-900 leading-snug">Konfirmasi</h3>
                        <p id="webConfirmSubject" class="hidden mt-2 inline-flex items-center gap-1.5 rounded-lg bg-slate-100 border border-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-700 max-w-full truncate"></p>
                        <p id="webConfirmMessage" class="mt-2.5 text-xs text-slate-600 leading-relaxed"></p>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-end gap-2.5 border-t border-slate-100 bg-slate-50/80 px-6 py-3.5">
                <button type="button" id="webConfirmCancelBtn" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition cursor-pointer">
                    Batal
                </button>
                <button type="button" id="webConfirmOkBtn" class="rounded-xl bg-rose-600 hover:bg-rose-700 px-4 py-2 text-xs font-bold text-white shadow-xs transition cursor-pointer">
                    Ya, Lanjutkan
                </button>
            </div>
        </div>
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

        (() => {
            const confirmModal = document.getElementById('webConfirmModal');
            const confirmBackdrop = document.getElementById('webConfirmBackdrop');
            const confirmIconWrap = document.getElementById('webConfirmIconWrap');
            const confirmIconSvg = document.getElementById('webConfirmIconSvg');
            const confirmEyebrow = document.getElementById('webConfirmEyebrow');
            const confirmTitle = document.getElementById('webConfirmTitle');
            const confirmSubject = document.getElementById('webConfirmSubject');
            const confirmMessage = document.getElementById('webConfirmMessage');
            const confirmCancelBtn = document.getElementById('webConfirmCancelBtn');
            const confirmOkBtn = document.getElementById('webConfirmOkBtn');
            const toastContainer = document.getElementById('webToastContainer');

            const variantStyles = {
                danger: {
                    eyebrow: 'KONFIRMASI PENGHAPUSAN',
                    eyebrowCls: 'text-[10px] font-bold uppercase tracking-wider text-rose-600 mb-0.5',
                    iconCls: 'w-11 h-11 rounded-2xl bg-rose-50 text-rose-600 border border-rose-200 grid place-items-center shrink-0',
                    okCls: 'rounded-xl bg-rose-600 hover:bg-rose-700 px-4 py-2 text-xs font-bold text-white shadow-xs transition cursor-pointer',
                    svg: '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/>',
                },
                warning: {
                    eyebrow: 'KONFIRMASI PERUBAHAN',
                    eyebrowCls: 'text-[10px] font-bold uppercase tracking-wider text-amber-600 mb-0.5',
                    iconCls: 'w-11 h-11 rounded-2xl bg-amber-50 text-amber-600 border border-amber-200 grid place-items-center shrink-0',
                    okCls: 'rounded-xl bg-amber-600 hover:bg-amber-700 px-4 py-2 text-xs font-bold text-white shadow-xs transition cursor-pointer',
                    svg: '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
                },
                success: {
                    eyebrow: 'KONFIRMASI AKTIVASI',
                    eyebrowCls: 'text-[10px] font-bold uppercase tracking-wider text-emerald-600 mb-0.5',
                    iconCls: 'w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-600 border border-emerald-200 grid place-items-center shrink-0',
                    okCls: 'rounded-xl bg-emerald-600 hover:bg-emerald-700 px-4 py-2 text-xs font-bold text-white shadow-xs transition cursor-pointer',
                    svg: '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/>',
                },
                primary: {
                    eyebrow: 'KONFIRMASI TINDAKAN',
                    eyebrowCls: 'text-[10px] font-bold uppercase tracking-wider text-purple-600 mb-0.5',
                    iconCls: 'w-11 h-11 rounded-2xl bg-purple-50 text-purple-600 border border-purple-200 grid place-items-center shrink-0',
                    okCls: 'rounded-xl bg-purple-600 hover:bg-purple-700 px-4 py-2 text-xs font-bold text-white shadow-xs transition cursor-pointer',
                    svg: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
                },
            };

            let pendingResolver = null;

            const closeConfirm = (result) => {
                if (!confirmModal) return;
                confirmModal.classList.add('hidden');
                confirmModal.classList.remove('flex');
                if (pendingResolver) {
                    const resolve = pendingResolver;
                    pendingResolver = null;
                    resolve(result);
                }
            };

            confirmBackdrop?.addEventListener('click', () => closeConfirm(false));
            confirmCancelBtn?.addEventListener('click', () => closeConfirm(false));
            confirmOkBtn?.addEventListener('click', () => closeConfirm(true));
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && confirmModal && !confirmModal.classList.contains('hidden')) {
                    closeConfirm(false);
                }
            });

            const showConfirm = (options = {}) => {
                if (!confirmModal) {
                    return Promise.resolve(false);
                }
                if (pendingResolver) {
                    closeConfirm(false);
                }
                const style = variantStyles[options.variant] || variantStyles.danger;
                confirmEyebrow.textContent = options.eyebrow || style.eyebrow;
                confirmEyebrow.className = style.eyebrowCls;
                confirmIconWrap.className = style.iconCls;
                confirmIconSvg.innerHTML = style.svg;
                confirmTitle.textContent = options.title || 'Konfirmasi';
                confirmMessage.textContent = options.message || '';
                confirmCancelBtn.textContent = options.cancelText || 'Batal';
                confirmOkBtn.textContent = options.confirmText || 'Ya, Lanjutkan';
                confirmOkBtn.className = style.okCls;

                if (options.subject) {
                    confirmSubject.textContent = options.subject;
                    confirmSubject.classList.remove('hidden');
                } else {
                    confirmSubject.textContent = '';
                    confirmSubject.classList.add('hidden');
                }

                confirmModal.classList.remove('hidden');
                confirmModal.classList.add('flex');
                confirmOkBtn.focus();

                return new Promise((resolve) => {
                    pendingResolver = resolve;
                });
            };

            const toastPalette = {
                success: {
                    label: 'Berhasil',
                    wrap: 'border-emerald-200 bg-white text-slate-800',
                    badge: 'bg-emerald-100 text-emerald-700',
                    icon: '✓',
                },
                warning: {
                    label: 'Perhatian',
                    wrap: 'border-amber-200 bg-white text-slate-800',
                    badge: 'bg-amber-100 text-amber-700',
                    icon: '!',
                },
                error: {
                    label: 'Gagal',
                    wrap: 'border-rose-200 bg-white text-slate-800',
                    badge: 'bg-rose-100 text-rose-700',
                    icon: '✕',
                },
                info: {
                    label: 'Informasi',
                    wrap: 'border-blue-200 bg-white text-slate-800',
                    badge: 'bg-blue-100 text-blue-700',
                    icon: 'i',
                },
            };

            const showToast = (message, type = 'success', duration = 3800) => {
                if (!toastContainer || !message) return;
                const theme = toastPalette[type] || toastPalette.info;
                const toast = document.createElement('div');
                toast.className = `pointer-events-auto flex items-start gap-3 rounded-2xl border ${theme.wrap} p-3.5 shadow-xl transition-all duration-200`;
                toast.setAttribute('role', 'status');

                const iconBadge = document.createElement('span');
                iconBadge.className = `w-7 h-7 rounded-xl ${theme.badge} grid place-items-center text-xs font-extrabold shrink-0 mt-0.5`;
                iconBadge.textContent = theme.icon;

                const body = document.createElement('div');
                body.className = 'min-w-0 flex-1';

                const heading = document.createElement('p');
                heading.className = 'text-[11px] font-bold uppercase tracking-wider text-slate-500';
                heading.textContent = theme.label;

                const text = document.createElement('p');
                text.className = 'text-xs font-medium text-slate-700 leading-snug mt-0.5';
                text.textContent = message;

                const closeBtn = document.createElement('button');
                closeBtn.type = 'button';
                closeBtn.className = 'text-slate-400 hover:text-slate-600 text-xs font-bold px-1 shrink-0 cursor-pointer';
                closeBtn.setAttribute('aria-label', 'Tutup notifikasi');
                closeBtn.textContent = '✕';

                const dismiss = () => {
                    toast.classList.add('opacity-0', 'translate-x-2');
                    setTimeout(() => toast.remove(), 200);
                };

                closeBtn.addEventListener('click', dismiss);
                body.append(heading, text);
                toast.append(iconBadge, body, closeBtn);
                toastContainer.appendChild(toast);

                if (duration > 0) {
                    setTimeout(dismiss, duration);
                }
            };

            document.addEventListener('submit', async (event) => {
                const form = event.target;
                if (!(form instanceof HTMLFormElement) || !form.dataset.confirmMessage) return;
                event.preventDefault();
                const confirmed = await showConfirm({
                    eyebrow: form.dataset.confirmEyebrow || '',
                    title: form.dataset.confirmTitle || 'Konfirmasi Tindakan',
                    message: form.dataset.confirmMessage,
                    subject: form.dataset.confirmSubject || '',
                    confirmText: form.dataset.confirmOk || 'Ya, Lanjutkan',
                    cancelText: form.dataset.confirmCancel || 'Batal',
                    variant: form.dataset.confirmVariant || 'danger',
                });
                if (confirmed) {
                    HTMLFormElement.prototype.submit.call(form);
                }
            });

            window.SocratesUI = {
                confirm: showConfirm,
                toast: showToast,
            };
        })();
    </script>
    @stack('scripts')
</body>
</html>
