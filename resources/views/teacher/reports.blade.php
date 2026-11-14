@extends('layouts.teacher')

@section('title', 'Rekap & Laporan Nilai')
@section('breadcrumb', 'Rekap & Laporan')

@section('content')
    <!-- Print Only Header (Kop Surat & Identitas Laporan) -->
    <div class="print-header hidden">
        <div class="print-kop border-b-2 border-slate-800 pb-3 mb-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-slate-900 uppercase">
                        {{ $teacher->school_origin ?: 'SocratesRead Learning System' }}
                    </h1>
                    <p class="text-xs text-slate-600">Laporan Rekapitulasi Evaluasi & Capaian Belajar Siswa</p>
                </div>
                <div class="text-right text-xs text-slate-500">
                    <p>Dicetak pada: <strong>{{ now()->locale('id')->translatedFormat('d F Y, H:i') }} WIB</strong></p>
                    <p>Tahun Ajaran: <strong>{{ date('Y') }}/{{ date('Y') + 1 }}</strong></p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-xs mb-4 p-3 bg-slate-50 border border-slate-200 rounded-lg">
            <div>
                <span class="text-slate-500">Mata Pelajaran / Materi:</span>
                <strong class="text-slate-800">{{ $selectedCategory && $selectedCategory !== 'all' ? $selectedCategory : 'Semua Mata Pelajaran' }}</strong>
            </div>
            <div>
                <span class="text-slate-500">Guru Pengajar:</span>
                <strong class="text-slate-800">{{ $teacher->name }}</strong>
            </div>
            <div>
                <span class="text-slate-500">Kelas / Rombel:</span>
                <strong class="text-slate-800">{{ $selectedClass ? $selectedClass->name . ' (' . $selectedClass->targetGradeLabel() . ')' : 'Semua Kelas' }}</strong>
            </div>
            <div>
                <span class="text-slate-500">NIP / NUPTK:</span>
                <strong class="text-slate-800">{{ $teacher->nip_nuptk ?: '—' }}</strong>
            </div>
            <div>
                <span class="text-slate-500">Jenis Penilaian:</span>
                <strong class="text-slate-800">
                    @if ($assessmentType === 'quiz')
                        Kuis Socrates (Pilihan Ganda)
                    @elseif ($assessmentType === 'combined')
                        Nilai Gabungan (Tantangan HOTS + Kuis)
                    @else
                        Tantangan Guru (Socratic HOTS)
                    @endif
                </strong>
            </div>
            <div>
                <span class="text-slate-500">Kriteria Ketuntasan Minimal (KKM):</span>
                <strong class="text-slate-800">{{ $kkm }}</strong>
            </div>
        </div>
    </div>

    <!-- Web Header & Action Bar -->
    <section class="stage-heading no-print flex flex-col md:flex-row md:items-center md:justify-between gap-4" aria-labelledby="report-heading">
        <div>
            <p class="eyebrow">REKAPITULASI HASIL BELAJAR</p>
            <h1 id="report-heading">Rekap & Laporan Nilai</h1>
            <p class="welcome-subtitle">Leger nilai per kelas dan rekap capaian tantangan membaca siswa.</p>
        </div>
        <div class="flex items-center gap-2.5 flex-wrap">
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-xs">
                <x-icon name="printer" class="w-4 h-4 text-slate-600" />
                <span>Cetak Laporan</span>
            </button>
            <a href="{{ route('teacher.reports.export_excel', request()->all()) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-2 text-xs font-semibold transition shadow-xs">
                <x-icon name="file-spreadsheet" class="w-4 h-4 text-white" />
                <span>Unduh Excel (.xls)</span>
            </a>
        </div>
    </section>

    <!-- Filter Control Card -->
    <section class="panel no-print mb-6" aria-labelledby="filter-heading">
        <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <x-icon name="filter" class="w-4 h-4 text-blue-600" />
                <h2 id="filter-heading" class="text-xs font-bold text-slate-800 uppercase tracking-wide">Filter Laporan</h2>
            </div>
            <span class="text-xs text-slate-400">Pilih kelas, mata pelajaran, dan jenis evaluasi</span>
        </div>

        <form method="GET" action="{{ route('teacher.reports') }}" class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <!-- Dropdown Kelas -->
                <div class="grid gap-1.5">
                    <label for="class_id" class="text-xs font-semibold text-slate-700">Pilih Kelas</label>
                    <select id="class_id" name="class_id" onchange="this.form.submit()" class="min-h-10 rounded-lg border border-slate-300 px-3 text-sm outline-none bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-100">
                        @forelse ($classes as $cls)
                            <option value="{{ $cls->id }}" {{ (string) $selectedClassId === (string) $cls->id ? 'selected' : '' }}>
                                {{ $cls->name }} ({{ $cls->targetGradeLabel() }})
                            </option>
                        @empty
                            <option value="">Belum ada kelas</option>
                        @endforelse
                    </select>
                </div>

                <!-- Dropdown Mata Pelajaran -->
                <div class="grid gap-1.5">
                    <label for="category" class="text-xs font-semibold text-slate-700">Mata Pelajaran / Topik</label>
                    <select id="category" name="category" onchange="this.form.submit()" class="min-h-10 rounded-lg border border-slate-300 px-3 text-sm outline-none bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-100">
                        <option value="all" {{ $selectedCategory === 'all' ? 'selected' : '' }}>Semua Mata Pelajaran</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat }}" {{ $selectedCategory === $cat ? 'selected' : '' }}>
                                {{ $cat }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Jenis Penilaian Selector -->
                <div class="grid gap-1.5 sm:col-span-2 lg:col-span-1">
                    <label class="text-xs font-semibold text-slate-700">Jenis Penilaian</label>
                    <div class="inline-flex rounded-lg border border-slate-300 bg-slate-50 p-1">
                        <label class="flex-1 text-center cursor-pointer">
                            <input type="radio" name="type" value="teacher_challenge" onchange="this.form.submit()" {{ $assessmentType === 'teacher_challenge' ? 'checked' : '' }} class="sr-only peer">
                            <span class="block py-1 px-2 text-xs font-semibold rounded-md transition peer-checked:bg-blue-600 peer-checked:text-white text-slate-600 hover:text-slate-900">
                                Tantangan Guru
                            </span>
                        </label>
                        <label class="flex-1 text-center cursor-pointer">
                            <input type="radio" name="type" value="quiz" onchange="this.form.submit()" {{ $assessmentType === 'quiz' ? 'checked' : '' }} class="sr-only peer">
                            <span class="block py-1 px-2 text-xs font-semibold rounded-md transition peer-checked:bg-blue-600 peer-checked:text-white text-slate-600 hover:text-slate-900">
                                Kuis
                            </span>
                        </label>
                        <label class="flex-1 text-center cursor-pointer">
                            <input type="radio" name="type" value="combined" onchange="this.form.submit()" {{ $assessmentType === 'combined' ? 'checked' : '' }} class="sr-only peer">
                            <span class="block py-1 px-2 text-xs font-semibold rounded-md transition peer-checked:bg-blue-600 peer-checked:text-white text-slate-600 hover:text-slate-900">
                                Gabungan
                            </span>
                        </label>
                    </div>
                </div>
            </div>
        </form>
    </section>

    <!-- Ringkasan Statistik (KPI Cards) -->
    <section class="progress-summary teacher-summary mb-6" aria-label="Statistik capaian kelas">
        <article class="panel progress-stat">
            <span class="stat-icon icon-blue"><x-icon name="chart-no-axes-combined" /></span>
            <div>
                <p>Rata-rata Kelas</p>
                <strong>{{ $classAverage }}<small>/100</small></strong>
                <small>Berdasarkan nilai yang masuk</small>
            </div>
        </article>
        <article class="panel progress-stat">
            <span class="stat-icon icon-teal"><x-icon name="check" /></span>
            <div>
                <p>Ketuntasan Siswa</p>
                <strong>{{ $passRate }}<small>%</small></strong>
                <small>{{ $passedStudentsCount }} dari {{ count($studentReports) }} tuntas (KKM {{ $kkm }})</small>
            </div>
        </article>
        <article class="panel progress-stat">
            <span class="stat-icon icon-violet"><x-icon name="users" /></span>
            <div>
                <p>Total Siswa</p>
                <strong>{{ $totalStudentsCount }}</strong>
                <small>Terdaftar di {{ $selectedClass ? $selectedClass->name : 'kelas' }}</small>
            </div>
        </article>
        <article class="panel progress-stat">
            <span class="stat-icon icon-orange"><x-icon name="clock" /></span>
            <div>
                <p>Belum Mengumpulkan</p>
                <strong>{{ $pendingSubmissions }}</strong>
                <small>Tantangan materi yang tersisa</small>
            </div>
        </article>
    </section>

    <!-- Main Leger Table Card -->
    <section class="panel overflow-hidden" aria-labelledby="table-heading">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pb-4 mb-4 border-b border-slate-100">
            <div>
                <h2 id="table-heading" class="text-base font-bold text-slate-800">
                    Leger Nilai: {{ $selectedClass ? $selectedClass->name : 'Seluruh Kelas' }}
                </h2>
                <p class="text-xs text-slate-500">
                    Mata Pelajaran: <strong>{{ $selectedCategory && $selectedCategory !== 'all' ? $selectedCategory : 'Semua Kategori' }}</strong> · 
                    Mode Penilaian: 
                    <strong>
                        @if ($assessmentType === 'quiz')
                            Kuis Socrates
                        @elseif ($assessmentType === 'combined')
                            Rata-Rata Gabungan (Tantangan + Kuis)
                        @else
                            Tantangan Guru (HOTS)
                        @endif
                    </strong>
                </p>
            </div>
            <div class="flex items-center gap-2 text-xs">
                <span class="font-medium text-emerald-700">Tuntas (&ge; {{ $kkm }})</span>
                <span class="font-medium text-rose-700">Belum Tuntas (&lt; {{ $kkm }})</span>
            </div>
        </div>

        @if ($classes->isEmpty())
            <div class="py-12 text-center text-slate-500">
                <div class="w-12 h-12 mx-auto rounded-full bg-blue-50 text-blue-600 grid place-items-center mb-3">
                    <x-icon name="users" class="w-6 h-6" />
                </div>
                <h3 class="text-sm font-semibold text-slate-800">Anda belum memiliki kelas</h3>
                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">Buat kelas terlebih dahulu dan bagikan kode gabung kepada siswa untuk memulai penilaian.</p>
                <div class="mt-4">
                    <a href="{{ route('teacher.classrooms') }}" class="challenge-button inline-flex">Buat Kelas Baru <x-icon name="arrow-right" /></a>
                </div>
            </div>
        @elseif ($students->isEmpty())
            <div class="py-12 text-center text-slate-500">
                <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 text-slate-600 grid place-items-center mb-3">
                    <x-icon name="users" class="w-6 h-6" />
                </div>
                <h3 class="text-sm font-semibold text-slate-800">Belum ada siswa di kelas ini</h3>
                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                    Bagikan kode gabung <strong>{{ $selectedClass->join_code }}</strong> agar siswa dapat masuk ke kelas {{ $selectedClass->name }}.
                </p>
                <div class="mt-4">
                    <a href="{{ route('teacher.classrooms.members', $selectedClass->id) }}" class="challenge-button inline-flex">Kelola Anggota Kelas <x-icon name="arrow-right" /></a>
                </div>
            </div>
        @elseif ($documents->isEmpty())
            <div class="py-12 text-center text-slate-500">
                <div class="w-12 h-12 mx-auto rounded-full bg-amber-50 text-amber-600 grid place-items-center mb-3">
                    <x-icon name="book-open-text" class="w-6 h-6" />
                </div>
                <h3 class="text-sm font-semibold text-slate-800">Belum ada materi bacaan yang sesuai</h3>
                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">Unggah materi bacaan atau sesuaikan filter kategori/jenjang agar kolom penilaian tantangan tampil di sini.</p>
                <div class="mt-4">
                    <a href="{{ route('teacher.documents') }}" class="challenge-button inline-flex">Kelola Bacaan <x-icon name="arrow-right" /></a>
                </div>
            </div>
        @else
            <!-- Responsive Leger Table -->
            <div class="overflow-x-auto -mx-4 sm:mx-0">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50 border-y border-slate-200 text-slate-600 font-semibold tracking-wide">
                            <th scope="col" class="py-3 px-3 w-10 text-center">No</th>
                            <th scope="col" class="py-3 px-3 min-w-[180px]">Nama Siswa</th>
                            <th scope="col" class="py-3 px-3 w-28 text-center">NISN</th>
                            @foreach ($documents as $idx => $doc)
                                <th scope="col" class="py-3 px-3 text-center min-w-[120px]" title="{{ $doc->title }}">
                                    <span class="block truncate max-w-[140px] font-bold text-slate-800">
                                        M{{ $idx + 1 }}: {{ $doc->title }}
                                    </span>
                                    <span class="block text-[10px] text-slate-400 font-normal">
                                        {{ $doc->category ?: 'Materi' }}
                                    </span>
                                </th>
                            @endforeach
                            <th scope="col" class="py-3 px-3 text-center w-20 bg-blue-50/60 text-blue-900 font-bold border-l border-slate-200">Rata-Rata</th>
                            <th scope="col" class="py-3 px-3 text-center w-16 bg-blue-50/60 text-blue-900 font-bold">Predikat</th>
                            <th scope="col" class="py-3 px-3 text-center w-28 bg-blue-50/60 text-blue-900 font-bold">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($studentReports as $idx => $report)
                            @php($stu = $report['student'])
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-2.5 px-3 text-center text-slate-500 font-medium">{{ $idx + 1 }}</td>
                                <td class="py-2.5 px-3 font-semibold text-slate-800">
                                    <a href="{{ route('teacher.students.show', $stu->id) }}" class="hover:text-blue-600 transition">
                                        {{ $stu->name }}
                                    </a>
                                </td>
                                <td class="py-2.5 px-3 text-center text-slate-500 font-mono text-[11px]">
                                    {{ $stu->nisn ?: '—' }}
                                </td>
                                @foreach ($documents as $doc)
                                    @php($score = $report['scores'][$doc->id] ?? null)
                                    <td class="py-2.5 px-3 text-center">
                                        @if ($score !== null)
                                            <span class="inline-block min-w-8 py-0.5 px-1.5 rounded text-xs font-bold {{ $score >= $kkm ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' }}">
                                                {{ $score }}
                                            </span>
                                        @else
                                            <span class="text-slate-300 font-medium" title="Belum mengerjakan">—</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="py-2.5 px-3 text-center font-bold text-slate-800 bg-blue-50/30 border-l border-slate-100">
                                    {{ $report['average_score'] !== null ? $report['average_score'] : '—' }}
                                </td>
                                <td class="py-2.5 px-3 text-center font-bold bg-blue-50/30">
                                    @if ($report['predicate'] === 'A')
                                        <span class="inline-block w-6 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-800">A</span>
                                    @elseif ($report['predicate'] === 'B')
                                        <span class="inline-block w-6 py-0.5 rounded text-[11px] font-bold bg-blue-100 text-blue-800">B</span>
                                    @elseif ($report['predicate'] === 'C')
                                        <span class="inline-block w-6 py-0.5 rounded text-[11px] font-bold bg-amber-100 text-amber-800">C</span>
                                    @elseif ($report['predicate'] === 'D')
                                        <span class="inline-block w-6 py-0.5 rounded text-[11px] font-bold bg-rose-100 text-rose-800">D</span>
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-center bg-blue-50/30">
                                    @if ($report['status'] === 'Tuntas')
                                        <span class="inline-flex items-center gap-1 py-0.5 px-2 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Tuntas
                                        </span>
                                    @elseif ($report['status'] === 'Perlu Bimbingan')
                                        <span class="inline-flex items-center gap-1 py-0.5 px-2 rounded-full text-[10px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Remedial
                                        </span>
                                    @else
                                        <span class="inline-block py-0.5 px-2 rounded-full text-[10px] font-medium bg-slate-100 text-slate-500">
                                            Belum Ada
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-100 border-t-2 border-slate-300 font-bold text-slate-700">
                            <td colspan="3" class="py-3 px-3 text-right uppercase tracking-wider text-[11px]">
                                Rata-Rata Materi:
                            </td>
                            @foreach ($documents as $doc)
                                @php($stat = $docStats[$doc->id] ?? null)
                                <td class="py-3 px-3 text-center">
                                    @if ($stat && $stat['average'] !== null)
                                        <span class="text-xs font-bold text-slate-800">{{ $stat['average'] }}</span>
                                        <span class="block text-[9px] text-slate-500 font-normal">({{ $stat['completed_count'] }}/{{ $totalStudentsCount }})</span>
                                    @else
                                        <span class="text-slate-400 font-normal text-xs">—</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="py-3 px-3 text-center text-sm font-extrabold text-blue-700 bg-blue-100/60 border-l border-slate-200">
                                {{ $classAverage }}
                            </td>
                            <td colspan="2" class="py-3 px-3 text-center text-xs text-slate-600 bg-blue-100/60">
                                Ketuntasan: <strong>{{ $passRate }}%</strong>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </section>

    <!-- Print Only Signature Block -->
    <div class="print-signatures hidden mt-10 pt-4 text-xs text-slate-800">
        <div class="grid grid-cols-2 gap-8">
            <div class="text-center">
                <p>Mengetahui,</p>
                <p class="font-semibold">Kepala Sekolah</p>
                <div class="h-20"></div>
                <p class="font-bold underline">___________________________</p>
                <p class="text-[11px] text-slate-500">NIP. .....................................</p>
            </div>
            <div class="text-center">
                <p>{{ $teacher->school_origin ? explode(',', $teacher->school_origin)[0] : 'Tempat' }}, {{ now()->locale('id')->translatedFormat('d F Y') }}</p>
                <p class="font-semibold">Guru Pengajar / Pembimbing</p>
                <div class="h-20"></div>
                <p class="font-bold underline">{{ $teacher->name }}</p>
                <p class="text-[11px] text-slate-500">NIP / NUPTK: {{ $teacher->nip_nuptk ?: '.....................................' }}</p>
            </div>
        </div>
    </div>

    <!-- Print Stylesheet -->
    <style>
        @media print {
            @page {
                size: A4 landscape;
                margin: 12mm 15mm;
            }
            body {
                background: #ffffff !important;
                color: #0f172a !important;
                font-size: 10pt;
            }
            .sidebar, .topbar, .no-print, .scrim, .page-footer, footer, #webConfirmModal, #webToastContainer {
                display: none !important;
            }
            .app-shell, .main-content, .page-content {
                display: block !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                background: transparent !important;
            }
            .print-header {
                display: block !important;
            }
            .print-signatures {
                display: block !important;
                page-break-inside: avoid;
            }
            .panel {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            table {
                width: 100% !important;
                border: 1px solid #0f172a !important;
                border-collapse: collapse !important;
            }
            th, td {
                border: 0.5pt solid #475569 !important;
                padding: 4pt 6pt !important;
                font-size: 8.5pt !important;
            }
            th {
                background-color: #f1f5f9 !important;
                color: #0f172a !important;
            }
            tr {
                page-break-inside: avoid;
            }
        }
    </style>
@endsection
