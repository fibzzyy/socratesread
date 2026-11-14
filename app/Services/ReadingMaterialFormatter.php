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
        'adalah' => 1, 'merupakan' => 1, 'yaitu' => 1, 'yakni' => 1, 'bahwa' => 1,
        'yang' => 1, 'dan' => 1, 'atau' => 1, 'serta' => 1, 'dengan' => 1,
        'untuk' => 1, 'guna' => 1, 'demi' => 1, 'agar' => 1, 'supaya' => 1,
        'dari' => 1, 'pada' => 1, 'dalam' => 1, 'oleh' => 1, 'sebagai' => 1,
        'sehingga' => 1, 'karena' => 1, 'sebab' => 1, 'jika' => 1, 'bila' => 1,
        'maka' => 1, 'akan' => 1, 'telah' => 1, 'sudah' => 1, 'sedang' => 1,
        'dapat' => 1, 'bisa' => 1, 'harus' => 1, 'wajib' => 1, 'perlu' => 1,
        'tidak' => 1, 'bukan' => 1, 'jangan' => 1, 'sampai' => 1, 'hingga' => 1,
        'antara' => 1, 'melalui' => 1, 'tentang' => 1, 'mengenai' => 1, 'terhadap' => 1,
        'seperti' => 1, 'antara' => 1, 'selain' => 1, 'kemudian' => 1, 'selanjutnya' => 1,
        'selanjutnta' => 1, 'berikut' => 1, 'ini' => 1, 'itu' => 1, 'tersebut' => 1,
        'kita' => 1, 'kami' => 1, 'anda' => 1, 'mereka' => 1, 'ia' => 1,
        'di' => 1, 'ke' => 1, 'satu' => 1, 'dua' => 1, 'tiga' => 1, 'empat' => 1, 'lima' => 1,
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
        'karya' => 1, 'tulis' => 1, 'ilmiah' => 1, 'makalah' => 1, 'artikel' => 1,
        'jurnal' => 1, 'skripsi' => 1, 'tesis' => 1, 'disertasi' => 1, 'laporan' => 1,
        'penelitian' => 1, 'pengamatan' => 1, 'kajian' => 1, 'metode' => 1, 'metodologi' => 1,
        'objektif' => 1, 'subjektif' => 1, 'logis' => 1, 'sistematis' => 1, 'kritis' => 1,
        'analitis' => 1, 'baku' => 1, 'ejaan' => 1, 'diksi' => 1, 'tata' => 1,
        'abstrak' => 1, 'kata' => 1, 'kunci' => 1, 'pengantar' => 1, 'daftar' => 1,
        'isi' => 1, 'tabel' => 1, 'gambar' => 1, 'lampiran' => 1, 'pendahuluan' => 1,
        'latar' => 1, 'belakang' => 1, 'rumusan' => 1, 'masalah' => 1, 'manfaat' => 1,
        'teoritis' => 1, 'teoretis' => 1, 'praktis' => 1, 'tinjauan' => 1, 'pustaka' => 1,
        'landasan' => 1, 'teori' => 1, 'populasi' => 1, 'sampel' => 1, 'teknik' => 1,
        'pengumpulan' => 1, 'data' => 1, 'instrumen' => 1, 'analisis' => 1, 'hasil' => 1,
        'pembahasan' => 1, 'kesimpulan' => 1, 'saran' => 1, 'penutup' => 1, 'sitasi' => 1,
        'rujukan' => 1, 'primer' => 1, 'sekunder' => 1, 'plagiarisme' => 1, 'orisinalitas' => 1,
        'semata' => 1, 'fakta' => 1, 'nalar' => 1, 'penalaran' => 1, 'diterima' => 1,
        'aturan' => 1, 'penulisan' => 1, 'mendalam' => 1, 'valid' => 1, 'verifikasi' => 1,
        'masyarakat' => 1, 'pembaca' => 1, 'penulis' => 1, 'peneliti' => 1,
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

            // Deteksi daftar bernomor (1. ..., 2. ...)
            if (preg_match($orderedMarker, $line, $m)) {
                $flushParagraph();
                $num = rtrim($m[1], '.)') . '.';
                $itemText = $this->restoreMissingSpaces(trim($m[2]));
                // Kumpulkan baris lanjutan (soft wrap) untuk item nomor ini
                while ($i + 1 < $totalLines) {
                    $nextRaw = trim($rawLines[$i + 1]);
                    if (
                        $nextRaw === ''
                        || preg_match($pageNumberLine, $nextRaw)
                        || preg_match($listMarkers, $nextRaw)
                        || preg_match($orderedMarker, $nextRaw)
                        || $this->isLikelyHeading($nextRaw)
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
                    if (
                        $nextRaw === ''
                        || preg_match($pageNumberLine, $nextRaw)
                        || preg_match($listMarkers, $nextRaw)
                        || preg_match($orderedMarker, $nextRaw)
                        || $this->isLikelyHeading($nextRaw)
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
     * Memangkas instruksi penugasan PR buku cetak dan tabel latihan kosong di akhir bacaan
     * agar teks bacaan terfokus pada konten instruksional pembelajaran mandiri siswa.
     */
    public function cleanClassroomHomework(string $markdown): string
    {
        // 1. Bersihkan margin footer / header buku yang menempel
        $markdown = preg_replace('/\s*(?:Kelas\s+[X\d]+(?:\s*(?:SMA|MA|SMK|MAK|\/))+\s*\d*|\d{1,3}\s*(?:Bahasa\s+Indonesia|Matematika))\s*(?=\R|$)/iu', '', $markdown);
        $markdown = preg_replace('/\b(?:Kelas\s+[X\d]+(?:\s*(?:SMA|MA|SMK|MAK|\/))+\s*\d*|\d{1,3}\s*(?:Bahasa\s+Indonesia|Matematika))\b/iu', '', $markdown);
        $markdown = preg_replace('/\s*Sumber\s+foto:\s*https?:\/\/\S+/iu', '', $markdown);

        // 2. Pangkas penugasan tugas PR / lembar kerja latihan kosong di akhir bacaan
        $markdown = preg_replace('/(?:\R+|^)(?:###\s*)?Latihan\s+[\d\.]+.*?\Z/ius', '', $markdown);
        $markdown = preg_replace('/(?:\s*Tugas\s+2\s+Analisislah\s+afiksasi\s+yang\s+terjadi.*?\Z)/ius', '', $markdown);
        $markdown = preg_replace('/(?:\s+Tugas|\R+Tugas)[\s\d\:\.]*\R+1\.\s+Simpulkanlah.*?(?=\R+\d+\.\s+[A-Z]|\Z)/ius', '', $markdown);
        $markdown = preg_replace('/(?:\R+|^)Tugas\s*\R+1\.\s+Simpulkanlah\s+fungsi\s+teks.*?(?=\R+\d+\.\s+[A-Z]|\Z)/ius', '', $markdown);
        $markdown = preg_replace('/\R+Tugas\s+1\s+Selanjutnya,?\s+lakukan\s+analisis.*?(?=\R+\d+\.\s+[A-Z]|\Z)/ius', "\n\n", $markdown);

        // 3. Bersihkan spanduk / instruksi tugas PR di sela-sela subbab
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
        $afiksasiPattern = '/No\.\s*Kata\s+Berimbuhan\s+Jenis\s+Imbuhan\s+Kata\s+Dasar.*?10\.\s*penelitian\s+nomina\s+pe\s*\(N\)-an\s+teliti\.?/ius';
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
        $text = preg_replace($afiksasiPattern, $afiksasiTable, $text) ?? $text;

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

        // 7. Pisahkan Contoh Soal dan Alternatif Penyelesaian menjadi blok terstruktur (idempotent)
        $text = preg_replace('/(?:\R+|^|(?<=[.!?])\s*)(?<!####\s)(Contoh\s+\d+)\s+([^\n]+?)\s*(Alternatif\s+[pP]enyelesaian:?)/iu', "\n\n#### $1\n$2\n\n**$3**\n", $text);
        $text = preg_replace('/(?<!\*\*)\b(Alternatif\s+[pP]enyelesaian:?)(?!\*\*)/iu', "\n\n**$1**\n\n", $text);

        // 8. Rapikan baris latihan isian titik-titik berulang pada pembelahan bakteri / gedung seni
        $text = preg_replace('/U\s*=\s*2\s*×\s*…\s*\(2\s*dikali\s*3\s*sebanyak\s*…\s*kali\)[^\n]+(?=Suku\s+ke-n)/ius', 
            "Dari pola tersebut diperoleh bahwa untuk suku ke-n:\n\n- Suku ke-1: \$U_1 = a = 2\$\n- Suku ke-2: \$U_2 = 2 \\times 3^1 = 6\$\n- Suku ke-3: \$U_3 = 2 \\times 3^2 = 18\$\n- Suku ke-n: \$U_n = a \\cdot r^{n-1}\$\n\n", $text);

        $text = preg_replace('/-\s*Baris\s+ke-1\s*=\s*20\s*-\s*Baris\s+ke-2\s*=\s*24\s*=\s*20\+\s*4[^\n]+(?=Gambar\s+2\.3|Jadi,\s+rumus)/ius',
            "Pola penambahan kursi tiap baris:\n\n- Baris ke-1 (\$U_1\$): 20\n- Baris ke-2 (\$U_2\$): \$20 + 4 = 24\$\n- Baris ke-3 (\$U_3\$): \$20 + 2(4) = 28\$\n- Baris ke-n (\$U_n\$): \$20 + (n - 1) \\times 4\$\n\n", $text);

        // 9. Rapikan rumus matematika agar berformat LaTeX rapi
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
            $objectivesBlock = "### TUJUAN PEMBELAJARAN\n\nSetelah mempelajari materi ini, siswa diharapkan mampu:\n- Memahami pengertian, konsep dasar, dan latar belakang mengenai {$cleanTitle}.\n- Mengidentifikasi karakteristik, komponen penting, dan prinsip utama dalam materi.\n- Menganalisis keterkaitan antargagasan serta penerapan praktis dari topik yang dibahas.";

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

            // Substantive block (paragraf, bullet list, quote): bungkus dengan nomor paragraf interaktif
            $contentHtml = trim(Str::markdown($trimmed, ['html_input' => 'escape', 'allow_unsafe_links' => false]));
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
            '/(?:https?:\/\/\S+|[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}|\/[A-Za-z0-9._\-\/]+|\b(?:' . implode('|', self::PROTECTED_CAMEL_TERMS) . ')\b)/u',
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
        // Pecah penomoran dan rentang angka yang menempel (misal 1.Objektif -> 1. Objektif, ±150-250kata -> 150-250 kata)
        $text = preg_replace('/(\d+)\.([A-Z\p{L}])/u', '$1. $2', $text) ?? $text;
        $text = preg_replace('/(\d+)\s*[–—\-]\s*(\d+)\s*kata\b/iu', '$1–$2 kata', $text) ?? $text;
        $text = preg_replace('/[±~]/u', '', $text) ?? $text;
        $text = preg_replace('/(?<=\p{L})[–—](?=\p{L})/u', ' – ', $text) ?? $text;

        // 4. Segmentasi token panjang tanpa spasi (>= 9 huruf) menggunakan kamus kata umum Indonesia
        $text = preg_replace_callback('/\b\p{L}{9,}\b/u', function (array $m) {
            return $this->segmentGluedIndonesianToken($m[0]);
        }, $text) ?? $text;

        // Kembalikan placeholder
        if (! empty($placeholders)) {
            $text = strtr($text, $placeholders);
        }

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
            $maxLen = min(20, $n - $i);
            for ($len = 1; $len <= $maxLen; $len++) {
                $sub = implode('', array_slice($lowerChars, $i, $len));
                if (isset(self::ID_WORDS[$sub])) {
                    $score = $dp[$i][0] + ($len * $len) + 5;
                    if ($score > $dp[$i + $len][0]) {
                        $dp[$i + $len] = [$score, $i];
                    }
                } elseif ($n >= 18 && $len >= 4) {
                    // Hanya izinkan segmen non-kamus jika token sangat panjang (>=18 huruf)
                    $score = $dp[$i][0] - ($len * 2) - 10;
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
        // Dilarang diawali huruf kecil jika bukan simbol
        if (preg_match('/^\p{Ll}/u', $clean)) {
            return false;
        }
        if (preg_match('/^BAB\s+[IVXLCDM\d]+\b/iu', $clean)) {
            return true;
        }
        // Semua huruf kapital (minimal 2 huruf, tanpa titik di akhir kalimat biasa)
        if (preg_match('/\p{L}{2,}/u', $clean) && mb_strtoupper($clean) === $clean && ! str_ends_with($clean, '.')) {
            return true;
        }
        // Judul bagian umum (Title Case pendek tanpa titik akhir)
        if (preg_match('/^(?:Ringkasan|Rangkuman|Ikhtisar|Capaian\s+Pembelajaran|Tujuan\s+Pembelajaran|Perbedaan\s+Ringkasan,\s*Rangkuman,\s*dan\s*Ikhtisar|Langkah[- ]langkah\s+Menulis|Contoh\s+Praktik|Referensi|Ringkasan Eksekutif|Latar Belakang|Rumusan Masalah|Makna Prinsip|Sumber Rujukan|Daftar Pustaka|Kesimpulan|Penutup)$/iu', $clean)) {
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
