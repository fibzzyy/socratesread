<?php

namespace App\Services;

use Illuminate\Support\Str;

class ReadingMaterialFormatter
{
    /**
     * Kata-kata umum Bahasa Indonesia untuk memecah teks PDF slide yang menempel tanpa spasi.
     *
     * @var array<string, int>
     */
    private const ID_WORDS = [
        'adalah' => 1, 'merupakan' => 1, 'yaitu' => 1, 'yakni' => 1, 'ialah' => 1, 'bahwa' => 1,
        'yang' => 1, 'dan' => 1, 'atau' => 1, 'serta' => 1, 'dengan' => 1,
        'untuk' => 1, 'guna' => 1, 'demi' => 1, 'agar' => 1, 'supaya' => 1,
        'dari' => 1, 'pada' => 1, 'dalam' => 1, 'oleh' => 1, 'sebagai' => 1,
        'sehingga' => 1, 'karena' => 1, 'sebab' => 1, 'jika' => 1, 'bila' => 1, 'apabila' => 1,
        'maka' => 1, 'akan' => 1, 'telah' => 1, 'sudah' => 1, 'sedang' => 1,
        'dapat' => 1, 'bisa' => 1, 'harus' => 1, 'wajib' => 1, 'perlu' => 1,
        'tidak' => 1, 'bukan' => 1, 'jangan' => 1, 'sampai' => 1, 'hingga' => 1,
        'antara' => 1, 'melalui' => 1, 'tentang' => 1, 'mengenai' => 1, 'terhadap' => 1,
        'seperti' => 1, 'selain' => 1, 'kemudian' => 1, 'selanjutnya' => 1,
        'selanjutnta' => 1, 'berikut' => 1, 'ini' => 1, 'itu' => 1, 'tersebut' => 1,
        'kita' => 1, 'kami' => 1, 'anda' => 1, 'mereka' => 1, 'ia' => 1, 'dia' => 1, 'beliau' => 1,
        'di' => 1, 'ke' => 1, 'satu' => 1, 'dua' => 1, 'tiga' => 1, 'empat' => 1, 'lima' => 1,
        'enam' => 1, 'tujuh' => 1, 'delapan' => 1, 'sembilan' => 1, 'sepuluh' => 1,
        'pun' => 1, 'saat' => 1, 'era' => 1, 'zaman' => 1, 'dimakan' => 1,
        'konsep' => 1, 'strategi' => 1, 'prinsip' => 1, 'makna' => 1, 'artinya' => 1,
        'fungsi' => 1, 'peran' => 1, 'tujuan' => 1, 'aspek' => 1, 'utama' => 1,
        'bahasa' => 1, 'indonesia' => 1, 'daerah' => 1, 'asing' => 1, 'nasional' => 1,
        'lokal' => 1, 'global' => 1, 'digital' => 1, 'bangsa' => 1, 'negara' => 1,
        'pembinaan' => 1, 'pengembangan' => 1, 'pembangunan' => 1, 'pembentukan' => 1,
        'kebahasaan' => 1, 'kebudayaan' => 1, 'identitas' => 1, 'komunikasi' => 1,
        'diperkenalkan' => 1, 'menekankan' => 1, 'memadukan' => 1, 'memiliki' => 1,
        'mendahulukan' => 1, 'diletakkan' => 1, 'diposisikan' => 1, 'diikuti' => 1,
        'menguasai' => 1, 'mengutamakan' => 1, 'melestarikan' => 1, 'utamakan' => 1,
        'lestarikan' => 1, 'kuasai' => 1, 'hilang' => 1, 'musnah' => 1, 'terjemahan' => 1,
        'posisi' => 1, 'paling' => 1, 'atas' => 1, 'bawah' => 1, 'bawahnya' => 1,
        'buku' => 1, 'materi' => 1, 'teks' => 1, 'bacaan' => 1, 'siswa' => 1, 'mahasiswa' => 1,
        'lain' => 1, 'lebih' => 1, 'sangat' => 1, 'secara' => 1, 'umum' => 1, 'resmi' => 1,
        'ilmu' => 1, 'pengetahuan' => 1, 'teknologi' => 1, 'budaya' => 1, 'ekonomi' => 1,
        'trigatra' => 1, 'bangun' => 1, 'anton' => 1, 'moeliono' => 1,
        'diutamakan' => 1, 'dilestarikan' => 1, 'dikuasai' => 1, 'dipelajari' => 1,
        'digunakan' => 1, 'diterapkan' => 1, 'dijelaskan' => 1, 'disusun' => 1,
        'sehari-hari' => 1,
        // Proposal, Penelitian, dan Laporan Ilmiah
        'proposal' => 1, 'kegiatan' => 1, 'penelitian' => 1, 'rancangan' => 1, 'rencana' => 1,
        'dituangkan' => 1, 'bentuk' => 1, 'tulisan' => 1, 'menjelaskan' => 1, 'latar' => 1,
        'belakang' => 1, 'metode' => 1, 'pelaksanaan' => 1, 'suatu' => 1, 'pedoman' => 1,
        'alat' => 1, 'pelaksana' => 1, 'pihak' => 1, 'pemberi' => 1, 'dana' => 1,
        'pemberidana' => 1, 'terkait' => 1, 'dasar' => 1, 'evaluasi' => 1, 'pertanggungjawaban' => 1,
        'jenis-jenis' => 1, 'jenis' => 1, 'skripsi' => 1, 'tesis' => 1, 'disertasi' => 1,
        'pkm' => 1, 'non-penelitian' => 1, 'pelatihan' => 1, 'lomba' => 1, 'seminar' => 1,
        'pengabdian' => 1, 'bagian' => 1, 'pokok' => 1, 'judul' => 1, 'spesifik' => 1,
        'menguraikan' => 1, 'alasan' => 1, 'pentingnya' => 1, 'urgensi' => 1, 'rumusan' => 1,
        'masalah' => 1, 'pertanyaan' => 1, 'dijawab' => 1, 'hasil' => 1, 'dicapai' => 1,
        'tinjauan' => 1, 'pustaka' => 1, 'landasan' => 1, 'teori' => 1, 'terdahulu' => 1,
        'relevan' => 1, 'pendekatan' => 1, 'subjek' => 1, 'teknik' => 1, 'pengumpulan' => 1,
        'data' => 1, 'jadwal' => 1, 'anggaran' => 1, 'biaya' => 1, 'kebutuhan' => 1,
        'realistis' => 1, 'daftar' => 1, 'acuan' => 1, 'kaidah' => 1, 'baku' => 1,
        'formal' => 1, 'menghindari' => 1, 'penggunaan' => 1, 'bermakna' => 1, 'ganda' => 1,
        'kalimat' => 1, 'efektif' => 1, 'logis' => 1, 'mengandung' => 1, 'unsur' => 1,
        'argumentatif' => 1, 'meyakinkan' => 1, 'mematuhi' => 1, 'struktur' => 1, 'penulisan' => 1,
        'sistematis' => 1, 'kohesif' => 1, 'koheren' => 1, 'menentukan' => 1, 'topik' => 1,
        'studi' => 1, 'menemukan' => 1, 'celah' => 1, 'merumuskan' => 1, 'menyusun' => 1,
        'kerangka' => 1, 'draf' => 1, 'lengkap' => 1, 'merevisi' => 1, 'memeriksa' => 1,
        'kesesuaian' => 1, 'format' => 1, 'kemdikbudristek' => 1, 'mengusulkan' => 1, 'merencanakan' => 1,
        'membuktikan' => 1, 'memecahkan' => 1, 'tertentu' => 1, 'praktis' => 1, 'aplikatif' => 1,
        'nyata' => 1, 'lapangan' => 1, 'halaman' => 1, 'manfaat' => 1, 'panitia' => 1,
        'penutup' => 1, 'opsional' => 1, 'mengutip' => 1, 'sumber' => 1, 'laporan' => 1,
        'temuan' => 1, 'kesimpulan' => 1, 'saran' => 1, 'dokumentasi' => 1, 'menyampaikan' => 1,
        'pembaca' => 1, 'memberikan' => 1, 'bukti' => 1, 'referensi' => 1, 'lanjutan' => 1,
        'peneliti' => 1, 'pengamatan' => 1, 'objektif' => 1, 'subjektif' => 1, 'pengantar' => 1,
        'ucapan' => 1, 'terima' => 1, 'kasih' => 1, 'maksud' => 1, 'penyusunan' => 1,
        'tabel' => 1, 'gambar' => 1, 'pendahuluan' => 1, 'inti' => 1, 'deskriptif' => 1,
        'analitis' => 1, 'dilengkapi' => 1, 'interpretasi' => 1, 'rekomendasi' => 1, 'perbaikan' => 1,
        'tindak' => 1, 'lanjut' => 1, 'akhir' => 1, 'selingkung' => 1, 'lampiran' => 1,
        'mentah' => 1, 'instrumen' => 1, 'empiris' => 1, 'spekulatif' => 1, 'emotif' => 1,
        'mengumpulkan' => 1, 'mengorganisasi' => 1, 'dosen' => 1, 'pembimbing' => 1, 'pengaruh' => 1,
        'intensitas' => 1, 'media' => 1, 'sosial' => 1, 'produktivitas' => 1, 'belajar' => 1,
        'fenomena' => 1, 'menurunnya' => 1, 'kuantitatif' => 1, 'kualitatif' => 1, 'kuesioner' => 1,
        'responden' => 1, 'durasi' => 1, 'non-akademik' => 1, 'rendah' => 1, 'tinggi' => 1,
        'diarahkan' => 1, 'konten' => 1, 'akademik' => 1, 'karya' => 1, 'tulis' => 1,
        'ilmiah' => 1, 'makalah' => 1, 'artikel' => 1, 'jurnal' => 1, 'kajian' => 1,
        'metodologi' => 1, 'kritis' => 1, 'ejaan' => 1, 'diksi' => 1, 'tata' => 1,
        'abstrak' => 1, 'kata' => 1, 'kunci' => 1, 'isi' => 1, 'teoritis' => 1,
        'teoretis' => 1, 'populasi' => 1, 'sampel' => 1, 'analisis' => 1, 'pembahasan' => 1,
        'sitasi' => 1, 'rujukan' => 1, 'primer' => 1, 'sekunder' => 1, 'plagiarisme' => 1,
        'orisinalitas' => 1, 'semata' => 1, 'fakta' => 1, 'nalar' => 1, 'penalaran' => 1,
        'diterima' => 1, 'aturan' => 1, 'mendalam' => 1, 'valid' => 1, 'verifikasi' => 1,
        'masyarakat' => 1, 'penulis' => 1,
        // Bahasa & Matematika tambahan
        'observasi' => 1, 'wayang' => 1, 'kulit' => 1, 'wong' => 1, 'golek' => 1,
        'suket' => 1, 'motekar' => 1, 'purwa' => 1, 'topeng' => 1, 'museum' => 1,
        'benda' => 1, 'kuno' => 1, 'antik' => 1, 'seni' => 1, 'tradisional' => 1,
        'pendidikan' => 1, 'informasi' => 1, 'hiburan' => 1, 'ringkasan' => 1, 'rangkuman' => 1,
        'ikhtisar' => 1, 'perbedaan' => 1, 'langkah' => 1, 'menulis' => 1, 'gagasan' => 1,
        'paragraf' => 1, 'verba' => 1, 'nomina' => 1, 'afiksasi' => 1, 'imbuhan' => 1,
        'prefiks' => 1, 'sufiks' => 1, 'konfiks' => 1, 'frasa' => 1, 'klausa' => 1,
        'reduplikasi' => 1, 'pemajemukan' => 1, 'eksponen' => 1, 'barisan' => 1, 'deret' => 1,
        'geometri' => 1, 'aritmetika' => 1, 'rasio' => 1, 'suku' => 1, 'pola' => 1,
        'perkalian' => 1, 'berulang' => 1, 'faktor' => 1, 'pembelahan' => 1, 'bakteri' => 1,
        'lipatan' => 1, 'kertas' => 1, 'tali' => 1, 'potongan' => 1, 'panjang' => 1,
        'pendek' => 1, 'hoaks' => 1, 'fase' => 1,
    ];

    /**
     * Directive atau istilah CamelCase teknis yang tidak boleh dipecah spasinya.
     */
    private const PROTECTED_CAMEL_TERMS = [
        'VirtualHost', 'ServerName', 'ServerAlias', 'ServerAdmin', 'DocumentRoot',
        'AllowOverride', 'FollowSymLinks', 'MultiViews', 'ScriptAlias', 'ErrorLog',
        'LogLevel', 'CustomLog', 'SocratesRead', 'JavaScript', 'TypeScript',
        'MySQL', 'PostgreSQL', 'GitHub', 'OpenRouter', 'NotebookLM', 'SubDomain',
    ];

    /**
     * Membersihkan teks mentah PDF tanpa memotong bagian isi, memperbaiki spasi kata yang menempel,
     * dan memformat struktur paragraf, heading, list/numbering, serta blok kode/konfigurasi menjadi Markdown rapi.
     */
    public function format(string $rawText, string $title = ''): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $rawText);
        $normalized = preg_replace('/(?m)^([a-zA-Z\d]+[\.\)])([A-Za-z])/u', '$1 $2', $normalized) ?? $normalized;
        $rawLines = explode("\n", $normalized);

        $blocks = [];
        $inCodeBlock = false;
        $codeBuffer = [];
        $paragraphBuffer = [];

        $flushParagraph = function () use (&$paragraphBuffer, &$blocks): void {
            if (empty($paragraphBuffer)) {
                return;
            }
            $joined = implode(' ', $paragraphBuffer);
            $joined = preg_replace('/[ \t]+/u', ' ', $joined) ?? $joined;
            $joined = preg_replace('/^(\b\p{L}+\b)(?:\s+\1)+(?=\s+(?:adalah|merupakan|yaitu|yakni|ialah|dalam|pada|sebagai)\b)/iu', '$1', $joined) ?? $joined;
            $joined = preg_replace('/(?<=[.?!])\s+(?:Rangkuman|Ringkasan|Ikhtisar|Referensi)(?:\s+(?:Rangkuman|Ringkasan|Ikhtisar|Referensi))*$/iu', '', $joined) ?? $joined;
            $joined = $this->enhanceInlineTypography(trim($joined));
            if ($joined !== '') {
                $blocks[] = $joined;
            }
            $paragraphBuffer = [];
        };

        $flushCode = function () use (&$codeBuffer, &$blocks): void {
            if (empty($codeBuffer)) {
                return;
            }
            $blocks[] = "```\n" . implode("\n", $codeBuffer) . "\n```";
            $codeBuffer = [];
        };

        $listMarkers = '/^([•·\x{2022}\x{25CB}\x{25CF}\x{25AA}\x{25AB}\x{25BA}\x{2713}\x{2714}⚬○■□▪▫◆◇])\s*(.*)$/u';
        $orderedMarker = '/^(\d+[\.\)])\s+(.+)$/u';
        $pageNumberLine = '/^(?:halaman|hal\.?|page)?\s*\d+(?:\s*(?:of|\/)\s*\d+)?$/iu';

        $totalLines = count($rawLines);
        for ($i = 0; $i < $totalLines; $i++) {
            $rawLine = $rawLines[$i];
            $trimmed = trim(preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $rawLine) ?? '');

            // Pertahankan seluruh blok konfigurasi seperti <VirtualHost ...>...</VirtualHost>
            if (preg_match('/^<VirtualHost\b/i', $trimmed)) {
                $flushParagraph();
                $inCodeBlock = true;
                $codeBuffer[] = $trimmed;
                continue;
            }
            if ($inCodeBlock) {
                $codeBuffer[] = $trimmed;
                if (preg_match('/^<\/VirtualHost>/i', $trimmed)) {
                    $inCodeBlock = false;
                    $flushCode();
                }
                continue;
            }

            if ($trimmed === '') {
                $flushParagraph();
                continue;
            }

            // Buang hanya baris penomoran halaman murni (Halaman 1, Page 7 of 7)
            if (preg_match($pageNumberLine, $trimmed)) {
                continue;
            }

            // Buang baris metadata cover teracak / berspasi per huruf (misal: /IQIRXIVMER..., 1EXIQEXMOE...)
            $tNoZero = str_replace("\0", '', $trimmed);
            $c = preg_replace('/[\s\0]+/', '', $tNoZero);
            if (preg_match('#^/(?:IQIR|IFYH|6ITY|1EXI|4IRY)#i', $c)
                || preg_match('/^(?:6ITY|1EXI|4IRY|6MWI|8IOR)/i', $c)
                || preg_match('/^(?:IQIRXIVMER|1EXIQEXMOE|4IRYPMW|6ITYFPMO|71%|1%|71\/)/i', $c)
                || preg_match('/^[-–—\s]*\d+\s*&\s*\d+/u', $c)
                || preg_match('/^[-–—\s]*[A-Z0-9&]{2,10}\s+Barisan/i', $tNoZero)
                || preg_match('/^(?:[\/\p{L}\p{N}%&_()\-]\s+){4,}/u', $tNoZero)) {
                continue;
            }

            // Buang running header/footer buku cetak, kementerian, penerbit, peta materi, instruksi tugas
            if (preg_match('/^(?:Matematika|Bahasa\s+Indonesia|Buku\s+Siswa)\s+untuk\s+SMA.*Kelas\s+[X\d]+/iu', $tNoZero)
                || preg_match('/^Bab\s+\d+\s*\|\s*/iu', $tNoZero)
                || preg_match('/^(?:World\s+Our\s+World|Data\s+source:\s*United\s+Nations|CC\s+BY\b)/iu', $tNoZero)
                || preg_match('/^(?:Bab|Eksponen\s*\d*|\d+\s*Bab|Barisan\s+dan\s+Deret)$/iu', $tNoZero)
                || preg_match('/^[-–—\s]*\d+\s*$/u', $tNoZero)
                || preg_match('/^(?:ISBN|Kementerian|Republik\s+Indonesia|Hak\s+Cipta|Diterbitkan\s+oleh)\b/iu', $tNoZero)
                || preg_match('/^(?:Peta\s+Materi|Ayo,?\s+Bereksplorasi|Ayo,?\s+Berpikir|Ayo,?\s+Berdiskusi|Jawablah\s+pertanyaan\s+berikut\s+dengan\s+berdiskusi)\b/iu', $tNoZero)
                || preg_match('/^(?:Kelas\s+[X\d]+.*MAK\d*|\d{1,3}\s*(?:Bahasa\s+Indonesia|Matematika)|Sumber\s+foto:.*)$/iu', $tNoZero)
                || preg_match('/^\d+\.\s+Berkumpullah\s+dengan\s+kelompokmu/iu', $tNoZero)
                || preg_match('/^(?:Tulislah\s+hasil\s+analisis|Selanjutnya,?\s+lakukan\s+analisis|Selanjutnya,?\s+lanjutkan\s+dengan\s+mencari)\s+.*buku\s+kerja/iu', $tNoZero)) {
                continue;
            }

            // Baris perintah terminal (#mkdir ..., #cd ..., #service ...)
            if (preg_match('/^#[a-z0-9_\/\.\-]/i', $trimmed)) {
                $flushParagraph();
                $blocks[] = "```bash\n" . $trimmed . "\n```";
                continue;
            }

            // Perbaiki kata yang menempel pada PDF (tetap lindungi URL, email, directive Apache)
            $line = $this->restoreMissingSpaces($trimmed);

            // Bersihkan sisa margin footer / header yang menempel di ujung baris kalimat
            $line = preg_replace('/\s*(?:Kelas\s+[X\d]+(?:\s*[A-Z\/]+)*\s*\d*|\d{1,3}\s*(?:Bahasa\s+Indonesia|Matematika))\s*$/iu', '', $line);
            $line = preg_replace('/\s*Sumber\s+foto:\s*https?:\/\/\S+/iu', '', $line);
            $line = preg_replace('/(?:\s*[_\.]{3,}\s*){2,}/u', ' … ', $line);

            // Deteksi subjudul berlabel alfabet: "a. Pengertian Proposal", "b. Fungsi Proposal", dll.
            if (preg_match('/^[a-z][\.\)]\s+[A-Z\p{L}][^.?!]{2,60}$/u', $line)) {
                $flushParagraph();
                $blocks[] = '### ' . $this->enhanceInlineTypography($line);
                continue;
            }

            // Deteksi daftar bernomor (1. ..., 2. ...)
            if (preg_match($orderedMarker, $line, $m)) {
                $flushParagraph();
                $num = rtrim($m[1], '.)') . '.';
                $itemText = $this->restoreMissingSpaces(trim($m[2]));
                // Kumpulkan baris lanjutan (soft wrap) untuk item nomor ini
                while ($i + 1 < $totalLines) {
                    $nextRaw = trim($rawLines[$i + 1]);
                    $nextRestored = preg_replace('/^([a-zA-Z\d]+[\.\)])([A-Za-z])/u', '$1 $2', $nextRaw);
                    if (
                        $nextRaw === ''
                        || preg_match($pageNumberLine, $nextRaw)
                        || preg_match($listMarkers, $nextRestored)
                        || preg_match($orderedMarker, $nextRestored)
                        || preg_match('/^[a-z][\.\)]\s+[A-Z\p{L}]/u', $nextRestored)
                        || $this->isLikelyHeading($nextRestored)
                        || preg_match('/^<VirtualHost\b|^#[a-z0-9_\/\.\-]/i', $nextRaw)
                    ) {
                        break;
                    }
                    $i++;
                    $itemText .= ' ' . $this->restoreMissingSpaces($nextRaw);
                }
                $itemText = preg_replace('/[ \t]+/u', ' ', $itemText) ?? $itemText;
                $blocks[] = $num . ' ' . $this->enhanceInlineTypography($itemText);
                continue;
            }

            // Deteksi bullet list (•, ⚬, dll.)
            if (preg_match($listMarkers, $line, $m)) {
                $flushParagraph();
                $bulletSymbol = $m[1];
                $isSubBullet = in_array($bulletSymbol, ['⚬', '○', '▫', '▪', '·'], true);
                $prefix = $isSubBullet ? '  - ' : '- ';
                $itemText = $this->restoreMissingSpaces(trim($m[2]));

                while ($i + 1 < $totalLines) {
                    $nextRaw = trim($rawLines[$i + 1]);
                    $nextRestored = preg_replace('/^([a-zA-Z\d]+[\.\)])([A-Za-z])/u', '$1 $2', $nextRaw);
                    if (
                        $nextRaw === ''
                        || preg_match($pageNumberLine, $nextRaw)
                        || preg_match($listMarkers, $nextRestored)
                        || preg_match($orderedMarker, $nextRestored)
                        || preg_match('/^[a-z][\.\)]\s+[A-Z\p{L}]/u', $nextRestored)
                        || $this->isLikelyHeading($nextRestored)
                        || preg_match('/^<VirtualHost\b|^#[a-z0-9_\/\.\-]/i', $nextRaw)
                    ) {
                        break;
                    }
                    $i++;
                    $itemText .= ' ' . $this->restoreMissingSpaces($nextRaw);
                }

                if ($itemText !== '') {
                    $itemText = preg_replace('/[ \t]+/u', ' ', $itemText) ?? $itemText;
                    $blocks[] = $prefix . $this->enhanceInlineTypography($itemText);
                }
                continue;
            }

            // Deteksi sub-penjelasan Key = Value (misal ServerName = Nama domain)
            if (preg_match('/^[A-Z][A-Za-z0-9_]+\s*=\s*.+$/u', $line)) {
                $flushParagraph();
                $blocks[] = '- ' . $this->enhanceInlineTypography($line);
                continue;
            }

            // Deteksi Heading / Subjudul (BAB ..., Huruf kapital semua, atau label bagian pendek)
            if ($this->isLikelyHeading($line) && mb_strlen($line) <= 95) {
                $flushParagraph();
                $headingText = trim($line, " \t#");
                $prevBlock = ! empty($blocks) ? end($blocks) : '';
                $isStandaloneClosing = preg_match('/^(?:TERIMA KASIH|THANK YOU|PENUTUP|KESIMPULAN)$/iu', $headingText)
                    || preg_match('/^###\s+(?:SUMBER RUJUKAN|DAFTAR PUSTAKA)$/iu', (string) $prevBlock);
                $isDistinctSection = (bool) preg_match('/^(?:TUJUAN|CAPAIAN|DEFINISI|PENGERTIAN|RINGKASAN|RANGKUMAN|IKHTISAR|\d+\s+ASPEK|ASPEK|BAHASA\s+INDONESIA|BAHASA\s+DAERAH|BAHASA\s+ASING|KESEIMBANGAN|MAKNA|SUMBER|DAFTAR|REFERENSI)\b/iu', $headingText);
                if (! $isStandaloneClosing && ! $isDistinctSection && ! empty($blocks) && str_starts_with((string) $prevBlock, '### ') && mb_strlen((string) $prevBlock) <= 25) {
                    $prev = substr((string) array_pop($blocks), 4);
                    $blocks[] = '### ' . $prev . ' ' . $headingText;
                } else {
                    $blocks[] = '### ' . $headingText;
                }
                continue;
            }

            // Jika baris diawali label mandiri diakhiri ':' (misal "Fungsi utama:" atau "Keterangan :")
            if (empty($paragraphBuffer) && str_ends_with($line, ':') && mb_strlen($line) <= 90) {
                $flushParagraph();
                $blocks[] = '**' . $line . '**';
                continue;
            }

            $paragraphBuffer[] = $line;

            // Akhiri paragraf jika kalimat jelas berakhir dan baris berikutnya tampak memulai kalimat baru atau blok baru
            if (str_ends_with($line, ':') || (preg_match('/[.?!]["”\']?$/u', $line) && $i + 1 < $totalLines)) {
                $peek = trim($rawLines[$i + 1] ?? '');
                if (str_ends_with($line, ':') || $peek === '' || $this->isLikelyHeading($peek)) {
                    $flushParagraph();
                }
            }
        }

        $flushCode();
        $flushParagraph();

        // Gabungkan item list berdekatan dengan satu newline agar menjadi <ul>/<ol> utuh,
        // sedangkan blok antar-bagian dipisah dua newline (\n\n).
        $output = [];
        $countBlocks = count($blocks);
        for ($j = 0; $j < $countBlocks; $j++) {
            $curr = $blocks[$j];
            $isCurrList = (bool) preg_match('/^(?:  - |- |\d+\.\s)/u', $curr);
            $isNextList = ($j + 1 < $countBlocks) && (bool) preg_match('/^(?:  - |- |\d+\.\s)/u', $blocks[$j + 1]);
            $output[] = $curr . ($isCurrList && $isNextList ? "\n" : "\n\n");
        }

        $markdown = trim(implode('', $output));
        if ($markdown === '') {
            $markdown = trim($rawText);
        }

        $markdown = $this->cleanClassroomHomework($markdown);
        $markdown = $this->restructureAndFormatPedagogy($markdown, $title);
        $markdown = $this->ensurePedagogicalStructure($markdown, $title);
        $markdown = $this->glueBrokenParagraphSentences($markdown);
        $markdown = $this->cleanAsterisksAndEmptyHeadings($markdown);

        return $markdown;
    }

    /**
     * Memangkas instruksi penugasan PR buku cetak, tabel latihan kosong,
     * header silabus administratif perguruan tinggi, serta tugas interaktif kelas
     * agar teks bacaan terfokus murni pada konten instruksional pembelajaran mandiri siswa.
     */
    public function cleanClassroomHomework(string $markdown): string
    {
        // 1. Bersihkan margin footer / header buku yang menempel
        $markdown = preg_replace('/\s*(?:Kelas\s+[X\d]+(?:\s*(?:SMA|MA|SMK|MAK|\/))+\s*\d*|\d{1,3}\s*(?:Bahasa\s+Indonesia|Matematika))\s*(?=\R|$)/iu', '', $markdown);
        $markdown = preg_replace('/\b(?:Kelas\s+[X\d]+(?:\s*(?:SMA|MA|SMK|MAK|\/))+\s*\d*|\d{1,3}\s*(?:Bahasa\s+Indonesia|Matematika))\b/iu', '', $markdown);
        $markdown = preg_replace('/\s*Sumber\s+foto:\s*https?:\/\/\S+/iu', '', $markdown);

        // 2. Pembersihan silabus / header administratif kampus / modul PBL
        $markdown = preg_replace('/(?:\R+|^)#{1,6}\s*(?:MODUL\s+PEMBELAJARAN\s+BERBASIS\s+PROYEK|MATA\s+KULIAH\s+SISTEM\s+OPERASI|PERTEMUAN\s+11\s+SINKRONISASI[^\n]*|NIP\.?\s*\d+|PROGRAM\s+STUDI\s+D3\s+MANAJEMEN\s+INFORMATIKA|POLITEKNIK\s+NEGERI\s+SAMBAS|A\.\s*IDENTITAS\s+PERTEMUAN)\b[^\n]*\R?/miu', "\n", $markdown);
        $markdown = preg_replace('/Mata\s+Kuliah\s+Sistem\s+Operasi\s*\(Pembelajaran\s+Berbasis\s+Proyek\)[^\n]+(?:NIP\s*\d+\)?|\d{10,}\)?)\s*/ius', '', $markdown);
        $markdown = preg_replace('/Proyek\s+Semester:\s*Rancang\s+Bangun[^\n]*/ius', '', $markdown);
        $markdown = preg_replace('/POLITEKNIK\s+NEGERI\s+SAMBAS\s+Jurusan\s+Teknologi\s+Informasi[^\n]+/iu', '', $markdown);
        $markdown = preg_replace('/Penyusun:\s*Fathushahib[^\n]*/ius', '', $markdown);
        $markdown = preg_replace('/Dosen\s+Pengampu\s*Fathushahib[^\n]*/ius', '', $markdown);
        $markdown = preg_replace('/\bFathushahib,\s*S\.Kom\.,\s*M\.Kom\.?\b/iu', '', $markdown);
        $markdown = preg_replace('/Rancang\s+Bangun\s+Server\s+Network\s+Operations\s+Center\s*\(NOC\)[^\n]+/iu', '', $markdown);
        $markdown = preg_replace('/Kode\s*\/\s*Bobot\s+MIF-3103[^\n]+/iu', '', $markdown);
        $markdown = preg_replace('/Semester\s*\/\s*TA\s+Ganjil[^\n]+/iu', '', $markdown);
        $markdown = preg_replace('/Pertemuan\s+Ke-\s*11\s+dari\s*18\s+pertemuan[^\n]+/iu', '', $markdown);
        $markdown = preg_replace('/Alokasi\s+Waktu\s+Teori[^\n]+/iu', '', $markdown);
        $markdown = preg_replace('/Tahapan\s+PBL\s+Fase\s+4[^\n]+/iu', '', $markdown);
        $markdown = preg_replace('/[^\n]*\d+\.\s*(?:Logbook\s+minggu|Catat\s+seluruh\s+hasil\s+pada\s+logbook)[^\n]+(?:diparaf|paraf)\s+dosen\.\s*\R?/miu', '', $markdown);

        // 3. Pangkas penugasan tugas PR / lembar kerja latihan kosong di akhir bacaan
        $markdown = preg_replace('/(?:\R+|^)(?:###\s*)?Latihan\s+[\d\.]+.*?\Z/ius', '', $markdown);
        $markdown = preg_replace('/(?:\s*Tugas\s+2\s+Analisislah\s+afiksasi\s+yang\s+terjadi.*?\Z)/ius', '', $markdown);
        $markdown = preg_replace('/(?:\s+Tugas|\R+Tugas)[\s\d\:\.]*\R+1\.\s+Simpulkanlah.*?(?=\R+\d+\.\s+[A-Z]|\Z)/ius', '', $markdown);
        $markdown = preg_replace('/(?:\R+|^)Tugas\s*\R+1\.\s+Simpulkanlah\s+fungsi\s+teks.*?(?=\R+\d+\.\s+[A-Z]|\Z)/ius', '', $markdown);
        $markdown = preg_replace('/\R+Tugas\s+1\s+Selanjutnya,?\s+lakukan\s+analisis.*?(?=\R+\d+\.\s+[A-Z]|\Z)/ius', "\n\n", $markdown);

        // 4. Bersihkan spanduk / instruksi tugas PR di sela-sela subbab (BS)
        $markdown = preg_replace('/(?:\(Sumber:[^\)]+\))?\s*Tugas\s+Sekarang,?\s+kerjakan\s+tugas-tugas.*?Kegiatan\s+2\s+Menyusun\s+Ringkasan/ius', "(Sumber: http://istiqomahalmaky.blogspot.co.id)\n\n### Kegiatan 2: Menyusun Ringkasan", $markdown);
        $markdown = preg_replace('/Setelah\s+ringkasan\s+yang\s+kamu\s+buat\s+selesai,?\s+lakukanlah\s+aktivitas.*?Bacalah\s+teks\s+laporan\s+hasil\s+observasi\s+berjudul\s+D[’\']\s*topeng\s+Museum\s+Angkut\s+berikut\s+ini\.?\s*(?:Kemudian,?\s+kerjakan\s+tugas-tugasnya[^\n\.]*\.?)?/ius', "### Teks: D' Topeng Museum Angkut\n\n", $markdown);
        $markdown = preg_replace('/(?:Setelah\s+berlatih\s+memahami\s+gagasan\s+pokok[^\n]+)?Tulislah\s+pada\s+buku\s+kerjamu!\s*Paragraf\s+Gagasan\s+Pokok.*?Kegiatan\s+3\s+Menyimpulkan\s+Fungsi/ius', "### Kegiatan 3: Menyimpulkan Fungsi", $markdown);

        $markdown = preg_replace('/(?:\s+Tugas|\R+Tugas)\s+\d+[\s\:\.]*Selanjutnya,?\s+lakukan\s+analisis\s+kebahasaan.*?Paragraf\s+ke:\s*/ius', "\n\n", $markdown);
        $markdown = preg_replace('/(?:\R+|^)\s*Selanjutnya,?\s+lanjutkan\s+dengan\s+mencari\s+\d+\s+kata.*?Paragraf\s+ke:\s*/ius', "\n\n", $markdown);
        $markdown = preg_replace('/^.*\(tabel\s+ini\s+hanya\s+contoh\).*$\R?/miu', '', $markdown);
        $markdown = preg_replace('/(?:\R+|^)\s*Tugas\s+\d*[\s\:\.]*Setelah\s+(?:mengikuti|berlatih|membuat)[^\n]+(?:\R+|$)/iu', "\n\n", $markdown);
        $markdown = preg_replace('/(?:\R+|^)\s*Tugas\s+\d*[\s\:\.]*Selanjutnya,?\s+berlatihlah[^\n]+(?:\R+|$)/iu', "\n\n", $markdown);
        $markdown = preg_replace('/(?:\R+|^)\s*Tugas\s+Sekarang,?\s+kerjakan\s+tugas-tugas[^\n]+(?:\R+|$)/iu', "\n\n", $markdown);
        $markdown = preg_replace('/(?:\R+|^)\s*Tugas\s+Selanjutnya,?\s+untuk\s+menguji\s+pemahamanmu[^\n]+(?:\R+|$)/iu', "\n\n", $markdown);
        $markdown = preg_replace('/(?:\s*Lakukanlah\s+analisis\s+afiksasi\s+terhadap\s+kata-kata.*?\Z)/ius', '.', $markdown);
        $markdown = preg_replace('/(?:\R+|^)[^\n]*\(tabel\s+ini\s+hanya\s+contoh\)[^\n]*/iu', '', $markdown);
        $markdown = preg_replace('/(?:\R+|^)\s*Eksplorasi\s+[\d\.]*\s+Melipat\s+kertas.*?(?=(?:\R+[-–•]|\R+###|\R+\d+\.|\Z))/ius', '', $markdown);
        $markdown = preg_replace('/Ayo,?\s+berkolaborasi\s+dengan\s+temanmu[^\n\.\!]+[\.\!]/iu', '', $markdown);
        $markdown = preg_replace('/Ayo,?\s+bereksplorasi\s+melipat\s+kertas[^\n\.\!]+[\.\!]/iu', '', $markdown);
        $markdown = preg_replace('/Jika\s+kertas\s+tersebut\s+dilipat\s+sebanyak\s+\d+\s+kali[^\n]+Tabel\s+2\.2[^\n\.\!]+[\.\!]/iu', '', $markdown);
        $markdown = preg_replace('/Tabel\s+2\.2[^\n]+Banyaknya\s+bagian\s+sama\s+besar[^\n]+Jelaskan\./ius', '', $markdown);
        $markdown = preg_replace('/Siapkan\s+kertas\s+berbentuk\s+persegi\s+panjang\.\s*/iu', '', $markdown);

        $markdown = preg_replace('/\.{2,}(?=\s*$)/u', '.', $markdown);

        return trim($markdown);
    }

    /**
     * Menyempurnakan struktur hierarki, heading submateri, tabel teks, dan rumus matematika
     * agar tersaji secara bersih, elegan, dan terstruktur bagi siswa di Reading Room.
     */
    public function restructureAndFormatPedagogy(string $text, string $title = ''): string
    {
        // 0. Bersihkan baris asterisk kosong & heading yatim terlebih dahulu
        $text = $this->cleanAsterisksAndEmptyHeadings($text);

        // 1. Buang pengulangan kalimat pembuka jika sama persis dengan pertanyaan excerpt
        $text = preg_replace('/^(?:###\s*[^\n]+\R+)?(Apa\s+perbedaan\s+barisan\s+dan\s+deret[^\n\?]+\?)\R+/iu', '', $text);

        // 2. Bersihkan artefak titik-titik isian lembar kerja U2 - U1 = ... - ... = ...
        $text = preg_replace('/U2\s*[–\-]\s*U1\s*=\s*\.{3,}\s*[–\-]\s*\.{3,}\s*=\s*\.{3,}(?:\s*U\s*[–\-]\s*U\s*=\s*\.{3,}\s*[–\-]\s*\.{3,}\s*=\s*\.{3,})*/u', '', $text);
        $text = preg_replace('/-\s*Suku\s+ke-\d+\s+dilambangkan\s+dengan\s+U\s*=\s*\.{3,}\s*/u', '', $text);
        $text = preg_replace('/(?:Gambar\s+2\.1[^\n]+)?Tabel\s+2\.1\s*Banyak\s+Meja\s+dan\s+Kursi\s*Banyak\s+meja\s*1\s*2\s*3\s*4\s*5\s*6\s*Banyak\s+kursi\s*4\s*6\s*[…\.]*\s*/iu', '', $text);
        $text = preg_replace('/\b4\s+6\s+8\s+10\s*[…\.]*\s*$/mu', '', $text);

        // 3. Bersihkan tabel kata dan frasa nomina/verba yang melebur
        $patternNomina = '/a\.\s*Nomina\s+Paragraf\s+Kata\s+Frasa\s+I\s+wayang\s+seni\s+pertunjukan[^\n]+boneka\s+kayu/iu';
        $replacementNomina = "**a. Contoh Identifikasi Kata dan Frasa Nomina (Kata Benda):**\n\n"
            . "- **Kata Nomina:** wayang, boneka, warisan, seni, dunia, lembaga, bahan.\n"
            . "- **Frasa Nomina:** *seni pertunjukan*, *warisan budaya asli Indonesia*, *pertunjukan bayangan boneka tersohor*, *sebuah warisan mahakarya dunia*, *lembaga yang mengurusi kebudayaan*, *wayang kulit*, *wayang golek*.\n\n";
        $text = preg_replace($patternNomina, $replacementNomina, $text) ?? $text;

        $patternVerba = '/b\.\s*Verba\s+Paragraf\s+Kata\s+Frasa\s+I\s+adalah\s+sudah\s+membagi\s+menetapkan\s+disesuaikan\s+dibuat\s+berarti\s+merupakan\s+mengembangkan/iu';
        $replacementVerba = "**b. Contoh Identifikasi Kata dan Frasa Verba (Kata Kerja):**\n\n"
            . "- **Kata Verba:** adalah, menetapkan, disesuaikan, dibuat, berarti, merupakan, mengembangkan.\n"
            . "- **Frasa Verba:** *sudah membagi*.\n\n";
        $text = preg_replace($patternVerba, $replacementVerba, $text) ?? $text;
        $text = preg_replace('/(?<=[a-z\*\.\!])\s+(Berdasarkan\s+analisis\s+kata\s+dan\s+frasa)/iu', "\n\n$1", $text);

        // 4. Format tabel afiksasi teks menjadi Markdown table yang rapi
        $afiksasiPattern = '/(?:\|\s*No\.\s*\|\s*Kata\s+Berimbuhan.*?pe\s*\(N\)-an\s*\|\s*teliti\s*\||No\.\s*Kata\s+Berimbuhan\s+Jenis\s+Imbuhan\s+Kata\s+Dasar.*?10\.\s*penelitian\s+nomina\s+pe\s*\(N\)-an\s+teliti\.?)/ius';
        $afiksasiTable = "| No. | Kata Berimbuhan | Jenis | Imbuhan | Kata Dasar |\n"
            . "| :---: | :--- | :---: | :---: | :--- |\n"
            . "| 1 | disebut | Verba | di- | sebut |\n"
            . "| 2 | menakutkan | Verba | me(N)-kan | takut |\n"
            . "| 3 | kemampuan | Nomina | ke-an | mampu |\n"
            . "| 4 | getaran | Nomina | -an | getar |\n"
            . "| 5 | menyusui | Verba | me(N)-i | susu |\n"
            . "| 6 | berasal | Verba | be(R)- | asal |\n"
            . "| 7 | mengisap | Verba | me(N)- | isap |\n"
            . "| 8 | menggigit | Verba | me(N)- | gigit |\n"
            . "| 9 | gigitan | Nomina | -an | gigit |\n"
            . "| 10 | penelitian | Nomina | pe(N)-an | teliti |";
        $text = preg_replace_callback($afiksasiPattern, fn () => $afiksasiTable, $text) ?? $text;

        // 5. Format tabel & tahapan berjenjang pada materi Ringkasan, Rangkuman, dan Ikhtisar (RRI)
        $rriTablePattern = '/Aspek\s+Ringkasan\s+Rangkuman\s+Ikhtisar\s+Tujuan\s+Memahami\s+isi\s+teks\s+secara\s+cepat\s+Mengambil\s+inti\s+pokok[^\n]+Ada\s*\(interpretatif\)/ius';
        $rriTableReplacement = "### Perbandingan Ringkasan, Rangkuman, dan Ikhtisar\n\n"
            . "| Aspek | Ringkasan | Rangkuman | Ikhtisar |\n"
            . "| :--- | :--- | :--- | :--- |\n"
            . "| **Tujuan** | Memahami isi teks secara cepat | Mengambil inti pokok dari beberapa sumber | Menunjukkan pemahaman dan pendapat pribadi |\n"
            . "| **Sumber** | Satu teks | Bisa satu atau beberapa teks | Satu teks, dipahami dan ditafsirkan |\n"
            . "| **Urutan Isi** | Mengikuti alur teks asli | Tidak harus mengikuti teks asli | Bebas (sesuai logika & alur penulis) |\n"
            . "| **Bahasa** | Objektif | Subjektif / Mandiri | Subjektif / Interpretatif |\n"
            . "| **Panjang** | Singkat | Lebih padat | Lebih bebas |\n"
            . "| **Unsur Pendapat** | Tidak ada | Tidak ada | Ada (bersifat interpretatif) |";
        $text = preg_replace($rriTablePattern, $rriTableReplacement, $text) ?? $text;

        $rriStepsPattern = '/(?:###\s*)?Langkah[- ]langkah\s*menulis\s+1\.\s*Menulis\s+Ringkasan\s+.*?4\.\s*Boleh\s+disertai\s+penilaian\s+atau\s+tanggapan\s+singkat\./ius';
        $rriStepsReplacement = "### Langkah-langkah Menulis\n\n"
            . "**A. Langkah Menulis Ringkasan:**\n"
            . "1. Baca teks asli dengan saksama.\n"
            . "2. Tandai kalimat utama setiap paragraf.\n"
            . "3. Buat kerangka isi (urutan gagasan utama).\n"
            . "4. Tulis kembali dengan bahasa sendiri secara singkat dan padat.\n"
            . "5. Periksa kesesuaian dengan teks sumber (jangan mengubah makna).\n\n"
            . "**B. Langkah Menulis Rangkuman:**\n"
            . "1. Catat ide-ide penting dari setiap bagian teks.\n"
            . "2. Gabungkan ide-ide penting tersebut menjadi beberapa poin inti.\n"
            . "3. Susun ulang secara logis tanpa memperhatikan urutan asli.\n"
            . "4. Gunakan bahasa sendiri.\n\n"
            . "**C. Langkah Menulis Ikhtisar:**\n"
            . "1. Baca dan pahami isi teks secara menyeluruh.\n"
            . "2. Tentukan gagasan pokok dan pandangan pribadi.\n"
            . "3. Tulis kembali dengan gaya bahasa sendiri.\n"
            . "4. Boleh disertai penilaian atau tanggapan singkat.";
        $text = preg_replace($rriStepsPattern, $rriStepsReplacement, $text) ?? $text;
        $text = preg_replace('/###\s*Perbedaan\s+Ringkasan,\s*Rangkuman,\s*dan\s*Ikhtisar\s*(?=\R*###\s*Langkah-langkah\s+Menulis)/iu', '', $text);
        $text = preg_replace('/(###\s*Langkah-langkah\s+Menulis)\s*(\*\*A\.)/u', "$1\n\n$2", $text);
        $text = preg_replace('/(\*\*[A-Z]\.\s*Langkah\s+Menulis\s+[^\n:]+:\*\*)\s*(?=1\.)/u', "$1\n\n", $text);

        // 6. Pisahkan Heading dan Sub-bab menjadi blok Markdown terpisah (idempotent)
        $subheadingPatterns = [
            '/(?<!###\s)(?<!\*\*)\b(\d+\.\s+Kata\s+serta\s+Frasa\s+Verba\s+dan\s+Nomina)\s+/u' => "\n\n### $1\n\n",
            '/(?<!###\s)(?<!\*\*)\b(\d+\.\s+Afiksasi)\s+(?=Dalam\s+kegiatan\s+berbahasa)/u' => "\n\n### $1\n\n",
            '/(?<!###\s)(?<!\*\*)\b(\d+\.\s+Barisan\s+Aritmetika)\s+(?=Eksplorasi|\d|\p{L})/u' => "\n\n### $1\n\n",
            '/(?<!###\s)(?<!\*\*)\b(\d+\.\s+Barisan\s+Geometri)\s+(?=Lima\s+anak|Eksplorasi|\d|\p{L})/u' => "\n\n### $1\n\n",
            '/(?<!###\s)(?<!\*\*)\b(Kegiatan\s+\d+\s+Menganalisis\s+Kebahasaan[^\n]+)/u' => "\n\n### $1\n\n",
            '/(?<!\*\*)\b(Eksplorasi\s+\d+[\.\d]*\s+(?:Penyebaran\s+Hoaks|Sifat-Sifat\s+Eksponen|Pembelahan\s+Bakteri|Banyaknya\s+Kursi|Gedung\s+Pertunjukan\s+Seni))\b(?!\*\*)/u' => "**$1**",
        ];
        foreach ($subheadingPatterns as $pat => $rep) {
            $text = preg_replace($pat, $rep, $text) ?? $text;
        }

        // Pastikan heading penting tidak menempel langsung dengan paragraf di bawahnya
        $text = preg_replace('/(###\s*\d+\.\s+Kata\s+serta\s+Frasa\s+Verba\s+dan\s+Nomina)\s*(Jenis\s+kata|Untuk\s+memahami)/u', "$1\n\n$2", $text);
        $text = preg_replace('/(###\s*\d+\.\s+Afiksasi)\s*(Dalam\s+kegiatan)/u', "$1\n\n$2", $text);
        $text = preg_replace('/(###\s*Kegiatan\s+\d+\s+[^\n]+?)\s*(###|\d+\.|\p{Lu})/u', "$1\n\n$2", $text);

        // 7. Normalisasi Matematika: Rumus Eksponen (MTK)
        $mtkContohAwalPattern = '/Perhatikan\s+contoh\s+berikut\s+ini\.?\s*1\.\s*2\s*[⇥×x]\s*2.*?5\.\s*a\s*[⇥×x]\s*a.*?ditulis\s+dengan\s+a\b/ius';
        $text = preg_replace_callback($mtkContohAwalPattern, function () {
            return "Perkalian berulang adalah perkalian yang dilakukan secara berulang dengan faktor yang sama. Perhatikan contoh perpangkatan berikut ini:\n\n"
                . "1. \$2 \\times 2 \\times 2 \\times 2 \\times 2 \\times 2\$ ditulis dengan \$2^6\$\n"
                . "2. \$5 \\times 5 \\times 5 \\times 5 \\times 5 \\times 5 \\times 5 \\times 5\$ ditulis dengan \$5^8\$\n"
                . "3. \$15 \\times 15 \\times 15 \\times 15\$ ditulis dengan \$15^4\$\n"
                . "4. \$7 \\times 7 \\times 7 \\times 7 \\times 7 \\times 7 \\times 7 \\times 7 \\times 7 \\times 7\$ ditulis dengan \$7^{10}\$\n"
                . "5. \$a \\times a \\times a \\times a \\times a \\times a \\times a\$ ditulis dengan \$a^7\$";
        }, $text) ?? $text;

        $hoaksPattern = '/Ada\s+hubungan\s+yang\s+menarik.*?Simak\s+polanya\s+sebagai\s+berikut\.?\s*1\s*=\s*2.*?Bentuk\s+2\s*,\s*2[^\n]+merupakan\s+bentuk\s+bilangan\s+pangkat\./ius';
        $text = preg_replace_callback($hoaksPattern, function () {
            return "Ada hubungan yang menarik antara fase penyebaran dengan jumlah orang yang menerima hoaks. Simak pola perpangkatannya sebagai berikut:\n\n"
                . "- **Fase 1:** \$2 = 2^1\$\n"
                . "- **Fase 2:** \$4 = 2 \\times 2 = 2^2\$\n"
                . "- **Fase 3:** \$8 = 2 \\times 2 \\times 2 = 2^3\$\n"
                . "- **Fase 4:** \$16 = 2 \\times 2 \\times 2 \\times 2 = 2^4\$\n"
                . "- **Fase ke-\$n\$:** Jika banyak orang yang menerima hoaks pada fase ke-\$n\$ dinyatakan dengan \$m\$, maka:\n"
                . "  \$\$m = \\underbrace{2 \\times 2 \\times 2 \\times \\dots \\times 2}_{n\\text{ faktor}} = 2^n\$\$\n\n"
                . "Dengan memanfaatkan kesimpulan di atas, jumlah orang yang menerima hoaks pada **fase ke-5** dapat dengan mudah dihitung:\n"
                . "\$\$2^5 = 32\\text{ orang}\$\$\n\n"
                . "Bentuk \$2^1, 2^2, 2^3, 2^4,\$ dan \$2^n\$ ini merupakan bentuk **bilangan berpangkat (eksponen)**.";
        }, $text) ?? $text;

        $defEksponenPattern = '/Jika\s+a\s+adalah\s+bilangan\s+real\s+dan\s+n\s+adalah\s+bilangan\s+bulat\s+positif.*?Berikut\s+adalah\s+beberapa\s+definisi\s+penting.*?3\.\s*Jika\s+a\s+adalah\s+bilangan\s+real\s+dengan\s+a6=0[^\n]+/ius';
        $text = preg_replace_callback($defEksponenPattern, function () {
            return "Jika \$a\$ adalah bilangan real dan \$n\$ adalah bilangan bulat positif, maka \$a^n\$ menyatakan hasil kali bilangan \$a\$ sebanyak \$n\$ faktor dan ditulis:\n\n"
                . "\$\$a^n = \\underbrace{a \\times a \\times a \\times \\dots \\times a}_{n\\text{ faktor}}\$\$\n\n"
                . "Dalam bentuk \$a^n\$, \$a\$ disebut sebagai **bilangan pokok (basis)** dan \$n\$ disebut sebagai **pangkat (eksponen)**.\n\n"
                . "Berikut beberapa definisi penting perpangkatan:\n"
                . "1. Jika \$a\$ adalah bilangan real dengan \$a \\neq 0\$ dan \$n\$ bilangan bulat positif, maka pangkat bulat negatif didefinisikan sebagai:\n"
                . "   \$\$a^{-n} = \\frac{1}{a^n}\$\$\n"
                . "2. Untuk \$a \\neq 0\$, bilangan berpangkat nol didefinisikan sebagai:\n"
                . "   \$\$a^0 = 1\$\$\n"
                . "3. Jika \$a\$ adalah bilangan real dengan \$a \\geq 0\$ dan \$n\$ bilangan bulat positif, maka berlaku hubungan bentuk akar:\n"
                . "   \$\$a^{\\frac{1}{n}} = \\sqrt[n]{a}\$\$\n"
                . "4. Untuk bilangan rasional \$\\frac{m}{n}\$ dengan \$a > 0\$:\n"
                . "   \$\$a^{\\frac{m}{n}} = \\left(a^{\\frac{1}{n}}\\right)^m = (\\sqrt[n]{a})^m = \\sqrt[n]{a^m}\$\$";
        }, $text) ?? $text;

        $sifatEksponenPattern = '/(?:\d+\.\s*2\s*∙\s*2\s*4\)\s*7\)\s*<\s*;|Tabel\s+1\.1\s*Bentuk\s+Eksponen).*?\Z/ius';
        $text = preg_replace_callback($sifatEksponenPattern, function () {
            return "### Sifat-Sifat Eksponen\n\n"
                . "Perhatikan tabel perpangkatan basis 2 berikut:\n\n"
                . "| Nilai \$n\$ | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 |\n"
                . "| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |\n"
                . "| **Bentuk \$2^n\$** | \$2^1\$ | \$2^2\$ | \$2^3\$ | \$2^4\$ | \$2^5\$ | \$2^6\$ | \$2^7\$ | \$2^8\$ | \$2^9\$ | \$2^{10}\$ |\n"
                . "| **Hasil** | 2 | 4 | 8 | 16 | 32 | 64 | 128 | 256 | 512 | 1.024 |\n\n"
                . "Berikut sifat-sifat aljabar eksponen yang berlaku untuk \$a, b > 0\$ serta \$m, n\$ bilangan bulat maupun rasional:\n\n"
                . "1. **Perkalian Eksponen Basis Sama:**\n"
                . "   \$\$a^m \\cdot a^n = a^{m+n}\$\$\n"
                . "2. **Pembagian Eksponen Basis Sama:**\n"
                . "   \$\$\\frac{a^m}{a^n} = a^{m-n}, \\quad (a \\neq 0)\$\$\n"
                . "3. **Perpangkatan dari Bilangan Berpangkat:**\n"
                . "   \$\$(a^m)^n = a^{m \\cdot n}\$\$\n"
                . "4. **Perpangkatan dari Perkalian:**\n"
                . "   \$\$(a \\cdot b)^m = a^m \\cdot b^m\$\$\n"
                . "5. **Perpangkatan dari Pembagian:**\n"
                . "   \$\$\\left(\\frac{a}{b}\\right)^m = \\frac{a^m}{b^m}, \\quad (b \\neq 0)\$\$\n"
                . "6. **Perkalian Eksponen Pecahan Berpenyebut Sama:**\n"
                . "   \$\$a^{\\frac{m}{n}} \\cdot a^{\\frac{p}{n}} = a^{\\frac{m+p}{n}}\$\$\n"
                . "7. **Perkalian Eksponen Pecahan Berpenyebut Berbeda:**\n"
                . "   \$\$a^{\\frac{m}{n}} \\cdot a^{\\frac{p}{q}} = a^{\\frac{m}{n} + \\frac{p}{q}}\$\$\n\n"
                . "#### Contoh 1\n"
                . "Sederhanakanlah bentuk eksponen: \$(2^3 \\cdot 2^4)^2\$\n\n"
                . "**Alternatif Penyelesaian:**\n"
                . "Gunakan sifat perkalian basis sama \$a^m \\cdot a^n = a^{m+n}\$ terlebih dahulu:\n"
                . "\$\$2^3 \\cdot 2^4 = 2^{3+4} = 2^7\$\$\n"
                . "Kemudian terapkan sifat perpangkatan \$(a^m)^n = a^{m \\cdot n}\$:\n"
                . "\$\$(2^7)^2 = 2^{7 \\cdot 2} = 2^{14}\$\$\n\n"
                . "#### Contoh 2\n"
                . "Sederhanakanlah bentuk eksponen: \$\\left(\\frac{x^5 \\cdot y^2}{x^2 \\cdot y}\\right)^3\$\n\n"
                . "**Alternatif Penyelesaian:**\n"
                . "Sederhanakan variabel di dalam kurung menggunakan sifat pembagian \$\\frac{a^m}{a^n} = a^{m-n}\$:\n"
                . "\$\$x^{5-2} \\cdot y^{2-1} = x^3 \\cdot y\$\$\n"
                . "Pangkatkan suku di dalam kurung ke pangkat 3:\n"
                . "\$\$(x^3 \\cdot y)^3 = (x^3)^3 \\cdot y^3 = x^9 \\cdot y^3\$\$";
        }, $text) ?? $text;

        // 8. Normalisasi Matematika: Barisan Aritmetika & Geometri (p014 & P014)
        $p014AritmetikaPattern = '/Ayo,\s+bandingkan\s+banyak\s+meja\s+dan\s+kursi.*?selalu\s+tetap\s+atau\s+konstan\s+disebut\s+BARISAN\s+ARITMETIKA\s*\./ius';
        $text = preg_replace_callback($p014AritmetikaPattern, function () {
            return "Perhatikan pola penataan meja dan kursi berikut:\n\n"
                . "- Satu meja segi empat dilengkapi dengan 4 kursi.\n"
                . "- Dua meja disatukan menghasilkan 6 kursi.\n"
                . "- Tiga meja disatukan menghasilkan 8 kursi.\n\n"
                . "Jika diamati, susunan jumlah kursi membentuk barisan bilangan: **4, 6, 8, 10, ...**\n\n"
                . "Suatu barisan bilangan yang memiliki selisih (beda) tetap antara dua suku yang berurutan disebut **BARISAN ARITMETIKA**.\n"
                . "Beda dilambangkan dengan \$b\$, dengan rumus:\n"
                . "\$\$b = U_n - U_{n-1}\$\$\n\n"
                . "Contoh: \$b = U_2 - U_1 = 6 - 4 = 2\$.";
        }, $text) ?? $text;

        $p014Contoh1Pattern = '/Contoh\s+1\s+Diketahui\s+suatu\s+barisan\s+aritmetika.*?Jadi,\s+rumus\s+suku\s+ke-n\s+dari\s+barisan\s+tersebut\s+adalah\s+Un=3n/ius';
        $text = preg_replace_callback($p014Contoh1Pattern, function () {
            return "#### Contoh 1\n"
                . "Diketahui suatu barisan aritmetika dengan suku ke-3 = 9 dan suku ke-6 = 18. Tentukan rumus suku ke-\$n\$.\n\n"
                . "**Alternatif Penyelesaian:**\n"
                . "Gunakan rumus suku ke-\$n\$: \$U_n = a + (n - 1)b\$\n"
                . "- \$U_3 = a + 2b = 9\$ ... (Persamaan 1)\n"
                . "- \$U_6 = a + 5b = 18\$ ... (Persamaan 2)\n\n"
                . "Kurangkan Persamaan 2 dengan Persamaan 1:\n"
                . "\$\$(a + 5b) - (a + 2b) = 18 - 9 \\implies 3b = 9 \\implies b = 3\$\$\n\n"
                . "Substitusikan \$b = 3\$ ke Persamaan 1:\n"
                . "\$\$a + 2(3) = 9 \\implies a + 6 = 9 \\implies a = 3\$\$\n\n"
                . "Maka rumus suku ke-\$n\$ adalah:\n"
                . "\$\$U_n = a + (n - 1)b = 3 + (n - 1)3 = 3 + 3n - 3 = 3n\$\$\n\n"
                . "Jadi, rumus suku ke-\$n\$ dari barisan tersebut adalah **\$U_n = 3n\$**.";
        }, $text) ?? $text;

        $p014GeometriPattern = '/Mari,\s+belajar\s+Ayo,\s+Mengingat\s+Kembali\s*[-•]\s*Apakah\s+banyaknya\s+bagian\s+yang\s+sama\s+besar.*?tetap\s+atau\s+konstan\s+disebut\s+BARISAN\s+GEOMETRI\s*\./ius';
        $text = preg_replace_callback($p014GeometriPattern, function () {
            return "Suatu barisan bilangan dengan perbandingan (rasio) antara dua suku yang berurutan bernilai tetap atau konstan disebut **BARISAN GEOMETRI**.\n\n"
                . "Rasio pada barisan geometri dilambangkan dengan \$r\$, dan dihitung dengan cara membagi suku yang berurutan:\n"
                . "\$\$r = \\frac{U_n}{U_{n-1}} = \\frac{U_2}{U_1} = \\frac{U_3}{U_2}\$\$";
        }, $text) ?? $text;

        $bakteriPattern = '/(?:\*\*Eksplorasi[^\n\*]*Pembelahan\s+Bakteri[^\n\*]*\*\*|Eksplorasi\s+[\d\.]*\s+Pembelahan\s+Bakteri)\s+Pembelahan\s+bakteri.*?(?=####\s*Contoh|Contoh\s+3)/ius';
        $text = preg_replace_callback($bakteriPattern, function () {
            return "### Pembelahan Bakteri (Aplikasi Barisan Geometri)\n\n"
                . "Bakteri berkembang biak dengan cara membelah diri. Misalkan dalam waktu dua jam, setiap satu sel bakteri membelah menjadi 3 bagian.\n\n"
                . "Jika jumlah mula-mula adalah 2 sel bakteri (\$a = 2\$), dan rasio pembelahan adalah 3 (\$r = 3\$):\n"
                . "- Setelah 2 jam (fase 1): \$U_1 = 2 \\times 3^0 = 2\$\n"
                . "- Setelah 4 jam (fase 2): \$U_2 = 2 \\times 3^1 = 6\$\n"
                . "- Setelah 6 jam (fase 3): \$U_3 = 2 \\times 3^2 = 18\$\n\n"
                . "Dalam 20 jam, terjadi pembelahan sebanyak \$20 \\div 2 = 10\$ periode, sehingga \$n = 10\$:\n"
                . "\$\$U_n = a \\cdot r^{n-1}\$\$\n"
                . "\$\$U_{10} = 2 \\times 3^{10-1} = 2 \\times 3^9 = 2 \\times 19.683 = 39.366\\text{ sel}\$\$\n\n"
                . "**Rumus Umum Suku ke-\$n\$ Barisan Geometri:**\n"
                . "\$\$U_n = a \\cdot r^{n-1}\$\$\n\n"
                . "**Keterangan:**\n"
                . "- \$U_n\$ = suku ke-\$n\$\n"
                . "- \$a\$ = suku pertama (\$U_1\$)\n"
                . "- \$r\$ = rasio (\$\\frac{U_n}{U_{n-1}}\$)\n"
                . "- \$n\$ = nomor suku\n\n";
        }, $text) ?? $text;

        $geometriContohPattern = '/Contoh\s+3\s+Suku\s+pertama\s+dari\s+suatu\s+barisan\s+geometri.*?dapat\s+juga\s+memanfaatkan\s+konsep\s+sifat\s+bilangan\s+eksponen\./ius';
        $text = preg_replace_callback($geometriContohPattern, function () {
            return "#### Contoh 3\n"
                . "Suku pertama dari suatu barisan geometri adalah 4 dan suku ke-4 adalah 108. Tentukan rasio dari barisan tersebut.\n\n"
                . "**Alternatif Penyelesaian:**\n"
                . "Diketahui: \$a = 4\$ dan \$U_4 = 108\$.\n"
                . "Gunakan rumus suku ke-\$n\$: \$U_n = a \\cdot r^{n-1}\$\n"
                . "\$\$U_4 = a \\cdot r^3 \\implies 108 = 4 \\cdot r^3\$\$\n"
                . "\$\$r^3 = \\frac{108}{4} = 27 \\implies r = \\sqrt[3]{27} = 3\$\$\n\n"
                . "Jadi, rasio barisan geometri tersebut adalah **3**.\n\n"
                . "#### Contoh 4\n"
                . "Seutas tali dipotong menjadi 5 bagian dengan ukuran panjang membentuk barisan geometri. Jika panjang tali yang paling pendek adalah 16 cm dan yang paling panjang adalah 81 cm, tentukan panjang tali pada potongan ketiga.\n\n"
                . "**Alternatif Penyelesaian:**\n"
                . "Diketahui: \$n = 5\$, suku pertama \$a = U_1 = 16\\text{ cm}\$, dan suku terakhir \$U_5 = 81\\text{ cm}\$.\n"
                . "Tentukan rasio \$r\$ terlebih dahulu:\n"
                . "\$\$U_5 = a \\cdot r^4 \\implies 81 = 16 \\cdot r^4\$\$\n"
                . "\$\$r^4 = \\frac{81}{16} \\implies r = \\sqrt[4]{\\frac{81}{16}} = \\frac{3}{2}\$\$\n\n"
                . "Selanjutnya, hitung panjang potongan ketiga (\$U_3\$):\n"
                . "\$\$U_3 = a \\cdot r^2 = 16 \\cdot \\left(\\frac{3}{2}\\right)^2 = 16 \\cdot \\frac{9}{4} = 36\\text{ cm}\$\$\n\n"
                . "Jadi, panjang tali pada potongan ketiga adalah **36 cm**.";
        }, $text) ?? $text;

        // 9. Pisahkan Contoh Soal dan Alternatif Penyelesaian menjadi blok terstruktur (idempotent)
        $text = preg_replace('/(?:\R+|^|(?<=[.!?])\s*)(?<!####\s)(Contoh\s+\d+)\s+([^\n]+?)\s*(Alternatif\s+[pP]enyelesaian:?)/iu', "\n\n#### $1\n$2\n\n**$3**\n", $text);
        $text = preg_replace('/(?<!\*\*)\b(Alternatif\s+[pP]enyelesaian:?)(?!\*\*)/iu', "\n\n**$1**\n\n", $text);

        // 10. Rapikan baris latihan isian titik-titik berulang pada pembelahan bakteri / gedung seni
        $text = preg_replace('/U\s*=\s*2\s*×\s*…\s*\(2\s*dikali\s*3\s*sebanyak\s*…\s*kali\)[^\n]+(?=Suku\s+ke-n)/ius', 
            "Dari pola tersebut diperoleh bahwa untuk suku ke-n:\n\n- Suku ke-1: \$U_1 = a = 2\$\n- Suku ke-2: \$U_2 = 2 \\times 3^1 = 6\$\n- Suku ke-3: \$U_3 = 2 \\times 3^2 = 18\$\n- Suku ke-n: \$U_n = a \\cdot r^{n-1}\$\n\n", $text);

        $polaKursiPattern = '/(?:-\s*Baris\s+ke-1\s*=\s*20\s*-\s*Baris\s+ke-2\s*=\s*24\s*=\s*20\+\s*4[^\n]+|Pola\s+penambahan\s+kursi\s+tiap\s+baris:?\*?\*?\s*-?\s*Baris\s+ke-1[^\n]+)(?=Gambar\s+2\.3|Jadi,\s+rumus)/ius';
        $text = preg_replace_callback($polaKursiPattern, function () {
            return "Pola penambahan kursi tiap baris:\n\n- Baris ke-1 (\$U_1\$): 20\n- Baris ke-2 (\$U_2\$): \$20 + 4 = 24\$\n- Baris ke-3 (\$U_3\$): \$20 + 2(4) = 28\$\n- Baris ke-n (\$U_n\$): \$20 + (n - 1) \\times 4\$\n\n";
        }, $text) ?? $text;

        // 11. Rapikan rumus matematika agar berformat LaTeX rapi
        $text = preg_replace('/\bU\s*n\s*=\s*a\s*\+\s*\(n\s*-\s*1\)\s*b\b/iu', '$U_n = a + (n - 1)b$', $text);
        $text = preg_replace('/\bb\s*=\s*U\s*n\s*–\s*U\s*\(n[–\-]1\)\b/iu', '$b = U_n - U_{n-1}$', $text);
        $text = preg_replace('/\bU\s*n\s*=\s*a\s*\(\s*r\s*\)\s*\(n-1\)\b/iu', '$U_n = a \\cdot r^{n-1}$', $text);

        // 10. Sambungkan paragraf yang terpotong di tengah kalimat
        $text = $this->glueBrokenParagraphSentences($text);

        // 11. Bersihkan sisa tanda bintang dan heading kosong
        $text = $this->cleanAsterisksAndEmptyHeadings($text);

        // 12. Normalisasi spasi & baris baru
        $text = preg_replace('/[ \t]+/u', ' ', $text);
        $text = preg_replace('/\R{3,}/u', "\n\n", $text);

        return trim($text);
    }

    /**
     * Membersihkan tanda bintang (asterisk) yang menumpuk liar, baris kosong yang hanya berisi
     * simbol (**, ###), serta memastikan setiap heading memiliki pemisah baris yang bersih.
     */
    public function cleanAsterisksAndEmptyHeadings(string $text): string
    {
        // 1. Buang baris kosong yang hanya berisi tanda bintang (*), pagar (#), underscore (_), strip (-)
        $text = preg_replace('/^\s*[*#_–—\-]{2,}\s*$\R?/mu', '', $text);
        $text = preg_replace('/^\s*#{1,6}\s*$\R?/mu', '', $text);

        // 2. Koreksi eksplorasi matematika yang salah menyedot kata kalimat berikutnya
        $text = preg_replace('/\*\*(Eksplorasi\s+[\d\.]+\s+Pembelahan\s+Bakteri)[^\n\*]*\*\*\s*makhluk\*+/iu', "**$1**\n\nPembelahan bakteri merupakan makhluk", $text);
        $text = preg_replace('/\*\*(Eksplorasi\s+[\d\.]+\s+Penyebaran\s+Hoaks)[^\n\*]*\*\*\s*membagikan\*+/iu', "**$1**\n\nSeseorang membagikan", $text);
        $text = preg_replace('/\*\*(Eksplorasi\s+[\d\.]+\s+Sifat-Sifat\s+Eksponen)[^\n\*]*\*\*\s*tabel\*+/iu', "**$1**\n\nPerhatikan tabel", $text);
        $text = preg_replace('/\*\*Eksplorasi\s+1\.1\s+yang\s+sudah\s+kamu\*+/iu', '', $text);

        // 3. Bersihkan tumpukan bintang berlebih (misal makhluk******, tabel****, dll.)
        $text = preg_replace('/(?<!\*)\*{3,}(?!\*)/u', '', $text);
        $text = preg_replace('/(?<=\p{L})\*+\s*$/mu', '', $text);
        $text = preg_replace('/^\s*\*\s+/mu', '- ', $text);

        // 4. Pastikan setiap heading terisolasi dengan double newline (\n\n) agar tidak menyatu dengan paragraf
        $text = preg_replace('/(?<!\n)\n(#{1,6}\s+[^\n]+)/u', "\n\n$1", $text);
        $text = preg_replace('/(#{1,6}\s+[^\n]+)\n(?!\n)/u', "$1\n\n", $text);

        // 5. Normalisasi heading kosong berulang
        $text = preg_replace('/(?:^\s*#{1,6}\s*$\R?)+/mu', '', $text);

        return $text;
    }

    /**
     * Menyambungkan kembali paragraf-paragraf yang terpotong di tengah kalimat akibat pemisah halaman PDF,
     * sehingga setiap pergantian paragraf selalu terjadi pada batas kalimat lengkap (diakhiri tanda baca titik/penutup).
     */
    public function glueBrokenParagraphSentences(string $text): string
    {
        $blocks = preg_split('/\R{2,}/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $glued = [];
        $count = count($blocks);

        for ($i = 0; $i < $count; $i++) {
            $curr = trim($blocks[$i]);
            if ($curr === '') {
                continue;
            }

            // Jika blok saat ini adalah heading, list, kode, tabel, atau label sub-bagian tebal, jangan sambung ke berikutnya
            if (preg_match('/^(?:#{1,6}\s+|[-–•\d]\s+|```|<|\||\*\*[A-Z]\.)/u', $curr)) {
                $glued[] = $curr;
                continue;
            }

            while ($i + 1 < $count) {
                $next = trim($blocks[$i + 1]);
                if ($next === '') {
                    $i++;
                    continue;
                }

                // Jangan sambung jika blok berikutnya adalah heading, list, kode, tabel, atau label sub-bagian tebal
                if (preg_match('/^(?:#{1,6}\s+|[-–•\d]\s+|```|<|\||\*\*[A-Z]\.)/u', $next)) {
                    break;
                }

                $currClean = preg_replace('/[*_`\s]+$/u', '', $curr) ?? $curr;
                $endsCleanSentence = (bool) preg_match('/[.!?:\x{2026}”"’\)]$/u', $currClean);
                $nextStartsLower = (bool) preg_match('/^(?:[a-z\p{Ll}]|atau\b|dan\b|dari\b|yang\b|untuk\b|sehingga\b|serta\b|pada\b|dengan\b|yaitu\b|adalah\b)/u', $next);

                if (! $endsCleanSentence && $nextStartsLower) {
                    $curr = $curr . ' ' . $next;
                    $i++;
                } else {
                    break;
                }
            }

            $glued[] = $curr;
        }

        return implode("\n\n", $glued);
    }

    /**
     * Memastikan struktur pedagogis materi terjamin rapi bagi siswa:
     * - Jika materi belum memiliki bagian Tujuan/Capaian Pembelajaran, Socrates menganalisis
     *   dan menyintesiskan struktur Tujuan Pembelajaran di awal materi.
     * - Memastikan pemisahan heading yang jelas antara tujuan, definisi, dan isi materi.
     */
    public function ensurePedagogicalStructure(string $markdown, string $title = ''): string
    {
        $hasObjectives = preg_match('/###\s*(?:TUJUAN|CAPAIAN)\s+PEMBELAJARAN|Setelah mengikuti pembelajaran|Mahasiswa diharapkan mampu|Siswa diharapkan mampu/iu', $markdown);
        if (! $hasObjectives && trim($title) !== '') {
            $cleanTitle = trim(preg_replace('/[*#_]/u', '', $title));
            if (preg_match('/\b(?:P11|Sistem\s+Operasi|Sinkronisasi|Deadlock)\b/i', $cleanTitle)) {
                $objectivesBlock = "### TUJUAN PEMBELAJARAN\n\nSetelah mempelajari materi ini, siswa diharapkan mampu:\n- Menganalisis permasalahan sinkronisasi proses dan terjadinya race condition.\n- Menjelaskan konsep critical section dan cara kerja mekanisme mutex serta semaphore.\n- Mengidentifikasi empat syarat terjadinya deadlock dan strategi penanganannya pada sistem operasi.";
            } elseif (preg_match('/\b(?:P014|p014|Barisan\s+Geometri|Barisan\s+Aritmetika)\b/i', $cleanTitle)) {
                $objectivesBlock = "### TUJUAN PEMBELAJARAN\n\nSetelah mempelajari materi ini, siswa diharapkan mampu:\n- Memahami konsep dasar pola bilangan, barisan aritmetika, dan barisan geometri.\n- Menentukan suku ke-\$n\$ dan beda atau rasio pada barisan aritmetika dan geometri.\n- Menyelesaikan permasalahan kontekstual nyata menggunakan konsep barisan dan deret.";
            } else {
                $objectivesBlock = "### TUJUAN PEMBELAJARAN\n\nSetelah mempelajari materi ini, siswa diharapkan mampu:\n- Memahami pengertian, konsep dasar, dan latar belakang mengenai {$cleanTitle}.\n- Mengidentifikasi karakteristik, komponen penting, dan prinsip utama dalam materi.\n- Menganalisis keterkaitan antargagasan serta penerapan praktis dari topik yang dibahas.";
            }

            if (preg_match('/^(###\s+[^\n]+\n+)/u', $markdown, $m)) {
                $markdown = $m[1] . $objectivesBlock . "\n\n" . substr($markdown, strlen($m[1]));
            } else {
                $markdown = "### " . mb_strtoupper($cleanTitle) . "\n\n" . $objectivesBlock . "\n\n" . $markdown;
            }
        }

        return $markdown;
    }

    /**
     * Mengonversi teks terformat Markdown menjadi HTML yang aman & terstruktur rapi.
     */
    public function toHtml(string $formattedMarkdown): string
    {
        return $this->toHtmlWithParagraphMarkers($formattedMarkdown);
    }

    /**
     * Mengonversi Markdown materi menjadi HTML berstruktur dengan penomoran paragraf interaktif [¶1], [¶2], dst.
     * yang sinkron 1-to-1 dengan rujukan Socratic Coach.
     */
    public function toHtmlWithParagraphMarkers(string $formattedMarkdown, ?int &$pIndex = null): string
    {
        $blocks = preg_split('/\R{2,}/u', trim($formattedMarkdown), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $htmlBlocks = [];
        $currentIndex = $pIndex ?? 1;

        foreach ($blocks as $block) {
            $trimmed = trim($block);
            if ($trimmed === '' || preg_match('/^\s*[*#_–—\-]{2,}\s*$/u', $trimmed)) {
                continue;
            }

            // Heading: render langsung tanpa penomoran paragraf
            if (preg_match('/^#{1,6}\s+/u', $trimmed)) {
                $headingHtml = Str::markdown($trimmed, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
                $htmlBlocks[] = $headingHtml;
                continue;
            }

            // Substantive block (paragraf, bullet list, table, quote): bungkus dengan nomor paragraf interaktif
            $contentHtml = trim(Str::markdown($trimmed, ['html_input' => 'escape', 'allow_unsafe_links' => false]));
            if (str_contains($contentHtml, '<table')) {
                $contentHtml = preg_replace('/<table\b/u', '<div class="table-wrapper"><table', $contentHtml) ?? $contentHtml;
                $contentHtml = str_replace('</table>', '</table></div>', $contentHtml);
            }
            $htmlBlocks[] = <<<HTML
<div class="passage-para-block group" id="para-{$currentIndex}" data-paragraph-index="{$currentIndex}">
    <span class="para-marker" title="Paragraf {$currentIndex}">¶{$currentIndex}</span>
    <div class="para-content">{$contentHtml}</div>
</div>
HTML;
            $currentIndex++;
        }

        if ($pIndex !== null) {
            $pIndex = $currentIndex;
        }

        return implode("\n", $htmlBlocks);
    }

    /**
     * Membagi materi terformat menjadi beberapa halaman "kertas buku" yang seimbang
     * tanpa memotong di tengah daftar (list), heading yang yatim, atau blok kode.
     *
     * @return array<int, string> Array HTML per halaman
     */
    public function splitIntoPages(string $formattedMarkdown, int $idealPageChars = 2100): array
    {
        $chunks = preg_split('/\R{2,}/u', trim($formattedMarkdown), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (empty($chunks)) {
            $globalPIndex = 1;
            return [$this->toHtmlWithParagraphMarkers($formattedMarkdown, $globalPIndex)];
        }

        $totalLen = array_sum(array_map('mb_strlen', $chunks));
        // Hitung estimasi jumlah halaman yang pas dan proporsional dengan panjang materi
        $targetPages = max(1, (int) round($totalLen / $idealPageChars));
        if ($targetPages <= 1) {
            $globalPIndex = 1;
            return [$this->toHtmlWithParagraphMarkers($formattedMarkdown, $globalPIndex)];
        }

        $targetCharsPerPage = (int) ceil($totalLen / $targetPages);

        $pagesMd = [];
        $currentParts = [];
        $currentLen = 0;
        $totalChunks = count($chunks);

        for ($i = 0; $i < $totalChunks; $i++) {
            $chunk = trim($chunks[$i]);
            $chunkLen = mb_strlen($chunk);
            $isHeading = str_starts_with($chunk, '#');
            $pagesLeft = $targetPages - count($pagesMd);

            // Jika tinggal 1 halaman tersisa, masukkan semua sisa ke halaman terakhir
            if ($pagesLeft <= 1) {
                $currentParts[] = $chunk;
                $currentLen += $chunkLen;
                continue;
            }

            // Ganti halaman jika:
            // 1. Bertemu heading baru dan halaman saat ini sudah terisi >= 65% target
            // 2. Paragraf biasa sudah mencapai target rata-rata karakter per halaman
            $headingBreak = $isHeading && $currentLen >= ($targetCharsPerPage * 0.65) && ! empty($currentParts);
            $sizeBreak = ! $isHeading && $currentLen >= $targetCharsPerPage && ! empty($currentParts);

            if ($headingBreak || $sizeBreak) {
                $pagesMd[] = implode("\n\n", $currentParts);
                $currentParts = [];
                $currentLen = 0;
            }

            $currentParts[] = $chunk;
            $currentLen += $chunkLen;
        }

        if (! empty($currentParts)) {
            // Jika halaman terakhir sangat pendek (< 35% target) dan sudah ada halaman sebelumnya, gabungkan agar tidak ada halaman kosong
            if (count($pagesMd) > 0 && $currentLen < ($targetCharsPerPage * 0.35)) {
                $pagesMd[count($pagesMd) - 1] .= "\n\n" . implode("\n\n", $currentParts);
            } else {
                $pagesMd[] = implode("\n\n", $currentParts);
            }
        }

        $globalPIndex = 1;

        return array_map(function (string $md) use (&$globalPIndex) {
            return $this->toHtmlWithParagraphMarkers($md, $globalPIndex);
        }, $pagesMd);
    }

    /**
     * Memulihkan spasi pada teks PDF yang menempel akibat layout slide atau tabel.
     */
    public function restoreMissingSpaces(string $text): string
    {
        // Pisahkan marker bullet di awal baris jika menempel dengan kata pertama
        $text = preg_replace('/^([•·\x{2022}\x{25CB}\x{25CF}\x{25AA}\x{25AB}\x{25BA}\x{2713}\x{2714}⚬○■□▪▫◆◇])(?=\S)/u', '$1 ', $text) ?? $text;
        $text = preg_replace('/^(→)(?=\S)/u', '$1 ', $text) ?? $text;
        $text = preg_replace('/(\S)(→)(\S)/u', '$1 $2 $3', $text) ?? $text;
        $text = preg_replace('/(→)(?=\S)/u', '$1 ', $text) ?? $text;

        // Lindungi token khusus (URL, email, path, directive CamelCase) sebelum memecah kata
        $placeholders = [];
        $protect = function (string $value) use (&$placeholders): string {
            $token = '@@PROT' . count($placeholders) . '@@';
            $placeholders[$token] = $value;
            return $token;
        };

        $text = preg_replace_callback(
            '/(?:https?:\/\/\S+|\b[a-zA-Z0-9.-]+\.(?:net|com|org|edu|gov|id|co\.id|ac\.id|go\.id|sch\.id|io|ai|me)\b|[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}|\/[A-Za-z0-9._\-\/]+|\b(?:' . implode('|', self::PROTECTED_CAMEL_TERMS) . ')\b)/u',
            fn (array $m) => $protect($m[0]),
            $text
        ) ?? $text;

        // 1. Pecah batas CamelCase (misal "TrigatraBangunBahasa" -> "Trigatra Bangun Bahasa")
        $text = preg_replace('/(?<=\p{Ll})(?=\p{Lu})/u', ' ', $text) ?? $text;
        // 2. Pecah tanda baca dan tanda kutip tutup yang menempel dengan huruf berikutnya
        $text = preg_replace('/([,;:])(?=\p{L})/u', '$1 ', $text) ?? $text;
        $text = preg_replace('/([”’"])(?=\p{L})/u', '$1 ', $text) ?? $text;
        $text = preg_replace('/(?<=\p{Ll}\.)(?=\p{Lu})/u', ' ', $text) ?? $text;
        // 3. Pecah angka/tanda kurung tutup yang menempel dengan huruf
        $text = preg_replace('/\)(?=\p{L})/u', ') ', $text) ?? $text;
        $text = preg_replace('/(?<=\p{L})\(/u', ' (', $text) ?? $text;
        // Pecah penomoran dan rentang angka yang menempel (misal 1.Objektif -> 1. Objektif, a.Pengertian -> a. Pengertian)
        $text = preg_replace('/(?<=\s|^)(\d{1,3})\.([A-Z\p{L}])/u', '$1. $2', $text) ?? $text;
        $text = preg_replace('/(?<=\s|^)([a-zA-Z\d]{1,3}[\.\)])([A-Za-z])/u', '$1 $2', $text) ?? $text;
        $text = preg_replace('/(\d+)\s*[–—\-]\s*(\d+)\s*kata\b/iu', '$1–$2 kata', $text) ?? $text;
        $text = preg_replace('/[±~]/u', '', $text) ?? $text;
        $text = preg_replace('/(?<=\p{L})[–—](?=\p{L})/u', ' – ', $text) ?? $text;

        // Perbaikan kata-kata terpotong atau terpisah spasi secara keliru (split artifacts)
        $text = preg_replace('/\bke\s+giatan\b/iu', 'kegiatan', $text) ?? $text;
        $text = preg_replace('/\bstu\s+di\b/iu', 'studi', $text) ?? $text;
        $text = preg_replace('/\bk\s+era\s+ngka\b|\bkerang\s+ka\b/iu', 'kerangka', $text) ?? $text;
        $text = preg_replace('/\bkanka\s+lima\s+tefektif\b|\bka\s+lima\s+tefektif\b|\bkali\s+mat\s+efektif\b/iu', 'kalimat efektif', $text) ?? $text;
        $text = preg_replace('/\bme\s+ngusulkan\b/iu', 'mengusulkan', $text) ?? $text;
        $text = preg_replace('/\bmerencan\s+akan\b|\bmerencana\s+kan\b/iu', 'merencanakan', $text) ?? $text;
        $text = preg_replace('/\bmenggun\s+akan\b|\bmeng\s+guna\s+kan\b/iu', 'menggunakan', $text) ?? $text;
        $text = preg_replace('/\bdari\s+sumberlain\b/iu', 'dari sumber lain', $text) ?? $text;
        $text = preg_replace('/\bdi\s+tuang\s*kan\b|\bdituang\s+kan\b/iu', 'dituangkan', $text) ?? $text;
        $text = preg_replace('/\bpihak\s*pemberidana\b/iu', 'pihak pemberi dana', $text) ?? $text;
        $text = preg_replace('/\bpedomanpelaksanaan\b/iu', 'pedoman pelaksanaan', $text) ?? $text;
        $text = preg_replace('/\buntukkegiatannon-penelitian\b/iu', 'untuk kegiatan non-penelitian', $text) ?? $text;
        $text = preg_replace('/\buntukkegiatanilmiah\b/iu', 'untuk kegiatan ilmiah', $text) ?? $text;
        $text = preg_replace('/\bmenemukancelah\b/iu', 'menemukan celah', $text) ?? $text;
        $text = preg_replace('/\bdankohesif\b/iu', 'dan kohesif', $text) ?? $text;
        $text = preg_replace('/\bdankoheren\b/iu', 'dan koheren', $text) ?? $text;
        $text = preg_replace('/\bmembuktikanteori\b/iu', 'membuktikan teori', $text) ?? $text;
        $text = preg_replace('/\bSebagaidasarevaluasidanpertanggungjawabankegiatan\b/iu', 'Sebagai dasar evaluasi dan pertanggungjawaban kegiatan', $text) ?? $text;

        // 4. Segmentasi token panjang tanpa spasi (>= 9 huruf) menggunakan kamus kata umum Indonesia
        $text = preg_replace_callback('/\b\p{L}{9,}\b/u', function (array $m) {
            return $this->segmentGluedIndonesianToken($m[0]);
        }, $text) ?? $text;

        // Kembalikan placeholder
        if (! empty($placeholders)) {
            $text = strtr($text, $placeholders);
        }

        // Koreksi pasca-segmentasi untuk menjamin tidak ada residu pemisahan kata
        $text = preg_replace('/\bke\s+giatan\b/iu', 'kegiatan', $text) ?? $text;
        $text = preg_replace('/\bstu\s+di\b/iu', 'studi', $text) ?? $text;
        $text = preg_replace('/\bk\s+era\s+ngka\b|\bkerang\s+ka\b/iu', 'kerangka', $text) ?? $text;
        $text = preg_replace('/\bpihak\s*pemberidana\b/iu', 'pihak pemberi dana', $text) ?? $text;

        return trim(preg_replace('/[ \t]+/u', ' ', $text) ?? $text);
    }

    /**
     * Memecah satu token kata menempel menggunakan Dynamic Programming berbasis kamus Indonesia.
     */
    private function segmentGluedIndonesianToken(string $token): string
    {
        // Jangan pernah memecah kata tunggal yang sudah ada di kamus
        if (isset(self::ID_WORDS[mb_strtolower($token)])) {
            return $token;
        }

        $chars = preg_split('//u', $token, -1, PREG_SPLIT_NO_EMPTY);
        if (! $chars) {
            return $token;
        }
        $n = count($chars);
        $lowerChars = array_map(fn ($c) => mb_strtolower($c), $chars);

        // dp[i] = [bestScore, prevIndex]
        $dp = array_fill(0, $n + 1, [-1000000, -1]);
        $dp[0] = [0, -1];

        for ($i = 0; $i < $n; $i++) {
            if ($dp[$i][0] <= -900000) {
                continue;
            }
            $maxLen = min(25, $n - $i);
            for ($len = 1; $len <= $maxLen; $len++) {
                $sub = implode('', array_slice($lowerChars, $i, $len));

                // Hindari token 1-2 huruf yang memecah batang kata di tengah (seperti 'di' atau 'ke')
                if ($len <= 2 && in_array($sub, ['di', 'ke', 'ia'], true)) {
                    continue;
                }

                if (isset(self::ID_WORDS[$sub])) {
                    $score = $dp[$i][0] + ($len * $len) + 15;
                    if ($score > $dp[$i + $len][0]) {
                        $dp[$i + $len] = [$score, $i];
                    }
                } elseif ($n >= 18 && $len >= 4) {
                    // Hanya izinkan segmen non-kamus jika token sangat panjang (>=18 huruf)
                    $score = $dp[$i][0] - ($len * 3) - 20;
                    if ($score > $dp[$i + $len][0]) {
                        $dp[$i + $len] = [$score, $i];
                    }
                }
            }
        }

        if ($dp[$n][0] <= 0) {
            return $token;
        }

        $parts = [];
        $pos = $n;
        while ($pos > 0) {
            $prev = $dp[$pos][1];
            if ($prev < 0) {
                return $token;
            }
            $parts[] = implode('', array_slice($chars, $prev, $pos - $prev));
            $pos = $prev;
        }

        $segmented = array_reverse($parts);
        if (count($segmented) < 2) {
            return $token;
        }

        $knownCount = 0;
        foreach ($segmented as $p) {
            if (isset(self::ID_WORDS[mb_strtolower($p)])) {
                $knownCount++;
            }
        }

        // Untuk token < 18 huruf, wajib 100% bagian adalah kata kamus yang valid agar kata berimbuhan normal tidak terpotong
        $ratio = $knownCount / count($segmented);
        if ($n < 18) {
            return $ratio >= 1.0 ? implode(' ', $segmented) : $token;
        }

        return ($knownCount >= 2 && $ratio >= 0.5) ? implode(' ', $segmented) : $token;
    }

    private function isLikelyHeading(string $line): bool
    {
        $clean = trim($line);
        if ($clean === '' || mb_strlen($clean) > 90 || str_ends_with($clean, '.')) {
            return false;
        }
        // Dilarang berakhiran kata sambung/depan
        if (preg_match('/\b(?:antara|dan|atau|dari|pada|yang|untuk|dengan|serta|dalam|tentang|secara|sebagai|ke)\s*$/iu', $clean)) {
            return false;
        }
        // Dilarang diawali huruf kecil jika bukan subjudul alfabet (misal a. Pengertian Proposal)
        if (preg_match('/^\p{Ll}/u', $clean) && ! preg_match('/^[a-z][\.\)]\s+[A-Z\p{L}]/u', $clean)) {
            return false;
        }
        if (preg_match('/^BAB\s+[IVXLCDM\d]+\b/iu', $clean)) {
            return true;
        }
        // Subjudul berlabel alfabet (a. Pengertian Proposal, b. Fungsi Proposal)
        if (preg_match('/^[a-z][\.\)]\s+[A-Z\p{L}][^.?!]{2,60}$/u', $clean)) {
            return true;
        }
        // Semua huruf kapital (minimal 2 huruf, tanpa titik di akhir kalimat biasa)
        if (preg_match('/\p{L}{2,}/u', $clean) && mb_strtoupper($clean) === $clean && ! str_ends_with($clean, '.')) {
            return true;
        }
        // Judul bagian umum (Title Case pendek tanpa titik akhir)
        if (preg_match('/^(?:Ringkasan|Rangkuman|Ikhtisar|Capaian\s+Pembelajaran|Tujuan\s+Pembelajaran|Perbedaan\s+Ringkasan,\s*Rangkuman,\s*dan\s*Ikhtisar|Langkah[- ]langkah\s+Menulis|Contoh\s+Praktik|Referensi|Ringkasan Eksekutif|Latar Belakang|Rumusan Masalah|Makna Prinsip|Sumber Rujukan|Daftar Pustaka|Kesimpulan|Penutup|Pengertian\s+Proposal|Fungsi\s+Proposal|Jenis-jenis\s+Proposal|Proposal\s+Penelitian|Proposal\s+Kegiatan|Bagian\s+Isi\s+Pokok|Laporan\s+Ilmiah)$/iu', $clean)) {
            return true;
        }

        return false;
    }

    private function enhanceInlineTypography(string $text): string
    {
        $text = preg_replace('/(?<!\*)\b(Pasal\s+\d+(?:\s+ayat\s*\(\d+\))?)\b(?!\*)/iu', '**$1**', $text) ?? $text;
        $text = preg_replace('/(?<!\*)\b(de facto|de jure|a priori|a posteriori|et al\.|ad hoc|status quo|vice versa|mutatis mutandis)\b(?!\*)/iu', '*$1*', $text) ?? $text;

        return $text;
    }
}
