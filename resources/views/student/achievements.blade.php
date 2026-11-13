@extends('layouts.student')

@section('title', 'Ranking Angkatan Kelas ' . $selectedGrade . ' - Pencapaian')

@section('breadcrumb')
    <a href="{{ route('achievements') }}" class="text-slate-500 hover:text-blue-600">Pencapaian</a>
    <span class="mx-1">/</span>
    <strong>Ranking Angkatan Kelas {{ $selectedGrade }}</strong>
@endsection

@section('content')
    @php
        $visibleLeaderboard = $leaderboard->take(10);
        $otherLeaderboard = $leaderboard->slice(10);
    @endphp
    <div class="achievements-page">
    <section class="stage-heading achievement-header" aria-labelledby="achievements-title">
        <div>
            <p class="eyebrow">PENCAPAIAN & DAYA SAING ANGKATAN</p>
            <h1 id="achievements-title">Ranking Angkatan Kelas {{ $selectedGrade }}</h1>
            <p class="welcome-subtitle">Pantau daya saing dan keaktifan murid di angkatan Kelas {{ $selectedGrade }}. Pacu semangat belajar dan ketuntasan tantangan!</p>
        </div>
        <div class="achievement-rank-banner inline-flex items-center gap-2.5 rounded-2xl bg-gradient-to-r from-amber-500/10 via-amber-500/15 to-yellow-500/10 border border-amber-300/80 px-4 py-2.5 text-amber-900 shadow-2xs">
                <span class="achievement-rank-banner-icon"><x-icon name="trophy" /></span>
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Peringkat Angkatan Anda</div>
                    <div class="text-sm font-extrabold text-amber-950">
                        @if ($currentUserRank && $userCurrentGrade === $selectedGrade)
                            #{{ $currentUserRank }} dari {{ $cohortSummary['total_students'] }} Siswa Kelas {{ $selectedGrade }}
                        @else
                            Peringkat Anda di Kelas {{ $userCurrentGrade }}
                        @endif
                    </div>
                </div>
            </div>
    </section>

    <!-- Tab Selector Angkatan (Kelas 10, 11, 12) -->
    <div class="achievement-grade-tabs mb-6 flex flex-wrap items-center justify-between gap-3 bg-white p-3.5 px-4 rounded-2xl border border-slate-200/80 shadow-2xs">
        <div class="flex items-center gap-2.5 flex-wrap">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-blue-50 text-blue-700 text-xs font-bold border border-blue-200/70">
                <x-icon name="trophy" class="w-4 h-4 text-blue-600" />
                <span>Pilih Tingkat Angkatan:</span>
            </span>

            @foreach ([10, 11, 12] as $grade)
                <a href="{{ route('achievements', ['grade' => $grade]) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition border {{ $selectedGrade === $grade ? 'bg-blue-600 text-white border-blue-600 shadow-xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100 hover:border-slate-300' }}">
                    <span>Angkatan Kelas {{ $grade }}</span>
                    @if ($userCurrentGrade === $grade)
                        <span class="rounded-full px-2 py-0.5 text-[9px] font-extrabold uppercase tracking-wider {{ $selectedGrade === $grade ? 'bg-amber-400 text-amber-950' : 'bg-blue-100 text-blue-800' }}">
                            Angkatan Kamu
                        </span>
                    @endif
                </a>
            @endforeach
        </div>

        <div class="text-xs text-slate-500 font-medium">
            Tingkat Anda Saat Ini: <strong class="text-blue-600">Kelas {{ $userCurrentGrade }}</strong>
        </div>
    </div>

    <!-- 4 Cohort Metrics -->
    <div class="achievement-content">
    <section class="achievement-stats grid grid-cols-2 lg:grid-cols-4 gap-3.5 sm:gap-4 mb-7" aria-label="Statistik Angkatan">
        <article class="achievement-stat-card achievement-stat-rank panel p-4 rounded-2xl border border-slate-200/80 shadow-2xs flex items-center gap-3.5">
            <div class="achievement-stat-icon achievement-stat-icon-gold w-11 h-11 rounded-xl bg-amber-50 text-amber-600 grid place-items-center shrink-0 border border-amber-100">
                <x-icon name="trophy" class="w-5 h-5" />
            </div>
            <div class="achievement-stat-copy">
                <p class="achievement-stat-label text-[11px] font-bold uppercase tracking-wider text-slate-400">Peringkat Anda</p>
                <h3 class="achievement-stat-value text-xl font-extrabold text-slate-800">
                    @if ($userCurrentGrade === $selectedGrade && $currentUserRank)
                        #{{ $currentUserRank }}
                        <small class="text-xs font-normal text-slate-400">/ {{ $cohortSummary['total_students'] }}</small>
                    @else
                        <span class="text-xs font-semibold text-slate-400">Lihat Kelas {{ $userCurrentGrade }}</span>
                    @endif
                </h3>
            </div>
        </article>

        <article class="achievement-stat-card achievement-stat-students panel p-4 rounded-2xl border border-slate-200/80 shadow-2xs flex items-center gap-3.5">
            <div class="achievement-stat-icon achievement-stat-icon-blue w-11 h-11 rounded-xl bg-blue-50 text-blue-600 grid place-items-center shrink-0 border border-blue-100">
                <x-icon name="users" class="w-5 h-5" />
            </div>
            <div class="achievement-stat-copy">
                <p class="achievement-stat-label text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Siswa Aktif</p>
                <h3 class="achievement-stat-value text-xl font-extrabold text-blue-600">{{ $cohortSummary['total_students'] }} <span class="text-xs font-bold text-slate-500">Siswa</span></h3>
            </div>
        </article>

        <article class="achievement-stat-card achievement-stat-challenges panel p-4 rounded-2xl border border-slate-200/80 shadow-2xs flex items-center gap-3.5">
            <div class="achievement-stat-icon achievement-stat-icon-sage w-11 h-11 rounded-xl bg-emerald-50 text-emerald-600 grid place-items-center shrink-0 border border-emerald-100">
                <x-icon name="target" class="w-5 h-5" />
            </div>
            <div class="achievement-stat-copy">
                <p class="achievement-stat-label text-[11px] font-bold uppercase tracking-wider text-slate-400">Tantangan Guru Tuntas</p>
                <h3 class="achievement-stat-value text-xl font-extrabold text-slate-800">{{ $cohortSummary['total_challenges'] }} <small class="text-xs font-normal text-slate-400">tugas</small></h3>
            </div>
        </article>

        <article class="achievement-stat-card achievement-stat-xp panel p-4 rounded-2xl border border-slate-200/80 shadow-2xs flex items-center gap-3.5">
            <div class="achievement-stat-icon achievement-stat-icon-lavender w-11 h-11 rounded-xl bg-purple-50 text-purple-600 grid place-items-center shrink-0 border border-purple-100">
                <x-icon name="award" class="w-5 h-5" />
            </div>
            <div class="achievement-stat-copy">
                <p class="achievement-stat-label text-[11px] font-bold uppercase tracking-wider text-slate-400">XP Terkumpul Angkatan</p>
                <h3 class="achievement-stat-value text-xl font-extrabold text-purple-600">+{{ number_format($cohortSummary['total_xp']) }} <small class="text-xs font-bold text-slate-400">XP</small></h3>
            </div>
        </article>
    </section>

    <!-- Podium Juara 3 Besar Angkatan (Top 3) -->
        <section class="achievement-podium panel p-6 sm:p-8 rounded-3xl border border-slate-200/80 shadow-xs mb-8 bg-gradient-to-b from-slate-50/50 via-white to-white" aria-labelledby="podium-heading">
            <div class="text-center mb-6">
                <span class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full bg-amber-50 text-amber-800 text-xs font-bold border border-amber-200/60 mb-2">
                    <x-icon name="crown" class="w-3.5 h-3.5 text-amber-600" />
                    <span>PODIUM JUARA ANGKATAN KELAS {{ $selectedGrade }}</span>
                </span>
                <h2 id="podium-heading" class="text-xl sm:text-2xl font-extrabold text-slate-800">Siswa Paling Rajin & Unggul</h2>
                <p class="text-xs text-slate-500 mt-1">Daya saing tertinggi berdasarkan ketuntasan materi, latihan kuis, dan penyelesaian tantangan guru.</p>
            </div>

            @if ($topThree->isNotEmpty())
            <div class="achievement-podium-layout flex flex-col sm:flex-row items-center sm:items-end justify-center gap-4 sm:gap-6 pt-6 mx-auto">
                <!-- Juara 2 (Perak) -->
                @if ($topThree->count() === 1)
                    <div class="achievement-place achievement-place-placeholder achievement-place-second" aria-hidden="true">
                        <div class="achievement-placeholder-avatar"></div>
                        <div class="achievement-podium-base achievement-base-second"></div>
                    </div>
                @endif
                @if ($topThree->count() >= 2)
                    @php($second = $topThree->get(1))
                    <div class="achievement-place achievement-place-second w-full max-w-xs sm:max-w-none sm:w-1/3 flex flex-col items-center order-2 sm:order-1">
                        <div class="achievement-profile relative mb-3 flex flex-col items-center">
                            <div class="achievement-avatar w-16 h-16 rounded-2xl ring-4 ring-slate-300 bg-slate-100 grid place-items-center overflow-hidden shadow-md">
                                @if ($second->avatar_url)
                                    <img src="{{ $second->avatar_url }}" alt="{{ $second->name }}" class="w-full h-full object-cover">
                                @else
                                    <span class="text-sm font-bold text-slate-700">{{ $second->initials }}</span>
                                @endif
                            </div>
                            <span class="achievement-rank-pill achievement-rank-pill-second absolute -bottom-2 px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-700 text-white shadow-2xs">#2</span>
                        </div>
                        <h4 class="text-xs font-bold text-slate-800 text-center truncate max-w-[140px] flex items-center gap-1">
                            {{ $second->name }}
                            @if ($second->is_current_user)
                                <span class="text-[9px] bg-blue-100 text-blue-700 px-1 rounded font-extrabold">Kamu</span>
                            @endif
                        </h4>
                        <span class="text-[11px] font-extrabold text-blue-600 mt-0.5">+{{ $second->total_xp }} XP · Lvl {{ $second->level }}</span>
                        <div class="achievement-podium-base achievement-base-second w-full h-24 bg-gradient-to-t from-slate-200 to-slate-100 rounded-t-2xl border-t-2 border-slate-300 mt-3 flex items-center justify-center shadow-inner">
                            <span class="text-slate-500 text-xs font-bold">2nd Place</span>
                        </div>
                    </div>
                @endif

                <!-- Juara 1 (Emas) - Paling Tinggi di Tengah -->
                @if ($topThree->count() >= 1)
                    @php($first = $topThree->get(0))
                    <div class="achievement-place achievement-place-first w-full max-w-xs sm:max-w-none sm:w-1/3 flex flex-col items-center order-1 sm:order-2">
                        <div class="achievement-profile relative mb-3 flex flex-col items-center">
                            <span class="achievement-crown"><x-icon name="crown" /></span>
                            <div class="achievement-avatar w-20 h-20 rounded-2xl ring-4 ring-amber-400 bg-amber-50 grid place-items-center overflow-hidden shadow-lg">
                                @if ($first->avatar_url)
                                    <img src="{{ $first->avatar_url }}" alt="{{ $first->name }}" class="w-full h-full object-cover">
                                @else
                                    <span class="text-base font-extrabold text-amber-800">{{ $first->initials }}</span>
                                @endif
                            </div>
                            <span class="achievement-rank-pill achievement-rank-pill-first absolute -bottom-2 px-2.5 py-0.5 rounded-full text-[11px] font-extrabold bg-gradient-to-r from-amber-500 to-yellow-500 text-white shadow-sm">#1 Juara</span>
                        </div>
                        <h4 class="text-sm font-extrabold text-slate-900 text-center truncate max-w-[160px] flex items-center gap-1">
                            {{ $first->name }}
                            @if ($first->is_current_user)
                                <span class="text-[9px] bg-blue-100 text-blue-700 px-1 rounded font-extrabold">Kamu</span>
                            @endif
                        </h4>
                        <span class="text-xs font-black text-amber-600 mt-0.5">+{{ $first->total_xp }} XP · Level {{ $first->level }}</span>
                        <div class="achievement-podium-base achievement-base-first w-full h-32 bg-gradient-to-t from-amber-200/80 via-amber-100 to-yellow-50 rounded-t-2xl border-t-4 border-amber-400 mt-3 flex flex-col items-center justify-center shadow-md">
                            <span class="text-amber-800 text-xs font-extrabold uppercase tracking-wider">Juara 1 Angkatan</span>
                            <span class="text-[10px] text-amber-700 font-semibold">{{ $first->challenges_count }} challenge guru tuntas</span>
                        </div>
                    </div>
                @endif

                <!-- Juara 3 (Perunggu) -->
                @if ($topThree->count() === 1)
                    <div class="achievement-place achievement-place-placeholder achievement-place-third" aria-hidden="true">
                        <div class="achievement-placeholder-avatar"></div>
                        <div class="achievement-podium-base achievement-base-third"></div>
                    </div>
                @endif
                @if ($topThree->count() >= 3)
                    @php($third = $topThree->get(2))
                    <div class="achievement-place achievement-place-third w-full max-w-xs sm:max-w-none sm:w-1/3 flex flex-col items-center order-3">
                        <div class="achievement-profile relative mb-3 flex flex-col items-center">
                            <div class="achievement-avatar w-16 h-16 rounded-2xl ring-4 ring-amber-700/40 bg-amber-50/50 grid place-items-center overflow-hidden shadow-md">
                                @if ($third->avatar_url)
                                    <img src="{{ $third->avatar_url }}" alt="{{ $third->name }}" class="w-full h-full object-cover">
                                @else
                                    <span class="text-sm font-bold text-amber-900">{{ $third->initials }}</span>
                                @endif
                            </div>
                            <span class="achievement-rank-pill achievement-rank-pill-third absolute -bottom-2 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-800 text-white shadow-2xs">#3</span>
                        </div>
                        <h4 class="text-xs font-bold text-slate-800 text-center truncate max-w-[140px] flex items-center gap-1">
                            {{ $third->name }}
                            @if ($third->is_current_user)
                                <span class="text-[9px] bg-blue-100 text-blue-700 px-1 rounded font-extrabold">Kamu</span>
                            @endif
                        </h4>
                        <span class="text-[11px] font-extrabold text-blue-600 mt-0.5">+{{ $third->total_xp }} XP · Lvl {{ $third->level }}</span>
                        <div class="achievement-podium-base achievement-base-third w-full h-20 bg-gradient-to-t from-amber-100/70 to-amber-50/40 rounded-t-2xl border-t-2 border-amber-700/40 mt-3 flex items-center justify-center shadow-inner">
                            <span class="text-amber-900/60 text-xs font-bold">3rd Place</span>
                        </div>
                    </div>
                @endif
            </div>
            @else
                <div class="achievement-podium-empty" role="status">
                    <span class="achievement-podium-empty-icon"><x-icon name="users" /></span>
                    <strong>Belum ada siswa di angkatan Kelas {{ $selectedGrade }}</strong>
                    <p>Podium akan menampilkan peringkat setelah data siswa tersedia.</p>
                    <div class="achievement-empty-stage" aria-hidden="true"><span></span><span></span><span></span></div>
                </div>
            @endif
        </section>
    </div>

    <!-- Full Cohort Competitive Table -->
    <section class="panel p-5 sm:p-6 rounded-3xl border border-slate-200/80 shadow-xs mb-8" aria-labelledby="table-heading">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
            <div>
                <h3 id="table-heading" class="text-base font-bold text-slate-800">Tabel Daya Saing Angkatan Kelas {{ $selectedGrade }}</h3>
                <p class="text-xs text-slate-500">Peringkat siswa paling rajin dan berprestasi dalam mengerjakan kuis serta tantangan dari guru.</p>
            </div>
            <span class="text-xs font-semibold text-slate-500 bg-slate-100 px-3 py-1.5 rounded-xl self-start sm:self-center">
                Total {{ $leaderboard->count() }} Murid di Angkatan Ini
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-400 font-bold uppercase tracking-wider text-[10px]">
                        <th class="py-3 px-3 text-center w-16">Peringkat</th>
                        <th class="py-3 px-3">Murid</th>
                        <th class="py-3 px-3 text-center">Level</th>
                        <th class="py-3 px-3 text-right">Total XP</th>
                        <th class="py-3 px-3 text-center">Tantangan Guru</th>
                        <th class="py-3 px-3 text-center">Rata-rata Skor Guru</th>
                        <th class="py-3 px-3 text-center">Kuis Socrates</th>
                        <th class="py-3 px-3 text-center">Bacaan Tuntas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($visibleLeaderboard as $student)
                        <tr class="transition hover:bg-slate-50/70 {{ $student->is_current_user ? 'bg-blue-50/70 font-semibold border-l-4 border-l-blue-600' : '' }}">
                            <!-- Rank Column -->
                            <td class="py-3.5 px-3 text-center">
                                @if ($student->rank === 1)
                                    <span class="inline-flex w-7 h-7 rounded-full bg-amber-100 text-amber-700 text-sm font-bold items-center justify-center shadow-2xs">🥇</span>
                                @elseif ($student->rank === 2)
                                    <span class="inline-flex w-7 h-7 rounded-full bg-slate-200 text-slate-700 text-sm font-bold items-center justify-center shadow-2xs">🥈</span>
                                @elseif ($student->rank === 3)
                                    <span class="inline-flex w-7 h-7 rounded-full bg-amber-100 text-amber-800 text-sm font-bold items-center justify-center shadow-2xs">🥉</span>
                                @else
                                    <span class="inline-flex w-7 h-7 rounded-full bg-slate-100 text-slate-600 text-xs font-bold items-center justify-center">#{{ $student->rank }}</span>
                                @endif
                            </td>

                            <!-- Student Profile -->
                            <td class="py-3.5 px-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-xl bg-slate-100 text-slate-700 grid place-items-center text-xs font-bold shrink-0 overflow-hidden {{ $student->is_current_user ? 'ring-2 ring-blue-500' : '' }}">
                                        @if ($student->avatar_url)
                                            <img src="{{ $student->avatar_url }}" alt="{{ $student->name }}" class="w-full h-full object-cover">
                                        @else
                                            {{ $student->initials }}
                                        @endif
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-800 flex items-center gap-1.5">
                                            <span>{{ $student->name }}</span>
                                            @if ($student->is_current_user)
                                                <span class="rounded bg-blue-600 px-1.5 py-0.2 text-[9px] font-bold text-white uppercase tracking-wider">Anda</span>
                                            @endif
                                        </div>
                                        <small class="text-[10px] text-slate-400">Angkatan Kelas {{ $student->grade }}</small>
                                    </div>
                                </div>
                            </td>

                            <!-- Level -->
                            <td class="py-3.5 px-3 text-center">
                                <span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-bold bg-purple-50 text-purple-700 border border-purple-200/60">
                                    Lvl {{ $student->level }}
                                </span>
                            </td>

                            <!-- Total XP -->
                            <td class="py-3.5 px-3 text-right">
                                <span class="font-black text-blue-600 text-sm">+{{ number_format($student->total_xp) }}</span>
                                <span class="text-[10px] text-slate-400 font-bold">XP</span>
                            </td>

                            <!-- Tantangan Guru Selesai -->
                            <td class="py-3.5 px-3 text-center font-bold text-slate-700">
                                {{ $student->challenges_count }} tugas
                            </td>

                            <!-- Rata-rata Skor Guru -->
                            <td class="py-3.5 px-3 text-center">
                                @if ($student->challenges_count > 0)
                                    <span class="font-bold {{ $student->avg_challenge_score >= 80 ? 'text-emerald-600' : ($student->avg_challenge_score >= 60 ? 'text-blue-600' : 'text-amber-600') }}">
                                        {{ $student->avg_challenge_score }}/100
                                    </span>
                                @else
                                    <span class="text-slate-300">-</span>
                                @endif
                            </td>

                            <!-- Kuis Socrates -->
                            <td class="py-3.5 px-3 text-center">
                                @if ($student->quizzes_count > 0)
                                    <span class="font-semibold text-slate-700">{{ $student->quizzes_count }}x ({{ $student->avg_quiz_score }})</span>
                                @else
                                    <span class="text-slate-300">-</span>
                                @endif
                            </td>

                            <!-- Readings Completed -->
                            <td class="py-3.5 px-3 text-center font-medium text-slate-700">
                                {{ $student->readings_count }} materi
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-slate-400">
                                Belum ada data siswa di angkatan kelas ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($otherLeaderboard->isNotEmpty())
            <div class="achievement-members-more" aria-label="Anggota lainnya di angkatan ini">
                <div class="achievement-members-avatars" aria-hidden="true">
                    @foreach ($otherLeaderboard->take(5) as $otherStudent)
                        <span class="achievement-members-avatar">
                            @if ($otherStudent->avatar_url)
                                <img src="{{ $otherStudent->avatar_url }}" alt="">
                            @else
                                {{ $otherStudent->initials }}
                            @endif
                        </span>
                    @endforeach
                    @if ($otherLeaderboard->count() > 5)
                        <span class="achievement-members-avatar achievement-members-overflow">+{{ $otherLeaderboard->count() - 5 }}</span>
                    @endif
                </div>
                <p><strong>{{ $otherLeaderboard->count() }} anggota lainnya</strong><span>Peringkat selain 10 besar tidak ditampilkan.</span></p>
            </div>
        @endif
    </section>

    <!-- Personal Achievements / Lencana Prestasi Section -->
    <section class="panel p-6 sm:p-7 rounded-3xl border border-slate-200/80 shadow-xs" aria-labelledby="badges-heading">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 grid place-items-center shrink-0 border border-amber-200/60">
                <x-icon name="medal" class="w-5 h-5" />
            </div>
            <div>
                <h3 id="badges-heading" class="text-base font-bold text-slate-800">Lencana Prestasi Belajar</h3>
                <p class="text-xs text-slate-500">Koleksi medali penghargaan yang kamu raih selama membaca dan berpikir kritis di SocratesRead.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5 sm:gap-4">
            @foreach ($badges as $badge)
                <div class="p-4 rounded-2xl border transition flex items-start gap-3.5 {{ $badge['is_unlocked'] ? 'bg-white border-slate-200 shadow-2xs' : 'bg-slate-50/60 border-slate-200/50 opacity-65' }}">
                    <div class="w-11 h-11 rounded-xl grid place-items-center shrink-0 border {{ $badge['is_unlocked'] ? 'bg-' . $badge['color'] . '-50 text-' . $badge['color'] . '-600 border-' . $badge['color'] . '-200 shadow-2xs' : 'bg-slate-200/80 text-slate-400 border-slate-300' }}">
                        <x-icon name="{{ $badge['icon'] }}" class="w-5 h-5" />
                    </div>
                    <div class="flex-grow min-w-0">
                        <div class="flex items-center justify-between gap-1.5 mb-0.5">
                            <h4 class="text-xs font-bold text-slate-800 truncate">{{ $badge['name'] }}</h4>
                            @if ($badge['is_unlocked'])
                                <span class="rounded bg-emerald-50 text-emerald-700 border border-emerald-200 px-1.5 py-0.2 text-[9px] font-bold">Tercapai</span>
                            @else
                                <span class="rounded bg-slate-100 text-slate-500 border border-slate-200 px-1.5 py-0.2 text-[9px] font-bold flex items-center gap-0.5">
                                    <x-icon name="lock" class="w-2.5 h-2.5" /> Terkunci
                                </span>
                            @endif
                        </div>
                        <p class="text-[11px] text-slate-500 leading-tight mb-2">{{ $badge['description'] }}</p>
                        <div class="text-[10px] text-slate-400 font-semibold">Progres: <strong class="text-slate-600">{{ $badge['progress'] }}</strong></div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
    </div>
@endsection
