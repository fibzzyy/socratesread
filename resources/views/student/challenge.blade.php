@extends('layouts.student')

@section('title', 'Socratic Challenge')
@section('breadcrumb', 'Socratic Challenge')

@section('content')
    <section class="stage-heading" aria-labelledby="challenge-title"><div><p class="eyebrow">LATIHAN BERPIKIR KRITIS</p><h1 id="challenge-title">Socratic Challenge</h1><p class="welcome-subtitle">Kembangkan jawabanmu dengan bukti, alasan, dan rasa ingin tahu.</p></div><a class="back-link" href="{{ route('reading', ['document' => $session->document_id]) }}"><x-icon name="arrow-left" /> <span>Kembali ke bacaan</span></a></section>

    <div class="mb-5 flex flex-wrap items-center gap-2 border-b border-slate-200/80 pb-3" role="tablist" aria-label="Mode Tantangan">
        <a href="{{ route('challenge', ['session' => $session->id]) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition {{ request()->routeIs('challenge') ? 'bg-purple-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200/80' }}" role="tab" aria-selected="true">
            <x-icon name="circle-help" class="w-4 h-4" />
            <span>1. Esai Sokratik (5 Soal)</span>
            @if ($isLocked)
                <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] {{ request()->routeIs('challenge') ? 'bg-purple-700/90 text-purple-100' : 'bg-slate-200 text-slate-700' }}">
                    <x-icon name="lock" class="w-2.5 h-2.5 mr-0.5" /> Selesai
                </span>
            @endif
        </a>
        <a href="{{ route('quiz.timed', ['document' => $session->document_id]) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition {{ request()->routeIs('quiz.timed*') ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200/80' }}" role="tab" aria-selected="false">
            <x-icon name="gamepad-2" class="w-4 h-4" />
            <span>2. Kuis Interaktif ZEP (10 Soal Timer)</span>
            <span class="ml-0.5 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] {{ request()->routeIs('quiz.timed*') ? 'bg-indigo-700/90 text-indigo-100' : 'bg-indigo-100 text-indigo-700' }}">60s/soal</span>
        </a>
    </div>

    @if ($isLocked)
        <div class="mb-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50/90 p-4 text-emerald-900 shadow-xs" role="alert">
            <div class="flex items-start sm:items-center gap-3">
                <div class="grid w-8 h-8 rounded-lg bg-emerald-200/70 text-emerald-800 place-items-center shrink-0 mt-0.5 sm:mt-0">
                    <x-icon name="lock" class="w-4 h-4" />
                </div>
                <div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-900">Tugas Telah Dikumpulkan & Terkunci</h2>
                    <p class="text-xs text-emerald-700 mt-0.5">Diselesaikan pada {{ $session->completed_at ? $session->completed_at->locale('id')->translatedFormat('d F Y, H:i') : 'sebelumnya' }}. Untuk menjaga integritas pengerjaan, jawaban telah disimpan permanen dan tidak dapat diubah lagi.</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1.5 self-start sm:self-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-200/60 text-emerald-800 shrink-0">
                <x-icon name="shield-check" class="w-3.5 h-3.5" /> Terkunci (Mode Review)
            </span>
        </div>
    @endif

    <section class="challenge-intro"><div class="challenge-intro-mark"><x-icon name="circle-help" /></div><div><p class="section-overline">BERDASARKAN BACAANMU</p><h2>{{ $session->document->title }}</h2><p>{{ $questions->count() }} pertanyaan · sekitar 5 menit</p></div><span class="challenge-points">{{ $isLocked ? 'Sudah Selesai' : 'Latihan membaca' }}</span></section>

    <div class="challenge-layout" data-challenge data-session-id="{{ $session->id }}" data-is-locked="{{ $isLocked ? '1' : '0' }}" data-answer-url="{{ route('challenge.answer') }}" data-complete-url="{{ route('challenge.complete') }}">
        <section class="panel question-panel" aria-labelledby="question-heading">
            <div class="question-topline"><span data-question-number>PERTANYAAN 1 DARI {{ $questions->count() }}</span><span class="question-skill" data-question-skill>{{ mb_strtoupper($questions->first()->skill) }}</span></div>
            <div class="question-progress" aria-hidden="true"><span data-question-progress style="width:{{ round(100 / $questions->count()) }}%"></span></div>
            <div class="question-body" data-question-body>
                <span class="question-quote-mark" aria-hidden="true">“</span>
                <h2 id="question-heading" data-question-text>{{ $questions->first()->prompt }}</h2>
                <p class="question-context">{{ $questions->first()->context }}</p>
            </div>
            <div class="answer-area" data-answer-area>
                <div class="flex items-center justify-between mb-1.5">
                    <label for="student-answer">{{ $isLocked ? 'Jawabanmu (Terkunci)' : 'Jawabanmu' }}</label>
                    @if ($isLocked)
                        <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-700 bg-emerald-100/60 px-2 py-0.5 rounded">
                            <x-icon name="check-circle" class="w-3 h-3 text-emerald-600" /> Tersimpan Permanen
                        </span>
                    @endif
                </div>
                <textarea id="student-answer" rows="5" maxlength="800" placeholder="{{ $isLocked ? 'Belum ada jawaban tersimpan.' : 'Tuliskan pemikiranmu di sini. Coba sertakan bukti dari bacaan…' }}" data-answer {{ $isLocked ? 'readonly disabled' : '' }} class="{{ $isLocked ? '!bg-slate-50 !text-slate-800 !cursor-not-allowed !border-slate-200 font-medium' : '' }}">{{ $savedAnswers->get($questions->first()->id) }}</textarea>
                <div class="answer-hint-row">
                    <button type="button" class="hint-button" data-hint-toggle aria-expanded="false"><x-icon name="lightbulb" /><span data-hint-label>Beri saya petunjuk</span></button>
                    @if (! $isLocked)
                        <span class="character-count"><span data-count>0</span>/800</span>
                    @else
                        <span class="text-[10px] text-slate-500 font-medium">Mode Hanya Baca</span>
                    @endif
                </div>
                <div class="hint-box" hidden data-hint>{{ $questions->first()->hint }}</div>
                <p class="answer-error" role="alert" data-answer-error hidden>Tuliskan jawaban singkatmu sebelum melanjutkan.</p>
            </div>
            <div class="question-actions">
                <div class="flex items-center gap-2">
                    <a class="text-link" href="{{ route('reading', ['document' => $session->document_id]) }}">Kembali membaca</a>
                    <button class="outline-button" type="button" data-prev-question hidden><x-icon name="arrow-left" /> <span>Pertanyaan sebelumnya</span></button>
                </div>
                <div class="flex items-center gap-2">
                    @if ($isLocked)
                        <a class="outline-button" href="{{ route('dashboard') }}">Dashboard</a>
                    @endif
                    <button class="challenge-button" type="button" data-next-question>
                        <span data-next-label>{{ $isLocked ? ($questions->count() > 1 ? 'Pertanyaan berikutnya' : 'Selesai meninjau') : ($questions->count() > 1 ? 'Lanjutkan' : 'Selesaikan') }}</span>
                        <x-icon name="arrow-right" />
                    </button>
                </div>
            </div>
            <div class="challenge-complete" data-complete hidden><span class="complete-mark"><x-icon name="check" /></span><p class="section-overline">TANTANGAN SELESAI</p><h2>Kamu sudah berpikir lebih dalam.</h2><p>Jawabanmu telah tersimpan permanen dan terkunci untuk penilaian guru.</p><a class="challenge-button" href="{{ route('progress') }}">Lihat progress <x-icon name="arrow-right" /></a></div>
        </section>
        <aside class="challenge-side"><section class="panel thinking-panel"><p class="section-overline">KERANGKA BERPIKIR</p><h2>Buat jawabanmu kuat</h2><div class="thinking-step"><span class="thinking-number">01</span><div><strong>Sampaikan gagasan</strong><p>Apa yang kamu pikirkan?</p></div></div><div class="thinking-step"><span class="thinking-number">02</span><div><strong>Temukan bukti</strong><p>Bagian mana yang mendukungnya?</p></div></div><div class="thinking-step"><span class="thinking-number">03</span><div><strong>Jelaskan alasan</strong><p>Mengapa bukti itu penting?</p></div></div><div class="thinking-note"><x-icon name="triangle-alert" /><span>Di latihan ini, kita berlatih menjelaskan pemikiran—bukan mencari satu jawaban sempurna.</span></div></section><section class="panel challenge-session"><span class="session-mini-icon"><x-icon name="clock" /></span><span><strong>Waktu latihan</strong><small>5 menit · santai saja</small></span></section></aside>
    </div>
    <div class="toast-message" role="status" aria-live="polite" data-challenge-toast></div>
@endsection

@push('scripts')
<script>
(() => {
    const questions = @json($challengeQuestions);
    const savedAnswers = @json($savedAnswers);
    const isLocked = Boolean(@json($isLocked));
    let current = 0;
    const form = document.querySelector('[data-challenge]');
    const answer = form.querySelector('[data-answer]');
    const hintButton = form.querySelector('[data-hint-toggle]');
    const hint = form.querySelector('[data-hint]');
    const prevButton = form.querySelector('[data-prev-question]');
    const nextButton = form.querySelector('[data-next-question]');
    const countEl = form.querySelector('[data-count]');

    if (countEl) countEl.textContent = answer.value.length;
    if (!isLocked) {
        answer.addEventListener('input', () => {
            if (countEl) countEl.textContent = answer.value.length;
            form.querySelector('[data-answer-error]').hidden = true;
        });
    }

    hintButton.addEventListener('click', () => {
        const opening = hint.hidden;
        hint.hidden = !opening;
        hintButton.setAttribute('aria-expanded', String(opening));
        hintButton.querySelector('[data-hint-label]').textContent = opening ? 'Sembunyikan petunjuk' : 'Beri saya petunjuk';
    });

    const updateQuestionView = (idx) => {
        current = idx;
        const question = questions[current];
        form.querySelector('[data-question-number]').textContent = `PERTANYAAN ${current + 1} DARI ${questions.length}`;
        form.querySelector('[data-question-skill]').textContent = question.skill.toLocaleUpperCase('id-ID');
        form.querySelector('[data-question-text]').textContent = question.prompt;
        form.querySelector('.question-context').textContent = question.context || '';
        form.querySelector('[data-question-progress]').style.width = `${Math.round(((current + 1) / questions.length) * 100)}%`;
        hint.textContent = question.hint || '';
        hint.hidden = true;
        hintButton.setAttribute('aria-expanded', 'false');
        hintButton.querySelector('[data-hint-label]').textContent = 'Beri saya petunjuk';
        answer.value = savedAnswers[String(question.id)] || '';
        if (countEl) countEl.textContent = String(answer.value.length);

        if (prevButton) {
            prevButton.hidden = current === 0;
        }

        if (isLocked) {
            nextButton.disabled = false;
            nextButton.querySelector('[data-next-label]').textContent = current === questions.length - 1 ? 'Selesai meninjau' : 'Pertanyaan berikutnya';
        } else {
            nextButton.disabled = false;
            nextButton.querySelector('[data-next-label]').textContent = current === questions.length - 1 ? 'Selesaikan' : 'Lanjutkan';
        }
    };

    if (prevButton) {
        prevButton.addEventListener('click', () => {
            if (current > 0) {
                updateQuestionView(current - 1);
            }
        });
    }

    nextButton.addEventListener('click', async () => {
        if (isLocked) {
            if (current >= questions.length - 1) {
                window.location.href = "{{ route('dashboard') }}";
                return;
            }
            updateQuestionView(current + 1);
            return;
        }

        if (!answer.value.trim()) {
            form.querySelector('[data-answer-error]').hidden = false;
            answer.focus();
            return;
        }

        nextButton.disabled = true;
        nextButton.querySelector('[data-next-label]').textContent = 'Menyimpan…';
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const send = (url, payload) => fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        });

        try {
            const saveResponse = await send(form.dataset.answerUrl, {
                session_id: Number(form.dataset.sessionId),
                question_id: questions[current].id,
                answer: answer.value.trim()
            });
            if (!saveResponse.ok) {
                const errData = await saveResponse.json().catch(() => ({}));
                throw new Error(errData.message || 'Jawaban belum tersimpan. Periksa koneksi lalu coba lagi.');
            }
            savedAnswers[String(questions[current].id)] = answer.value.trim();

            if (current >= questions.length - 1) {
                const finishResponse = await send(form.dataset.completeUrl, {
                    session_id: Number(form.dataset.sessionId)
                });
                if (!finishResponse.ok) {
                    const errData = await finishResponse.json().catch(() => ({}));
                    throw new Error(errData.message || 'Tantangan belum dapat diselesaikan. Coba lagi.');
                }
                const finishData = await finishResponse.json();
                form.querySelector('[data-answer-area]').hidden = true;
                form.querySelector('.question-actions').hidden = true;
                form.querySelector('.question-body').hidden = true;
                form.querySelector('.question-topline').hidden = true;
                form.querySelector('.question-progress').hidden = true;
                const completeEl = form.querySelector('[data-complete]');
                completeEl.hidden = false;
                completeEl.querySelector('h2').textContent = finishData.xp_awarded ? `Tantangan selesai! (+${finishData.xp_awarded} XP)` : 'Tantangan selesai!';
                completeEl.querySelector('p').textContent = 'Jawabanmu telah tersimpan permanen dan terkunci untuk penilaian guru.';
                return;
            }
        } catch (error) {
            const errorMessage = form.querySelector('[data-answer-error]');
            errorMessage.textContent = error.message;
            errorMessage.hidden = false;
            nextButton.disabled = false;
            nextButton.querySelector('[data-next-label]').textContent = current === questions.length - 1 ? 'Selesaikan' : 'Lanjutkan';
            return;
        }

        updateQuestionView(current + 1);
        answer.focus();
    });
})();
</script>
@endpush
