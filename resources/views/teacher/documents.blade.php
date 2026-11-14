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
            <div class="upload-field-group">
                <label for="document-file">File PDF</label>
                <input id="document-file" name="file" class="upload-file-input" type="file" accept="application/pdf,.pdf" required>
                <small class="upload-help">Maksimal 50 MB tanpa batasan karakter. Pastikan PDF memiliki teks yang dapat dipilih (bukan hasil scan foto tanpa OCR).</small>
                @error('file')<small class="upload-field-error">{{ $message }}</small>@enderror
            </div>

            <!-- Cakupan Halaman & Batas Submateri (Opsi 1) -->
            <div class="rounded-xl border border-blue-100 bg-blue-50/40 p-3.5 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-blue-900 flex items-center gap-1.5">
                        <x-icon name="book-open" class="w-4 h-4 text-blue-600"/>
                        Cakupan Halaman & Batas Submateri Pertemuan
                    </span>
                    <span class="text-[11px] text-blue-700 bg-blue-100/70 px-2 py-0.5 rounded-full font-medium">Pengaturan Cerdas</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1">
                    <!-- Pilihan Mode Halaman -->
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-slate-700">Mode Pembagian Halaman</label>
                        <div class="flex items-center gap-3">
                            <label class="inline-flex items-center gap-1.5 text-xs text-slate-700 cursor-pointer">
                                <input type="radio" name="page_scope" value="all" checked class="text-blue-600 focus:ring-blue-500" id="scope-all">
                                <span>Seluruh Halaman (Modul/Slide)</span>
                            </label>
                            <label class="inline-flex items-center gap-1.5 text-xs text-slate-700 cursor-pointer">
                                <input type="radio" name="page_scope" value="range" class="text-blue-600 focus:ring-blue-500" id="scope-range">
                                <span>Pilih Rentang Halaman (Buku Tebal)</span>
                            </label>
                        </div>
                        <small class="text-[11px] text-slate-500 block">
                            Pilih "Rentang Halaman" jika materi hanya bab tertentu. Masukkan nomor lembar sesuai urutan di aplikasi PDF (misal di Chrome/Adobe), bukan nomor cetak pojok buku.
                        </small>
                    </div>

                    <!-- Input Rentang Halaman (Tersembunyi bila Seluruh Halaman) -->
                    <div id="page-range-inputs" class="hidden flex items-center gap-2">
                        <div class="flex-1">
                            <label for="page_start" class="text-[11px] font-semibold text-slate-600 block mb-1">Dari Halaman</label>
                            <input type="number" id="page_start" name="page_start" min="1" placeholder="Contoh: 1" class="upload-field bg-white py-1.5 text-xs">
                        </div>
                        <span class="text-slate-400 font-bold mt-5">-</span>
                        <div class="flex-1">
                            <label for="page_end" class="text-[11px] font-semibold text-slate-600 block mb-1">Sampai Halaman</label>
                            <input type="number" id="page_end" name="page_end" min="1" placeholder="Contoh: 25" class="upload-field bg-white py-1.5 text-xs">
                        </div>
                    </div>
                </div>

                <!-- Input Batas Submateri Awal & Terakhir -->
                <div class="pt-2 border-t border-blue-100/60 space-y-3">
                    <div>
                        <label for="start_subtopic_heading" class="text-xs font-semibold text-slate-700 flex items-center gap-1">
                            Mulai dari Submateri Awal yang Diajarkan Hari Ini (Opsional)
                        </label>
                        <input id="start_subtopic_heading" name="start_subtopic_heading" type="text" maxlength="160" class="upload-field bg-white text-xs mt-1" placeholder="Contoh: 2. Barisan Geometri (sisa latihan/bab sebelumnya di atasnya diabaikan)">
                        <small class="text-[11px] text-slate-500 block mt-1">
                            Gunakan ini jika materi baru dimulai di tengah halaman (setelah sisa latihan bab sebelumnya). Jika dikosongkan, sistem juga cerdas mendeteksi otomatis dari judul bacaan.
                        </small>
                    </div>

                    <div>
                        <label for="last_subtopic_heading" class="text-xs font-semibold text-slate-700 flex items-center gap-1">
                            Batas Submateri Terakhir yang Diajarkan Hari Ini (Opsional)
                        </label>
                        <input id="last_subtopic_heading" name="last_subtopic_heading" type="text" maxlength="160" class="upload-field bg-white text-xs mt-1" placeholder="Contoh: B. Sifat-Sifat Eksponen (materi setelahnya otomatis diabaikan)">
                        <small class="text-[11px] text-slate-500 block mt-1">
                            Gunakan ini jika waktu kelas habis di tengah halaman. Sistem otomatis memotong materi setelah submateri ini agar soal kuis 100% adil dan sesuai apa yang diajarkan.
                        </small>
                    </div>
                </div>
            </div>

            <!-- Sakelar Tantangan Guru -->
            <input type="hidden" name="has_teacher_challenges_toggle" value="1">
            <div class="rounded-xl border border-purple-100 bg-purple-50/40 p-3 flex items-center justify-between">
                <div class="flex items-start gap-2.5">
                    <input type="checkbox" id="enable_teacher_challenges" name="enable_teacher_challenges" value="1" checked class="mt-0.5 rounded text-purple-600 focus:ring-purple-500 cursor-pointer">
                    <div>
                        <label for="enable_teacher_challenges" class="text-xs font-bold text-purple-900 cursor-pointer block">
                            Aktifkan 5 Soal Esai Tantangan Guru untuk Materi Ini
                        </label>
                        <p class="text-[11px] text-purple-700">
                            Jika dinonaktifkan, siswa hanya akan membaca materi dan mengerjakan Kuis Socrates (10 Pilihan Ganda). Anda tetap bisa mengaktifkan atau membuat soal esai kapan saja nanti.
                        </p>
                    </div>
                </div>
                <span class="rounded bg-purple-100 text-purple-800 text-[10px] font-bold px-2 py-0.5 whitespace-nowrap">Esai Analitis</span>
            </div>

            <div class="teacher-upload-footer">
                <p><x-icon name="sparkles"/> AI otomatis merapikan teks materi, menyusun 10 Kuis Socrates & 5 Tantangan Guru (HOTS).</p>
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
                        <span data-quiz-badge-doc="{{ $document->id }}" class="rounded bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700 border border-indigo-200/60 inline-flex items-center gap-1">
                            <x-icon name="gamepad-2" class="w-3 h-3 text-indigo-600"/>
                            <span data-quiz-badge-text>Kuis Socrates: {{ $document->quiz_question_count ?? 10 }} Soal (⏱️ {{ $document->quiz_duration_minutes ?? 15 }}m)</span>
                        </span>
                        <span data-doc-challenge-pill="{{ $document->id }}" class="rounded bg-purple-50 px-2 py-0.5 text-[10px] font-bold text-purple-700 border border-purple-200/60 inline-flex items-center gap-1">
                            <x-icon name="award" class="w-3 h-3 text-purple-600"/>
                            <span data-doc-challenge-pill-text>{{ $document->teacher_challenges_count }} Tantangan Guru</span>
                        </span>
                    </div>
                    <span>{{ $document->category }} · <span class="rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-semibold text-blue-700">{{ $document->targetGradeLabel() }}</span> · <span data-doc-chars-id="{{ $document->id }}">{{ number_format(mb_strlen($document->body)) }} karakter</span> · {{ $document->created_at->locale('id')->translatedFormat('d M Y') }}</span>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-purple-300 bg-purple-50 px-2.5 py-1.5 text-xs font-bold text-purple-900 hover:bg-purple-100 transition shadow-2xs" data-challenge-btn data-doc-id="{{ $document->id }}" data-doc-title="{{ $document->title }}" data-get-url="{{ route('teacher.documents.challenges.index', $document) }}" data-store-url="{{ route('teacher.documents.challenges.store', $document) }}" data-suggestions-url="{{ route('teacher.documents.challenges.suggestions', $document) }}" data-analyze-url="{{ route('teacher.documents.challenges.analyze', $document) }}" data-recommend-answer-url="{{ route('teacher.documents.challenges.recommend_answer', $document) }}" title="Kelola Tantangan Guru (Esai & PG)">
                        <x-icon name="award" class="w-3.5 h-3.5 text-purple-600"/>
                        <span data-challenge-btn-label>Tantangan Guru ({{ $document->teacher_challenges_count }})</span>
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
                        @php
                            $maxCapacity = $document->maxRecommendedQuizQuestions();
                            $idealRec = $document->idealRecommendedQuizQuestions();
                            $counts = [5, 10, 15, 20, 25, 30, 40, 50];
                        @endphp
                        <div class="flex items-center gap-1">
                            <span class="text-[10px] text-slate-400 font-bold">📝</span>
                            <select name="quiz_question_count" class="text-[11px] font-semibold py-0.5 px-1 rounded border-0 bg-transparent text-slate-700 hover:text-blue-600 cursor-pointer focus:ring-0" title="Ubah Jumlah Soal Kuis Socrates (5 - {{ $maxCapacity }} Soal disarankan)">
                                @foreach($counts as $cnt)
                                    @php
                                        $isDisabled = $cnt > $maxCapacity;
                                        $isIdeal = $cnt === $idealRec;
                                        $isSelected = ($document->quiz_question_count ?? 10) == $cnt;
                                    @endphp
                                    <option value="{{ $cnt }}" @selected($isSelected) @disabled($isDisabled) class="{{ $isDisabled ? 'text-slate-400 bg-slate-100' : '' }}">
                                        {{ $cnt }} Soal{{ $isIdeal ? ' (Rekomendasi AI ✨)' : ($isDisabled ? ' (Materi terlalu ringkas)' : '') }}
                                    </option>
                                @endforeach
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
                            'formattedBodyHtml' => $document->formatted_body_html ?? '',
                            'quizCount' => $document->quiz_question_count ?? 10,
                            'quiz' => $document->quizQuestions->values(),
                            'teacherChallenges' => $document->teacherChallenges->values(),
                            'challengeMaxXp' => $document->challenge_max_xp ?? 0,
                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
                    </script>
                    <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition" data-preview-btn data-doc-id="{{ $document->id }}" title="Pratinjau Materi & Soal">
                        <x-icon name="eye" class="w-3.5 h-3.5 text-slate-500"/>
                        <span class="hidden sm:inline">Pratinjau & Soal</span>
                    </button>
                    <form method="POST" action="{{ route('teacher.documents.destroy', $document) }}"
                          data-confirm-title="Hapus Bacaan Secara Permanen?"
                          data-confirm-subject="{{ $document->title }}"
                          data-confirm-message="Sesi baca, seluruh jawaban siswa, tantangan terkait, dan XP siswa akan ikut dihapus. Tindakan ini tidak dapat dibatalkan."
                          data-confirm-ok="Ya, Hapus Permanen"
                          data-confirm-variant="danger">
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
        <div class="fixed inset-y-0 right-0 max-w-full flex pl-0 sm:pl-10">
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
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">ISI TEKS PEMBELAJARAN SISWA</p>
                                <button type="button" id="btnToggleEditBody" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold text-blue-700 bg-blue-50 border border-blue-200 hover:bg-blue-100 transition shadow-2xs">
                                    <x-icon name="edit-3" class="w-3.5 h-3.5 text-blue-600"/>
                                    <span id="labelToggleEditBody">Edit Teks Bacaan</span>
                                </button>
                            </div>
                            <div id="modalDocBody" class="passage-markdown prose prose-sm max-w-none text-xs text-slate-700 leading-relaxed space-y-3 font-normal"></div>
                            
                            <!-- Editor Mode Form -->
                            <div id="modalDocBodyEditWrapper" class="hidden space-y-2 mt-2">
                                <textarea id="modalDocBodyTextarea" rows="14" class="w-full rounded-xl border border-slate-300 p-3 text-xs font-mono text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 leading-relaxed" placeholder="Edit teks bacaan kurasi..."></textarea>
                                <div class="flex items-center justify-between pt-1">
                                    <span class="text-[11px] text-slate-400 font-mono" id="modalDocBodyCharCount">0 karakter</span>
                                    <div class="flex items-center gap-2">
                                        <button type="button" id="btnCancelEditBody" class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">Batal</button>
                                        <button type="button" id="btnSaveEditBody" class="px-3.5 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-2xs">
                                            <x-icon name="check" class="w-3.5 h-3.5"/>
                                            <span id="labelSaveEditBody">Simpan Perubahan Teks</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
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
                <!-- Info Alert Skoring 100 Poin & Bobot Kesulitan -->
                <div class="rounded-xl border border-purple-200 bg-purple-50/80 p-4 text-xs text-purple-950 flex items-start gap-3">
                    <x-icon name="sparkles" class="w-5 h-5 text-purple-600 shrink-0 mt-0.5" />
                    <div class="leading-relaxed">
                        <strong class="font-bold block text-purple-900 mb-1">💡 Penilaian Otomatis Proporsional (Total 100 Poin) & Reward Socrates:</strong>
                        Skor maksimal tetap <strong>100 Poin</strong> dan didistribusikan otomatis sesuai tingkat kesulitan tiap soal (<strong>Easy = bobot 1</strong>, <strong>Medium = bobot 2</strong>, <strong>Hard = bobot 3</strong>).
                        Setiap soal yang Anda buat sendiri juga dianalisis kesulitannya oleh Socrates beserta estimasi <strong>Reward XP</strong> siswa.
                    </div>
                </div>

                <!-- Rekomendasi Soal Otomatis dari AI -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-xl bg-amber-50/90 border border-amber-200 text-xs">
                    <div class="flex items-start gap-2">
                        <span class="text-amber-600 text-base leading-none mt-0.5">💡</span>
                        <div>
                            <span class="font-bold text-amber-950 block">Butuh rekomendasi soal kritis dari Socrates AI?</span>
                            <span class="text-slate-600 text-[11px]">Socrates menganalisis keseluruhan materi untuk menyusun soal kritis (Easy–Hard) beserta kunci jawaban langsung yang akurat.</span>
                        </div>
                    </div>
                    <button type="button" id="btnLoadSuggestions" class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs shadow-2xs transition">
                        <span>✨ Muat Rekomendasi Soal</span>
                    </button>
                </div>

                <!-- List of Existing Questions -->
                <div>
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">Daftar Soal Tantangan (<span id="challengeCountDisplay">0 Soal</span>)</h4>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span id="challengeMaxXpDisplay" class="rounded-full bg-blue-50 border border-blue-200 px-2.5 py-0.5 text-[10px] font-bold text-blue-700">Reward: +0 XP</span>
                            <span id="pointsPerQDisplay" class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-bold text-slate-700">Total 100 Poin</span>
                            <button type="button" id="btnClearAllChallenges" class="hidden text-rose-600 hover:text-rose-800 text-xs font-bold transition px-1.5 py-0.5 rounded hover:bg-rose-50">
                                Hapus Semua
                            </button>
                        </div>
                    </div>
                    <div id="challengeListContainer" class="space-y-3">
                        <!-- Rendered dynamically -->
                    </div>
                </div>

                <!-- Form Tambah Soal Baru -->
                <div class="rounded-2xl border border-slate-200/80 bg-slate-50/60 p-4 sm:p-5">
                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-3">Tambah Soal Baru</h4>

                    <!-- Type Radio Selector -->
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700">
                                <input type="radio" name="new_q_type" value="essay" checked class="text-purple-600 focus:ring-purple-500" id="typeEssayRadio">
                                <span>📝 Soal Esai (Analisis Pemahaman)</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700">
                                <input type="radio" name="new_q_type" value="multiple_choice" class="text-purple-600 focus:ring-purple-500" id="typePGRadio">
                                <span>🔘 Pilihan Ganda (A/B/C/D)</span>
                            </label>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <label for="newQDifficulty" class="text-[11px] font-bold text-slate-500">Kesulitan:</label>
                            <select id="newQDifficulty" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold text-slate-700">
                                <option value="auto" selected>✨ Otomatis (Analisis Socrates)</option>
                                <option value="easy">Easy (Bobot 1)</option>
                                <option value="medium">Medium (Bobot 2)</option>
                                <option value="hard">Hard (Bobot 3)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Pertanyaan Input -->
                    <div class="space-y-1 mb-3">
                        <label for="newQText" class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Pertanyaan:</label>
                        <textarea id="newQText" rows="3" class="w-full rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-800 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 outline-none" placeholder="Tuliskan pertanyaan tantangan secara langsung... (Kunci jawaban akan otomatis direkomendasikan dari materi)"></textarea>
                    </div>

                    <!-- Esai Fields (Kunci Jawaban Langsung) -->
                    <div id="essayFields" class="space-y-1.5 mb-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2 flex-wrap">
                                <label for="newQRubric" class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Kunci Jawaban:</label>
                                <span id="rubricAutoStatus" class="hidden text-[10px] font-semibold text-purple-700 bg-purple-50 border border-purple-200/80 rounded-full px-2 py-0.5"></span>
                            </div>
                            <div class="flex items-center gap-2.5">
                                <button type="button" id="btnRecommendRubric" class="text-[11px] font-bold text-purple-600 hover:text-purple-800 transition">
                                    ✨ Rekomendasikan dari Materi
                                </button>
                                <button type="button" id="btnClearRubric" data-clear-rubric class="text-rose-600 hover:text-rose-800 text-xs font-bold transition">
                                    Hapus
                                </button>
                            </div>
                        </div>
                        <textarea id="newQRubric" rows="3" class="w-full rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-800 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 outline-none" placeholder="Kunci jawaban akan terisi otomatis dari materi saat Anda menuliskan pertanyaan di atas, atau tulis/ubah sendiri kunci jawaban di sini..."></textarea>
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

            let currentEditingDocId = null;
            let currentRawBody = '';
            const toggleEditBtn = document.getElementById('btnToggleEditBody');
            const cancelEditBtn = document.getElementById('btnCancelEditBody');
            const saveEditBtn = document.getElementById('btnSaveEditBody');
            const editWrapper = document.getElementById('modalDocBodyEditWrapper');
            const textarea = document.getElementById('modalDocBodyTextarea');
            const charCountEl = document.getElementById('modalDocBodyCharCount');
            const labelSave = document.getElementById('labelSaveEditBody');

            const updateBodyCharCount = (text) => {
                if (charCountEl) charCountEl.textContent = `${(text || '').length.toLocaleString()} karakter`;
            };

            textarea?.addEventListener('input', () => {
                updateBodyCharCount(textarea.value);
            });

            toggleEditBtn?.addEventListener('click', () => {
                const bodyEl = document.getElementById('modalDocBody');
                const isEditing = !editWrapper.classList.contains('hidden');
                if (isEditing) {
                    editWrapper.classList.add('hidden');
                    bodyEl?.classList.remove('hidden');
                } else {
                    textarea.value = currentRawBody;
                    updateBodyCharCount(currentRawBody);
                    bodyEl?.classList.add('hidden');
                    editWrapper.classList.remove('hidden');
                    textarea.focus();
                }
            });

            cancelEditBtn?.addEventListener('click', () => {
                const bodyEl = document.getElementById('modalDocBody');
                editWrapper?.classList.add('hidden');
                bodyEl?.classList.remove('hidden');
            });

            saveEditBtn?.addEventListener('click', async () => {
                if (!currentEditingDocId) return;
                const newBody = textarea.value.trim();
                if (newBody.length < 30) {
                    if (window.SocratesUI?.toast) {
                        window.SocratesUI.toast('Teks bacaan minimal berisi 30 karakter.', 'warning');
                    }
                    return;
                }

                saveEditBtn.disabled = true;
                if (labelSave) labelSave.textContent = 'Menyimpan...';

                try {
                    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    const res = await fetch(`/teacher/documents/${currentEditingDocId}/body`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token || '',
                        },
                        body: JSON.stringify({ body: newBody }),
                    });

                    const json = await res.json();
                    if (res.ok && json.success) {
                        currentRawBody = json.body;
                        const bodyEl = document.getElementById('modalDocBody');
                        if (bodyEl) {
                            bodyEl.innerHTML = json.formatted_body_html;
                            bodyEl.classList.remove('hidden');
                        }
                        editWrapper?.classList.add('hidden');

                        const scriptEl = document.getElementById('doc-preview-data-' + currentEditingDocId);
                        if (scriptEl) {
                            try {
                                const parsed = JSON.parse(scriptEl.textContent);
                                parsed.body = json.body;
                                parsed.formattedBodyHtml = json.formatted_body_html;
                                scriptEl.textContent = JSON.stringify(parsed);
                            } catch (e) {}
                        }

                        const charBadge = document.querySelector(`[data-doc-chars-id="${currentEditingDocId}"]`);
                        if (charBadge && json.chars) {
                            charBadge.textContent = `${json.chars.toLocaleString()} karakter`;
                        }

                        setTimeout(() => renderKaTeXIn(modal), 40);

                        if (labelSave) labelSave.textContent = 'Tersimpan ✓';
                        setTimeout(() => {
                            if (labelSave) labelSave.textContent = 'Simpan Perubahan Teks';
                            saveEditBtn.disabled = false;
                        }, 1200);
                    } else {
                        if (window.SocratesUI?.toast) {
                            window.SocratesUI.toast(json.message || 'Gagal menyimpan perubahan teks bacaan.', 'error');
                        }
                        saveEditBtn.disabled = false;
                        if (labelSave) labelSave.textContent = 'Simpan Perubahan Teks';
                    }
                } catch (err) {
                    console.error('Save body error', err);
                    if (window.SocratesUI?.toast) {
                        window.SocratesUI.toast('Terjadi kesalahan koneksi saat menyimpan teks bacaan.', 'error');
                    }
                    saveEditBtn.disabled = false;
                    if (labelSave) labelSave.textContent = 'Simpan Perubahan Teks';
                }
            });

            const renderKaTeXIn = (container) => {
                if (typeof window.renderMathInElement === 'function' && container) {
                    try {
                        window.renderMathInElement(container, {
                            delimiters: [
                                { left: '$$', right: '$$', display: true },
                                { left: '$', right: '$', display: false },
                                { left: '\\(', right: '\\)', display: false },
                                { left: '\\[', right: '\\]', display: true }
                            ],
                            throwOnError: false
                        });
                    } catch (e) {
                        console.warn('KaTeX render warning:', e);
                    }
                }
            };

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

                setTimeout(() => renderKaTeXIn(modal), 40);
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

                // Render structured body in Preview Drawer
                currentEditingDocId = docId;
                currentRawBody = body;
                if (editWrapper) editWrapper.classList.add('hidden');
                if (textarea) textarea.value = body;
                updateBodyCharCount(body);

                const bodyEl = document.getElementById('modalDocBody');
                if (bodyEl) {
                    bodyEl.classList.remove('hidden');
                    bodyEl.innerHTML = '';
                }
                if (data.formattedBodyHtml) {
                    if (bodyEl) bodyEl.innerHTML = data.formattedBodyHtml;
                } else {
                    const paragraphs = body.split(/\r?\n\r?\n/).filter(p => p.trim() !== '');
                    if (paragraphs.length === 0) {
                        if (bodyEl) bodyEl.innerHTML = `<p>${formatInlineMarkdown(body)}</p>`;
                    } else {
                        paragraphs.forEach(p => {
                            const pEl = document.createElement('p');
                            pEl.innerHTML = formatInlineMarkdown(p.trim());
                            if (bodyEl) bodyEl.appendChild(pEl);
                        });
                    }
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
                    const diffBadgeMap = {
                        easy: { label: 'Easy', cls: 'bg-emerald-100 text-emerald-800' },
                        medium: { label: 'Medium', cls: 'bg-amber-100 text-amber-800' },
                        hard: { label: 'Hard', cls: 'bg-rose-100 text-rose-800' },
                    };
                    teacherChallenges.forEach((item, idx) => {
                        const seq = item.sequence || (idx + 1);
                        const isEssay = item.type === 'essay';
                        const diffKey = String(item.difficulty || 'medium').toLowerCase();
                        const diffBadge = diffBadgeMap[diffKey] || diffBadgeMap.medium;
                        const pts = item.points_max !== undefined ? Number(item.points_max).toFixed(2) : (100 / teacherChallenges.length).toFixed(2);
                        const card = document.createElement('div');
                        card.className = 'rounded-xl border border-slate-200/80 bg-slate-50/70 p-4 space-y-2.5';

                        let contentHtml = '';
                        if (isEssay) {
                            contentHtml = `
                                <h4 class="text-xs font-bold text-slate-900 leading-snug">${item.question}</h4>
                                ${item.rubric ? `
                                    <div class="rounded-lg bg-purple-50/70 border border-purple-200/60 p-2.5 text-[11px] text-purple-950 flex items-start gap-1.5 mt-2">
                                        <span class="font-bold text-purple-700 shrink-0">🔑 Kunci Jawaban:</span>
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
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-1.5">
                                    <span class="rounded bg-slate-200/80 text-slate-800 text-[10px] font-bold px-2 py-0.5">#${seq}</span>
                                    <span class="rounded ${isEssay ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'} text-[10px] font-bold px-2 py-0.5 uppercase">
                                        ${isEssay ? 'Esai Analisis' : 'Pilihan Ganda'}
                                    </span>
                                    <span class="rounded ${diffBadge.cls} text-[10px] font-bold px-2 py-0.5 uppercase">${diffBadge.label}</span>
                                </div>
                                <span class="text-[10px] text-slate-500 font-semibold">Bobot: ${pts} Poin</span>
                            </div>
                            ${contentHtml}
                        `;
                        challengesList.appendChild(card);
                    });
                }

                switchTab('text');
                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
                setTimeout(() => renderKaTeXIn(modal), 60);
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
                        selects.forEach(s => { s.disabled = true; });
                        if (savedPill) {
                            savedPill.textContent = '⏳ Menyimpan...';
                            savedPill.classList.remove('hidden', 'text-emerald-600', 'bg-emerald-50', 'text-rose-600', 'bg-rose-50');
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
                                const docId = form.dataset.docId;
                                const badgeText = document.querySelector(`[data-quiz-badge-doc="${docId}"] [data-quiz-badge-text]`);
                                if (badgeText && json.quiz_question_count && json.quiz_duration_minutes) {
                                    badgeText.textContent = `Kuis Socrates: ${json.quiz_question_count} Soal (⏱️ ${json.quiz_duration_minutes}m)`;
                                }
                                if (savedPill) {
                                    savedPill.textContent = `✓ ${json.quiz_question_count || ''} Soal Tersimpan`;
                                    savedPill.classList.remove('text-amber-700', 'bg-amber-50', 'text-rose-600', 'bg-rose-50');
                                    savedPill.classList.add('text-emerald-600', 'bg-emerald-50');
                                    setTimeout(() => savedPill.classList.add('hidden'), 2500);
                                }
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
                                    savedPill.classList.remove('text-amber-700', 'bg-amber-50', 'text-emerald-600', 'bg-emerald-50');
                                    savedPill.classList.add('text-rose-600', 'bg-rose-50');
                                    setTimeout(() => savedPill.classList.add('hidden'), 3000);
                                }
                            }
                        } catch (err) {
                            console.error('Gagal memperbarui pengaturan kuis:', err);
                            if (savedPill) {
                                savedPill.textContent = '⚠️ Error';
                                savedPill.classList.remove('text-amber-700', 'bg-amber-50', 'text-emerald-600', 'bg-emerald-50');
                                savedPill.classList.add('text-rose-600', 'bg-rose-50');
                                setTimeout(() => savedPill.classList.add('hidden'), 3000);
                            }
                        } finally {
                            selects.forEach(s => { s.disabled = false; });
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
            const challengeMaxXpDisplay = document.getElementById('challengeMaxXpDisplay');

            const typeEssayRadio = document.getElementById('typeEssayRadio');
            const typePGRadio = document.getElementById('typePGRadio');
            const essayFields = document.getElementById('essayFields');
            const pgFields = document.getElementById('pgFields');

            const newQText = document.getElementById('newQText');
            const newQDifficulty = document.getElementById('newQDifficulty');
            const newQRubric = document.getElementById('newQRubric');
            const rubricAutoStatus = document.getElementById('rubricAutoStatus');
            const btnRecommendRubric = document.getElementById('btnRecommendRubric');
            const btnClearRubric = document.getElementById('btnClearRubric');
            const btnClearAllChallenges = document.getElementById('btnClearAllChallenges');
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
            let currentAnalyzeUrl = null;
            let currentRecommendAnswerUrl = null;
            let activeChallenges = [];
            let editingIndex = null;

            const escapeHtml = (str) => String(str ?? '').replace(/[&<>"']/g, ch => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
            }[ch]));

            const notify = (message, type = 'info', duration = 3800) => {
                if (window.SocratesUI?.toast) {
                    window.SocratesUI.toast(message, type, duration);
                }
            };

            const askConfirm = (options) => {
                if (window.SocratesUI?.confirm) {
                    return window.SocratesUI.confirm(options);
                }
                return Promise.resolve(false);
            };

            let recommendTimer = null;
            let recommendAbortController = null;
            let lastRecommendedQuestion = '';
            let lastRecommendedExcerpt = '';
            let rubricManuallyEdited = false;
            let rubricDismissedForQuestion = false;

            const setRubricStatus = (text, show = true) => {
                if (!rubricAutoStatus) return;
                if (!show || !text) {
                    rubricAutoStatus.classList.add('hidden');
                    rubricAutoStatus.textContent = '';
                    return;
                }
                rubricAutoStatus.textContent = text;
                rubricAutoStatus.classList.remove('hidden');
            };

            const resetNewQuestionFormState = () => {
                if (recommendTimer) {
                    clearTimeout(recommendTimer);
                    recommendTimer = null;
                }
                if (recommendAbortController) {
                    recommendAbortController.abort();
                    recommendAbortController = null;
                }
                lastRecommendedQuestion = '';
                lastRecommendedExcerpt = '';
                rubricManuallyEdited = false;
                rubricDismissedForQuestion = false;
                setRubricStatus('', false);
            };

            const requestRecommendedAnswer = async (questionText, signal = undefined) => {
                if (!currentRecommendAnswerUrl) return null;
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const res = await fetch(currentRecommendAnswerUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ question: questionText }),
                    signal,
                });
                if (!res.ok) return null;
                return await res.json();
            };

            const resolveDifficultyForItem = async (chosenDiff, itemPayload) => {
                if (chosenDiff !== 'auto') {
                    return { difficulty: chosenDiff, difficulty_weight: diffWeights[chosenDiff] || 2 };
                }
                if (!currentAnalyzeUrl) {
                    return { difficulty: 'medium', difficulty_weight: 2 };
                }
                const controller = new AbortController();
                const timer = setTimeout(() => controller.abort(), 4500);
                try {
                    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    const res = await fetch(currentAnalyzeUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                        body: JSON.stringify(itemPayload),
                        signal: controller.signal,
                    });
                    if (res.ok) {
                        const analyzed = await res.json();
                        return {
                            difficulty: analyzed.difficulty || 'medium',
                            difficulty_weight: analyzed.difficulty_weight || 2,
                        };
                    }
                } catch (_) {
                    // Fallback instan jika jaringan/AI lambat
                } finally {
                    clearTimeout(timer);
                }
                return { difficulty: 'medium', difficulty_weight: 2 };
            };

            const fetchRecommendedAnswer = async (force = false) => {
                if (!currentRecommendAnswerUrl || !typeEssayRadio?.checked) return;
                const qText = (newQText?.value || '').trim();
                if (qText.length < 8) return;
                if (!force && (rubricManuallyEdited || rubricDismissedForQuestion)) return;
                if (!force && qText === lastRecommendedQuestion && newQRubric.value.trim() !== '') return;

                if (recommendAbortController) {
                    recommendAbortController.abort();
                }
                recommendAbortController = new AbortController();

                setRubricStatus('⏳ Mencari jawaban dari materi...', true);
                try {
                    const json = await requestRecommendedAnswer(qText, recommendAbortController.signal);
                    if (!json) {
                        setRubricStatus('', false);
                        return;
                    }
                    if ((newQText?.value || '').trim() !== qText) return;
                    if (!force && rubricManuallyEdited) return;

                    if (json.recommended_answer) {
                        newQRubric.value = json.recommended_answer;
                        lastRecommendedQuestion = qText;
                        lastRecommendedExcerpt = json.reference_excerpt || '';
                        rubricManuallyEdited = false;
                        rubricDismissedForQuestion = false;
                        setRubricStatus('✨ Rekomendasi otomatis dari materi — silakan ubah atau klik Hapus', true);
                    } else {
                        setRubricStatus('', false);
                    }
                } catch (err) {
                    if (err?.name !== 'AbortError') {
                        setRubricStatus('', false);
                    }
                }
            };

            newQText?.addEventListener('input', () => {
                const qText = newQText.value.trim();
                if (qText !== lastRecommendedQuestion) {
                    rubricDismissedForQuestion = false;
                }
                if (recommendTimer) {
                    clearTimeout(recommendTimer);
                }
                if (!typeEssayRadio?.checked || qText.length < 8 || rubricManuallyEdited) {
                    return;
                }
                recommendTimer = setTimeout(() => fetchRecommendedAnswer(false), 650);
            });

            newQText?.addEventListener('blur', () => {
                const qText = newQText.value.trim();
                if (typeEssayRadio?.checked && qText.length >= 8 && !rubricManuallyEdited && !rubricDismissedForQuestion && newQRubric.value.trim() === '') {
                    fetchRecommendedAnswer(false);
                }
            });

            newQRubric?.addEventListener('input', () => {
                const hasText = newQRubric.value.trim() !== '';
                rubricManuallyEdited = hasText;
                if (hasText) {
                    setRubricStatus('✎ Diedit manual', true);
                } else {
                    setRubricStatus('', false);
                }
            });

            btnClearRubric?.addEventListener('click', () => {
                newQRubric.value = '';
                lastRecommendedExcerpt = '';
                rubricManuallyEdited = false;
                rubricDismissedForQuestion = true;
                setRubricStatus('', false);
                newQRubric.focus();
            });

            btnRecommendRubric?.addEventListener('click', () => {
                const qText = (newQText?.value || '').trim();
                if (qText.length < 5) {
                    notify('Tuliskan pertanyaan terlebih dahulu agar Socrates dapat merekomendasikan kunci jawabannya dari materi.', 'warning');
                    newQText?.focus();
                    return;
                }
                rubricManuallyEdited = false;
                rubricDismissedForQuestion = false;
                fetchRecommendedAnswer(true);
            });

            const diffWeights = { easy: 1, medium: 2, hard: 3 };
            const diffBadgeStyles = {
                easy: { label: 'Easy', cls: 'bg-emerald-100 text-emerald-800 border border-emerald-200' },
                medium: { label: 'Medium', cls: 'bg-amber-100 text-amber-800 border border-amber-200' },
                hard: { label: 'Hard', cls: 'bg-rose-100 text-rose-800 border border-rose-200' },
            };

            const computeChallengeMetrics = (list) => {
                if (!list.length) return { points: [], maxXp: 0 };
                let totalWeight = 0;
                let xp = 15;
                const weights = list.map(item => {
                    const diff = String(item.difficulty || 'medium').toLowerCase();
                    const w = Number(item.difficulty_weight || diffWeights[diff] || 2);
                    totalWeight += w;
                    xp += diff === 'easy' ? 3 : (diff === 'hard' ? 8 : 5);
                    return w;
                });
                let running = 0;
                const points = weights.map((w, idx) => {
                    if (idx === weights.length - 1) {
                        return Math.max(0, Number((100 - running).toFixed(2)));
                    }
                    const p = Number(((w / Math.max(1, totalWeight)) * 100).toFixed(2));
                    running += p;
                    return p;
                });
                return { points, maxXp: Math.min(75, Math.max(20, xp)) };
            };

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

            const buildEditCardDom = (card, item, index, metrics) => {
                card.className = 'p-4 rounded-xl border-2 border-purple-300 bg-purple-50/30 shadow-sm space-y-3';
                card.dataset.editingCard = '1';
                const isEssay = item.type === 'essay';
                const diffKey = String(item.difficulty || 'medium').toLowerCase();
                const originalQuestion = String(item.question || '').trim();
                const originalRubric = String(item.rubric || '').trim();
                card._editPendingExcerpt = item.reference_excerpt || '';
                card._editUsedCopilot = false;

                const header = document.createElement('div');
                header.className = 'flex flex-wrap items-center justify-between gap-2 pb-2 border-b border-purple-200/70';
                header.innerHTML = `
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="rounded bg-slate-200 text-slate-800 font-bold text-xs px-2 py-0.5">#${index + 1}</span>
                        <span class="rounded ${isEssay ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'} text-[10px] font-bold px-2 py-0.5 uppercase">
                            ${isEssay ? 'Esai Analisis' : 'Pilihan Ganda'}
                        </span>
                        <span class="rounded bg-purple-600 text-white text-[10px] font-bold px-2 py-0.5 uppercase">Mode Edit</span>
                    </div>
                    <button type="button" data-edit-cancel class="text-slate-500 hover:text-slate-800 text-xs font-semibold px-2 py-1 rounded-lg hover:bg-slate-100 transition cursor-pointer">
                        Batal
                    </button>
                `;

                const diffWrap = document.createElement('div');
                diffWrap.className = 'flex flex-wrap items-center gap-2';
                diffWrap.innerHTML = `
                    <label class="text-[11px] font-bold text-slate-600 uppercase">Kesulitan:</label>
                    <select data-edit-difficulty class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 focus:border-purple-400 focus:outline-hidden">
                        <option value="easy">🟢 Easy (Bobot 1 - Dasar)</option>
                        <option value="medium">🟡 Medium (Bobot 2 - Pemahaman/Analisis)</option>
                        <option value="hard">🔴 Hard (Bobot 3 - Evaluasi/Kasus)</option>
                        <option value="auto">✨ Otomatis (Analisis Ulang)</option>
                    </select>
                `;
                diffWrap.querySelector('[data-edit-difficulty]').value = ['easy', 'medium', 'hard'].includes(diffKey) ? diffKey : 'medium';

                const qWrap = document.createElement('div');
                qWrap.className = 'space-y-1';
                qWrap.innerHTML = `<label class="block text-[11px] font-bold text-slate-600 uppercase">Pertanyaan:</label>`;
                const qInput = document.createElement('textarea');
                qInput.rows = 2;
                qInput.dataset.editQuestion = '1';
                qInput.className = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 leading-relaxed focus:border-purple-400 focus:outline-hidden';
                qInput.placeholder = 'Tuliskan teks pertanyaan...';
                qInput.value = item.question || '';
                qWrap.appendChild(qInput);

                card.append(header, diffWrap, qWrap);

                if (isEssay) {
                    const rubricWrap = document.createElement('div');
                    rubricWrap.className = 'space-y-1.5';
                    rubricWrap.innerHTML = `
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <div class="flex items-center gap-2 flex-wrap">
                                <label class="text-[11px] font-bold text-purple-800 uppercase">🔑 Kunci Jawaban:</label>
                                <span data-edit-rubric-status class="hidden text-[10px] font-semibold text-purple-700 bg-purple-100 px-2 py-0.5 rounded-full border border-purple-200"></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" data-edit-recommend-rubric class="text-[11px] font-semibold text-purple-700 hover:text-purple-900 bg-purple-50 hover:bg-purple-100 border border-purple-200 px-2 py-0.5 rounded-md transition cursor-pointer">
                                    ✨ Rekomendasikan dari Materi
                                </button>
                                <button type="button" data-edit-clear-rubric class="text-[11px] font-bold text-rose-600 hover:text-rose-800 hover:bg-rose-50 px-2 py-0.5 rounded-md transition cursor-pointer">
                                    Hapus
                                </button>
                            </div>
                        </div>
                    `;
                    const rubricInput = document.createElement('textarea');
                    rubricInput.rows = 3;
                    rubricInput.dataset.editRubric = '1';
                    rubricInput.className = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 leading-relaxed focus:border-purple-400 focus:outline-hidden';
                    rubricInput.placeholder = 'Tuliskan kunci jawaban dari materi atau klik Rekomendasikan dari Materi...';
                    rubricInput.value = item.rubric || '';
                    rubricWrap.appendChild(rubricInput);

                    const statusBadge = rubricWrap.querySelector('[data-edit-rubric-status]');
                    const btnRec = rubricWrap.querySelector('[data-edit-recommend-rubric]');
                    const btnClear = rubricWrap.querySelector('[data-edit-clear-rubric]');

                    btnClear.addEventListener('click', () => {
                        rubricInput.value = '';
                        card._editPendingExcerpt = '';
                        card._editUsedCopilot = false;
                        statusBadge.classList.add('hidden');
                        rubricInput.focus();
                    });

                    btnRec.addEventListener('click', async () => {
                        const qVal = qInput.value.trim();
                        if (qVal.length < 5) {
                            notify('Tuliskan pertanyaan minimal 5 karakter sebelum meminta rekomendasi kunci jawaban.', 'warning');
                            qInput.focus();
                            return;
                        }
                        const origLabel = btnRec.textContent;
                        btnRec.disabled = true;
                        btnRec.textContent = '⏳ Mencari...';
                        try {
                            const json = await requestRecommendedAnswer(qVal);
                            if (json?.recommended_answer) {
                                rubricInput.value = json.recommended_answer;
                                card._editPendingExcerpt = json.reference_excerpt || '';
                                card._editUsedCopilot = true;
                                statusBadge.textContent = '✨ Rekomendasi materi dimuat';
                                statusBadge.classList.remove('hidden');
                            } else {
                                notify('Belum ditemukan kecocokan langsung di teks untuk pertanyaan tersebut.', 'info');
                            }
                        } catch (_) {
                            notify('Gagal memuat rekomendasi jawaban dari materi.', 'error');
                        } finally {
                            btnRec.disabled = false;
                            btnRec.textContent = origLabel;
                        }
                    });

                    card.appendChild(rubricWrap);
                } else {
                    const optsGrid = document.createElement('div');
                    optsGrid.className = 'grid grid-cols-1 sm:grid-cols-2 gap-2';
                    ['A', 'B', 'C', 'D'].forEach((letter) => {
                        const optRow = document.createElement('div');
                        optRow.className = 'flex items-center gap-1.5';
                        const badge = document.createElement('span');
                        badge.className = 'w-6 h-6 rounded-md bg-slate-200 text-slate-700 text-xs font-bold grid place-items-center shrink-0';
                        badge.textContent = letter;
                        const input = document.createElement('input');
                        input.type = 'text';
                        input.dataset.editOpt = letter;
                        input.className = 'w-full rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-800 focus:border-purple-400 focus:outline-hidden';
                        input.placeholder = `Opsi ${letter}...`;
                        input.value = item.options?.[letter] || '';
                        optRow.append(badge, input);
                        optsGrid.appendChild(optRow);
                    });

                    const correctWrap = document.createElement('div');
                    correctWrap.className = 'flex items-center gap-2';
                    correctWrap.innerHTML = `
                        <label class="text-[11px] font-bold text-emerald-700 uppercase">Kunci Benar:</label>
                        <select data-edit-correct class="rounded-lg border border-emerald-200 bg-white px-2.5 py-1 text-xs font-bold text-emerald-800 focus:border-emerald-400 focus:outline-hidden">
                            <option value="A">Pilihan A</option>
                            <option value="B">Pilihan B</option>
                            <option value="C">Pilihan C</option>
                            <option value="D">Pilihan D</option>
                        </select>
                    `;
                    correctWrap.querySelector('[data-edit-correct]').value = String(item.correct_answer || 'A').toUpperCase();

                    card.append(optsGrid, correctWrap);
                }

                const footer = document.createElement('div');
                footer.className = 'flex items-center justify-between gap-2 pt-2 border-t border-purple-200/70 flex-wrap';
                footer.innerHTML = `
                    <span class="text-[11px] text-slate-500">Bobot saat ini: <strong>${(metrics.points[index] ?? 0).toFixed(2)} Poin</strong></span>
                    <div class="flex items-center gap-2">
                        <button type="button" data-edit-cancel class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100 transition cursor-pointer">
                            Batal
                        </button>
                        <button type="button" data-edit-apply class="rounded-lg bg-purple-600 hover:bg-purple-700 px-3.5 py-1.5 text-xs font-bold text-white shadow-2xs transition cursor-pointer">
                            ✓ Terapkan Perubahan
                        </button>
                    </div>
                `;
                card.appendChild(footer);

                card.querySelectorAll('[data-edit-cancel]').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        editingIndex = null;
                        renderChallengeList();
                    });
                });

                card.querySelector('[data-edit-apply]').addEventListener('click', async () => {
                    const applyBtn = card.querySelector('[data-edit-apply]');
                    const prevText = applyBtn.textContent;
                    applyBtn.disabled = true;
                    applyBtn.textContent = 'Menerapkan...';
                    const ok = await commitEditCard(card, index, originalQuestion, originalRubric);
                    if (ok) {
                        editingIndex = null;
                        renderChallengeList();
                        notify(`Perubahan soal #${index + 1} diterapkan ke daftar.`, 'success', 2600);
                    } else {
                        applyBtn.disabled = false;
                        applyBtn.textContent = prevText;
                    }
                });
            };

            const commitEditCard = async (card, index, originalQuestion = '', originalRubric = '') => {
                const item = activeChallenges[index];
                if (!card || !item) return true;

                const qInput = card.querySelector('[data-edit-question]');
                const qText = (qInput?.value || '').trim();
                if (!qText) {
                    notify('Pertanyaan soal tidak boleh kosong.', 'warning');
                    qInput?.focus();
                    return false;
                }

                const chosenDiff = card.querySelector('[data-edit-difficulty]')?.value || 'medium';
                const updated = {
                    ...item,
                    question: qText,
                    context: null,
                };

                if (item.type === 'essay') {
                    const rubricText = (card.querySelector('[data-edit-rubric]')?.value || '').trim();
                    updated.rubric = rubricText;
                    if (card._editUsedCopilot) {
                        updated.reference_excerpt = card._editPendingExcerpt || null;
                    } else if (qText !== originalQuestion || rubricText !== originalRubric) {
                        updated.reference_excerpt = null;
                    }
                } else {
                    const optAVal = (card.querySelector('[data-edit-opt="A"]')?.value || '').trim();
                    const optBVal = (card.querySelector('[data-edit-opt="B"]')?.value || '').trim();
                    const optCVal = (card.querySelector('[data-edit-opt="C"]')?.value || '').trim();
                    const optDVal = (card.querySelector('[data-edit-opt="D"]')?.value || '').trim();
                    if (!optAVal || !optBVal) {
                        notify('Harap isi minimal Opsi A dan Opsi B.', 'warning');
                        return false;
                    }
                    updated.options = { A: optAVal, B: optBVal, C: optCVal, D: optDVal };
                    updated.correct_answer = card.querySelector('[data-edit-correct]')?.value || 'A';
                }

                const diffData = await resolveDifficultyForItem(chosenDiff, updated);
                updated.difficulty = diffData.difficulty;
                updated.difficulty_weight = diffData.difficulty_weight;
                activeChallenges[index] = updated;
                return true;
            };

            const renderChallengeList = () => {
                challengeListContainer.innerHTML = '';
                const total = activeChallenges.length;
                challengeCountDisplay.textContent = `${total} Soal`;
                btnClearAllChallenges?.classList.toggle('hidden', total === 0);
                const metrics = computeChallengeMetrics(activeChallenges);
                pointsPerQDisplay.textContent = total > 0 ? `Total 100 Poin (Proporsional)` : `0 Poin`;
                if (challengeMaxXpDisplay) {
                    challengeMaxXpDisplay.textContent = `Reward Maks: +${metrics.maxXp} XP`;
                }

                if (total === 0) {
                    challengeListContainer.innerHTML = `
                        <div class="p-4 text-center rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-400">
                            Belum ada soal tantangan. Muat rekomendasi Socrates AI atau tambahkan soal di bawah!
                        </div>
                    `;
                    return;
                }

                activeChallenges.forEach((item, index) => {
                    const card = document.createElement('div');
                    if (editingIndex === index) {
                        buildEditCardDom(card, item, index, metrics);
                        challengeListContainer.appendChild(card);
                        return;
                    }

                    card.className = 'p-3.5 rounded-xl border border-slate-200 bg-white shadow-2xs space-y-2';
                    const isEssay = item.type === 'essay';
                    const diffKey = String(item.difficulty || 'medium').toLowerCase();
                    const diffInfo = diffBadgeStyles[diffKey] || diffBadgeStyles.medium;
                    const itemPoints = (metrics.points[index] ?? 0).toFixed(2);

                    let contentHtml = `<p class="text-xs text-slate-800 font-semibold leading-snug">${escapeHtml(item.question)}</p>`;
                    if (isEssay && item.rubric) {
                        contentHtml += `<p class="text-[11px] text-purple-800 bg-purple-50/70 p-2.5 rounded-lg border border-purple-100"><strong>🔑 Kunci Jawaban:</strong> ${escapeHtml(item.rubric)}</p>`;
                    } else if (!isEssay && item.options) {
                        contentHtml += `
                            <div class="grid grid-cols-2 gap-1.5 text-[11px] text-slate-600 mt-1">
                                <span class="${item.correct_answer === 'A' ? 'font-bold text-emerald-700' : ''}">A: ${escapeHtml(item.options.A || '')}</span>
                                <span class="${item.correct_answer === 'B' ? 'font-bold text-emerald-700' : ''}">B: ${escapeHtml(item.options.B || '')}</span>
                                <span class="${item.correct_answer === 'C' ? 'font-bold text-emerald-700' : ''}">C: ${escapeHtml(item.options.C || '')}</span>
                                <span class="${item.correct_answer === 'D' ? 'font-bold text-emerald-700' : ''}">D: ${escapeHtml(item.options.D || '')}</span>
                            </div>
                        `;
                    }

                    card.innerHTML = `
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="rounded bg-slate-100 text-slate-700 font-bold text-xs px-2 py-0.5">#${index + 1}</span>
                                <span class="rounded ${isEssay ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'} text-[10px] font-bold px-2 py-0.5 uppercase">
                                    ${isEssay ? 'Esai Analisis' : 'Pilihan Ganda'}
                                </span>
                                <span class="rounded ${diffInfo.cls} text-[10px] font-bold px-2 py-0.5 uppercase">${diffInfo.label}</span>
                                <span class="text-[10px] text-slate-500 font-semibold">Bobot: ${itemPoints} Poin</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-indigo-200 bg-indigo-50/70 px-2.5 py-1 text-xs font-bold text-indigo-700 hover:bg-indigo-100 transition cursor-pointer" data-edit-idx="${index}">
                                    Edit
                                </button>
                                <button type="button" class="rounded-lg px-2 py-1 text-rose-600 hover:bg-rose-50 hover:text-rose-800 text-xs font-bold transition cursor-pointer" data-del-idx="${index}">
                                    Hapus
                                </button>
                            </div>
                        </div>
                        ${contentHtml}
                    `;

                    card.querySelector('[data-edit-idx]').addEventListener('click', () => {
                        editingIndex = index;
                        renderChallengeList();
                        const openQ = challengeListContainer.querySelector('[data-editing-card] [data-edit-question]');
                        openQ?.focus();
                    });

                    card.querySelector('[data-del-idx]').addEventListener('click', () => {
                        activeChallenges.splice(index, 1);
                        if (editingIndex === index) {
                            editingIndex = null;
                        } else if (editingIndex !== null && editingIndex > index) {
                            editingIndex--;
                        }
                        renderChallengeList();
                    });

                    challengeListContainer.appendChild(card);
                });
                setTimeout(() => renderKaTeXIn(challengeListContainer), 40);
            };

            const syncDocumentChallengeSummary = (docId, totalChallenges, challenges = [], maxXp = 0) => {
                if (!docId) return;
                const pillText = document.querySelector(`[data-doc-challenge-pill="${docId}"] [data-doc-challenge-pill-text]`);
                if (pillText) {
                    pillText.textContent = `${totalChallenges} Tantangan Guru`;
                }
                const btnLabel = document.querySelector(`[data-challenge-btn][data-doc-id="${docId}"] [data-challenge-btn-label]`);
                if (btnLabel) {
                    btnLabel.textContent = `Tantangan Guru (${totalChallenges})`;
                }
                const scriptEl = document.getElementById('doc-preview-data-' + docId);
                if (scriptEl) {
                    try {
                        const parsed = JSON.parse(scriptEl.textContent);
                        parsed.teacherChallenges = challenges;
                        parsed.challengeMaxXp = maxXp;
                        scriptEl.textContent = JSON.stringify(parsed);
                    } catch (_) {}
                }
            };

            btnClearAllChallenges?.addEventListener('click', async () => {
                if (!activeChallenges.length) return;
                const confirmed = await askConfirm({
                    eyebrow: 'HAPUS DAFTAR DRAFT',
                    title: 'Kosongkan Semua Soal Tantangan?',
                    subject: challengeDocTitle?.textContent?.replace(/^Materi:\s*/i, '') || '',
                    message: 'Seluruh soal di daftar ini akan dihapus dari editor. Tekan "Simpan Seluruh Tantangan" di bawah jika ingin mengosongkannya secara permanen.',
                    confirmText: 'Ya, Kosongkan Semua',
                    variant: 'warning',
                });
                if (!confirmed) return;
                activeChallenges = [];
                editingIndex = null;
                renderChallengeList();
                notify('Daftar soal dikosongkan. Jangan lupa tekan Simpan untuk menerapkan.', 'info');
            });

            const openChallengeModal = async (btn) => {
                currentDocId = btn.dataset.docId;
                currentStoreUrl = btn.dataset.storeUrl;
                currentSuggestionsUrl = btn.dataset.suggestionsUrl;
                currentAnalyzeUrl = btn.dataset.analyzeUrl;
                currentRecommendAnswerUrl = btn.dataset.recommendAnswerUrl || '';
                challengeDocTitle.textContent = `Materi: ${btn.dataset.docTitle}`;
                activeChallenges = [];
                editingIndex = null;
                if (newQText) newQText.value = '';
                if (newQRubric) newQRubric.value = '';
                resetNewQuestionFormState();
                renderChallengeList();

                challengeModal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');

                try {
                    const res = await fetch(btn.dataset.getUrl, { credentials: 'same-origin' });
                    if (res.ok) {
                        const json = await res.json();
                        activeChallenges = json.challenges || [];
                        editingIndex = null;
                        renderChallengeList();
                    }
                } catch (err) {
                    console.error('Failed to load challenges:', err);
                    notify('Gagal memuat daftar tantangan dari server.', 'error');
                }
            };

            btnLoadSuggestions?.addEventListener('click', async () => {
                if (!currentSuggestionsUrl) return;
                const oldHtml = btnLoadSuggestions.innerHTML;
                btnLoadSuggestions.disabled = true;
                btnLoadSuggestions.textContent = 'Menganalisis materi...';
                try {
                    const res = await fetch(currentSuggestionsUrl, { credentials: 'same-origin' });
                    if (res.ok) {
                        const json = await res.json();
                        if (json.suggestions && json.suggestions.length > 0) {
                            activeChallenges = json.suggestions;
                            editingIndex = null;
                            renderChallengeList();
                            notify('Rekomendasi soal dimuat! Klik tombol "Edit" pada soal mana pun untuk mengubah isinya.', 'info', 4200);
                        } else {
                            notify('Belum ada rekomendasi yang tersedia untuk materi ini.', 'warning');
                        }
                    } else {
                        notify('Gagal memuat rekomendasi soal.', 'error');
                    }
                } catch (err) {
                    notify('Gagal memuat rekomendasi soal esai.', 'error');
                } finally {
                    btnLoadSuggestions.disabled = false;
                    btnLoadSuggestions.innerHTML = oldHtml;
                }
            });

            const closeChallengeModal = () => {
                editingIndex = null;
                challengeModal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            };

            challengeBackdrop?.addEventListener('click', closeChallengeModal);
            challengeCloseBtn?.addEventListener('click', closeChallengeModal);
            challengeCancelBtn?.addEventListener('click', closeChallengeModal);

            document.querySelectorAll('[data-challenge-btn]').forEach(btn => {
                btn.addEventListener('click', () => openChallengeModal(btn));
            });

            btnAddQuestion?.addEventListener('click', async () => {
                const qText = newQText.value.trim();
                if (!qText) {
                    notify('Harap isi teks pertanyaan terlebih dahulu.', 'warning');
                    newQText?.focus();
                    return;
                }

                let itemPayload;
                if (typePGRadio.checked) {
                    if (!optA.value.trim() || !optB.value.trim()) {
                        notify('Harap isi minimal opsi A dan B.', 'warning');
                        optA?.focus();
                        return;
                    }
                    itemPayload = {
                        type: 'multiple_choice',
                        question: qText,
                        context: null,
                        options: {
                            A: optA.value.trim(),
                            B: optB.value.trim(),
                            C: optC.value.trim(),
                            D: optD.value.trim(),
                        },
                        correct_answer: correctOpt.value,
                    };
                } else {
                    itemPayload = {
                        type: 'essay',
                        question: qText,
                        context: null,
                        rubric: newQRubric.value.trim(),
                        reference_excerpt: lastRecommendedExcerpt || null,
                    };
                }

                const chosenDiff = newQDifficulty ? newQDifficulty.value : 'auto';
                const prevText = btnAddQuestion.innerHTML;
                if (chosenDiff === 'auto') {
                    btnAddQuestion.disabled = true;
                    btnAddQuestion.textContent = 'Menganalisis kesulitan...';
                }
                try {
                    const diffData = await resolveDifficultyForItem(chosenDiff, itemPayload);
                    itemPayload.difficulty = diffData.difficulty;
                    itemPayload.difficulty_weight = diffData.difficulty_weight;
                } finally {
                    if (chosenDiff === 'auto') {
                        btnAddQuestion.disabled = false;
                        btnAddQuestion.innerHTML = prevText;
                    }
                }

                activeChallenges.push(itemPayload);
                if (typePGRadio.checked) {
                    optA.value = ''; optB.value = ''; optC.value = ''; optD.value = '';
                } else {
                    newQRubric.value = '';
                }
                newQText.value = '';
                resetNewQuestionFormState();
                renderChallengeList();
                notify('Soal ditambahkan ke daftar.', 'success', 2400);
            });

            btnSaveAllChallenges?.addEventListener('click', async () => {
                if (!currentStoreUrl) return;

                if (editingIndex !== null) {
                    const openCard = challengeListContainer.querySelector('[data-editing-card]');
                    if (openCard) {
                        const committed = await commitEditCard(
                            openCard,
                            editingIndex,
                            String(activeChallenges[editingIndex]?.question || '').trim(),
                            String(activeChallenges[editingIndex]?.rubric || '').trim()
                        );
                        if (!committed) return;
                        editingIndex = null;
                        renderChallengeList();
                    }
                }

                if (activeChallenges.length === 0) {
                    const confirmed = await askConfirm({
                        eyebrow: 'KONFIRMASI KOSONGKAN SOAL',
                        title: 'Kosongkan Seluruh Tantangan Guru?',
                        subject: challengeDocTitle?.textContent?.replace(/^Materi:\s*/i, '') || '',
                        message: 'Tidak ada soal tantangan pada materi ini. Menyimpan akan menghapus seluruh soal tantangan guru yang sebelumnya ada.',
                        confirmText: 'Ya, Kosongkan',
                        variant: 'warning',
                    });
                    if (!confirmed) return;
                }

                const prevLabel = btnSaveAllChallenges.innerHTML;
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
                        syncDocumentChallengeSummary(
                            currentDocId,
                            json.total_challenges ?? activeChallenges.length,
                            json.challenges || [],
                            json.max_xp ?? 0
                        );
                        closeChallengeModal();
                        notify(json.message || 'Tantangan guru berhasil disimpan!', 'success', 4000);
                    } else {
                        notify(json.message || 'Gagal menyimpan tantangan.', 'error');
                    }
                } catch (err) {
                    notify('Terjadi kesalahan saat menyimpan tantangan.', 'error');
                } finally {
                    btnSaveAllChallenges.disabled = false;
                    btnSaveAllChallenges.innerHTML = prevLabel;
                }
            });

            // Toggle input rentang halaman
            const scopeAll = document.getElementById('scope-all');
            const scopeRange = document.getElementById('scope-range');
            const rangeInputs = document.getElementById('page-range-inputs');
            const toggleRangeInputs = () => {
                if (scopeRange && scopeRange.checked) {
                    rangeInputs?.classList.remove('hidden');
                } else {
                    rangeInputs?.classList.add('hidden');
                }
            };
            scopeAll?.addEventListener('change', toggleRangeInputs);
            scopeRange?.addEventListener('change', toggleRangeInputs);
        })();
    </script>
@endsection
