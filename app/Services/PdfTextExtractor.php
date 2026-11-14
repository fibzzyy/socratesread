<?php

namespace App\Services;

use App\Exceptions\PdfDocumentRejected;
use Illuminate\Http\UploadedFile;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;

class PdfTextExtractor
{
    /** @return array{text: string, pages: int, extracted_pages?: int} */
    public function extract(
        UploadedFile $file,
        ?int $pageStart = null,
        ?int $pageEnd = null,
        ?string $lastSubtopicHeading = null,
        ?string $startSubtopicHeading = null,
        ?string $documentTitle = null
    ): array
    {
        $config = new Config();
        $config->setDecodeMemoryLimit(128 * 1024 * 1024);

        $pdf = (new Parser([], $config))->parseFile($file->getRealPath());
        $pagesList = $pdf->getPages();
        $pages = count($pagesList);

        if ($pages === 0) {
            throw new PdfDocumentRejected('PDF tidak memiliki halaman.');
        }

        $startIdx = ($pageStart !== null && $pageStart >= 1) ? max(0, $pageStart - 1) : 0;
        $endIdx = ($pageEnd !== null && $pageEnd >= 1) ? min($pages - 1, $pageEnd - 1) : ($pages - 1);

        if ($startIdx > $endIdx) {
            $startIdx = 0;
            $endIdx = $pages - 1;
        }

        $pageTexts = [];
        foreach ($pagesList as $idx => $page) {
            if ($idx < $startIdx || $idx > $endIdx) {
                continue;
            }
            try {
                $rawText = str_replace("\0", '', (string) $page->getText());
            } catch (Throwable $e) {
                Log::warning("PdfTextExtractor: failed extracting page " . ($idx + 1), ['error' => $e->getMessage()]);
                continue;
            }
            $rawPage = function_exists('mb_scrub')
                ? mb_scrub($rawText, 'UTF-8')
                : mb_convert_encoding($rawText, 'UTF-8', 'UTF-8');

            // Bersihkan baris cover font-shifted dan running header/footer buku
            $lines = explode("\n", $rawPage);
            $cleanedLines = [];
            foreach ($lines as $line) {
                $t = trim($line);
                if ($t === '') {
                    $cleanedLines[] = $line;
                    continue;
                }
                // Baris cover font-shifted teracak (misal /IQIRXIVMER..., 1EXIQEXMOE..., dll)
                $c = preg_replace('/[\s\0]+/', '', $t);
                if (preg_match('#^/(?:IQIR|IFYH|6ITY|1EXI|4IRY)#i', $c)
                    || preg_match('/^(?:6ITY|1EXI|4IRY|6MWI|8IOR)/i', $c)
                    || preg_match('/^(?:IQIRXIVMER|1EXIQEXMOE|4IRYPMW|6ITYFPMO|71%|1%|71\/)/i', $c)
                    || preg_match('/^[-–—\s]*\d+\s*&\s*\d+/u', $c)
                    || preg_match('/^[-–—\s]*[A-Z0-9&]{2,10}\s+Barisan/i', $t)
                    || preg_match('/^(?:[\/\p{L}\p{N}%&_()\-]\s+){4,}/u', $t)) {
                    continue;
                }
                // Running header & footer buku cetak, kementerian, penerbit, peta materi, instruksi tugas
                if (preg_match('/^(?:Matematika|Bahasa\s+Indonesia|Buku\s+Siswa)\s+untuk\s+SMA.*Kelas\s+[X\d]+/iu', $t)
                    || preg_match('/^Bab\s+\d+\s*\|\s*/iu', $t)
                    || preg_match('/^(?:World\s+Our\s+World|Data\s+source:\s*United\s+Nations|CC\s+BY\b)/iu', $t)
                    || preg_match('/^(?:Bab|Eksponen\s*\d*|\d+\s*Bab|Barisan\s+dan\s+Deret)$/iu', $t)
                    || preg_match('/^[-–—\s]*\d+\s*$/u', $t)
                    || preg_match('/^(?:ISBN|Kementerian|Republik\s+Indonesia|Hak\s+Cipta|Diterbitkan\s+oleh)\b/iu', $t)
                    || preg_match('/^(?:Peta\s+Materi|Ayo,?\s+Bereksplorasi|Ayo,?\s+Berpikir|Ayo,?\s+Berdiskusi|Jawablah\s+pertanyaan\s+berikut\s+dengan\s+berdiskusi)\b/iu', $t)) {
                    continue;
                }
                $cleanedLines[] = $line;
            }
            $rawPage = implode("\n", $cleanedLines);

            $rawPageText = $this->cleanShadowAndDuplicates($rawPage);
            $pageTexts[] = $this->normalizeSlidePageText($rawPageText);
        }

        $text = implode("\n\n", array_filter($pageTexts, fn ($t) => trim($t) !== ''));
        $text = function_exists('mb_scrub') ? mb_scrub($text, 'UTF-8') : mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $text = $this->cleanShadowAndDuplicates($text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\R{3,}/u', "\n\n", trim($text)) ?? $text;

        if ($text === '') {
            throw new PdfDocumentRejected('PDF tidak berisi teks yang bisa diekstrak. Pastikan halaman yang dipilih memiliki teks digital.');
        }

        // Potong sisa latihan/materi bab sebelumnya di atas jika guru menentukan submateri awal atau terdeteksi dari judul
        if (! empty($startSubtopicHeading) || ! empty($documentTitle)) {
            $text = $this->trimTextBeforeStartSubtopic($text, $startSubtopicHeading, $documentTitle);
        }

        // Potong teks jika guru menentukan batas submateri terakhir pertemuan ini
        if (! empty($lastSubtopicHeading)) {
            $text = $this->cutTextAtSubtopicBoundary($text, $lastSubtopicHeading);
        }

        return [
            'text' => $text,
            'pages' => $pages,
            'extracted_pages' => ($endIdx - $startIdx + 1),
        ];
    }

    /**
     * Memotong teks sebelum kemunculan submateri awal agar sisa latihan/materi bab sebelumnya di atasnya diabaikan.
     * Menggabungkan Solusi 1 (input manual guru) dan Solusi 2 (deteksi cerdas otomatis dari judul bacaan).
     */
    public function trimTextBeforeStartSubtopic(string $text, ?string $startHeading = null, ?string $documentTitle = null): string
    {
        $targetHeading = trim((string) $startHeading);

        // Jika tidak ada input manual submateri awal, gunakan deteksi otomatis cerdas dari judul dokumen
        if ($targetHeading === '' && ! empty($documentTitle)) {
            $cleanTitle = trim(preg_replace('/^(?:Bab|Pertemuan|Modul|Materi)\s*[\dIVXLCDM.\-:\s]*/iu', '', $documentTitle) ?? $documentTitle);
            if (mb_strlen($cleanTitle) >= 4) {
                $searchRegion = mb_substr($text, 0, 3000);
                $escaped = preg_quote($cleanTitle, '/');
                $pattern = '/(?:^|\R)\s*(((?:[A-Z]|\d+(?:\.\d+)*)\.?\s*)?' . $escaped . ')\b/iu';
                if (preg_match($pattern, $searchRegion, $m)) {
                    $foundHeading = trim($m[1]);
                    $foundPos = mb_stripos($text, $foundHeading);
                    if ($foundPos !== false && $foundPos >= 40) {
                        $targetHeading = $foundHeading;
                    }
                }
            }
        }

        if ($targetHeading === '') {
            return $text;
        }

        $pos = mb_stripos($text, $targetHeading);
        if ($pos === false) {
            $bareHeading = trim(preg_replace('/^[\d.\-\s]+/u', '', $targetHeading) ?? $targetHeading);
            if ($bareHeading !== '' && mb_strlen($bareHeading) >= 4) {
                $escaped = preg_quote($bareHeading, '/');
                $pattern = '/(?:^|\R)\s*(((?:[A-Z]|\d+(?:\.\d+)*)\.?\s*)?' . $escaped . ')\b/iu';
                if (preg_match($pattern, $text, $m)) {
                    $pos = mb_stripos($text, trim($m[1]));
                } else {
                    $pos = mb_stripos($text, $bareHeading);
                }
            }
        }

        if ($pos !== false && $pos > 0) {
            $trimmed = trim(mb_substr($text, $pos));
            if (mb_strlen($trimmed) >= 60) {
                return $trimmed;
            }
        }

        return $text;
    }

    /**
     * Memotong teks tepat saat submateri berikutnya dimulai setelah $lastSubtopicHeading.
     */
    public function cutTextAtSubtopicBoundary(string $text, string $lastHeading): string
    {
        $cleanHeading = trim($lastHeading);
        if ($cleanHeading === '') {
            return $text;
        }

        $pos = mb_stripos($text, $cleanHeading);
        if ($pos === false) {
            // Coba pencarian dengan pembersihan nomor di depan (misal "2.4 Kaidah" -> "Kaidah")
            $bareHeading = trim(preg_replace('/^[\d.\-\s]+/u', '', $cleanHeading) ?? $cleanHeading);
            if ($bareHeading !== '') {
                $pos = mb_stripos($text, $bareHeading);
            }
        }

        if ($pos === false) {
            return $text;
        }

        $afterHeadingStart = $pos + mb_strlen($cleanHeading);
        $remainingText = mb_substr($text, $afterHeadingStart);

        // Cari kemunculan heading berikutnya:
        // Pola: Baris baru diikuti (A-Z. atau 1.2 / 2.5 atau Bab X atau Kata Tebal / Heading huruf kapital atau 1. Kata)
        if (preg_match('/\R+(?=(?:[A-Z]\.|\d+\.\d+|\bBab\s+[IVXLCDM\d]+|[A-Z]{3,}\b|\d+\.\s+[A-Z]))/u', $remainingText, $m, PREG_OFFSET_CAPTURE)) {
            $byteOffset = $m[0][1];
            $charOffset = mb_strlen(substr($remainingText, 0, $byteOffset));
            $cutoffPoint = $afterHeadingStart + $charOffset;

            $cutResult = trim(mb_substr($text, 0, $cutoffPoint));
            if (mb_strlen($cutResult) >= 80) {
                return $cutResult;
            }
        }

        return $text;
    }

    /**
     * Membersihkan duplikasi teks akibat efek bayangan (drop shadow) atau double-layer pada slide Canva/PPT.
     */
    public function cleanShadowAndDuplicates(string $text): string
    {
        $text = function_exists('mb_scrub') ? mb_scrub($text, 'UTF-8') : mb_convert_encoding($text, 'UTF-8', 'UTF-8');

        // 1. Kata menempel ganda: RingkasanRingkasan -> Ringkasan, RangkumanRangkuman -> Rangkuman
        $text = preg_replace('/\b(\p{L}{4,35})\1\b/u', '$1', $text) ?? $text;

        // 1b. Gabungkan judul yang terpotong di tanda ampersand: "Rangkuman&\nIkhtisar" -> "Rangkuman & Ikhtisar"
        $text = preg_replace('/(\b\p{L}+)&\s*\R+\s*(\b\p{L}+)/u', '$1 & $2', $text) ?? $text;

        // 2. Baris identik berulang tanpa spasi: CAPAIAN PEMBELAJARANCAPAIAN PEMBELAJARAN
        $lines = explode("\n", $text);
        foreach ($lines as &$l) {
            $clean = trim($l);
            $len = mb_strlen($clean);
            if ($len >= 6 && $len % 2 === 0) {
                $half = (int) ($len / 2);
                $first = mb_substr($clean, 0, $half);
                $second = mb_substr($clean, $half);
                if ($first === $second) {
                    $l = $first;
                }
            }
            if (preg_match('/^(.{3,50})\s+\1$/u', $clean, $m)) {
                $l = $m[1];
            }
        }
        unset($l);
        $text = implode("\n", $lines);

        // 3. Kata berulang berurutan: Ringkasan Ringkasan Ringkasan -> Ringkasan
        $text = preg_replace('/\b(\p{L}{4,})\s+\1\b/u', '$1', $text) ?? $text;
        $text = preg_replace('/\b(\p{L}{4,})\s+\1\b/u', '$1', $text) ?? $text;

        // 4. Artefak judul slide yang bocor di akhir kalimat setelah titik
        $text = preg_replace('/(?<=[.?!])\s+(?:Rangkuman|Ringkasan|Ikhtisar|Referensi)\s*$/ium', '', $text) ?? $text;

        return $text;
    }

    /**
     * Memeriksa dan memindahkan judul slide yang terekstrak di bagian bawah halaman
     * (ciri khas urutan layer slide Canva / PowerPoint) agar kembali menjadi heading di bagian atas halaman.
     */
    public function normalizeSlidePageText(string $rawPageText): string
    {
        $cleaned = $this->cleanShadowAndDuplicates($rawPageText);
        $rawLines = array_values(array_filter(array_map('trim', explode("\n", $cleaned)), fn ($l) => $l !== ''));
        
        $lines = [];
        foreach ($rawLines as $l) {
            // Abaikan nomor slide acak seperti "1 2 3"
            if (preg_match('/^(?:\d\s*)+$/', $l) && mb_strlen(preg_replace('/\s+/', '', $l)) <= 4) {
                continue;
            }
            $lines[] = $l;
        }

        if (count($lines) < 2) {
            return $cleaned;
        }

        $isHeadingCandidate = function (string $str): bool {
            $clean = trim($str, " \t\n\r\0\x0B#*_-:");
            if ($clean === '' || mb_strlen($clean) > 65 || str_ends_with($clean, '.')) {
                return false;
            }
            if (preg_match('/^(?:TUJUAN\s+PEMBELAJARAN|CAPAIAN\s+PEMBELAJARAN|RINGKASAN|RANGKUMAN|IKHTISAR|PERBEDAAN\s+.*|LANGKAH-LANGKAH\s+.*|DEFINISI|PENGERTIAN|\d+\s+ASPEK\s+UTAMA|ASPEK\s+UTAMA|BAHASA\s+INDONESIA|BAHASA\s+DAERAH|BAHASA\s+ASING|CONTOH\s+.*|SUMBER\s+RUJUKAN|DAFTAR\s+PUSTAKA|REFERENSI|KESIMPULAN|PENUTUP|TERIMA\s+KASIH)$/iu', $clean)) {
                return true;
            }
            if (! preg_match('/[.?!]$/u', $clean) && preg_match('/^(?:[A-Z][a-z0-9&.,\-\/]+\s*){1,6}$/u', $clean) && ! preg_match('/\b(?:adalah|merupakan|yaitu|yakni|yang|dengan|untuk|dalam|dari|pada)\b/iu', $clean)) {
                return true;
            }
            if (mb_strtoupper($clean) === $clean && preg_match('/^\p{Lu}[\p{Lu}\s\d&.,\-\/]{2,45}$/u', $clean) && ! preg_match('/\b(?:ADALAH|MERUPAKAN|YANG|DAN|DENGAN|UNTUK)\b/u', $clean)) {
                return true;
            }
            return false;
        };

        $lastLine = end($lines);
        $prevLastLine = count($lines) > 1 ? $lines[count($lines) - 2] : '';
        $twoLineHeading = trim($prevLastLine . ' ' . $lastLine);

        if ($isHeadingCandidate($twoLineHeading) && count($lines) > 2) {
            $firstLine = $lines[0];
            if (! $isHeadingCandidate($firstLine)) {
                array_pop($lines);
                array_pop($lines);
                array_unshift($lines, $twoLineHeading);
            }
        } elseif ($isHeadingCandidate($lastLine) && count($lines) > 1) {
            $firstLine = $lines[0];
            if (! $isHeadingCandidate($firstLine)) {
                array_pop($lines);
                array_unshift($lines, $lastLine);
            }
        }

        return $this->cleanShadowAndDuplicates(implode("\n", $lines));
    }
}
