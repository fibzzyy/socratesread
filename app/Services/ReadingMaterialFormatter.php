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

            // Baris perintah terminal (#mkdir ..., #cd ..., #service ...)
            if (preg_match('/^#[a-z0-9_\/\.\-]/i', $trimmed)) {
                $flushParagraph();
                $blocks[] = "```bash\n" . $trimmed . "\n```";
                continue;
            }

            // Perbaiki kata yang menempel pada PDF (tetap lindungi URL, email, directive Apache)
            $line = $this->restoreMissingSpaces($trimmed);

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
                if (! $isStandaloneClosing && ! empty($blocks) && str_starts_with((string) $prevBlock, '### ')) {
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
        return $markdown !== '' ? $markdown : trim($rawText);
    }

    /**
     * Mengonversi teks terformat Markdown menjadi HTML yang aman & terstruktur rapi.
     */
    public function toHtml(string $formattedMarkdown): string
    {
        return Str::markdown($formattedMarkdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * Membagi materi terformat menjadi beberapa halaman "kertas buku" yang seimbang
     * tanpa memotong di tengah daftar (list), heading yang yatim, atau blok kode.
     *
     * @return array<int, string> Array HTML per halaman
     */
    public function splitIntoPages(string $formattedMarkdown, int $targetChars = 950): array
    {
        $chunks = preg_split('/\R{2,}/u', trim($formattedMarkdown), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (empty($chunks)) {
            return [$this->toHtml($formattedMarkdown)];
        }

        $pagesMd = [];
        $currentParts = [];
        $currentLen = 0;

        $totalChunks = count($chunks);
        for ($i = 0; $i < $totalChunks; $i++) {
            $chunk = trim($chunks[$i]);
            $chunkLen = mb_strlen($chunk);
            $isHeading = str_starts_with($chunk, '#');

            // Jangan pisahkan heading dari blok berikutnya agar heading tidak yatim di bawah halaman
            if ($isHeading && $currentLen > ($targetChars * 0.65) && ! empty($currentParts)) {
                $pagesMd[] = implode("\n\n", $currentParts);
                $currentParts = [];
                $currentLen = 0;
            }

            $currentParts[] = $chunk;
            $currentLen += $chunkLen;

            if (! $isHeading && $currentLen >= $targetChars && $i < $totalChunks - 1) {
                $pagesMd[] = implode("\n\n", $currentParts);
                $currentParts = [];
                $currentLen = 0;
            }
        }

        if (! empty($currentParts)) {
            // Jika halaman terakhir sangat pendek (< 220 char) dan ada lebih dari 1 halaman, gabungkan dengan halaman sebelumnya
            if (count($pagesMd) > 0 && $currentLen < 220) {
                $pagesMd[count($pagesMd) - 1] .= "\n\n" . implode("\n\n", $currentParts);
            } else {
                $pagesMd[] = implode("\n\n", $currentParts);
            }
        }

        return array_map(fn (string $md) => $this->toHtml($md), $pagesMd);
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
        if ($clean === '' || mb_strlen($clean) > 90) {
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
        if (preg_match('/^(?:Ringkasan Eksekutif|Latar Belakang|Rumusan Masalah|Tujuan Pembelajaran|Makna Prinsip|Sumber Rujukan|Daftar Pustaka|Kesimpulan|Penutup)$/iu', $clean)) {
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
