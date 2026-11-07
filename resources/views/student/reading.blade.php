@extends('layouts.student')

@section('title', 'Reading Room')
@section('breadcrumb', 'Reading Room')

@section('content')
    @if (session('notice')) <div class="auth-notice" role="status">{{ session('notice') }}</div> @endif
    <section class="stage-heading" aria-labelledby="reading-title"><div><p class="eyebrow">RUANG FOKUSMU</p><h1 id="reading-title">Reading Room</h1><p class="welcome-subtitle">Baca dengan tenang. Temukan makna di balik setiap paragraf.</p></div><a class="back-link" href="{{ route('dashboard') }}"><x-icon name="arrow-left" /> <span>Kembali ke dashboard</span></a></section>

    <div class="reading-room-grid">
        <article class="panel passage-panel" aria-labelledby="passage-title">
            <div class="passage-toolbar"><div class="passage-tags"><span class="category-tag tag-blue">{{ mb_strtoupper($document->category) }}</span><span class="reading-time"><x-icon name="clock" /> {{ $document->estimated_minutes }} menit membaca</span></div><div class="text-controls" aria-label="Pengaturan teks"><span>Ukuran teks</span><button type="button" data-font-step="-1" aria-label="Kecilkan ukuran teks">A−</button><button type="button" data-font-step="1" aria-label="Besarkan ukuran teks">A+</button></div></div>
            <div class="passage-content" data-reading-text>
                <p class="section-overline">{{ mb_strtoupper($document->category) }} · BACAAN PILIHAN</p><h2 id="passage-title">{{ $document->title }}</h2><p class="passage-lede">{!! \Illuminate\Support\Str::inlineMarkdown(e($document->excerpt)) !!}</p>
                @foreach (preg_split('/\R{2,}/', $document->body) as $paragraph)
                    <p>{!! \Illuminate\Support\Str::inlineMarkdown(e($paragraph)) !!}</p>
                @endforeach
                <div class="passage-note"><x-icon name="lightbulb" /><span>Saat membaca, cari kalimat yang menjadi <strong>bukti</strong> untuk setiap gagasan penting.</span></div>
            </div>
            <div class="passage-bottom"><span class="read-progress-label"><span class="read-progress-dot"></span> Progres membaca <strong data-read-progress>{{ $session->isCompleted() ? 100 : $session->progress_percent }}%</strong></span><div class="progress-track"><span style="width:{{ $session->isCompleted() ? 100 : $session->progress_percent }}%"></span></div>@if ($session->isCompleted())<button type="button" class="outline-button is-complete" disabled><span>Tugas Selesai & Terkunci</span><x-icon name="check" /></button>@else<button type="button" class="outline-button" data-complete-reading data-session-id="{{ $session->id }}" data-progress-url="{{ route('reading.progress') }}"><span data-complete-label>Selesai membaca</span><x-icon name="check" /></button>@endif</div>
        </article>

        <aside class="reading-side-column" aria-label="Panduan membaca">
            <section class="panel session-card">
                <div class="session-head"><div class="session-icon"><x-icon name="book-open-text" /></div><div><p class="section-overline">SESI MEMBACAMU</p><h2>{{ $document->title }}</h2></div></div>
                <div class="session-stat-row"><span>Status sesi</span><strong>{{ $session->isCompleted() ? 'Selesai & Terkunci' : $session->progress_percent . '% dibaca' }}</strong></div>
                <div class="session-stat-row"><span>Mulai membaca</span><strong>{{ $session->started_at->format('H:i') }}</strong></div>
                @if ($session->completed_at)
                    <div class="session-stat-row"><span>Waktu kumpul</span><strong>{{ $session->completed_at->format('H:i') }}</strong></div>
                @endif
                <div class="session-progress"><div class="progress-track"><span style="width:{{ $session->isCompleted() ? 100 : $session->progress_percent }}%"></span></div><span>{{ $session->isCompleted() ? 100 : $session->progress_percent }}%</span></div>

                @if ($session->isCompleted())
                    <a class="challenge-button session-cta !bg-emerald-600 hover:!bg-emerald-700" href="{{ route('challenge', ['session' => $session->id]) }}">
                        <x-icon name="lock" class="size-3.5" /> <span>Lihat hasil jawaban</span>
                    </a>
                @else
                    <a class="challenge-button session-cta" href="{{ route('challenge', ['session' => $session->id]) }}">
                        Lanjut ke pertanyaan <x-icon name="arrow-right" />
                    </a>
                @endif

                <div class="mt-4 pt-3 border-t border-slate-100 space-y-2">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">2 TANTANGAN EVALUASI</p>
                    <a class="flex items-center justify-between p-2.5 rounded-xl border border-indigo-200 bg-indigo-50/80 hover:bg-indigo-100 text-indigo-950 text-xs font-bold transition shadow-xs" href="{{ route('quiz.timed', ['document' => $document->id]) }}">
                        <div class="flex items-center gap-2">
                            <x-icon name="gamepad-2" class="w-4 h-4 text-indigo-600" />
                            <span>1. Kuis Interaktif ZEP (10 Soal Timer)</span>
                        </div>
                        <x-icon name="arrow-right" class="w-3.5 h-3.5 text-indigo-500" />
                    </a>
                    <a class="flex items-center justify-between p-2.5 rounded-xl border border-purple-200 bg-purple-50/80 hover:bg-purple-100 text-purple-950 text-xs font-bold transition shadow-xs" href="{{ route('challenge', ['session' => $session->id]) }}">
                        <div class="flex items-center gap-2">
                            <x-icon name="circle-help" class="w-4 h-4 text-purple-600" />
                            <span>2. Esai Sokratik (5 Soal)</span>
                        </div>
                        <x-icon name="arrow-right" class="w-3.5 h-3.5 text-purple-500" />
                    </a>
                </div>
            </section>
            <section class="panel focus-card"><p class="section-overline">CARA MEMBACA AKTIF</p><h2>Berhenti sejenak dan tanyakan…</h2><ul class="focus-list"><li><span>01</span><p>Apa gagasan utama bagian ini?</p></li><li><span>02</span><p>Kalimat mana yang mendukung gagasan itu?</p></li><li><span>03</span><p>Adakah sudut pandang lain yang perlu dipikirkan?</p></li></ul><div class="focus-tip"><x-icon name="lightbulb" /> Tidak perlu terburu-buru menemukan jawaban.</div></section>
            <section class="panel coach-panel" data-reading-coach data-endpoint="{{ route('reading.coach') }}" data-session-id="{{ $session->id }}" data-max-length="{{ config('ai.limits.message_characters', 1200) }}" aria-labelledby="coach-title">
                <div class="coach-heading"><div class="session-icon"><x-icon name="message-circle" /></div><div><p class="section-overline">DISKUSI SOCRATIC</p><h2 id="coach-title">Tanya Socrates</h2></div><span class="coach-status"><i></i> Tutor AI</span></div>
                <div class="coach-transcript" data-coach-transcript aria-live="polite" aria-label="Percakapan dengan tutor"><p class="coach-message coach-message-assistant">Halo! Bagian mana dari bacaan ini yang ingin kamu pahami lebih dalam?</p></div>
                <div class="coach-prompts" aria-label="Contoh pertanyaan"><button type="button" data-coach-prompt="Apa gagasan utama bacaan ini?">Gagasan utama</button><button type="button" data-coach-prompt="Bukti apa yang mendukung ide penting dalam bacaan?">Cari bukti</button></div>
                <form class="coach-form" data-coach-form><label class="sr-only" for="coach-message">Tulis pertanyaan atau pemikiranmu</label><textarea id="coach-message" name="message" rows="2" maxlength="{{ config('ai.limits.message_characters', 1200) }}" placeholder="Tulis pertanyaan atau pemikiranmu…" required data-coach-input></textarea><div class="coach-form-footer"><span data-coach-count>0 / {{ config('ai.limits.message_characters', 1200) }}</span><button type="submit" class="challenge-button" data-coach-send><span data-coach-send-label>Kirim</span><x-icon name="arrow-right" /></button></div></form>
                <p class="coach-error" role="alert" data-coach-error hidden></p>
                <p class="coach-footnote"><x-icon name="shield-check" /> AI membantu kamu berpikir; periksa kembali jawabannya dengan bacaan.</p>
            </section>
            <div class="privacy-note"><x-icon name="shield-check" /><span>Waktumu untuk membaca. Fokusmu yang utama.</span></div>
        </aside>
    </div>
    <div class="toast-message" role="status" aria-live="polite" data-reading-toast></div>
@endsection

@push('scripts')
<script>
(() => {
    const text = document.querySelector('[data-reading-text]');
    let fontSize = 15;
    document.querySelectorAll('[data-font-step]').forEach((button) => button.addEventListener('click', () => {
        fontSize = Math.min(20, Math.max(13, fontSize + Number(button.dataset.fontStep)));
        text.style.setProperty('--passage-font-size', `${fontSize}px`);
    }));
    const complete = document.querySelector('[data-complete-reading]');
    complete.addEventListener('click', async () => {
        complete.disabled = true;
        try {
            const response = await fetch(complete.dataset.progressUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                body: JSON.stringify({ session_id: Number(complete.dataset.sessionId), progress: 100 }),
            });
            if (!response.ok) throw new Error('Progres belum berhasil disimpan. Coba lagi.');
            const result = await response.json();
        } catch (error) {
            document.querySelector('[data-reading-toast]').textContent = error.message;
            complete.disabled = false;
            return;
        }
        complete.querySelector('[data-complete-label]').textContent = 'Tandai selesai';
        complete.classList.add('is-complete');
        document.querySelector('[data-read-progress]').textContent = '100%';
        document.querySelector('.passage-bottom .progress-track span').style.width = '100%';
        document.querySelector('[data-reading-toast]').textContent = result.xp_awarded ? `Bacaan selesai. +${result.xp_awarded} XP untukmu!` : 'Bacaan selesai. Siap menguji pemahamanmu?';
        window.setTimeout(() => { document.querySelector('[data-reading-toast]').textContent = ''; }, 3200);
    });
})();
</script>
@endpush
