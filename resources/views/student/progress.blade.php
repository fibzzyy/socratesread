@extends('layouts.student')

@section('title', $activeClassroom ? 'Progress ' . $activeClassroom->name : 'Progress Belajar')
@section('breadcrumb')
    @if ($activeClassroom)
        <a href="{{ route('progress') }}" class="text-slate-500 hover:text-blue-600">Progress</a>
        <span class="mx-1">/</span>
        <strong>{{ $activeClassroom->name }}</strong>
    @else
        <strong>Progress</strong>
    @endif
@endsection

@section('content')
    @php
        $maxWeeklyXp = max(1, (int) $weeklyActivity->max('xp'));
        $weeklyXpTotal = (int) $weeklyActivity->sum('xp');
    @endphp

    @if ($activeClassroom)
        {{-- ============================================================== --}}
        {{-- TAMPILAN 1: DETAIL PROGRESS KHUSUS KELAS YANG DIPILIH           --}}
        {{-- ============================================================== --}}

        <!-- Navigation Bar Kembali ke Semua Kelas & Switcher -->
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 bg-white p-3.5 px-4 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="flex items-center gap-3">
                <a href="{{ route('progress') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition" title="Kembali ke daftar kelas">
                    <x-icon name="arrow-left" class="w-3.5 h-3.5" />
                    <span>Semua Kelas</span>
                </a>
                <div class="h-4 w-px bg-slate-200"></div>
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span class="text-xs font-bold text-slate-800">Kelas Aktif:</span>
                    <span class="text-xs font-semibold text-blue-600 bg-blue-50 border border-blue-200 px-2.5 py-0.5 rounded-lg">
                        {{ $activeClassroom->name }}
                    </span>
                    <span class="text-xs text-slate-400">· Guru: {{ $activeClassroom->teacher?->name ?? 'Guru Pengajar' }}</span>
                </div>
            </div>

            <!-- Ganti Kelas Cepat jika siswa punya kelas lain -->
            @if ($classrooms->count() > 1)
                <div class="flex items-center gap-1.5">
                    <span class="text-xs text-slate-400 font-medium">Pindah:</span>
                    @foreach ($classrooms as $cls)
                        @if ($cls->id !== $activeClassroom->id)
                            <a href="{{ route('progress', ['classroom' => $cls->id]) }}" class="text-xs px-2.5 py-1 rounded-lg bg-slate-50 hover:bg-blue-50 text-slate-600 hover:text-blue-600 font-medium border border-slate-200 transition">
                                {{ $cls->name }}
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        <section class="stage-heading" aria-labelledby="progress-title">
            <div>
                <p class="eyebrow">CAPAIAN KELAS {{ mb_strtoupper($activeClassroom->name) }}</p>
                <h1 id="progress-title">Progress Kelas {{ $activeClassroom->name }}</h1>
                <p class="welcome-subtitle">Pantau aktivitas membaca, penguasaan kompetensi berpikir kritis, dan latihan Sokratik di kelas ini.</p>
            </div>
            <div class="date-filter progress-filter">
                <x-icon name="calendar-days" />
                <span>7 hari terakhir</span>
            </div>
        </section>

        <!-- 4 Kartu Ringkasan Khusus Kelas Terpilih -->
        <section class="progress-summary" aria-label="Ringkasan progress kelas">
            <article class="panel progress-stat">
                <span class="stat-icon icon-blue"><x-icon name="book-open-text" /></span>
                <div>
                    <p>Materi selesai</p>
                    <strong>{{ $completedReadings }}</strong>
                    <small>{{ $weeklyReadingCount }} dalam 7 hari terakhir</small>
                </div>
            </article>
            <article class="panel progress-stat">
                <span class="stat-icon icon-violet"><x-icon name="zap" /></span>
                <div>
                    <p>XP kelas ini</p>
                    <strong>{{ number_format($summary['xp']) }} <small>XP</small></strong>
                    <small>Kontribusi ke total XP akun</small>
                </div>
            </article>
            <article class="panel progress-stat">
                <span class="stat-icon icon-orange"><x-icon name="flame" /></span>
                <div>
                    <p>Streak belajar</p>
                    <strong>{{ $summary['longest_streak'] }} <small>hari</small></strong>
                    <small>Streak aktif: {{ $summary['current_streak'] }} hari</small>
                </div>
            </article>
            <article class="panel progress-stat">
                <span class="stat-icon icon-teal"><x-icon name="award" /></span>
                <div>
                    <p>Tantangan selesai</p>
                    <strong>{{ $completedChallenges }}</strong>
                    <small>Latihan Sokratik tersimpan</small>
                </div>
            </article>
        </section>

        <!-- 4 Blok Utama: Pengalaman Mingguan, Kompetensi Membaca, Target Mingguan, Jejak Belajar -->
        <div class="progress-content-grid">
            <!-- 1. PENGALAMAN MINGGUAN -->
            <section class="panel activity-detail-panel" aria-labelledby="weekly-activity-title">
                <div class="section-heading">
                    <div>
                        <p class="section-overline">PENGALAMAN MINGGUAN</p>
                        <h2 id="weekly-activity-title">Aktivitas 7 hari terakhir</h2>
                    </div>
                    <div class="chart-key"><i></i> XP kelas</div>
                </div>
                <div class="weekly-chart" role="img" aria-label="Grafik XP tujuh hari terakhir di kelas ini">
                    <div class="weekly-grid">
                        <span>maks</span>
                        <span>75%</span>
                        <span>50%</span>
                        <span>0 XP</span>
                    </div>
                    <div class="weekly-bars">
                        @foreach ($weeklyActivity as $day)
                            <div class="week-day {{ $day['date']->isToday() ? 'is-today' : '' }}">
                                <span class="bar-value">{{ $day['xp'] ?: '—' }}</span>
                                <i style="--bar-height:{{ $day['xp'] ? max(8, (int) round($day['xp'] / $maxWeeklyXp * 100)) : 4 }}%"></i>
                                <small>{{ $day['label'] }}</small>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="weekly-chart-caption">
                    <span><i class="caption-dot"></i> Total XP minggu ini di kelas {{ $activeClassroom->name }}</span>
                    <strong>{{ $weeklyXpTotal }} XP</strong>
                </div>
            </section>

            <!-- 2. KOMPETENSI MEMBACA (Dihitung Realtime & Proporsional terhadap Materi Kelas) -->
            <section class="panel skill-panel" aria-labelledby="skill-heading">
                <div class="section-heading">
                    <div>
                        <p class="section-overline">KOMPETENSI MEMBACA</p>
                        <h2 id="skill-heading">Latihan yang dikerjakan</h2>
                    </div>
                    <span class="info-mark" aria-label="Jumlah materi yang telah dilatih pada setiap keterampilan berpikir kritis Sokratik"><x-icon name="info" /></span>
                </div>
                <p class="skill-intro">Menunjukkan kemajuan latihan 5 keterampilan Sokratik pada materi kelas <strong>{{ $activeClassroom->name }}</strong>.</p>

                @foreach ($skillsCompetency as $skill)
                    <div class="skill-row">
                        <div class="skill-row-label">
                            <span class="skill-symbol skill-symbol-blue"><x-icon name="search-check" /></span>
                            <div>
                                <strong>{{ $skill->skill }}</strong>
                                <small>{{ $skill->practiced_count }} dari {{ $skill->target_count }} materi dilatih</small>
                            </div>
                            <b>{{ $skill->percent }}%</b>
                        </div>
                        <div class="skill-track">
                            <span style="width: {{ $skill->percent }}%"></span>
                        </div>
                    </div>
                @endforeach

                <div class="skill-note">
                    <x-icon name="lightbulb" />
                    Setiap bacaan yang kamu selesaikan tantangannya akan meningkatkan capaian kompetensi di atas secara bertahap.
                </div>
            </section>

            <!-- 3. TARGET MINGGUAN -->
            <section class="panel goal-panel">
                <div class="goal-copy">
                    <p class="section-overline">TARGET MINGGUAN</p>
                    <h2>{{ $weeklyReadingCount >= 5 ? 'Target tercapai!' : 'Bangun ritme membaca.' }}</h2>
                    <p>Selesaikan bacaan pada kelas {{ $activeClassroom->name }} untuk menjaga konsistensi belajarmu.</p>
                    <div class="goal-count">
                        <strong>{{ min(5, $weeklyReadingCount) }}</strong>
                        <span>/ 5 materi dibaca minggu ini</span>
                    </div>
                    <div class="goal-track">
                        <span style="width: {{ min(100, $weeklyReadingCount * 20) }}%"></span>
                    </div>
                    <a href="{{ route('reading') }}" class="text-link">Buka ruang baca <x-icon name="arrow-right" /></a>
                </div>
                <div class="goal-ring" style="--goal-progress:{{ min(100, $weeklyReadingCount * 20) }}%" role="img" aria-label="Target mingguan {{ min(100, $weeklyReadingCount * 20) }} persen tercapai">
                    <div>
                        <strong>{{ min(100, $weeklyReadingCount * 20) }}%</strong>
                        <small>tercapai</small>
                    </div>
                </div>
            </section>

            <!-- 4. JEJAK BELAJAR -->
            <section class="panel reflection-panel" aria-labelledby="activity-history-title">
                <span class="reflection-mark" aria-hidden="true"><x-icon name="clock" /></span>
                <p class="section-overline">JEJAK BELAJAR</p>
                <h2 id="activity-history-title">Aktivitas terbaru di kelas ini</h2>
                <div class="recent-activity-list progress-activity-list">
                    @forelse ($recentActivities as $activity)
                        <article class="recent-activity-row">
                            <span class="recent-activity-icon"><x-icon name="{{ $activity->event_type === 'reading_completed' ? 'book-open-text' : 'award' }}" /></span>
                            <div>
                                <strong>{{ $activity->summary }}</strong>
                                <small>{{ $activity->readingSession?->document?->title ?? 'Sesi materi' }} · {{ $activity->created_at->locale('id')->diffForHumans() }}</small>
                            </div>
                            <span class="recent-activity-xp">{{ $activity->xp_awarded ? '+'.$activity->xp_awarded.' XP' : 'Tercatat' }}</span>
                        </article>
                    @empty
                        <div class="activity-empty">Belum ada aktivitas di kelas {{ $activeClassroom->name }}. Mulai baca materi dan jawab tantangannya.</div>
                    @endforelse
                </div>
            </section>
        </div>

    @else
        {{-- ============================================================== --}}
        {{-- TAMPILAN 2: DAFTAR KELAS & RINGKASAN PROGRESS SISWA            --}}
        {{-- ============================================================== --}}

        <section class="stage-heading" aria-labelledby="progress-title">
            <div>
                <p class="eyebrow">LANGKAH KECIL, PERKEMBANGAN BESAR</p>
                <h1 id="progress-title">Progress belajarmu</h1>
                <p class="welcome-subtitle">Pilih kelas di bawah ini untuk melihat capaian kompetensi membaca, target, dan grafik belajarmu secara mendalam.</p>
            </div>
            <div class="date-filter progress-filter">
                <x-icon name="calendar-days" />
                <span>Semua waktu</span>
            </div>
        </section>

        <!-- Ringkasan Keseluruhan Akun -->
        <section class="progress-summary mb-6" aria-label="Ringkasan progress keseluruhan">
            <article class="panel progress-stat">
                <span class="stat-icon icon-blue"><x-icon name="book-open-text" /></span>
                <div>
                    <p>Total materi selesai</p>
                    <strong>{{ $completedReadings }}</strong>
                    <small>{{ $weeklyReadingCount }} dalam 7 hari terakhir</small>
                </div>
            </article>
            <article class="panel progress-stat">
                <span class="stat-icon icon-violet"><x-icon name="zap" /></span>
                <div>
                    <p>Total XP terkumpul</p>
                    <strong>{{ number_format($summary['xp']) }} <small>XP</small></strong>
                    <small>Level {{ $summary['level'] }} · {{ $summary['xp_to_next_level'] }} XP menuju level berikutnya</small>
                </div>
            </article>
            <article class="panel progress-stat">
                <span class="stat-icon icon-orange"><x-icon name="flame" /></span>
                <div>
                    <p>Streak terpanjang</p>
                    <strong>{{ $summary['longest_streak'] }} <small>hari</small></strong>
                    <small>Streak aktif: {{ $summary['current_streak'] }} hari</small>
                </div>
            </article>
            <article class="panel progress-stat">
                <span class="stat-icon icon-teal"><x-icon name="award" /></span>
                <div>
                    <p>Total tantangan selesai</p>
                    <strong>{{ $completedChallenges }}</strong>
                    <small>Latihan Sokratik tersimpan</small>
                </div>
            </article>
        </section>

        <!-- KELAS SAYA: Kartu-kartu Kelas untuk Diklik -->
        <section class="mb-8" aria-labelledby="classroom-list-heading">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="section-overline text-xs font-semibold text-blue-600 uppercase tracking-wider">PILIH KELAS UNTUK DETAIL PROGRESS</p>
                    <h2 id="classroom-list-heading" class="text-xl font-bold text-slate-800">Kelas yang Kamu Ikuti</h2>
                </div>
                <a href="{{ route('classrooms.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700 inline-flex items-center gap-1">
                    Kelola Kelas <x-icon name="arrow-right" class="w-3.5 h-3.5" />
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                @forelse ($classroomCards as $card)
                    <div class="panel p-5 rounded-2xl border border-slate-200/90 bg-white hover:border-blue-400 hover:shadow-md transition-all duration-200 flex flex-col justify-between">
                        <div>
                            <div class="flex items-start justify-between gap-3 mb-3">
                                <div class="flex items-center gap-3">
                                    <span class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold border border-blue-100">
                                        <x-icon name="book-open-text" class="w-5 h-5" />
                                    </span>
                                    <div>
                                        <h3 class="font-bold text-slate-800 text-base leading-snug">{{ $card['classroom']->name }}</h3>
                                        <p class="text-xs text-slate-500">Guru: {{ $card['teacher_name'] }}</p>
                                    </div>
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600">
                                    {{ $card['target_grade_label'] }}
                                </span>
                            </div>

                            <!-- Progress Bar Materi Selesai -->
                            <div class="my-4">
                                <div class="flex justify-between text-xs text-slate-600 mb-1.5 font-medium">
                                    <span>Materi selesai</span>
                                    <strong class="text-slate-800">{{ $card['completed_readings'] }} / {{ $card['total_documents'] }} materi ({{ $card['progress_percent'] }}%)</strong>
                                </div>
                                <div class="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-blue-600 rounded-full transition-all duration-300" style="width: {{ $card['progress_percent'] }}%"></div>
                                </div>
                            </div>

                            <!-- Mini Stat Grid -->
                            <div class="grid grid-cols-2 gap-2 py-2.5 border-t border-slate-100 text-center mb-4">
                                <div class="bg-slate-50 p-2 rounded-xl">
                                    <span class="block text-[11px] text-slate-400 font-medium">XP Kelas</span>
                                    <strong class="text-xs font-bold text-slate-800">+{{ $card['xp_earned'] }} XP</strong>
                                </div>
                                <div class="bg-slate-50 p-2 rounded-xl">
                                    <span class="block text-[11px] text-slate-400 font-medium">Tantangan</span>
                                    <strong class="text-xs font-bold text-slate-800">{{ $card['completed_challenges'] }} selesai</strong>
                                </div>
                            </div>
                        </div>

                        <!-- Tombol Buka Progress Kelas -->
                        <a href="{{ route('progress', ['classroom' => $card['classroom']->id]) }}" class="w-full inline-flex items-center justify-center gap-2 py-2 px-4 text-xs font-bold rounded-xl bg-blue-600 hover:bg-blue-700 text-white transition shadow-sm">
                            <span>Buka Progress Kelas Ini</span>
                            <x-icon name="arrow-right" class="w-3.5 h-3.5" />
                        </a>
                    </div>
                @empty
                    <div class="col-span-full panel p-8 text-center text-slate-500 rounded-2xl border border-dashed border-slate-300">
                        <span class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-slate-100 text-slate-400 mb-3">
                            <x-icon name="users" class="w-6 h-6" />
                        </span>
                        <h3 class="font-bold text-slate-700 text-base mb-1">Kamu belum bergabung dengan kelas manapun</h3>
                        <p class="text-xs text-slate-500 max-w-md mx-auto mb-4">Masukkan kode gabung dari gurumu untuk mengakses materi dan melihat progress belajarmu.</p>
                        <a href="{{ route('classrooms.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-xs font-semibold rounded-xl hover:bg-blue-700 transition">
                            Gabung Kelas Sekarang
                        </a>
                    </div>
                @endforelse
            </div>
        </section>

        <!-- Jejak Belajar Global -->
        <section class="panel reflection-panel" aria-labelledby="global-activity-title">
            <span class="reflection-mark" aria-hidden="true"><x-icon name="clock" /></span>
            <p class="section-overline">JEJAK BELAJAR TERAKHIR</p>
            <h2 id="global-activity-title">Aktivitas belajar terbaru</h2>
            <div class="recent-activity-list progress-activity-list">
                @forelse ($recentActivities as $activity)
                    <article class="recent-activity-row">
                        <span class="recent-activity-icon"><x-icon name="{{ $activity->event_type === 'reading_completed' ? 'book-open-text' : 'award' }}" /></span>
                        <div>
                            <strong>{{ $activity->summary }}</strong>
                            <small>{{ $activity->readingSession?->document?->title ?? 'Sesi membaca' }} · {{ $activity->created_at->locale('id')->diffForHumans() }}</small>
                        </div>
                        <span class="recent-activity-xp">{{ $activity->xp_awarded ? '+'.$activity->xp_awarded.' XP' : 'Tercatat' }}</span>
                    </article>
                @empty
                    <div class="activity-empty">Belum ada aktivitas. Mulai baca materi di kelasmu dan selesaikan tantangan Sokratik.</div>
                @endforelse
            </div>
        </section>
    @endif
@endsection
