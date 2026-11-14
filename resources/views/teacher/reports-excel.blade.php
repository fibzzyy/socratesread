<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    @php
        echo '<!--[if gte mso 9]>
        <xml>
            <x:ExcelWorkbook>
                <x:ExcelWorksheets>
                    <x:ExcelWorksheet>
                        <x:Name>Rekap Nilai</x:Name>
                        <x:WorksheetOptions>
                            <x:DisplayGridlines/>
                        </x:WorksheetOptions>
                    </x:ExcelWorksheet>
                </x:ExcelWorksheets>
            </x:ExcelWorkbook>
        </xml>
        <![endif]-->';
    @endphp
    <style>
        body {
            font-family: Calibri, Arial, sans-serif;
            font-size: 11pt;
            color: #0f172a;
        }
        .title-main {
            font-size: 15pt;
            font-weight: bold;
            text-align: center;
            color: #1e3a8a;
        }
        .title-sub {
            font-size: 11pt;
            font-weight: bold;
            text-align: center;
            color: #334155;
        }
        .meta-label {
            font-weight: bold;
            color: #475569;
            width: 140px;
        }
        .meta-val {
            font-weight: normal;
            color: #0f172a;
        }
        table.report-table {
            border-collapse: collapse;
            width: 100%;
        }
        table.report-table th {
            background-color: #1e3a8a;
            color: #ffffff;
            font-weight: bold;
            font-size: 10pt;
            text-align: center;
            vertical-align: middle;
            border: 0.5pt solid #0f172a;
            padding: 8px 6px;
        }
        table.report-table th.th-sub {
            background-color: #2563eb;
            font-size: 9pt;
            font-weight: normal;
        }
        table.report-table td {
            font-size: 10pt;
            vertical-align: middle;
            border: 0.5pt solid #cbd5e1;
            padding: 6px 8px;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .font-bold {
            font-weight: bold;
        }
        /* MSO format for Text (preserves leading zero and prevents scientific notation e.g. 0081234567) */
        .text-nisn {
            mso-number-format: "\@";
            text-align: center;
        }
        .score-pass {
            background-color: #dcfce7;
            color: #166534;
            font-weight: bold;
            text-align: center;
        }
        .score-fail {
            background-color: #fee2e2;
            color: #991b1b;
            font-weight: bold;
            text-align: center;
        }
        .score-empty {
            color: #94a3b8;
            text-align: center;
        }
        .summary-col {
            background-color: #eff6ff;
            font-weight: bold;
            text-align: center;
        }
        .tfoot-label {
            background-color: #f1f5f9;
            font-weight: bold;
            text-align: right;
            border: 0.5pt solid #94a3b8;
        }
        .tfoot-val {
            background-color: #f1f5f9;
            font-weight: bold;
            text-align: center;
            border: 0.5pt solid #94a3b8;
        }
    </style>
</head>
<body>
    @php
        $colSpanTotal = 3 + count($documents) + 3; // No, Nama, NISN + [Materi...] + Rata-Rata, Predikat, Keterangan
    @endphp

    <table class="report-table">
        <!-- Header Judul Laporan -->
        <tr>
            <td colspan="{{ $colSpanTotal }}" class="title-main">
                REKAPITULASI EVALUASI HASIL BELAJAR SISWA
            </td>
        </tr>
        <tr>
            <td colspan="{{ $colSpanTotal }}" class="title-sub">
                {{ strtoupper($teacher->school_origin ?: 'SOCRATESREAD LEARNING PLATFORM') }}
            </td>
        </tr>
        <tr><td colspan="{{ $colSpanTotal }}"></td></tr>

        <!-- Identitas Rekapitulasi (Header 1 Mapel & Header 2 Guru Pengajar) -->
        <tr>
            <td class="meta-label">Mata Pelajaran:</td>
            <td colspan="2" class="meta-val font-bold">{{ $selectedCategory && $selectedCategory !== 'all' ? $selectedCategory : 'Semua Mata Pelajaran' }}</td>
            <td class="meta-label">Kelas / Rombel:</td>
            <td colspan="{{ max(1, $colSpanTotal - 5) }}" class="meta-val font-bold">{{ $selectedClass ? $selectedClass->name . ' (' . $selectedClass->targetGradeLabel() . ')' : 'Semua Kelas' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Guru Pengajar:</td>
            <td colspan="2" class="meta-val font-bold">{{ $teacher->name }}</td>
            <td class="meta-label">NIP / NUPTK:</td>
            <td colspan="{{ max(1, $colSpanTotal - 5) }}" class="meta-val font-bold">{{ $teacher->nip_nuptk ?: '—' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Jenis Penilaian:</td>
            <td colspan="2" class="meta-val">
                @if ($assessmentType === 'quiz')
                    Kuis Socrates (Pilihan Ganda)
                @elseif ($assessmentType === 'combined')
                    Nilai Gabungan (Tantangan HOTS & Kuis)
                @else
                    Tantangan Guru (Socratic HOTS)
                @endif
            </td>
            <td class="meta-label">KKM / Standar:</td>
            <td colspan="{{ max(1, $colSpanTotal - 5) }}" class="meta-val font-bold">{{ $kkm }}</td>
        </tr>
        <tr>
            <td class="meta-label">Tanggal Ekspor:</td>
            <td colspan="2" class="meta-val">{{ now()->locale('id')->translatedFormat('d F Y, H:i') }} WIB</td>
            <td class="meta-label">Rata-Rata Kelas:</td>
            <td colspan="{{ max(1, $colSpanTotal - 5) }}" class="meta-val font-bold">{{ $classAverage }} (Ketuntasan: {{ $passRate }}%)</td>
        </tr>
        <tr><td colspan="{{ $colSpanTotal }}"></td></tr>

        <!-- Table Headings -->
        <thead>
            <tr>
                <th style="width: 40px;">No</th>
                <th style="min-width: 220px;">Nama Siswa</th>
                <th style="width: 120px;">NISN</th>
                @foreach ($documents as $idx => $doc)
                    <th style="min-width: 120px;">
                        Materi {{ $idx + 1 }}<br>
                        <small style="font-weight: normal; font-size: 8pt;">{{ Str::limit($doc->title, 25) }}</small>
                    </th>
                @endforeach
                <th style="width: 80px; background-color: #172554;">Rata-Rata</th>
                <th style="width: 70px; background-color: #172554;">Predikat</th>
                <th style="width: 110px; background-color: #172554;">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($studentReports as $idx => $report)
                @php($stu = $report['student'])
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="font-bold">{{ $stu->name }}</td>
                    <td class="text-nisn">{{ $stu->nisn ?: '—' }}</td>
                    @foreach ($documents as $doc)
                        @php($score = $report['scores'][$doc->id] ?? null)
                        @if ($score !== null)
                            <td class="{{ $score >= $kkm ? 'score-pass' : 'score-fail' }}">
                                {{ $score }}
                            </td>
                        @else
                            <td class="score-empty">—</td>
                        @endif
                    @endforeach
                    <td class="summary-col font-bold">
                        {{ $report['average_score'] !== null ? $report['average_score'] : '—' }}
                    </td>
                    <td class="summary-col font-bold">
                        {{ $report['predicate'] }}
                    </td>
                    <td class="text-center font-bold" style="color: {{ $report['status'] === 'Tuntas' ? '#166534' : ($report['status'] === 'Perlu Bimbingan' ? '#991b1b' : '#64748b') }};">
                        {{ $report['status'] }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $colSpanTotal }}" class="text-center" style="padding: 20px; color: #64748b;">
                        Belum ada data siswa atau nilai untuk filter kelas dan mata pelajaran ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if (!empty($studentReports) && $documents->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="3" class="tfoot-label">Rata-Rata Materi:</td>
                    @foreach ($documents as $doc)
                        @php($stat = $docStats[$doc->id] ?? null)
                        <td class="tfoot-val">
                            {{ ($stat && $stat['average'] !== null) ? $stat['average'] : '—' }}
                        </td>
                    @endforeach
                    <td class="tfoot-val" style="background-color: #dbeafe; color: #1e3a8a; font-size: 11pt;">
                        {{ $classAverage }}
                    </td>
                    <td colspan="2" class="tfoot-val" style="background-color: #dbeafe; font-size: 9pt;">
                        Ketuntasan: {{ $passRate }}%
                    </td>
                </tr>
            </tfoot>
        @endif
    </table>

    <br><br>
    <!-- Blok Tanda Tangan Formal -->
    <table style="width: 100%; border: none;">
        <tr>
            <td colspan="3" style="border: none; text-align: center; width: 45%;">
                Mengetahui,<br>
                Kepala Sekolah
                <br><br><br><br>
                <strong><u>___________________________</u></strong><br>
                <small>NIP. .....................................</small>
            </td>
            <td colspan="{{ max(1, $colSpanTotal - 6) }}" style="border: none; width: 10%;"></td>
            <td colspan="3" style="border: none; text-align: center; width: 45%;">
                {{ $teacher->school_origin ? explode(',', $teacher->school_origin)[0] : 'Tempat' }}, {{ now()->locale('id')->translatedFormat('d F Y') }}<br>
                Guru Pengajar
                <br><br><br><br>
                <strong><u>{{ $teacher->name }}</u></strong><br>
                <small>NIP / NUPTK: {{ $teacher->nip_nuptk ?: '.....................................' }}</small>
            </td>
        </tr>
    </table>
</body>
</html>
