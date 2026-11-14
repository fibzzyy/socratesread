@extends('layouts.teacher')

@section('title', 'Ringkasan Guru')
@section('breadcrumb', 'Ringkasan')

@section('content')
    @php($maxDailyActivity = max(1, $weeklyActivity->max('activity_count')))
    <section class="stage-heading" aria-labelledby="teacher-dashboard-title">
        <div class="teacher-dashboard-heading-copy">
            <span class="teacher-dashboard-heading-icon" aria-hidden="true"><x-icon name="layout-dashboard" /></span>
            <div class="teacher-dashboard-heading-text">
                <h1 id="teacher-dashboard-title">Ringkasan belajar siswa</h1>
                <p class="welcome-subtitle">Ikhtisar aktivitas anggota kelas pada bacaan yang Anda unggah.</p>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('teacher.export.progress') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-xs" title="Unduh data progres siswa (.CSV kompatibel Excel)">
                <x-icon name="download" class="w-3.5 h-3.5 text-slate-500" />
                <span>Unduh Rekap CSV</span>
            </a>
            <a href="{{ route('teacher.documents') }}" class="challenge-button">Kelola bacaan <x-icon name="arrow-right" /></a>
        </div>
    </section>

    <section class="progress-summary teacher-summary" aria-label="Ringkasan kelas">
        <article class="panel progress-stat"><span class="stat-icon icon-blue"><x-icon name="book-open-text" /></span><div><p>Bacaan Anda</p><strong>{{ $documentCount }}</strong><small>Dokumen yang sudah diunggah</small></div></article>
        <article class="panel progress-stat"><span class="stat-icon icon-violet"><x-icon name="layout-dashboard" /></span><div><p>Siswa aktif</p><strong>{{ $studentCount }}</strong><small>Anggota kelas yang membaca</small></div></article>
        <article class="panel progress-stat"><span class="stat-icon icon-teal"><x-icon name="check" /></span><div><p>Sesi selesai</p><strong>{{ $completedCount }} <small>/ {{ $sessionCount }}</small></strong><small>Seluruh sesi pada bacaan Anda</small></div></article>
        <article class="panel progress-stat"><span class="stat-icon icon-orange"><x-icon name="chart-no-axes-combined" /></span><div><p>Progres rata-rata</p><strong>{{ $averageProgress }}<small>%</small></strong><small>Progres sesi membaca siswa</small></div></article>
    </section>

    <div class="teacher-analytics-grid">
        <section class="panel activity-detail-panel" aria-labelledby="teacher-weekly-title">
            <div class="section-heading"><div><h2 id="teacher-weekly-title"><x-icon name="chart-no-axes-combined" />Jejak belajar 7 hari</h2></div><div class="chart-key"><i></i> Aktivitas</div></div>
            <div class="weekly-chart" role="img" aria-label="Grafik aktivitas membaca siswa tujuh hari terakhir">
                <div class="weekly-grid"><span>maks</span><span>75%</span><span>50%</span><span>0</span></div>
                <div class="weekly-bars">
                    @foreach ($weeklyActivity as $day)
                        <div class="week-day {{ $day['date']->isToday() ? 'is-today' : '' }}"><span class="bar-value">{{ $day['activity_count'] ?: '—' }}</span><i style="--bar-height:{{ $day['activity_count'] ? max(8, (int) round($day['activity_count'] / $maxDailyActivity * 100)) : 4 }}%"></i><small>{{ $day['label'] }}</small></div>
                    @endforeach
                </div>
            </div>
            <div class="weekly-chart-caption"><span><i class="caption-dot"></i> Total aktivitas</span><strong>{{ $weeklyActivity->sum('activity_count') }}</strong></div>
        </section>

        <section class="panel reflection-panel teacher-recent-panel" aria-labelledby="teacher-recent-title">
            <span class="reflection-mark" aria-hidden="true"><x-icon name="clock" /></span><h2 id="teacher-recent-title"><x-icon name="megaphone" />Siswa mulai berkembang</h2>
            <div class="recent-activity-list">
                @forelse ($recentActivities as $activity)
                    <article class="recent-activity-row"><span class="recent-activity-icon"><x-icon name="{{ $activity->event_type === 'reading_completed' ? 'book-open-text' : 'award' }}" /></span><div><strong>{{ $activity->student_name }} · {{ $activity->summary }}</strong><small>{{ $activity->document_title }} · {{ \Illuminate\Support\Carbon::parse($activity->created_at)->locale('id')->diffForHumans() }}</small></div><span class="recent-activity-xp">{{ $activity->xp_awarded ? '+'.$activity->xp_awarded.' XP' : 'Tercatat' }}</span></article>
                @empty
                    <div class="activity-empty">Aktivitas siswa pada bacaan yang Anda unggah akan muncul di sini.</div>
                @endforelse
            </div>
        </section>

        <!-- Panel Ikhtisar Keterampilan Socratic Siswa -->
        <section class="panel socratic-skills-panel" style="grid-column: 1 / -1;" aria-labelledby="teacher-skill-title">
            <div class="section-heading">
                <div>
                    <h2 id="teacher-skill-title">Ikhtisar Keterampilan Socratic Siswa</h2>
                </div>
                <span class="info-mark" title="Frekuensi latihan cara berpikir kritis yang dikerjakan siswa pada bacaan Anda"><x-icon name="lightbulb" /></span>
            </div>
            <p class="skill-intro">Sebaran jawaban latihan berpikir siswa pada dokumen bacaan yang Anda unggah.</p>
            @php($maxSkillCount = max(1, (int) $socraticSkills->max('answer_count')))
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($socraticSkills as $skill)
                    <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3.5">
                        <div class="skill-row-label">
                            <span class="skill-symbol skill-symbol-blue"><x-icon name="search-check" /></span>
                            <div>
                                <strong class="text-xs font-bold text-slate-800">{{ $skill->skill }}</strong>
                                <small class="text-[11px] text-slate-500">{{ $skill->student_count }} siswa berlatih</small>
                            </div>
                            <b class="text-xs font-bold text-slate-700">{{ $skill->answer_count }} <span class="text-[10px] font-normal text-slate-400">jawaban</span></b>
                        </div>
                        <div class="skill-track mt-2">
                            <span style="width:{{ min(100, (int) round($skill->answer_count / $maxSkillCount * 100)) }}%"></span>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full">
                        <div class="teacher-empty-state">
                            <span class="session-icon"><x-icon name="lightbulb" /></span>
                            <strong>Belum ada jawaban Socratic</strong>
                            <p>Setelah siswa menjawab pertanyaan berpikir kritis pada dokumen Anda, ringkasan kompetensinya akan muncul di sini.</p>
                        </div>
                    </div>
                @endforelse
            </div>
            <div class="skill-note mt-3">
                <x-icon name="info" />
                <span>Metode Socratic melatih proses dan kerangka berpikir siswa secara berkesinambungan.</span>
            </div>
        </section>

        <section class="panel teacher-students-panel" aria-labelledby="teacher-students-title">
            <div class="section-heading">
                <div>
                    <h2 id="teacher-students-title">Pembaca bacaan Anda</h2>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('teacher.export.progress') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition" title="Unduh CSV">
                        <x-icon name="download" class="w-3.5 h-3.5 text-slate-500" />
                        <span class="hidden sm:inline">Ekspor CSV</span>
                    </a>
                    <span class="document-count">{{ $students->total() }} siswa</span>
                </div>
            </div>
            <p class="skill-intro">Hanya progres anggota kelas pada dokumen yang Anda unggah yang ditampilkan. Isi jawaban pribadi siswa tidak ditampilkan.</p>
            <div class="teacher-table-wrap"><table class="teacher-student-table"><thead><tr><th scope="col">Siswa</th><th scope="col">Sesi</th><th scope="col">Selesai</th><th scope="col">Progres</th><th scope="col">XP bacaan</th><th scope="col">Aktivitas</th></tr></thead><tbody>
                @forelse ($students as $student)
                    @php($currentYear = (int) now()->year)
                    @php($studentGrade = $student->entry_grade ? min(12, $student->entry_grade + max(0, $currentYear - ($student->enrollment_year ?? $currentYear))) : null)
                    <tr><td><button type="button" class="teacher-student-name hover:text-blue-600 transition text-left cursor-pointer group" data-student-btn data-detail-url="{{ route('teacher.students.show', $student->id) }}" title="Lihat detail belajar {{ $student->name }}"><span class="avatar avatar-empty group-hover:ring-2 group-hover:ring-blue-400 transition"><x-icon name="user" class="avatar-placeholder-icon" /></span><div><strong class="group-hover:underline text-slate-800 group-hover:text-blue-600">{{ $student->name }}</strong>@if ($studentGrade) <span class="rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-semibold text-blue-700">Kelas {{ $studentGrade }}</span>@endif</div></button></td><td>{{ $student->session_count }}</td><td>{{ $student->completed_count }}</td><td><span class="teacher-progress-cell"><span class="skill-track"><span style="width:{{ min(100, (int) $student->average_progress) }}%"></span></span><small>{{ (int) $student->average_progress }}%</small></span></td><td>{{ (int) $student->earned_xp }}</td><td>{{ $student->last_activity_at ? \Illuminate\Support\Carbon::parse($student->last_activity_at)->locale('id')->diffForHumans() : '—' }}</td></tr>
                @empty
                    <tr><td colspan="6"><div class="teacher-empty-state"><span class="session-icon"><x-icon name="lightbulb" /></span><strong>Belum ada anggota kelas yang membaca</strong><p>Setelah siswa bergabung ke kelas dan membaca dokumen Anda, ringkasan progresnya akan muncul di sini.</p></div></td></tr>
                @endforelse
            </tbody></table></div>
            @if ($students->hasPages())<div class="teacher-pagination">{{ $students->links() }}</div>@endif
        </section>
    </div>

    <!-- Drawer Detail Siswa -->
    <div id="studentDetailModal" class="hidden fixed inset-0 z-50 overflow-hidden" role="dialog" aria-modal="true" aria-labelledby="modalStudentTitle">
        <!-- Backdrop -->
        <div id="studentModalBackdrop" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs transition-opacity"></div>

        <div class="fixed inset-y-0 right-0 max-w-full flex pl-0 sm:pl-10">
            <div id="studentModalPanel" class="w-screen max-w-md md:max-w-lg bg-white shadow-2xl flex flex-col transform transition ease-in-out duration-300">
                <!-- Header Drawer -->
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 bg-slate-50/80">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">DETAIL BELAJAR SISWA</p>
                        <h2 id="modalStudentTitle" class="text-base font-bold text-slate-800">Profil Siswa</h2>
                    </div>
                    <button type="button" id="closeStudentModal" class="rounded-lg p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-200/60 transition" aria-label="Tutup detail">
                        <x-icon name="arrow-right" class="size-4" />
                    </button>
                </div>

                <!-- Body Drawer (Scrollable) -->
                <div class="flex-1 overflow-y-auto p-6 space-y-6">
                    <!-- Loading state -->
                    <div id="studentModalLoading" class="py-16 text-center text-slate-400">
                        <span class="inline-block animate-pulse text-blue-600 mb-2 font-bold text-lg">Memuat data...</span>
                        <p class="text-xs">Mengambil riwayat membaca dan aktivitas siswa...</p>
                    </div>

                    <!-- Error state -->
                    <div id="studentModalError" class="hidden py-12 text-center text-red-500">
                        <p class="text-sm font-semibold">Gagal memuat detail siswa.</p>
                        <p class="text-xs text-slate-500 mt-1">Pastikan koneksi lancar dan coba lagi.</p>
                    </div>

                    <!-- Content (hidden while loading) -->
                    <div id="studentModalContent" class="hidden space-y-6">
                        <!-- Profile Card -->
                        <div class="flex items-start gap-4 p-4 rounded-2xl bg-blue-50/50 border border-blue-100/60">
                            <div id="modalAvatarBox" class="size-13 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center shrink-0 overflow-hidden">
                                <span class="avatar-placeholder" aria-hidden="true"></span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3 id="modalName" class="font-bold text-base text-slate-900 truncate"></h3>
                                    <span id="modalGradeBadge" class="rounded bg-blue-100 text-blue-800 text-[10px] font-bold px-2 py-0.5"></span>
                                </div>
                                <p id="modalEmail" class="text-xs text-slate-500 truncate mt-0.5"></p>
                                <p id="modalSchool" class="text-xs text-slate-600 mt-1 flex items-center gap-1.5">
                                    <span class="text-slate-400">Sekolah:</span>
                                    <strong id="modalSchoolText" class="font-medium text-slate-700"></strong>
                                </p>
                            </div>
                        </div>

                        <!-- Classes Enrolled -->
                        <div>
                            <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Kelas yang Diikuti</p>
                            <div id="modalClassesList" class="flex flex-wrap gap-1.5"></div>
                        </div>

                        <!-- 4 Summary Stats -->
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3 text-center">
                                <p class="text-[10px] uppercase font-semibold text-slate-400">Sesi Baca</p>
                                <strong id="modalStatSessions" class="text-base font-bold text-slate-800">0</strong>
                            </div>
                            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3 text-center">
                                <p class="text-[10px] uppercase font-semibold text-slate-400">Selesai 100%</p>
                                <strong id="modalStatCompleted" class="text-base font-bold text-emerald-600">0</strong>
                            </div>
                            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3 text-center">
                                <p class="text-[10px] uppercase font-semibold text-slate-400">Rata Progres</p>
                                <strong id="modalStatProgress" class="text-base font-bold text-blue-600">0%</strong>
                            </div>
                            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3 text-center">
                                <p class="text-[10px] uppercase font-semibold text-slate-400">Total XP</p>
                                <strong id="modalStatXp" class="text-base font-bold text-amber-600">0 XP</strong>
                            </div>
                        </div>

                        <!-- Reading Sessions List -->
                        <div>
                            <div class="flex items-center justify-between mb-2.5">
                                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Riwayat Bacaan pada Materi Anda</p>
                                <span id="modalSessionsCount" class="text-[11px] text-slate-400 font-medium">0 materi</span>
                            </div>
                            <div id="modalSessionsContainer" class="space-y-3">
                                <!-- Populated dynamically -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const modal = document.getElementById('studentDetailModal');
            const backdrop = document.getElementById('studentModalBackdrop');
            const closeBtn = document.getElementById('closeStudentModal');
            const loadingEl = document.getElementById('studentModalLoading');
            const errorEl = document.getElementById('studentModalError');
            const contentEl = document.getElementById('studentModalContent');

            if (!modal) return;

            const openModal = async (url) => {
                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
                loadingEl.classList.remove('hidden');
                errorEl.classList.add('hidden');
                contentEl.classList.add('hidden');

                try {
                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (!response.ok) throw new Error('Failed to load student details');
                    const data = await response.json();
                    renderStudentDetail(data);
                } catch (err) {
                    loadingEl.classList.add('hidden');
                    errorEl.classList.remove('hidden');
                }
            };

            const closeModal = () => {
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            };

            backdrop.addEventListener('click', closeModal);
            closeBtn.addEventListener('click', closeModal);
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                    closeModal();
                }
            });

            document.querySelectorAll('[data-student-btn]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const url = btn.dataset.detailUrl;
                    if (url) openModal(url);
                });
            });

            const renderStudentDetail = (data) => {
                const s = data.student;
                document.getElementById('modalStudentTitle').textContent = s.name;
                document.getElementById('modalName').textContent = s.name;
                document.getElementById('modalEmail').textContent = s.email;
                document.getElementById('modalSchoolText').textContent = s.school_origin;

                const gradeBadge = document.getElementById('modalGradeBadge');
                if (s.grade) {
                    gradeBadge.textContent = s.grade;
                    gradeBadge.classList.remove('hidden');
                } else {
                    gradeBadge.classList.add('hidden');
                }

                const avatarBox = document.getElementById('modalAvatarBox');
                if (s.avatar_url) {
                    avatarBox.classList.remove('avatar-empty');
                    avatarBox.innerHTML = `<img src="${s.avatar_url}" alt="${s.name}" class="size-full object-cover">`;
                } else {
                    avatarBox.classList.add('avatar-empty');
                    avatarBox.innerHTML = '<span class="avatar-placeholder" aria-hidden="true"></span>';
                }

                const classesList = document.getElementById('modalClassesList');
                classesList.innerHTML = '';
                if (s.classes && s.classes.length > 0) {
                    s.classes.forEach(c => {
                        const pill = document.createElement('span');
                        pill.className = 'rounded-md bg-slate-100 text-slate-700 font-semibold px-2.5 py-1 text-xs';
                        pill.textContent = c;
                        classesList.appendChild(pill);
                    });
                } else {
                    classesList.innerHTML = '<span class="text-xs text-slate-400">Tidak ada kelas aktif</span>';
                }

                document.getElementById('modalStatSessions').textContent = s.session_count;
                document.getElementById('modalStatCompleted').textContent = s.completed_count;
                document.getElementById('modalStatProgress').textContent = s.average_progress + '%';
                document.getElementById('modalStatXp').textContent = s.earned_xp + ' XP';

                const sessionsContainer = document.getElementById('modalSessionsContainer');
                document.getElementById('modalSessionsCount').textContent = data.sessions.length + ' materi';
                sessionsContainer.innerHTML = '';

                if (data.sessions.length === 0) {
                    sessionsContainer.innerHTML = `
                        <div class="py-6 text-center text-slate-400 border border-dashed border-slate-200 rounded-xl">
                            <p class="text-xs">Siswa ini belum mulai membaca materi unggahan Anda.</p>
                        </div>
                    `;
                } else {
                    data.sessions.forEach(sess => {
                        const item = document.createElement('div');
                        item.className = 'p-3.5 rounded-xl border border-slate-100 bg-slate-50/70 text-xs space-y-2';
                        item.innerHTML = `
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-semibold text-blue-700">${sess.category}</span>
                                        <span class="rounded bg-slate-200/70 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600">${sess.target_grade}</span>
                                    </div>
                                    <h4 class="font-bold text-slate-800 text-sm mt-1 truncate">${sess.title}</h4>
                                </div>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold ${sess.is_completed ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}">
                                    ${sess.status}
                                </span>
                            </div>
                            <div class="space-y-1">
                                <div class="flex items-center justify-between text-[11px] text-slate-500">
                                    <span>Progres membaca</span>
                                    <strong>${sess.progress_percent}%</strong>
                                </div>
                                <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden">
                                    <div class="bg-blue-600 h-1.5 rounded-full" style="width: ${sess.progress_percent}%"></div>
                                </div>
                            </div>
                            <div class="flex items-center justify-between text-[11px] text-slate-400 pt-1 border-t border-slate-100">
                                <span>Mulai: ${sess.started_at}</span>
                                <span>Latihan dijawab: <strong>${sess.answers_count}</strong></span>
                            </div>
                        `;
                        sessionsContainer.appendChild(item);
                    });
                }

                loadingEl.classList.add('hidden');
                contentEl.classList.remove('hidden');
            };
        })();
    </script>
@endsection
