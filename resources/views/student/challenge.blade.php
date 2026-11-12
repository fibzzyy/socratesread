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
                    <label for="student-answer" class="text-xs font-bold text-slate-700 uppercase tracking-wider">{{ $isLocked ? 'Jawaban Anda (Terkunci)' : 'Jawaban Anda' }}</label>
                    @if ($isLocked)
                        <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-700 bg-emerald-100/60 px-2 py-0.5 rounded">
                            <x-icon name="check-circle" class="w-3 h-3 text-emerald-600" /> Tersimpan Permanen
                        </span>
                    @endif
                </div>
                <textarea id="student-answer" rows="5" maxlength="800" placeholder="{{ $isLocked ? 'Belum ada jawaban tersimpan.' : 'Tuliskan pemikiranmu di sini sepahammu. Coba sertakan bukti dari bacaan…' }}" data-answer {{ $isLocked ? 'readonly disabled' : '' }} class="{{ $isLocked ? '!bg-slate-50 !text-slate-800 !cursor-not-allowed !border-slate-200 font-medium' : '' }}">{{ $savedAnswers->get($questions->first()->id) }}</textarea>
                <div id="student-answer-preview" data-answer-preview hidden class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs sm:text-sm leading-relaxed text-slate-700 whitespace-pre-line font-normal"></div>
                <div class="answer-hint-row">
                    <button type="button" class="hint-button" data-hint-toggle aria-expanded="false"><x-icon name="lightbulb" /><span data-hint-label>Beri saya petunjuk</span></button>
                    @if (! $isLocked)
                        <span class="character-count"><span data-count>0</span>/800</span>
                    @else
                        <span class="text-[10px] text-slate-500 font-medium">Mode Review / Terkunci</span>
                    @endif
                </div>
                <div class="hint-box" hidden data-hint>{{ $questions->first()->hint }}</div>
                <p class="answer-error" role="alert" data-answer-error hidden>Tuliskan jawaban singkatmu sebelum memeriksa.</p>

                <!-- Review / Analisis Jawaban AI (NotebookLM Style) -->
                <div class="evaluation-review-box mt-4 rounded-2xl border p-4 sm:p-5 transition-all duration-300" data-eval-box hidden>
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2.5">
                        <div class="inline-flex items-center gap-1.5 text-xs font-bold" data-eval-badge>
                            <!-- Badge diisi oleh JavaScript -->
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-black px-2.5 py-0.5 rounded-full" data-eval-score-text>Skor: 0 / 100</span>
                        </div>
                    </div>

                    <!-- Teks Feedback / Analisis AI -->
                    <p class="text-xs sm:text-sm leading-relaxed font-medium" data-eval-feedback-text></p>

                    <!-- Poin Konsep yang Belum Lengkap -->
                    <div class="mt-3 pt-3 border-t border-slate-200/70 text-xs" data-eval-missing-box hidden>
                        <strong class="font-bold text-[11px] uppercase tracking-wider block mb-1">Poin yang Perlu Diperdalam:</strong>
                        <ul class="list-disc list-inside space-y-1 text-slate-700" data-eval-missing-list></ul>
                    </div>

                    <!-- Petunjuk Perbaikan -->
                    <div class="mt-2.5 pt-2.5 border-t border-slate-200/70 text-xs" data-eval-hint-box hidden>
                        <strong class="font-bold text-[11px] uppercase tracking-wider block mb-0.5">Petunjuk Perbaikan:</strong>
                        <p class="italic text-slate-700" data-eval-hint-text></p>
                    </div>

                    <!-- Kunci Jawaban yang Benar Berdasarkan Materi (Tampil saat jawaban salah / belum tuntas) -->
                    <div class="mt-2.5 pt-2.5 border-t border-slate-200/70 text-xs" data-eval-model-box hidden>
                        <strong class="font-bold text-[11px] uppercase tracking-wider block mb-0.5 text-slate-800">Kunci Jawaban yang Benar Berdasarkan Materi:</strong>
                        <p class="text-slate-700 font-medium leading-relaxed" data-eval-model-text></p>
                    </div>
                </div>
            </div>
            <div class="question-actions">
                <div class="flex items-center gap-2">
                    <a class="text-link" href="{{ route('reading', ['document' => $session->document_id]) }}">Kembali membaca</a>
                    <button class="outline-button" type="button" data-prev-question hidden><x-icon name="arrow-left" /> <span>Pertanyaan sebelumnya</span></button>
                </div>
                <div class="flex items-center gap-2">
                    @if ($isLocked)
                        <a class="outline-button" href="{{ route('dashboard') }}">Dashboard</a>
                    @else
                        <button class="outline-button" type="button" data-edit-btn hidden>
                            <x-icon name="pencil" class="w-3.5 h-3.5" />
                            <span>Ubah Jawaban</span>
                        </button>
                    @endif
                    <button class="challenge-button" type="button" data-action-btn>
                        <span data-action-label>{{ $isLocked ? ($questions->count() > 1 ? 'Pertanyaan berikutnya' : 'Selesai meninjau') : 'Periksa Jawaban' }}</span>
                        <x-icon name="arrow-right" data-action-icon />
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
    const savedEvaluations = @json($savedEvaluations ?? []);
    const isLocked = Boolean(@json($isLocked));
    let current = 0;

    const form = document.querySelector('[data-challenge]');
    const answer = form.querySelector('[data-answer]');
    const answerPreview = form.querySelector('[data-answer-preview]');
    const hintButton = form.querySelector('[data-hint-toggle]');
    const hint = form.querySelector('[data-hint]');
    const prevButton = form.querySelector('[data-prev-question]');
    const editButton = form.querySelector('[data-edit-btn]');
    const actionButton = form.querySelector('[data-action-btn]');
    const actionLabel = form.querySelector('[data-action-label]');
    const countEl = form.querySelector('[data-count]');
    const errorEl = form.querySelector('[data-answer-error]');

    // Evaluation review box elements
    const evalBox = form.querySelector('[data-eval-box]');
    const evalBadge = form.querySelector('[data-eval-badge]');
    const evalScoreText = form.querySelector('[data-eval-score-text]');
    const evalFeedbackText = form.querySelector('[data-eval-feedback-text]');
    const evalMissingBox = form.querySelector('[data-eval-missing-box]');
    const evalMissingList = form.querySelector('[data-eval-missing-list]');
    const evalHintBox = form.querySelector('[data-eval-hint-box]');
    const evalHintText = form.querySelector('[data-eval-hint-text]');
    const evalModelBox = form.querySelector('[data-eval-model-box]');
    const evalModelText = form.querySelector('[data-eval-model-text]');

    if (countEl) countEl.textContent = answer.value.length;
    if (!isLocked) {
        answer.addEventListener('input', () => {
            if (countEl) countEl.textContent = answer.value.length;
            errorEl.hidden = true;
        });
    }

    hintButton.addEventListener('click', () => {
        const opening = hint.hidden;
        hint.hidden = !opening;
        hintButton.setAttribute('aria-expanded', String(opening));
        hintButton.querySelector('[data-hint-label]').textContent = opening ? 'Sembunyikan petunjuk' : 'Beri saya petunjuk';
    });

    const renderEvaluation = (evalData) => {
        if (!evalData) {
            evalBox.hidden = true;
            return;
        }

        evalBox.hidden = false;

        const score = Number(evalData.score ?? (evalData.evaluation && evalData.evaluation.score) ?? 0);
        const rawLevel = String(evalData.understanding_level || (evalData.evaluation && evalData.evaluation.understanding_level) || '');
        const feedback = String(evalData.feedback || (evalData.evaluation && evalData.evaluation.feedback) || 'Jawaban Anda telah dievaluasi berdasarkan materi bacaan.');
        const missing = (evalData.missing_concepts || (evalData.evaluation && evalData.evaluation.missing_concepts) || []);
        const hintText = evalData.hint || (evalData.evaluation && evalData.evaluation.hint) || '';
        const levelLower = rawLevel.toLowerCase();

        const isWrong = score < 40 || levelLower.includes('salah') || levelLower.includes('belum tepat');
        const isAlmost = !isWrong && (score < 70 || levelLower.includes('hampir') || levelLower.includes('cukup'));

        let boxClasses = '';
        let badgeHtml = '';
        let scoreClass = '';

        if (!isWrong && !isAlmost) {
            boxClasses = 'border-emerald-300 bg-emerald-50/80 text-emerald-950';
            scoreClass = 'bg-emerald-100 text-emerald-800';
            badgeHtml = `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800"><svg class="w-3.5 h-3.5 mr-1 text-emerald-600 stroke-[3]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>✓ Benar / Tepat & Sesuai</span>`;
        } else if (isAlmost) {
            boxClasses = 'border-amber-300 bg-amber-50/80 text-amber-950';
            scoreClass = 'bg-amber-100 text-amber-900';
            badgeHtml = `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-900"><svg class="w-3.5 h-3.5 mr-1 text-amber-600 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>⚠ Hampir Benar / Perlu Perbaikan</span>`;
        } else {
            boxClasses = 'border-rose-300 bg-rose-50/80 text-rose-950';
            scoreClass = 'bg-rose-100 text-rose-900';
            badgeHtml = `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-900"><svg class="w-3.5 h-3.5 mr-1 text-rose-600 stroke-[3]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>✕ Salah / Belum Tepat</span>`;
        }

        evalBox.className = `evaluation-review-box mt-4 rounded-2xl border p-4 sm:p-5 transition-all duration-300 ${boxClasses}`;
        evalBadge.innerHTML = badgeHtml;
        evalScoreText.className = `text-xs font-black px-2.5 py-0.5 rounded-full ${scoreClass}`;
        evalScoreText.textContent = `Skor: ${score} / 100`;
        evalFeedbackText.textContent = feedback;

        if (Array.isArray(missing) && missing.length > 0) {
            evalMissingList.innerHTML = missing.map(c => `<li>${c}</li>`).join('');
            evalMissingBox.hidden = false;
        } else {
            evalMissingBox.hidden = true;
        }

        if (hintText && String(hintText).trim().length > 0) {
            evalHintText.textContent = String(hintText).trim();
            evalHintBox.hidden = false;
        } else {
            evalHintBox.hidden = true;
        }

        const qObj = questions[current] || {};
        const modelAns = String(qObj.hint || '').trim();
        if (evalModelBox && evalModelText && (isWrong || isAlmost) && modelAns.length > 0) {
            evalModelText.textContent = modelAns;
            evalModelBox.hidden = false;
        } else if (evalModelBox) {
            evalModelBox.hidden = true;
        }

        const spans = evalData.matched_spans || (evalData.evaluation && evalData.evaluation.matched_spans) || [];
        syncAnswerPreview(!isWrong || isAlmost, spans);
    };

    const escapeHtml = (str) => String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const formatHighlightedAnswerHtml = (rawText, spans, allowHighlight) => {
        if (!allowHighlight || !Array.isArray(spans) || spans.length === 0 || !rawText) {
            return escapeHtml(rawText || '(Belum ada jawaban tersimpan.)');
        }
        const lowerText = rawText.toLowerCase();
        const ranges = [];
        spans.forEach((sp) => {
            const cleanSpan = String(sp || '').trim();
            if (!cleanSpan) return;
            const pos = lowerText.indexOf(cleanSpan.toLowerCase());
            if (pos === -1) return;
            const end = pos + cleanSpan.length;
            const overlaps = ranges.some((r) => !(end <= r.start || pos >= r.end));
            if (!overlaps) ranges.push({ start: pos, end });
        });
        if (ranges.length === 0) return escapeHtml(rawText);
        ranges.sort((a, b) => a.start - b.start);

        let html = '';
        let cursor = 0;
        ranges.forEach((r) => {
            if (r.start > cursor) html += escapeHtml(rawText.slice(cursor, r.start));
            html += `<strong class="font-bold text-slate-950">${escapeHtml(rawText.slice(r.start, r.end))}</strong>`;
            cursor = r.end;
        });
        if (cursor < rawText.length) html += escapeHtml(rawText.slice(cursor));
        return html;
    };

    const syncAnswerPreview = (allowHighlight, spans) => {
        if (!answerPreview) return;
        const showPreview = isLocked || answer.readOnly;
        if (showPreview && answer.value.trim().length > 0) {
            answerPreview.innerHTML = formatHighlightedAnswerHtml(answer.value.trim(), spans, allowHighlight);
            answerPreview.hidden = false;
            answer.hidden = true;
        } else {
            answerPreview.hidden = true;
            answer.hidden = false;
        }
    };

    const updateQuestionView = (idx) => {
        current = idx;
        const q = questions[current];
        const qId = String(q.id);

        form.querySelector('[data-question-number]').textContent = `PERTANYAAN ${current + 1} DARI ${questions.length}`;
        form.querySelector('[data-question-skill]').textContent = q.skill.toLocaleUpperCase('id-ID');
        form.querySelector('[data-question-text]').textContent = q.prompt;
        form.querySelector('.question-context').textContent = q.context || '';
        form.querySelector('[data-question-progress]').style.width = `${Math.round(((current + 1) / questions.length) * 100)}%`;

        hint.textContent = q.hint || '';
        hint.hidden = true;
        hintButton.setAttribute('aria-expanded', 'false');
        hintButton.querySelector('[data-hint-label]').textContent = 'Beri saya petunjuk';

        answer.value = savedAnswers[qId] || '';
        if (countEl) countEl.textContent = String(answer.value.length);
        errorEl.hidden = true;

        if (prevButton) {
            prevButton.hidden = current === 0;
        }

        const existingEval = savedEvaluations[qId] || null;

        if (isLocked) {
            answer.readOnly = true;
            answer.disabled = true;
            if (editButton) editButton.hidden = true;
            renderEvaluation(existingEval);
            if (!existingEval) {
                syncAnswerPreview(false, []);
            }
            actionButton.disabled = false;
            actionLabel.textContent = current === questions.length - 1 ? 'Selesai meninjau' : 'Pertanyaan berikutnya';
        } else {
            if (existingEval) {
                answer.readOnly = true;
                answer.classList.add('!bg-slate-50');
                renderEvaluation(existingEval);
                if (editButton) editButton.hidden = false;
                actionButton.disabled = false;
                actionLabel.textContent = current === questions.length - 1 ? 'Selesaikan Tantangan' : 'Pertanyaan Berikutnya';
            } else {
                answer.readOnly = false;
                answer.disabled = false;
                answer.classList.remove('!bg-slate-50');
                renderEvaluation(null);
                if (answerPreview) answerPreview.hidden = true;
                answer.hidden = false;
                if (editButton) editButton.hidden = true;
                actionButton.disabled = false;
                actionLabel.textContent = 'Periksa Jawaban';
            }
        }
    };

    if (prevButton) {
        prevButton.addEventListener('click', () => {
            if (current > 0) {
                updateQuestionView(current - 1);
            }
        });
    }

    if (editButton) {
        editButton.addEventListener('click', () => {
            answer.readOnly = false;
            answer.disabled = false;
            answer.hidden = false;
            if (answerPreview) answerPreview.hidden = true;
            answer.classList.remove('!bg-slate-50');
            answer.focus();
            evalBox.hidden = true;
            editButton.hidden = true;
            actionLabel.textContent = 'Periksa Jawaban';
        });
    }

    actionButton.addEventListener('click', async () => {
        const q = questions[current];
        const qId = String(q.id);

        if (isLocked) {
            if (current >= questions.length - 1) {
                window.location.href = "{{ route('dashboard') }}";
                return;
            }
            updateQuestionView(current + 1);
            return;
        }

        const existingEval = savedEvaluations[qId] || null;
        const isCurrentlyReviewed = existingEval && evalBox.hidden === false;

        // If the student already reviewed this question, move to the next or complete
        if (isCurrentlyReviewed) {
            if (current >= questions.length - 1) {
                actionButton.disabled = true;
                actionLabel.textContent = 'Menyelesaikan…';
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const send = (url, payload) => fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify(payload)
                });

                try {
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
                } catch (err) {
                    errorEl.textContent = err.message;
                    errorEl.hidden = false;
                    actionButton.disabled = false;
                    actionLabel.textContent = 'Selesaikan Tantangan';
                    return;
                }
            } else {
                updateQuestionView(current + 1);
                return;
            }
        }

        // Student is submitting/checking an answer
        if (!answer.value.trim()) {
            errorEl.textContent = 'Tuliskan jawaban singkatmu sebelum memeriksa.';
            errorEl.hidden = false;
            answer.focus();
            return;
        }

        actionButton.disabled = true;
        actionLabel.textContent = 'Menganalisis Jawaban…';
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const send = (url, payload) => fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        });

        try {
            const saveResponse = await send(form.dataset.answerUrl, {
                session_id: Number(form.dataset.sessionId),
                question_id: q.id,
                answer: answer.value.trim()
            });

            if (!saveResponse.ok) {
                const errData = await saveResponse.json().catch(() => ({}));
                throw new Error(errData.message || 'Jawaban belum tersimpan. Periksa koneksi lalu coba lagi.');
            }

            const data = await saveResponse.json();
            savedAnswers[qId] = answer.value.trim();
            savedEvaluations[qId] = data.evaluation;

            answer.readOnly = true;
            answer.classList.add('!bg-slate-50');
            renderEvaluation(data.evaluation);
            if (editButton) editButton.hidden = false;

            actionButton.disabled = false;
            actionLabel.textContent = current === questions.length - 1 ? 'Selesaikan Tantangan' : 'Pertanyaan Berikutnya';
        } catch (error) {
            errorEl.textContent = error.message;
            errorEl.hidden = false;
            actionButton.disabled = false;
            actionLabel.textContent = 'Periksa Jawaban';
        }
    });

    // Initial render
    updateQuestionView(0);
})();
</script>
@endpush
