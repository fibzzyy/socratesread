@extends('layouts.teacher')

@section('title', 'Kelola Bacaan')
@section('breadcrumb', 'Kelola bacaan')

@section('content')
    <section class="stage-heading" aria-labelledby="teacher-documents-title">
        <div><p class="eyebrow">MATERI UNTUK BERPIKIR LEBIH DALAM</p><h1 id="teacher-documents-title">Kelola bacaan</h1><p class="welcome-subtitle">Unggah PDF berbasis teks untuk menjadi bahan Reading Room siswa.</p></div>
    </section>

    @if (session('status')) <div class="auth-notice" role="status">{{ session('status') }}</div> @endif

    <section class="panel teacher-upload-panel" aria-labelledby="upload-heading">
        <div class="section-heading"><div><p class="section-overline">TAMBAHKAN MATERI</p><h2 id="upload-heading">Unggah dokumen PDF</h2></div><span class="session-icon"><x-icon name="book-open-text"/></span></div>
        <form method="POST" action="{{ route('teacher.documents.store') }}" enctype="multipart/form-data" class="teacher-upload-form">
            @csrf
            <div class="upload-fields-row">
                <div class="upload-field-group"><label for="document-title">Judul bacaan</label><input id="document-title" name="title" class="upload-field" type="text" maxlength="160" value="{{ old('title') }}" placeholder="Contoh: Ekosistem pesisir" required>@error('title')<small class="upload-field-error">{{ $message }}</small>@enderror</div>
                <div class="upload-field-group">
                    <label for="document-category">Mata Pelajaran / Kelas</label>
                    @if ($teacherClasses->isNotEmpty())
                        <select id="document-category" name="category" class="upload-field bg-white" required>
                            <option value="" disabled {{ old('category') ? '' : 'selected' }}>Pilih Mata Pelajaran / Kelas</option>
                            @foreach ($teacherClasses as $class)
                                <option value="{{ $class->name }}" {{ old('category') === $class->name ? 'selected' : '' }} data-target-grade="{{ $class->target_grade ?? 'all' }}">
                                    {{ $class->name }} @if($class->target_grade) (Kelas {{ $class->target_grade }}) @else (Semua Tingkat) @endif
                                </option>
                            @endforeach
                        </select>
                    @else
                        <input id="document-category" name="category" class="upload-field" type="text" maxlength="80" value="{{ old('category') }}" placeholder="Contoh: Bahasa Indonesia / IPS" required>
                        <small class="text-[11px] text-slate-500 mt-1">
                            Belum ada kelas. Anda bisa membuat kelas di menu <a href="{{ route('teacher.classrooms') }}" class="text-blue-600 underline font-semibold">Kelas Saya</a>.
                        </small>
                    @endif
                    @error('category')<small class="upload-field-error">{{ $message }}</small>@enderror
                </div>
                <div class="upload-field-group">
                    <label for="document-target-grade">Peruntukan Tingkat</label>
                    <select id="document-target-grade" name="target_grade" class="upload-field bg-white">
                        <option value="all" {{ old('target_grade', 'all') === 'all' ? 'selected' : '' }}>Semua Kelas (Umum)</option>
                        <option value="10" {{ old('target_grade') === '10' ? 'selected' : '' }}>Khusus Kelas 10</option>
                        <option value="11" {{ old('target_grade') === '11' ? 'selected' : '' }}>Khusus Kelas 11</option>
                        <option value="12" {{ old('target_grade') === '12' ? 'selected' : '' }}>Khusus Kelas 12</option>
                    </select>
                    @error('target_grade')<small class="upload-field-error">{{ $message }}</small>@enderror
                </div>
            </div>
            <div class="upload-field-group"><label for="document-file">File PDF</label><input id="document-file" name="file" class="upload-file-input" type="file" accept="application/pdf,.pdf" required><small class="upload-help">Maksimal 50 MB tanpa batasan karakter. Pastikan PDF memiliki teks yang dapat dipilih (bukan hasil scan foto tanpa OCR).</small>@error('file')<small class="upload-field-error">{{ $message }}</small>@enderror</div>
            <div class="teacher-upload-footer">
                <p><x-icon name="sparkles"/> AI otomatis merapikan paragraf materi & merumuskan 3 tantangan Sokratik (HOTS).</p>
                <button class="challenge-button" type="submit">
                    <span>Proses dengan AI</span>
                    <x-icon name="arrow-right"/>
                </button>
            </div>
        </form>
    </section>

    <section class="panel teacher-documents-panel" aria-labelledby="teacher-documents-list-title">
        <div class="section-heading">
            <div>
                <p class="section-overline">KOLEKSI GURU</p>
                <h2 id="teacher-documents-list-title">Bacaan yang diunggah</h2>
            </div>
            <span class="document-count">{{ $documents->count() }} dokumen</span>
        </div>

        <!-- Filter Tab Jenjang -->
        <div class="mb-4 flex flex-wrap items-center gap-2 border-b border-slate-100 pb-3">
            <a href="{{ route('teacher.documents', ['grade' => 'all']) }}" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $selectedGrade === 'all' ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                <span>Semua Tingkat</span>
                <span class="rounded-full px-1.5 py-0.2 text-[10px] {{ $selectedGrade === 'all' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700' }}">{{ $gradeCounts['all'] }}</span>
            </a>
            <a href="{{ route('teacher.documents', ['grade' => '10']) }}" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $selectedGrade === '10' ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                <span>Kelas 10</span>
                <span class="rounded-full px-1.5 py-0.2 text-[10px] {{ $selectedGrade === '10' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700' }}">{{ $gradeCounts['10'] }}</span>
            </a>
            <a href="{{ route('teacher.documents', ['grade' => '11']) }}" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $selectedGrade === '11' ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                <span>Kelas 11</span>
                <span class="rounded-full px-1.5 py-0.2 text-[10px] {{ $selectedGrade === '11' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700' }}">{{ $gradeCounts['11'] }}</span>
            </a>
            <a href="{{ route('teacher.documents', ['grade' => '12']) }}" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $selectedGrade === '12' ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                <span>Kelas 12</span>
                <span class="rounded-full px-1.5 py-0.2 text-[10px] {{ $selectedGrade === '12' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700' }}">{{ $gradeCounts['12'] }}</span>
            </a>
        </div>

        @forelse ($documents as $document)
            <article class="teacher-document-row">
                <span class="teacher-document-icon"><x-icon name="book-open-text"/></span>
                <div class="teacher-document-copy">
                    <div class="flex items-center gap-2 flex-wrap">
                        <strong>{{ $document->title }}</strong>
                        <span class="rounded bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 border border-emerald-200/60 inline-flex items-center gap-1">
                            <x-icon name="sparkles" class="w-3 h-3 text-emerald-600"/>
                            {{ $document->questions_count }} Esai
                        </span>
                        <span class="rounded bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700 border border-blue-200/60 inline-flex items-center gap-1">
                            <x-icon name="check-square" class="w-3 h-3 text-blue-600"/>
                            {{ $document->quiz_questions_count }} PG (Timer & Latihan)
                        </span>
                    </div>
                    <span>{{ $document->category }} · <span class="rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-semibold text-blue-700">{{ $document->targetGradeLabel() }}</span> · {{ number_format(mb_strlen($document->body)) }} karakter · {{ $document->created_at->locale('id')->translatedFormat('d M Y') }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition" data-preview-btn data-doc-title="{{ $document->title }}" data-doc-category="{{ $document->category }}" data-doc-grade="{{ $document->targetGradeLabel() }}" data-doc-excerpt="{{ $document->excerpt }}" data-doc-body="{{ e($document->body) }}" data-doc-questions='@json($document->questions)' data-doc-quiz='@json($document->quizQuestions)' title="Pratinjau Materi & Soal">
                        <x-icon name="eye" class="w-3.5 h-3.5 text-slate-500"/>
                        <span class="hidden sm:inline">Pratinjau & Soal</span>
                    </button>
                    <form method="POST" action="{{ route('teacher.documents.destroy', $document) }}" onsubmit="return confirm('Hapus bacaan ini secara permanen? Sesi baca, jawaban siswa, dan XP terkait juga akan dihapus. Tindakan ini tidak dapat dibatalkan.')">
                        @csrf @method('DELETE')
                        <button type="submit" class="teacher-document-delete" aria-label="Hapus bacaan {{ $document->title }}">
                            <x-icon name="trash-2"/>
                            <span>Hapus</span>
                        </button>
                    </form>
                </div>
            </article>
        @empty
            <div class="teacher-empty-state">
                <span class="session-icon"><x-icon name="book-open-text"/></span>
                @if ($selectedGrade !== 'all')
                    <strong>Belum ada bacaan untuk Kelas {{ $selectedGrade }}</strong>
                    <p>Unggah materi baru dengan peruntukan Kelas {{ $selectedGrade }} di form atas.</p>
                @else
                    <strong>Belum ada bacaan</strong>
                    <p>Dokumen yang diproses akan muncul di sini dan tersedia untuk siswa.</p>
                @endif
            </div>
        @endforelse
    </section>

    <!-- Drawer Pratinjau Dokumen & Soal Sokratik -->
    <div id="docPreviewModal" class="hidden fixed inset-0 z-50 overflow-hidden" role="dialog" aria-modal="true" aria-labelledby="modalDocTitle">
        <div id="docModalBackdrop" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs transition-opacity"></div>
        <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
            <div id="docModalPanel" class="w-screen max-w-lg md:max-w-xl bg-white shadow-2xl flex flex-col transform transition ease-in-out duration-300">
                <!-- Header Drawer -->
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 bg-slate-50/80">
                    <div class="min-w-0 pr-4">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="rounded bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700 uppercase tracking-wider" id="modalDocCategory">KATEGORI</span>
                            <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600" id="modalDocGrade">TINGKAT</span>
                        </div>
                        <h2 id="modalDocTitle" class="text-base font-bold text-slate-900 truncate">Pratinjau Materi</h2>
                    </div>
                    <button type="button" id="closeDocModal" class="rounded-lg p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-200/60 transition" aria-label="Tutup pratinjau">
                        <x-icon name="arrow-right" class="size-4" />
                    </button>
                </div>

                <!-- Navigation Tabs -->
                <div class="flex border-b border-slate-200 bg-white px-6 overflow-x-auto">
                    <button type="button" class="border-b-2 border-blue-600 px-3 py-2.5 text-xs font-bold text-blue-600 whitespace-nowrap transition" id="tabDocTextBtn">
                        Teks Bacaan (Kurasi AI)
                    </button>
                    <button type="button" class="border-b-2 border-transparent px-3 py-2.5 text-xs font-semibold text-slate-500 hover:text-slate-700 whitespace-nowrap transition" id="tabDocQuizBtn">
                        10 Pilihan Ganda (<span id="modalQuizCount">10</span>)
                    </button>
                    <button type="button" class="border-b-2 border-transparent px-3 py-2.5 text-xs font-semibold text-slate-500 hover:text-slate-700 whitespace-nowrap transition" id="tabDocQuestionsBtn">
                        5 Esai Sokratik (<span id="modalQuestionsCount">5</span>)
                    </button>
                </div>

                <!-- Body Drawer (Scrollable) -->
                <div class="flex-1 overflow-y-auto p-6">
                    <!-- Tab Content 1: Teks Bacaan -->
                    <div id="tabDocTextContent" class="space-y-4">
                        <div class="rounded-xl border border-blue-100 bg-blue-50/60 p-4">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-blue-600 mb-1">INTISARI BACAAN (EXCERPT)</p>
                            <p id="modalDocExcerpt" class="text-xs text-slate-700 leading-relaxed font-medium text-justify [text-justify:inter-word]"></p>
                        </div>

                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">ISI TEKS PEMBELAJARAN SISWA</p>
                            <div id="modalDocBody" class="prose prose-sm max-w-none text-xs text-slate-700 leading-relaxed space-y-3 font-normal text-justify [text-justify:inter-word]"></div>
                        </div>
                    </div>

                    <!-- Tab Content 2: Kuis Pilihan Ganda -->
                    <div id="tabDocQuizContent" class="hidden space-y-4">
                        <div class="rounded-xl border border-blue-100 bg-blue-50/60 p-3.5 text-xs text-blue-900 flex items-start gap-2.5">
                            <x-icon name="timer" class="w-4 h-4 text-blue-600 shrink-0 mt-0.5" />
                            <span>10 Soal Pilihan Ganda otomatis dipakai untuk <strong>Kuis Timer (60s/soal)</strong> dan <strong>Latihan PG Mandiri</strong> siswa. Kunci jawaban diberi tanda hijau.</span>
                        </div>

                        <div id="modalDocQuizList" class="space-y-3.5">
                            <!-- Populated dynamically -->
                        </div>
                    </div>

                    <!-- Tab Content 3: Pertanyaan Sokratik -->
                    <div id="tabDocQuestionsContent" class="hidden space-y-4">
                        <div class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-3.5 text-xs text-emerald-900 flex items-start gap-2.5">
                            <x-icon name="sparkles" class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
                            <span>Pertanyaan dirumuskan dengan metode dialektika Sokratik untuk menguji pemahaman mendalam tanpa jalan pintas rangkuman instan.</span>
                        </div>

                        <div id="modalDocQuestionsList" class="space-y-3.5">
                            <!-- Populated dynamically -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const categorySelect = document.getElementById('document-category');
            const targetGradeSelect = document.getElementById('document-target-grade');
            if (categorySelect && targetGradeSelect && categorySelect.tagName === 'SELECT') {
                categorySelect.addEventListener('change', () => {
                    const selected = categorySelect.options[categorySelect.selectedIndex];
                    const grade = selected?.dataset?.targetGrade;
                    if (grade) {
                        targetGradeSelect.value = grade;
                    }
                });
            }

            // Preview Drawer Logic
            const modal = document.getElementById('docPreviewModal');
            const backdrop = document.getElementById('docModalBackdrop');
            const closeBtn = document.getElementById('closeDocModal');

            const tabTextBtn = document.getElementById('tabDocTextBtn');
            const tabQuizBtn = document.getElementById('tabDocQuizBtn');
            const tabQuestionsBtn = document.getElementById('tabDocQuestionsBtn');

            const tabTextContent = document.getElementById('tabDocTextContent');
            const tabQuizContent = document.getElementById('tabDocQuizContent');
            const tabQuestionsContent = document.getElementById('tabDocQuestionsContent');

            if (!modal) return;

            const switchTab = (activeTab) => {
                const tabs = [
                    { id: 'text', btn: tabTextBtn, content: tabTextContent },
                    { id: 'quiz', btn: tabQuizBtn, content: tabQuizContent },
                    { id: 'questions', btn: tabQuestionsBtn, content: tabQuestionsContent },
                ];

                tabs.forEach(tab => {
                    if (tab.id === activeTab) {
                        tab.btn.className = 'border-b-2 border-blue-600 px-3 py-2.5 text-xs font-bold text-blue-600 whitespace-nowrap transition';
                        tab.content.classList.remove('hidden');
                    } else {
                        tab.btn.className = 'border-b-2 border-transparent px-3 py-2.5 text-xs font-semibold text-slate-500 hover:text-slate-700 whitespace-nowrap transition';
                        tab.content.classList.add('hidden');
                    }
                });
            };

            tabTextBtn.addEventListener('click', () => switchTab('text'));
            tabQuizBtn.addEventListener('click', () => switchTab('quiz'));
            tabQuestionsBtn.addEventListener('click', () => switchTab('questions'));

            const openModal = (btn) => {
                const title = btn.dataset.docTitle || 'Materi Bacaan';
                const category = btn.dataset.docCategory || 'Umum';
                const grade = btn.dataset.docGrade || 'Semua Kelas';
                const excerpt = btn.dataset.docExcerpt || 'Belum ada intisari.';
                const body = btn.dataset.docBody || '';
                let questions = [];
                let quizQuestions = [];
                try {
                    questions = JSON.parse(btn.dataset.docQuestions || '[]');
                } catch (_) {}
                try {
                    quizQuestions = JSON.parse(btn.dataset.docQuiz || '[]');
                } catch (_) {}

                document.getElementById('modalDocTitle').textContent = title;
                document.getElementById('modalDocCategory').textContent = category;
                document.getElementById('modalDocGrade').textContent = grade;
                const formatInlineMarkdown = (str) => {
                    if (!str) return '';
                    return str
                        .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
                        .replace(/\*\*(.+?)\*\*/g, '<strong class="font-bold text-slate-900">$1</strong>')
                        .replace(/\*(.+?)\*/g, '<em class="italic text-slate-800">$1</em>');
                };

                document.getElementById('modalDocExcerpt').innerHTML = formatInlineMarkdown(excerpt);

                // Render paragraphs in Body
                const bodyEl = document.getElementById('modalDocBody');
                bodyEl.innerHTML = '';
                const paragraphs = body.split(/\r?\n\r?\n/).filter(p => p.trim() !== '');
                if (paragraphs.length === 0) {
                    bodyEl.innerHTML = `<p class="text-justify [text-justify:inter-word]">${formatInlineMarkdown(body)}</p>`;
                } else {
                    paragraphs.forEach(p => {
                        const pEl = document.createElement('p');
                        pEl.innerHTML = formatInlineMarkdown(p.trim());
                        pEl.className = 'text-justify [text-justify:inter-word]';
                        bodyEl.appendChild(pEl);
                    });
                }

                // Render 10 Multiple Choice Questions (PG)
                document.getElementById('modalQuizCount').textContent = quizQuestions.length;
                const quizList = document.getElementById('modalDocQuizList');
                quizList.innerHTML = '';

                if (quizQuestions.length === 0) {
                    quizList.innerHTML = `
                        <div class="py-8 text-center text-slate-400 border border-dashed border-slate-200 rounded-xl">
                            <p class="text-xs">Belum ada soal Pilihan Ganda tersimpan untuk bacaan ini.</p>
                        </div>
                    `;
                } else {
                    quizQuestions.forEach((qq, idx) => {
                        const seq = qq.sequence || (idx + 1);
                        const card = document.createElement('div');
                        card.className = 'rounded-xl border border-slate-200/80 bg-slate-50/70 p-4 space-y-2.5';
                        
                        let optionsHtml = '';
                        const opts = Array.isArray(qq.options) ? qq.options : [];
                        opts.forEach(opt => {
                            const isCorrect = String(opt.key).toUpperCase() === String(qq.correct_answer).toUpperCase();
                            optionsHtml += `
                                <div class="flex items-start gap-2.5 p-2 rounded-lg text-xs ${isCorrect ? 'bg-emerald-50 border border-emerald-300 text-emerald-950 font-semibold' : 'bg-white border border-slate-200 text-slate-700'}">
                                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-md text-[10px] font-bold shrink-0 ${isCorrect ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600'}">
                                        ${opt.key}
                                    </span>
                                    <span class="flex-1">${opt.text}</span>
                                    ${isCorrect ? '<span class="text-[10px] uppercase font-bold text-emerald-700 bg-emerald-100/70 px-1.5 py-0.5 rounded shrink-0">Kunci Jawaban</span>' : ''}
                                </div>
                            `;
                        });

                        card.innerHTML = `
                            <div class="flex items-center justify-between">
                                <span class="rounded bg-blue-50 text-blue-700 border border-blue-200 px-2 py-0.5 text-[10px] font-bold">
                                    Nomor ${seq} dari ${quizQuestions.length}
                                </span>
                                <span class="text-[10px] text-slate-500 font-medium">PG: Kuis & Latihan</span>
                            </div>
                            <h4 class="text-xs font-bold text-slate-900 leading-snug">${qq.question}</h4>
                            <div class="space-y-1.5 pt-1">
                                ${optionsHtml}
                            </div>
                            ${qq.explanation ? `
                                <div class="rounded-lg bg-blue-50/70 border border-blue-200/60 p-2.5 text-[11px] text-blue-900 flex items-start gap-1.5 mt-2">
                                    <span class="font-bold text-blue-700 shrink-0">💡 Pembahasan:</span>
                                    <span>${qq.explanation}</span>
                                </div>
                            ` : ''}
                        `;
                        quizList.appendChild(card);
                    });
                }

                // Render Socratic Questions (5 Esai)
                document.getElementById('modalQuestionsCount').textContent = questions.length;
                const questionsList = document.getElementById('modalDocQuestionsList');
                questionsList.innerHTML = '';

                if (questions.length === 0) {
                    questionsList.innerHTML = `
                        <div class="py-8 text-center text-slate-400 border border-dashed border-slate-200 rounded-xl">
                            <p class="text-xs">Belum ada pertanyaan Sokratik yang tersimpan untuk bacaan ini.</p>
                        </div>
                    `;
                } else {
                    const skillColors = {
                        1: { bg: 'bg-blue-50', text: 'text-blue-700', border: 'border-blue-200' },
                        2: { bg: 'bg-violet-50', text: 'text-violet-700', border: 'border-violet-200' },
                        3: { bg: 'bg-amber-50', text: 'text-amber-700', border: 'border-amber-200' },
                        4: { bg: 'bg-rose-50', text: 'text-rose-700', border: 'border-rose-200' },
                        5: { bg: 'bg-emerald-50', text: 'text-emerald-700', border: 'border-emerald-200' },
                    };

                    questions.forEach((q, idx) => {
                        const seq = q.sequence || (idx + 1);
                        const colors = skillColors[seq] || skillColors[1];
                        const card = document.createElement('div');
                        card.className = 'rounded-xl border border-slate-100 bg-slate-50/70 p-4 space-y-2';
                        card.innerHTML = `
                            <div class="flex items-center justify-between">
                                <span class="rounded ${colors.bg} ${colors.text} ${colors.border} border px-2 py-0.5 text-[10px] font-bold">
                                    Tantangan ${seq}: ${q.skill || 'Berpikir Kritis'}
                                </span>
                            </div>
                            <h4 class="text-xs font-bold text-slate-900 leading-snug">${q.prompt}</h4>
                            <p class="text-[11px] text-slate-500 leading-normal">${q.context || ''}</p>
                            ${q.hint ? `
                                <div class="rounded-lg bg-amber-50/80 border border-amber-200/60 p-2.5 text-[11px] text-amber-900 flex items-start gap-1.5 mt-2">
                                    <span class="font-bold text-amber-700 shrink-0">💡 Petunjuk:</span>
                                    <span>${q.hint}</span>
                                </div>
                            ` : ''}
                        `;
                        questionsList.appendChild(card);
                    });
                }

                switchTab('text');
                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
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

            document.querySelectorAll('[data-preview-btn]').forEach(btn => {
                btn.addEventListener('click', () => openModal(btn));
            });
        })();
    </script>
@endsection
