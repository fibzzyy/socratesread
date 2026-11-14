@php
    $user = auth()->user();
    $userInitials = collect(explode(' ', $user->name))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
    $isStudent = $user->role === 'student';
    $isTeacher = $user->role === 'teacher';
@endphp

<div class="profile-dropdown-container relative inline-block text-left" data-dropdown-scope>
    {{-- Trigger Button (Radix / shadcn style with focus-visible ring & concentric avatar ring) --}}
    <button type="button"
            class="profile-dropdown-trigger group relative flex items-center gap-1.5 sm:gap-2.5 p-1 pl-1 sm:pl-1.5 pr-2 sm:pr-3 min-h-[38px] sm:min-h-[42px] rounded-full border border-slate-200/90 bg-white/95 hover:bg-slate-50 hover:border-slate-300 transition-all duration-150 shadow-xs focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2 active:scale-98 cursor-pointer select-none"
            aria-expanded="false"
            aria-haspopup="menu"
            aria-label="Menu akun {{ $user->name }}"
            data-dropdown-trigger>
        
        {{-- Avatar & Animated Pulse Indicator --}}
        <div class="relative shrink-0">
            <div class="w-8 h-8 rounded-full overflow-hidden flex items-center justify-center font-bold text-xs bg-gradient-to-tr from-blue-600 to-indigo-600 text-white shadow-xs ring-1 ring-slate-900/10">
                @if ($user->avatarUrl())
                    <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" class="w-full h-full object-cover">
                @else
                    {{ mb_strtoupper($userInitials) }}
                @endif
            </div>
            {{-- Online pulse indicator --}}
            <span class="absolute -bottom-0.5 -right-0.5 flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500 border-2 border-white"></span>
            </span>
        </div>

        {{-- Username & Role Label --}}
        <div class="hidden sm:flex flex-col text-left leading-tight pr-0.5">
            <span class="text-xs font-semibold text-slate-800 max-w-[120px] truncate tracking-tight">{{ $user->name }}</span>
            <span class="text-[10px] font-medium text-slate-400">
                {{ $isTeacher ? 'Guru Pengajar' : ($user->currentGrade() ? 'Kelas ' . $user->currentGrade() : 'Siswa') }}
            </span>
        </div>

        {{-- Animated Chevron Indicator --}}
        <x-icon name="chevron-down" class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200 group-hover:text-slate-600 pointer-events-none" data-dropdown-chevron />
    </button>

    {{-- Dropdown Menu Content (shadcn/ui Popover & DropdownMenu spec) --}}
    <div class="profile-dropdown-menu absolute right-0 top-full mt-2 w-[calc(100vw-2rem)] sm:w-80 max-w-[340px] max-h-[calc(100vh-5rem)] overflow-y-auto bg-white rounded-2xl shadow-2xl shadow-slate-900/15 border border-slate-200/80 ring-1 ring-slate-900/5 z-50 transform transition-all duration-150 origin-top-right opacity-0 scale-95 pointer-events-none"
         role="menu"
         aria-orientation="vertical"
         tabindex="-1"
         data-dropdown-panel>
        
        {{-- 1. Identity Header Card (Radix Avatar + Badges + Metas) --}}
        <div class="p-4 bg-gradient-to-b from-slate-50/90 via-blue-50/20 to-white border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="relative shrink-0">
                    <div class="w-12 h-12 rounded-full overflow-hidden flex items-center justify-center font-bold text-sm bg-gradient-to-tr from-blue-600 to-indigo-600 text-white shadow-md ring-2 ring-white">
                        @if ($user->avatarUrl())
                            <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" class="w-full h-full object-cover">
                        @else
                            {{ mb_strtoupper($userInitials) }}
                        @endif
                    </div>
                </div>

                <div class="min-w-0 flex-1">
                    <h3 class="font-bold text-slate-900 text-sm truncate leading-tight tracking-tight">{{ $user->name }}</h3>
                    <p class="text-xs text-slate-500 truncate mt-0.5">{{ $user->email }}</p>
                    
                    {{-- Role Badge (shadcn badge design token) --}}
                    <div class="mt-2 flex items-center gap-1.5 flex-wrap">
                        @if ($isTeacher)
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/80 shadow-2xs">
                                <x-icon name="graduation-cap" class="w-3 h-3 text-emerald-600" />
                                Guru SocratesRead
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-blue-50 text-blue-700 border border-blue-200/80 shadow-2xs">
                                <x-icon name="book-open-text" class="w-3 h-3 text-blue-600" />
                                Siswa SocratesRead
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- 2. Contextual Information Banner --}}
        <div class="px-4 py-2 bg-slate-50/80 border-b border-slate-100 flex items-center justify-between text-xs text-slate-600">
            <span class="flex items-center gap-1.5 text-slate-500 font-medium truncate max-w-[200px]" title="{{ $user->school_origin ?: 'SocratesRead Indonesia' }}">
                <x-icon name="graduation-cap" class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                <span class="truncate">{{ $user->school_origin ?: 'SocratesRead' }}</span>
            </span>
            @if ($isStudent && $user->currentGrade())
                <span class="px-2 py-0.5 bg-white rounded-md border border-slate-200 text-slate-700 font-semibold text-[10px] shadow-2xs shrink-0">
                    Kelas {{ $user->currentGrade() }}
                </span>
            @elseif ($isTeacher && $user->subject_area)
                <span class="px-2 py-0.5 bg-white rounded-md border border-slate-200 text-slate-700 font-semibold text-[10px] shadow-2xs shrink-0 truncate max-w-[100px]" title="{{ $user->subject_area }}">
                    {{ $user->subject_area }}
                </span>
            @endif
        </div>

        {{-- 3. Menu Group: Core Activities --}}
        <div class="p-1.5 space-y-0.5" role="group" aria-label="Aktivitas dan akun">
            <div class="px-2.5 pt-1.5 pb-1 text-[10px] font-bold tracking-wider text-slate-400 uppercase select-none">
                Aktivitas & Portofolio
            </div>
            
            {{-- Profile Link --}}
            <a href="{{ route('profile') }}"
               class="flex items-center gap-2.5 px-2.5 py-2 text-xs font-medium text-slate-700 rounded-xl hover:bg-slate-100/80 hover:text-slate-900 focus:bg-slate-100 focus:text-slate-900 focus:outline-none transition-colors group cursor-pointer"
               role="menuitem"
               tabindex="-1">
                <span class="w-7 h-7 rounded-lg bg-slate-100/90 flex items-center justify-center text-slate-500 group-hover:bg-blue-50 group-hover:text-blue-600 transition-colors shadow-2xs shrink-0">
                    <x-icon name="user" class="w-3.5 h-3.5" />
                </span>
                <span class="flex-1 font-medium">Profil Lengkap</span>
                <x-icon name="chevron-right" class="w-3.5 h-3.5 text-slate-300 opacity-0 group-hover:opacity-100 group-focus:opacity-100 transition-opacity" />
            </a>

            @if ($isStudent)
                {{-- Student: Pencapaian --}}
                <a href="{{ route('achievements') }}"
                   class="flex items-center gap-2.5 px-2.5 py-2 text-xs font-medium text-slate-700 rounded-xl hover:bg-slate-100/80 hover:text-slate-900 focus:bg-slate-100 focus:text-slate-900 focus:outline-none transition-colors group cursor-pointer"
                   role="menuitem"
                   tabindex="-1">
                    <span class="w-7 h-7 rounded-lg bg-slate-100/90 flex items-center justify-center text-slate-500 group-hover:bg-amber-50 group-hover:text-amber-600 transition-colors shadow-2xs shrink-0">
                        <x-icon name="award" class="w-3.5 h-3.5" />
                    </span>
                    <span class="flex-1 font-medium">Pencapaian & Lencana</span>
                    <x-icon name="chevron-right" class="w-3.5 h-3.5 text-slate-300 opacity-0 group-hover:opacity-100 group-focus:opacity-100 transition-opacity" />
                </a>

                {{-- Student: Progres Membaca --}}
                <a href="{{ route('progress') }}"
                   class="flex items-center gap-2.5 px-2.5 py-2 text-xs font-medium text-slate-700 rounded-xl hover:bg-slate-100/80 hover:text-slate-900 focus:bg-slate-100 focus:text-slate-900 focus:outline-none transition-colors group cursor-pointer"
                   role="menuitem"
                   tabindex="-1">
                    <span class="w-7 h-7 rounded-lg bg-slate-100/90 flex items-center justify-center text-slate-500 group-hover:bg-indigo-50 group-hover:text-indigo-600 transition-colors shadow-2xs shrink-0">
                        <x-icon name="chart-no-axes-combined" class="w-3.5 h-3.5" />
                    </span>
                    <span class="flex-1 font-medium">Progres Membaca</span>
                    <x-icon name="chevron-right" class="w-3.5 h-3.5 text-slate-300 opacity-0 group-hover:opacity-100 group-focus:opacity-100 transition-opacity" />
                </a>
            @else
                {{-- Teacher: Kelas Saya --}}
                <a href="{{ route('teacher.classrooms') }}"
                   class="flex items-center gap-2.5 px-2.5 py-2 text-xs font-medium text-slate-700 rounded-xl hover:bg-slate-100/80 hover:text-slate-900 focus:bg-slate-100 focus:text-slate-900 focus:outline-none transition-colors group cursor-pointer"
                   role="menuitem"
                   tabindex="-1">
                    <span class="w-7 h-7 rounded-lg bg-slate-100/90 flex items-center justify-center text-slate-500 group-hover:bg-emerald-50 group-hover:text-emerald-600 transition-colors shadow-2xs shrink-0">
                        <x-icon name="users" class="w-3.5 h-3.5" />
                    </span>
                    <span class="flex-1 font-medium">Ruang Kelas</span>
                    <x-icon name="chevron-right" class="w-3.5 h-3.5 text-slate-300 opacity-0 group-hover:opacity-100 group-focus:opacity-100 transition-opacity" />
                </a>

                {{-- Teacher: Dokumen & Tantangan --}}
                <a href="{{ route('teacher.documents') }}"
                   class="flex items-center gap-2.5 px-2.5 py-2 text-xs font-medium text-slate-700 rounded-xl hover:bg-slate-100/80 hover:text-slate-900 focus:bg-slate-100 focus:text-slate-900 focus:outline-none transition-colors group cursor-pointer"
                   role="menuitem"
                   tabindex="-1">
                    <span class="w-7 h-7 rounded-lg bg-slate-100/90 flex items-center justify-center text-slate-500 group-hover:bg-teal-50 group-hover:text-teal-600 transition-colors shadow-2xs shrink-0">
                        <x-icon name="book-open-text" class="w-3.5 h-3.5" />
                    </span>
                    <span class="flex-1 font-medium">Kelola Dokumen Bacaan</span>
                    <x-icon name="chevron-right" class="w-3.5 h-3.5 text-slate-300 opacity-0 group-hover:opacity-100 group-focus:opacity-100 transition-opacity" />
                </a>
            @endif
        </div>

        {{-- Separator --}}
        <div class="h-px bg-slate-100 mx-2 my-1" role="separator"></div>

        {{-- 4. Menu Group: Preferences & Settings --}}
        <div class="p-1.5 space-y-0.5" role="group" aria-label="Preferensi akun">
            <div class="px-2.5 pt-1.5 pb-1 text-[10px] font-bold tracking-wider text-slate-400 uppercase select-none">
                Sistem & Pengaturan
            </div>

            <a href="{{ route('settings.profile') }}"
               class="flex items-center gap-2.5 px-2.5 py-2 text-xs font-medium text-slate-700 rounded-xl hover:bg-slate-100/80 hover:text-slate-900 focus:bg-slate-100 focus:text-slate-900 focus:outline-none transition-colors group cursor-pointer"
               role="menuitem"
               tabindex="-1">
                <span class="w-7 h-7 rounded-lg bg-slate-100/90 flex items-center justify-center text-slate-500 group-hover:bg-slate-200 group-hover:text-slate-800 transition-colors shadow-2xs shrink-0">
                    <x-icon name="settings" class="w-3.5 h-3.5" />
                </span>
                <span class="flex-1 font-medium">Pengaturan & Keamanan</span>
                <x-icon name="chevron-right" class="w-3.5 h-3.5 text-slate-300 opacity-0 group-hover:opacity-100 group-focus:opacity-100 transition-opacity" />
            </a>
        </div>

        {{-- Separator --}}
        <div class="h-px bg-slate-100" role="separator"></div>

        {{-- 5. Destructive Action: Logout (shadcn destructive item styling) --}}
        <div class="p-1.5 bg-slate-50/50" role="none">
            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <button type="submit"
                        class="w-full flex items-center gap-2.5 px-2.5 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 hover:text-rose-700 focus:bg-rose-50 focus:text-rose-700 focus:outline-none rounded-xl transition-colors cursor-pointer group text-left"
                        role="menuitem"
                        tabindex="-1">
                    <span class="w-7 h-7 rounded-lg bg-rose-50 flex items-center justify-center text-rose-600 group-hover:bg-rose-100/80 transition-colors shadow-2xs shrink-0">
                        <x-icon name="log-out" class="w-3.5 h-3.5" />
                    </span>
                    <span class="flex-1">Keluar dari Akun</span>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    (() => {
        if (window.__profileDropdownEnhanced) return;
        window.__profileDropdownEnhanced = true;

        const initDropdowns = () => {
            document.querySelectorAll('[data-dropdown-scope]').forEach(container => {
                if (container.__isEnhanced) return;
                container.__isEnhanced = true;

                const trigger = container.querySelector('[data-dropdown-trigger]');
                const panel = container.querySelector('[data-dropdown-panel]');
                const chevron = container.querySelector('[data-dropdown-chevron]');

                if (!trigger || !panel) return;

                const getMenuItems = () => Array.from(panel.querySelectorAll('[role="menuitem"]'));

                const openMenu = () => {
                    panel.classList.remove('opacity-0', 'scale-95', 'pointer-events-none');
                    panel.classList.add('opacity-100', 'scale-100', 'pointer-events-auto');
                    trigger.setAttribute('aria-expanded', 'true');
                    if (chevron) chevron.classList.add('rotate-180');

                    // Accessibility: Focus first menu item on open
                    const items = getMenuItems();
                    if (items.length > 0) {
                        items[0].focus();
                    }
                };

                const closeMenu = (restoreFocus = true) => {
                    panel.classList.add('opacity-0', 'scale-95', 'pointer-events-none');
                    panel.classList.remove('opacity-100', 'scale-100', 'pointer-events-auto');
                    trigger.setAttribute('aria-expanded', 'false');
                    if (chevron) chevron.classList.remove('rotate-180');

                    if (restoreFocus) {
                        trigger.focus();
                    }
                };

                trigger.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isOpen = trigger.getAttribute('aria-expanded') === 'true';
                    isOpen ? closeMenu() : openMenu();
                });

                // Radix-compliant Keyboard Navigation across menu items
                panel.addEventListener('keydown', (e) => {
                    const items = getMenuItems();
                    const currentIndex = items.indexOf(document.activeElement);

                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        const nextIndex = currentIndex < items.length - 1 ? currentIndex + 1 : 0;
                        items[nextIndex]?.focus();
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        const prevIndex = currentIndex > 0 ? currentIndex - 1 : items.length - 1;
                        items[prevIndex]?.focus();
                    } else if (e.key === 'Home') {
                        e.preventDefault();
                        items[0]?.focus();
                    } else if (e.key === 'End') {
                        e.preventDefault();
                        items[items.length - 1]?.focus();
                    } else if (e.key === 'Escape') {
                        e.preventDefault();
                        closeMenu(true);
                    } else if (e.key === 'Tab') {
                        closeMenu(false);
                    }
                });

                document.addEventListener('click', (e) => {
                    if (!container.contains(e.target)) {
                        closeMenu(false);
                    }
                });

                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && trigger.getAttribute('aria-expanded') === 'true') {
                        closeMenu(true);
                    }
                });
            });
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initDropdowns);
        } else {
            initDropdowns();
        }
    })();
</script>
