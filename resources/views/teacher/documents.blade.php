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
                <div class="upload-field-group">
                    <label for="document-quiz-duration">Durasi Kuis Keseluruhan</label>
                    <select id="document-quiz-duration" name="quiz_duration_minutes" class="upload-field bg-white">
                        <option value="5" {{ old('quiz_duration_minutes') == '5' ? 'selected' : '' }}>5 Menit</option>
                        <option value="10" {{ old('quiz_duration_minutes') == '10' ? 'selected' : '' }}>10 Menit</option>
                        <option value="15" {{ old('quiz_duration_minutes', '15') == '15' ? 'selected' : '' }}>15 Menit (Standar)</option>
                        <option value="20" {{ old('quiz_duration_minutes') == '20' ? 'selected' : '' }}>20 Menit</option>
                        <option value="30" {{ old('quiz_duration_minutes') == '30' ? 'selected' : '' }}>30 Menit</option>
                        <option value="45" {{ old('quiz_duration_minutes') == '45' ? 'selected' : '' }}>45 Menit</option>
                        <option value="60" {{ old('quiz_duration_minutes') == '60' ? 'selected' : '' }}>60 Menit (1 Jam)</option>
                    </select>
                    @error('quiz_duration_minutes')<small class="upload-field-error">{{ $message }}</small>@enderror
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
                        <span class="rounded bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700 border border-indigo-200/60 inline-flex items-center gap-1">
                            <x-icon name="gamepad-2" class="w-3 h-3 text-indigo-600"/>
                            Kuis Socrates: {{ $document->quiz_question_count ?? 10 }} Soal (⏱️ {{ $document->quiz_duration_minutes ?? 15 }}m)
                        </span>
                        <span class="rounded bg-purple-50 px-2 py-0.5 text-[10px] font-bold text-purple-700 border border-purple-200/60 inline-flex items-center gap-1">
                            <x-icon name="award" class="w-3 h-3 text-purple-600"/>
                            {{ $document->teacher_challenges_count }} Tantangan Guru
                        </span>
                    </div>
                    <span>{{ $document->category }} · <span class="rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-semibold text-blue-700">{{ $document->targetGradeLabel() }}</span> · {{ number_format(mb_strlen($document->body)) }} karakter · {{ $document->created_at->locale('id')->translatedFormat('d M Y') }}</span>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-purple-300 bg-purple-50 px-2.5 py-1.5 text-xs font-bold text-purple-900 hover:bg-purple-100 transition shadow-2xs" data-challenge-btn data-doc-id="{{ $document->id }}" data-doc-title="{{ $document->title }}" data-get-url="{{ route('teacher.documents.challenges.index', $document) }}" data-store-url="{{ route('teacher.documents.challenges.store', $document) }}" data-suggestions-url="{{ route('teacher.documents.challenges.suggestions', $document) }}" title="Kelola Tantangan Guru (Esai & PG)">
                        <x-icon name="award" class="w-3.5 h-3.5 text-purple-600"/>
                        <span>Tantangan Guru ({{ $document->teacher_challenges_count }})</span>
                    </button>
                    <form method="POST" action="{{ route('teacher.documents.quiz_duration', $document) }}" data-quiz-settings-form data-doc-id="{{ $document->id }}" class="inline-flex items-center gap-1.5 bg-slate-50 border border-slate-200 rounded-lg p-1 relative" title="Pengaturan Kuis Socrates untuk Siswa">
                        @csrf @method('PATCH')
                        <div class="flex items-center gap-1">
                            <span class="text-[10px] text-slate-400 font-bold pl-1">⏱️</span>
                            <select name="quiz_duration_minutes" class="text-[11px] font-semibold py-0.5 px-1 rounded border-0 bg-transparent text-slate-700 hover:text-amber-600 cursor-pointer focus:ring-0" title="Ubah Durasi Kuis Socrates">
                                <option value="5" @selected(($document->quiz_duration_minutes ?? 15) == 5)>5m</option>
                                <option value="10" @selected(($document->quiz_duration_minutes ?? 15) == 10)>10m</option>
                                <option value="15" @selected(($document->quiz_duration_minutes ?? 15) == 15)>15m</option>
                                <option value="20" @selected(($document->quiz_duration_minutes ?? 15) == 20)>20m</option>
                                <option value="30" @selected(($document->quiz_duration_minutes ?? 15) == 30)>30m</option>
                                <option value="45" @selected(($document->quiz_duration_minutes ?? 15) == 45)>45m</option>
                                <option value="60" @selected(($document->quiz_duration_minutes ?? 15) == 60)>60m</option>
                            </select>
                        </div>
                        <span class="text-slate-300">|</span>
                        <div class="flex items-center gap-1">
                            <span class="text-[10px] text-slate-400 font-bold">📝</span>
                            <select name="quiz_question_count" class="text-[11px] font-semibold py-0.5 px-1 rounded border-0 bg-transparent text-slate-700 hover:text-blue-600 cursor-pointer focus:ring-0" title="Ubah Jumlah Soal Kuis Socrates (5 - 100 Soal)">
                                <option value="5" @selected(($document->quiz_question_count ?? 10) == 5)>5 Soal</option>
                                <option value="10" @selected(($document->quiz_question_count ?? 10) == 10)>10 Soal</option>
                                <option value="15" @selected(($document->quiz_question_count ?? 10) == 15)>15 Soal</option>
                                <option value="20" @selected(($document->quiz_question_count ?? 10) == 20)>20 Soal</option>
                                <option value="25" @selected(($document->quiz_question_count ?? 10) == 25)>25 Soal</option>
                                <option value="30" @selected(($document->quiz_question_count ?? 10) == 30)>30 Soal</option>
                                <option value="50" @selected(($document->quiz_question_count ?? 10) == 50)>50 Soal</option>
                                <option value="100" @selected(($document->quiz_question_count ?? 10) == 100)>100 Soal</option>
                            </select>
                        </div>
                        <span class="quiz-saved-pill hidden text-[9px] font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">✓ Tersimpan</span>
                    </form>
                    <script type="application/json" id="doc-preview-data-{{ $document->id }}">
                        {!! json_encode([
                            'id' => $document->id,
                            'title' => $document->title,
                            'category' => $document->category,
                            'grade' => $document->targetGradeLabel(),
                            'excerpt' => $document->excerpt,
                            'body' => $document->body,
                            'quizCount' => $document->quiz_question_count ?? 10,
                            'quiz' => $document->quizQuestions->values(),
                            'teacherChallenges' => $document->teacherChallenges->values(),
                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
                    </script>
                    <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition" data-preview-btn data-doc-id="{{ $document->id }}" title="Pratinjau Materi & Soal">
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
                        Kuis Socrates (<span id="modalQuizCount">10</span> Soal)
                    </button>
                    <button type="button" class="border-b-2 border-transparent px-3 py-2.5 text-xs font-semibold text-slate-500 hover:text-slate-700 whitespace-nowrap transition" id="tabDocTeacherChallengesBtn">
                        Tantangan Guru (<span id="modalTeacherChallengesCount">0</span> Soal)
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

                    <!-- Tab Content 2: Kuis Socrates (Pilihan Ganda) -->
                    <div id="tabDocQuizContent" class="hidden space-y-4">
                        <div class="rounded-xl border border-indigo-100 bg-indigo-50/60 p-3.5 text-xs text-indigo-950 flex items-start gap-2.5">
                            <x-icon name="gamepad-2" class="w-4 h-4 text-indigo-600 shrink-0 mt-0.5" />
                            <span>Kuis Pilihan Ganda Socrates berfokus pada substansi materi bacaan. Jumlah soal menyesuaikan pilihan guru (<strong id="modalQuizSubtitleCount">10</strong> Soal). Kunci jawaban ditandai hijau.</span>
                        </div>

                        <div id="modalDocQuizList" class="space-y-3.5">
                            <!-- Populated dynamically -->
                        </div>
                    </div>

                    <!-- Tab Content 3: Tantangan Guru (PG & Esai) -->
                    <div id="tabDocTeacherChallengesContent" class="hidden space-y-4">
                        <div class="rounded-xl border border-purple-100 bg-purple-50/60 p-3.5 text-xs text-purple-950 flex items-start gap-2.5">
                            <x-icon name="award" class="w-4 h-4 text-purple-600 shrink-0 mt-0.5" />
                            <span>Tantangan khusus guru (Total 100 Poin) · Menampilkan soal pilihan ganda maupun esai analisis pemahaman materi.</span>
                        </div>

                        <div id="modalDocTeacherChallengesList" class="space-y-3.5">
                            <!-- Populated dynamically -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Kelola Tantangan Guru (Esai Analisis & PG - 100 Poin) -->
    <div id="challengeModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="challengeModalTitle">
        <div id="challengeBackdrop" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs transition-opacity"></div>
        <div class="relative w-full max-w-3xl max-h-[90vh] flex flex-col rounded-2xl bg-white shadow-2xl border border-slate-200 overflow-hidden z-10 animate-scale-in">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4 bg-slate-50/80">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-purple-100 text-purple-700 grid place-items-center">
                        <x-icon name="award" class="w-4 h-4" />
                    </span>
                    <div>
                        <h3 id="challengeModalTitle" class="text-sm font-bold text-slate-800">Kelola Tantangan Guru</h3>
                        <p class="text-[11px] text-slate-500" id="challengeDocTitle">Memuat materi...</p>
                    </div>
                </div>
                <button type="button" id="challengeCloseBtn" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition">
                    <x-icon name="x" class="w-5 h-5" />
                </button>
            </div>

            <!-- Content Area (Scrollable) -->
            <div class="flex-grow overflow-y-auto p-6 space-y-6">
                <!-- Info Alert Skoring 100 Poin -->
                <div class="rounded-xl border border-purple-200 bg-purple-50/80 p-4 text-xs text-purple-950 flex items-start gap-3">
                    <x-icon name="sparkles" class="w-5 h-5 text-purple-600 shrink-0 mt-0.5" />
                    <div class="leading-relaxed">
                        <strong class="font-bold block text-purple-900 mb-1">💡 Penilaian Otomatis (Total 100 Poin):</strong>
                        Berapa pun jumlah soal esai yang Anda buat, skor maksimal jika seluruhnya benar adalah <strong>100 Poin</strong> (bobot terdistribusi rata: 100 / N).
                        Jawaban esai murid dinilai mendalam oleh AI berdasarkan kesesuaian materi & kata kunci rubrik yang Anda tentukan (menghasilkan nilai proporsional seperti 30.45, 45, dsb).
                    </div>
                </div>

                <!-- Rekomendasi Soal Otomatis dari AI -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-xl bg-amber-50/90 border border-amber-200 text-xs">
                    <div class="flex items-start gap-2">
                        <span class="text-amber-600 text-base leading-none mt-0.5">💡</span>
                        <div>
                            <span class="font-bold text-amber-950 block">Butuh bantuan menentukan soal esai?</span>
                            <span class="text-slate-600 text-[11px]">Sistem dapat memuat rekomendasi draf soal analisis beserta kunci konsep langsung dari materi bacaan ini.</span>
                        </div>
                    </div>
                    <button type="button" id="btnLoadSuggestions" class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs shadow-2xs transition">
                        <span>✨ Muat Rekomendasi Soal</span>
                    </button>
                </div>

                <!-- List of Existing Questions -->
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">Daftar Soal Tantangan (<span id="challengeCountDisplay">0 Soal</span>)</h4>
                        <span id="pointsPerQDisplay" class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-bold text-slate-700">0 Poin / Soal</span>
                    </div>
                    <div id="challengeListContainer" class="space-y-3">
                        <!-- Rendered dynamically -->
                    </div>
                </div>

                <!-- Form Tambah Soal Baru -->
                <div class="rounded-2xl border border-slate-200/80 bg-slate-50/60 p-4 sm:p-5">
                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-3">Tambah Soal Baru</h4>

                    <!-- Type Radio Selector -->
                    <div class="flex items-center gap-4 mb-4">
                        <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700">
                            <input type="radio" name="new_q_type" value="essay" checked class="text-purple-600 focus:ring-purple-500" id="typeEssayRadio">
                            <span>📝 Soal Esai (Analisis Pemahaman)</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700">
                            <input type="radio" name="new_q_type" value="multiple_choice" class="text-purple-600 focus:ring-purple-500" id="typePGRadio">
                            <span>🔘 Pilihan Ganda (A/B/C/D)</span>
                        </label>
                    </div>

                    <!-- Pertanyaan Input -->
                    <div class="space-y-1 mb-3">
                        <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Pertanyaan:</label>
                        <textarea id="newQText" rows="3" class="w-full rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-800 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 outline-none" placeholder="Tuliskan pertanyaan tantangan esai..."></textarea>
                    </div>

                    <!-- Esai Fields (Rubrik Konsep) -->
                    <div id="essayFields" class="space-y-1 mb-3">
                        <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Kunci Konsep / Panduan Penilaian Esai (untuk AI):</label>
                        <textarea id="newQRubric" rows="2" class="w-full rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-800 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 outline-none" placeholder="Kata kunci, argumen utama, atau bukti yang diharapkan muncul dalam jawaban murid..."></textarea>
                    </div>

                    <!-- PG Fields (Opsi A/B/C/D & Kunci) -->
                    <div id="pgFields" class="hidden space-y-2 mb-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <input type="text" id="optA" placeholder="Opsi A" class="rounded-lg border border-slate-200 p-2 text-xs bg-white">
                            <input type="text" id="optB" placeholder="Opsi B" class="rounded-lg border border-slate-200 p-2 text-xs bg-white">
                            <input type="text" id="optC" placeholder="Opsi C" class="rounded-lg border border-slate-200 p-2 text-xs bg-white">
                            <input type="text" id="optD" placeholder="Opsi D" class="rounded-lg border border-slate-200 p-2 text-xs bg-white">
                        </div>
                        <div class="flex items-center gap-2 pt-1">
                            <label class="text-[11px] font-bold text-slate-500">Kunci Benar:</label>
                            <select id="correctOpt" class="rounded-lg border border-slate-200 p-1.5 text-xs bg-white font-bold text-blue-700">
                                <option value="A">Opsi A</option>
                                <option value="B">Opsi B</option>
                                <option value="C">Opsi C</option>
                                <option value="D">Opsi D</option>
                            </select>
                        </div>
                    </div>

                    <button type="button" id="btnAddQuestion" class="mt-2 inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-xs transition">
                        <x-icon name="plus" class="w-3.5 h-3.5" />
                        <span>Tambah ke Daftar Soal</span>
                    </button>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-between border-t border-slate-200 px-6 py-4 bg-slate-50/80">
                <button type="button" id="challengeCancelBtn" class="outline-button text-xs">Batal</button>
                <button type="button" id="btnSaveAllChallenges" class="challenge-button text-xs bg-purple-600 hover:bg-purple-700">
                    <span>Simpan Seluruh Tantangan (Skor 100 Poin)</span>
                    <x-icon name="arrow-right" class="w-4 h-4" />
                </button>
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
            const tabTeacherChallengesBtn = document.getElementById('tabDocTeacherChallengesBtn');

            const tabTextContent = document.getElementById('tabDocTextContent');
            const tabQuizContent = document.getElementById('tabDocQuizContent');
            const tabTeacherChallengesContent = document.getElementById('tabDocTeacherChallengesContent');

            if (!modal) return;

            const switchTab = (activeTab) => {
                const tabs = [
                    { id: 'text', btn: tabTextBtn, content: tabTextContent },
                    { id: 'quiz', btn: tabQuizBtn, content: tabQuizContent },
                    { id: 'challenges', btn: tabTeacherChallengesBtn, content: tabTeacherChallengesContent },
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
            tabTeacherChallengesBtn?.addEventListener('click', () => switchTab('challenges'));

            const openModal = (btn) => {
                const docId = btn.dataset.docId;
                let data = null;
                const scriptEl = document.getElementById('doc-preview-data-' + docId);
                if (scriptEl) {
                    try {
                        data = JSON.parse(scriptEl.textContent);
                    } catch (e) {
                        console.error('Failed to parse doc data', e);
                    }
                }
                data = data || {};
                const title = data.title || btn.dataset.docTitle || 'Materi Bacaan';
                const category = data.category || btn.dataset.docCategory || 'Umum';
                const grade = data.grade || btn.dataset.docGrade || 'Semua Kelas';
                const excerpt = data.excerpt || btn.dataset.docExcerpt || 'Belum ada intisari.';
                const body = data.body || '';
                const maxCount = parseInt(data.quizCount || 10, 10);
                const allQuiz = Array.isArray(data.quiz) ? data.quiz : [];
                const quizQuestions = allQuiz.slice(0, maxCount);
                const teacherChallenges = Array.isArray(data.teacherChallenges) ? data.teacherChallenges : [];

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

                // Render Kuis Socrates (Pilihan Ganda)
                document.getElementById('modalQuizCount').textContent = quizQuestions.length;
                const quizSubtitle = document.getElementById('modalQuizSubtitleCount');
                if (quizSubtitle) quizSubtitle.textContent = quizQuestions.length;

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
                                <span class="rounded bg-indigo-50 text-indigo-700 border border-indigo-200 px-2 py-0.5 text-[10px] font-bold">
                                    Soal ${seq} dari ${quizQuestions.length}
                                </span>
                                <span class="text-[10px] text-slate-500 font-semibold">Kuis Socrates</span>
                            </div>
                            <h4 class="text-xs font-bold text-slate-900 leading-snug">${qq.question}</h4>
                            <div class="space-y-1.5 pt-1">
                                ${optionsHtml}
                            </div>
                            ${qq.explanation ? `
                                <div class="rounded-lg bg-indigo-50/70 border border-indigo-200/60 p-2.5 text-[11px] text-indigo-950 flex items-start gap-1.5 mt-2">
                                    <span class="font-bold text-indigo-700 shrink-0">💡 Pembahasan:</span>
                                    <span>${qq.explanation}</span>
                                </div>
                            ` : ''}
                        `;
                        quizList.appendChild(card);
                    });
                }

                // Render Tantangan Guru (PG & Esai)
                document.getElementById('modalTeacherChallengesCount').textContent = teacherChallenges.length;
                const challengesList = document.getElementById('modalDocTeacherChallengesList');
                challengesList.innerHTML = '';

                if (teacherChallenges.length === 0) {
                    challengesList.innerHTML = `
                        <div class="py-8 text-center text-slate-400 border border-dashed border-slate-200 rounded-xl">
                            <p class="text-xs">Belum ada soal tantangan guru tersimpan untuk materi ini.</p>
                            <p class="text-[11px] text-slate-400 mt-1">Gunakan tombol <strong>"Tantangan Guru"</strong> di daftar bacaan untuk menambahkan soal esai/PG.</p>
                        </div>
                    `;
                } else {
                    const pointsPerItem = (100 / teacherChallenges.length).toFixed(2);
                    teacherChallenges.forEach((item, idx) => {
                        const seq = item.sequence || (idx + 1);
                        const isEssay = item.type === 'essay';
                        const card = document.createElement('div');
                        card.className = 'rounded-xl border border-slate-200/80 bg-slate-50/70 p-4 space-y-2.5';

                        let contentHtml = '';
                        if (isEssay) {
                            contentHtml = `
                                <h4 class="text-xs font-bold text-slate-900 leading-snug">${item.question}</h4>
                                ${item.rubric ? `
                                    <div class="rounded-lg bg-purple-50/70 border border-purple-200/60 p-2.5 text-[11px] text-purple-950 flex items-start gap-1.5 mt-2">
                                        <span class="font-bold text-purple-700 shrink-0">🔑 Kunci Konsep / Rubrik AI:</span>
                                        <span>${item.rubric}</span>
                                    </div>
                                ` : ''}
                            `;
                        } else {
                            let optionsHtml = '';
                            const opts = typeof item.options === 'object' && item.options ? item.options : {};
                            ['A', 'B', 'C', 'D'].forEach(k => {
                                const text = opts[k] || '';
                                if (!text) return;
                                const isCorrect = String(k).toUpperCase() === String(item.correct_answer).toUpperCase();
                                optionsHtml += `
                                    <div class="flex items-start gap-2.5 p-2 rounded-lg text-xs ${isCorrect ? 'bg-emerald-50 border border-emerald-300 text-emerald-950 font-semibold' : 'bg-white border border-slate-200 text-slate-700'}">
                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-md text-[10px] font-bold shrink-0 ${isCorrect ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600'}">
                                            ${k}
                                        </span>
                                        <span class="flex-1">${text}</span>
                                        ${isCorrect ? '<span class="text-[10px] uppercase font-bold text-emerald-700 bg-emerald-100/70 px-1.5 py-0.5 rounded shrink-0">Kunci Jawaban</span>' : ''}
                                    </div>
                                `;
                            });
                            contentHtml = `
                                <h4 class="text-xs font-bold text-slate-900 leading-snug">${item.question}</h4>
                                <div class="space-y-1.5 pt-1">
                                    ${optionsHtml}
                                </div>
                            `;
                        }

                        card.innerHTML = `
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="rounded bg-slate-200/80 text-slate-800 text-[10px] font-bold px-2 py-0.5">#${seq}</span>
                                    <span class="rounded ${isEssay ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'} text-[10px] font-bold px-2 py-0.5 uppercase">
                                        ${isEssay ? 'Esai Analisis' : 'Pilihan Ganda'}
                                    </span>
                                </div>
                                <span class="text-[10px] text-slate-500 font-semibold">Bobot: ${pointsPerItem} Poin</span>
                            </div>
                            ${contentHtml}
                        `;
                        challengesList.appendChild(card);
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

            // Auto-save Pengaturan Kuis Socrates (Durasi & Jumlah Soal) secara silent tanpa reload
            document.querySelectorAll('[data-quiz-settings-form]').forEach(form => {
                const selects = form.querySelectorAll('select');
                const savedPill = form.querySelector('.quiz-saved-pill');
                selects.forEach(select => {
                    select.addEventListener('change', async () => {
                        const formData = new FormData(form);
                        if (savedPill) {
                            savedPill.textContent = '⏳ Menyimpan...';
                            savedPill.classList.remove('hidden', 'text-emerald-600', 'bg-emerald-50');
                            savedPill.classList.add('text-amber-700', 'bg-amber-50');
                        }
                        try {
                            const res = await fetch(form.action, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                                },
                                body: formData
                            });
                            if (res.ok) {
                                const json = await res.json();
                                if (savedPill) {
                                    savedPill.textContent = `✓ ${json.quiz_question_count || ''} Soal Tersimpan`;
                                    savedPill.classList.remove('text-amber-700', 'bg-amber-50');
                                    savedPill.classList.add('text-emerald-600', 'bg-emerald-50');
                                    setTimeout(() => savedPill.classList.add('hidden'), 2500);
                                }
                                const docId = form.dataset.docId;
                                const scriptEl = document.getElementById('doc-preview-data-' + docId);
                                if (scriptEl) {
                                    try {
                                        const parsed = JSON.parse(scriptEl.textContent);
                                        if (json.quiz_question_count) {
                                            parsed.quizCount = json.quiz_question_count;
                                        }
                                        if (json.quiz_questions && json.quiz_questions.length > 0) {
                                            parsed.quiz = json.quiz_questions;
                                        }
                                        scriptEl.textContent = JSON.stringify(parsed);
                                    } catch (_) {}
                                }
                            } else {
                                if (savedPill) {
                                    savedPill.textContent = '⚠️ Gagal simpan';
                                    savedPill.classList.remove('text-amber-700', 'bg-amber-50');
                                    savedPill.classList.add('text-rose-600', 'bg-rose-50');
                                    setTimeout(() => savedPill.classList.add('hidden'), 3000);
                                }
                            }
                        } catch (err) {
                            console.error('Gagal memperbarui pengaturan kuis:', err);
                            if (savedPill) {
                                savedPill.textContent = '⚠️ Error';
                                setTimeout(() => savedPill.classList.add('hidden'), 3000);
                            }
                        }
                    });
                });
            });

            // ==========================================
            // KELOLA TANTANGAN GURU (PG & ESAI - 100 POIN)
            // ==========================================
            const challengeModal = document.getElementById('challengeModal');
            const challengeBackdrop = document.getElementById('challengeBackdrop');
            const challengeCloseBtn = document.getElementById('challengeCloseBtn');
            const challengeCancelBtn = document.getElementById('challengeCancelBtn');
            const challengeDocTitle = document.getElementById('challengeDocTitle');
            const challengeListContainer = document.getElementById('challengeListContainer');
            const challengeCountDisplay = document.getElementById('challengeCountDisplay');
            const pointsPerQDisplay = document.getElementById('pointsPerQDisplay');

            const typeEssayRadio = document.getElementById('typeEssayRadio');
            const typePGRadio = document.getElementById('typePGRadio');
            const essayFields = document.getElementById('essayFields');
            const pgFields = document.getElementById('pgFields');

            const newQText = document.getElementById('newQText');
            const newQRubric = document.getElementById('newQRubric');
            const optA = document.getElementById('optA');
            const optB = document.getElementById('optB');
            const optC = document.getElementById('optC');
            const optD = document.getElementById('optD');
            const correctOpt = document.getElementById('correctOpt');
            const btnAddQuestion = document.getElementById('btnAddQuestion');
            const btnSaveAllChallenges = document.getElementById('btnSaveAllChallenges');
            const btnLoadSuggestions = document.getElementById('btnLoadSuggestions');

            let currentDocId = null;
            let currentStoreUrl = null;
            let currentSuggestionsUrl = null;
            let activeChallenges = [];

            const toggleQuestionTypeFields = () => {
                if (typePGRadio.checked) {
                    pgFields.classList.remove('hidden');
                    essayFields.classList.add('hidden');
                } else {
                    pgFields.classList.add('hidden');
                    essayFields.classList.remove('hidden');
                }
            };

            typeEssayRadio?.addEventListener('change', toggleQuestionTypeFields);
            typePGRadio?.addEventListener('change', toggleQuestionTypeFields);

            const renderChallengeList = () => {
                challengeListContainer.innerHTML = '';
                const total = activeChallenges.length;
                challengeCountDisplay.textContent = `${total} Soal`;
                const points = total > 0 ? (100 / total).toFixed(2) : 0;
                pointsPerQDisplay.textContent = `${points} Poin / Soal (Total 100 Poin)`;

                if (total === 0) {
                    challengeListContainer.innerHTML = `
                        <div class="p-4 text-center rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-400">
                            Belum ada soal tantangan. Tambahkan soal pertama di bawah!
                        </div>
                    `;
                    return;
                }

                activeChallenges.forEach((item, index) => {
                    const card = document.createElement('div');
                    card.className = 'p-3.5 rounded-xl border border-slate-200 bg-white shadow-2xs space-y-2';
                    const isEssay = item.type === 'essay';

                    let contentHtml = `<p class="text-xs text-slate-800 font-semibold">${item.question}</p>`;
                    if (isEssay && item.rubric) {
                        contentHtml += `<p class="text-[11px] text-purple-700 bg-purple-50/70 p-2 rounded-lg border border-purple-100"><strong>Kunci Konsep:</strong> ${item.rubric}</p>`;
                    } else if (!isEssay && item.options) {
                        contentHtml += `
                            <div class="grid grid-cols-2 gap-1.5 text-[11px] text-slate-600 mt-1">
                                <span class="${item.correct_answer === 'A' ? 'font-bold text-emerald-700' : ''}">A: ${item.options.A || ''}</span>
                                <span class="${item.correct_answer === 'B' ? 'font-bold text-emerald-700' : ''}">B: ${item.options.B || ''}</span>
                                <span class="${item.correct_answer === 'C' ? 'font-bold text-emerald-700' : ''}">C: ${item.options.C || ''}</span>
                                <span class="${item.correct_answer === 'D' ? 'font-bold text-emerald-700' : ''}">D: ${item.options.D || ''}</span>
                            </div>
                        `;
                    }

                    card.innerHTML = `
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="rounded bg-slate-100 text-slate-700 font-bold text-xs px-2 py-0.5">#${index + 1}</span>
                                <span class="rounded ${isEssay ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'} text-[10px] font-bold px-2 py-0.5 uppercase">
                                    ${isEssay ? 'Esai Analisis' : 'Pilihan Ganda'}
                                </span>
                                <span class="text-[10px] text-slate-400 font-semibold">Bobot: ${points} Poin</span>
                            </div>
                            <button type="button" class="text-rose-600 hover:text-rose-800 text-xs font-bold transition p-1" data-del-idx="${index}">
                                Hapus
                            </button>
                        </div>
                        ${contentHtml}
                    `;

                    card.querySelector('[data-del-idx]').addEventListener('click', () => {
                        activeChallenges.splice(index, 1);
                        renderChallengeList();
                    });

                    challengeListContainer.appendChild(card);
                });
            };

            const openChallengeModal = async (btn) => {
                currentDocId = btn.dataset.docId;
                currentStoreUrl = btn.dataset.storeUrl;
                currentSuggestionsUrl = btn.dataset.suggestionsUrl;
                challengeDocTitle.textContent = `Materi: ${btn.dataset.docTitle}`;
                activeChallenges = [];
                renderChallengeList();

                challengeModal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');

                try {
                    const res = await fetch(btn.dataset.getUrl, { credentials: 'same-origin' });
                    if (res.ok) {
                        const json = await res.json();
                        activeChallenges = json.challenges || [];
                        renderChallengeList();
                    }
                } catch (err) {
                    console.error('Failed to load challenges:', err);
                }
            };

            btnLoadSuggestions?.addEventListener('click', async () => {
                if (!currentSuggestionsUrl) return;
                const oldHtml = btnLoadSuggestions.innerHTML;
                btnLoadSuggestions.disabled = true;
                btnLoadSuggestions.textContent = 'Memuat rekomendasi...';
                try {
                    const res = await fetch(currentSuggestionsUrl, { credentials: 'same-origin' });
                    if (res.ok) {
                        const json = await res.json();
                        if (json.suggestions && json.suggestions.length > 0) {
                            activeChallenges = json.suggestions;
                            renderChallengeList();
                        } else {
                            alert('Belum ada rekomendasi yang tersedia untuk materi ini.');
                        }
                    }
                } catch (err) {
                    alert('Gagal memuat rekomendasi soal esai.');
                } finally {
                    btnLoadSuggestions.disabled = false;
                    btnLoadSuggestions.innerHTML = oldHtml;
                }
            });

            const closeChallengeModal = () => {
                challengeModal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            };

            challengeBackdrop?.addEventListener('click', closeChallengeModal);
            challengeCloseBtn?.addEventListener('click', closeChallengeModal);
            challengeCancelBtn?.addEventListener('click', closeChallengeModal);

            document.querySelectorAll('[data-challenge-btn]').forEach(btn => {
                btn.addEventListener('click', () => openChallengeModal(btn));
            });

            btnAddQuestion?.addEventListener('click', () => {
                const qText = newQText.value.trim();
                if (!qText) {
                    alert('Harap isi teks pertanyaan.');
                    return;
                }

                if (typePGRadio.checked) {
                    if (!optA.value.trim() || !optB.value.trim()) {
                        alert('Harap isi minimal opsi A dan B.');
                        return;
                    }
                    activeChallenges.push({
                        type: 'multiple_choice',
                        question: qText,
                        options: {
                            A: optA.value.trim(),
                            B: optB.value.trim(),
                            C: optC.value.trim(),
                            D: optD.value.trim(),
                        },
                        correct_answer: correctOpt.value,
                    });
                    optA.value = ''; optB.value = ''; optC.value = ''; optD.value = '';
                } else {
                    activeChallenges.push({
                        type: 'essay',
                        question: qText,
                        rubric: newQRubric.value.trim(),
                    });
                    newQRubric.value = '';
                }

                newQText.value = '';
                renderChallengeList();
            });

            btnSaveAllChallenges?.addEventListener('click', async () => {
                if (!currentStoreUrl) return;
                if (activeChallenges.length === 0) {
                    alert('Harap tambahkan minimal 1 soal tantangan.');
                    return;
                }

                btnSaveAllChallenges.disabled = true;
                btnSaveAllChallenges.textContent = 'Menyimpan...';

                try {
                    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    const res = await fetch(currentStoreUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ challenges: activeChallenges }),
                    });

                    const json = await res.json();
                    if (res.ok) {
                        alert(json.message || 'Tantangan guru berhasil disimpan!');
                        window.location.reload();
                    } else {
                        alert(json.message || 'Gagal menyimpan tantangan.');
                    }
                } catch (err) {
                    alert('Terjadi kesalahan saat menyimpan tantangan.');
                } finally {
                    btnSaveAllChallenges.disabled = false;
                    btnSaveAllChallenges.textContent = 'Simpan Seluruh Tantangan (Skor 100 Poin)';
                }
            });
        })();
    </script>
@endsection
