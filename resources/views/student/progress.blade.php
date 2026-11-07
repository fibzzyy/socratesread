@extends('layouts.student')

@section('title', 'Progress Belajar')
@section('breadcrumb', 'Progress')

@section('content')
    @php
        $maxWeeklyXp = max(1, (int) $weeklyActivity->max('xp'));
        $weeklyXpTotal = (int) $weeklyActivity->sum('xp');
        $maxAnswers = max(1, (int) $answersBySkill->max('answer_count'));
    @endphp

    <section class="stage-heading" aria-labelledby="progress-title"><div><p class="eyebrow">LANGKAH KECIL, PERKEMBANGAN BESAR</p><h1 id="progress-title">Progress belajarmu</h1><p class="welcome-subtitle">Lihat kebiasaan membaca dan cara berpikirmu berkembang.</p></div><div class="date-filter progress-filter"><x-icon name="calendar-days" /><span>7 hari terakhir</span></div></section>

    <section class="progress-summary" aria-label="Ringkasan progress">
        <article class="panel progress-stat"><span class="stat-icon icon-blue"><x-icon name="book-open-text" /></span><div><p>Bacaan selesai</p><strong>{{ $completedReadings }}</strong><small>{{ $weeklyReadingCount }} dalam 7 hari terakhir</small></div></article>
        <article class="panel progress-stat"><span class="stat-icon icon-violet"><x-icon name="zap" /></span><div><p>XP terkumpul</p><strong>{{ number_format($summary['xp']) }} <small>XP</small></strong><small>Level {{ $summary['level'] }} · {{ $summary['xp_to_next_level'] }} XP menuju level berikutnya</small></div></article>
        <article class="panel progress-stat"><span class="stat-icon icon-orange"><x-icon name="flame" /></span><div><p>Streak terpanjang</p><strong>{{ $summary['longest_streak'] }} <small>hari</small></strong><small>Streak saat ini: {{ $summary['current_streak'] }} hari</small></div></article>
        <article class="panel progress-stat"><span class="stat-icon icon-teal"><x-icon name="award" /></span><div><p>Tantangan selesai</p><strong>{{ $completedChallenges }}</strong><small>Jawaban lengkap tersimpan</small></div></article>
    </section>

    <div class="progress-content-grid">
        <section class="panel activity-detail-panel" aria-labelledby="weekly-activity-title">
            <div class="section-heading"><div><p class="section-overline">PENGALAMAN MINGGUAN</p><h2 id="weekly-activity-title">Aktivitas 7 hari terakhir</h2></div><div class="chart-key"><i></i> XP diperoleh</div></div>
            <div class="weekly-chart" role="img" aria-label="Grafik XP tujuh hari terakhir">
                <div class="weekly-grid"><span>maks</span><span>75%</span><span>50%</span><span>0 XP</span></div>
                <div class="weekly-bars">
                    @foreach ($weeklyActivity as $day)
                        <div class="week-day {{ $day['date']->isToday() ? 'is-today' : '' }}"><span class="bar-value">{{ $day['xp'] ?: '—' }}</span><i style="--bar-height:{{ $day['xp'] ? max(8, (int) round($day['xp'] / $maxWeeklyXp * 100)) : 4 }}%"></i><small>{{ $day['label'] }}</small></div>
                    @endforeach
                </div>
            </div>
            <div class="weekly-chart-caption"><span><i class="caption-dot"></i> Total XP minggu ini</span><strong>{{ $weeklyXpTotal }} XP</strong></div>
        </section>

        <section class="panel skill-panel" aria-labelledby="skill-heading"><div class="section-heading"><div><p class="section-overline">KOMPETENSI MEMBACA</p><h2 id="skill-heading">Latihan yang dikerjakan</h2></div><span class="info-mark" aria-label="Jumlah jawaban, bukan nilai benar atau salah"><x-icon name="info" /></span></div><p class="skill-intro">Kami menampilkan aktivitas jawaban, bukan skor otomatis benar atau salah.</p>
            @forelse ($answersBySkill as $skill)
                <div class="skill-row"><div class="skill-row-label"><span class="skill-symbol skill-symbol-blue"><x-icon name="search-check" /></span><div><strong>{{ $skill->skill }}</strong><small>Jawaban tersimpan</small></div><b>{{ $skill->answer_count }}</b></div><div class="skill-track"><span style="width:{{ min(100, (int) round($skill->answer_count / $maxAnswers * 100)) }}%"></span></div></div>
            @empty
                <div class="teacher-empty-state"><span class="session-icon"><x-icon name="lightbulb" /></span><strong>Belum ada jawaban</strong><p>Jawaban pada Socratic Challenge akan muncul di sini.</p></div>
            @endforelse
            <div class="skill-note"><x-icon name="lightbulb" /> Pertanyaan membantu latihan; jumlah jawaban bukan penilaian kemampuan.</div>
        </section>

        <section class="panel goal-panel"><div class="goal-copy"><p class="section-overline">TARGET MINGGUAN</p><h2>{{ $weeklyReadingCount >= 5 ? 'Target tercapai!' : 'Bangun ritme membaca.' }}</h2><p>Selesaikan lima bacaan dalam tujuh hari untuk menjaga kebiasaan belajar.</p><div class="goal-count"><strong>{{ min(5, $weeklyReadingCount) }}</strong><span>/ 5 bacaan selesai</span></div><div class="goal-track"><span style="width:{{ min(100, $weeklyReadingCount * 20) }}%"></span></div><a href="{{ route('reading') }}" class="text-link">Lanjutkan membaca <x-icon name="arrow-right" /></a></div><div class="goal-ring" style="--goal-progress:{{ min(100, $weeklyReadingCount * 20) }}%" role="img" aria-label="Target mingguan {{ min(100, $weeklyReadingCount * 20) }} persen tercapai"><div><strong>{{ min(100, $weeklyReadingCount * 20) }}%</strong><small>tercapai</small></div></div></section>

        <section class="panel reflection-panel" aria-labelledby="activity-history-title"><span class="reflection-mark" aria-hidden="true"><x-icon name="clock" /></span><p class="section-overline">JEJAK BELAJAR</p><h2 id="activity-history-title">Aktivitas terbaru</h2>
            <div class="recent-activity-list progress-activity-list">
                @forelse ($recentActivities as $activity)
                    <article class="recent-activity-row"><span class="recent-activity-icon"><x-icon name="{{ $activity->event_type === 'reading_completed' ? 'book-open-text' : 'award' }}" /></span><div><strong>{{ $activity->summary }}</strong><small>{{ $activity->readingSession?->document?->title ?? 'Sesi membaca' }} · {{ $activity->created_at->locale('id')->diffForHumans() }}</small></div><span class="recent-activity-xp">{{ $activity->xp_awarded ? '+'.$activity->xp_awarded.' XP' : 'Tercatat' }}</span></article>
                @empty
                    <div class="activity-empty">Belum ada aktivitas. Mulai dari satu bacaan, lalu lanjutkan dengan pertanyaan Socratic.</div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
