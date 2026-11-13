<?php

namespace App\Services;

use App\Exceptions\PdfDocumentRejected;
use Illuminate\Http\UploadedFile;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;

class PdfTextExtractor
{
    /** @return array{text: string, pages: int} */
    public function extract(UploadedFile $file): array
    {
        $config = new Config();
        $config->setDecodeMemoryLimit(128 * 1024 * 1024);

        $pdf = (new Parser([], $config))->parseFile($file->getRealPath());
        $pagesList = $pdf->getPages();
        $pages = count($pagesList);

        if ($pages === 0) {
            throw new PdfDocumentRejected('PDF tidak memiliki halaman.');
        }

        $pageTexts = [];
        foreach ($pagesList as $page) {
            $rawPageText = $this->cleanShadowAndDuplicates($page->getText());
            $pageTexts[] = $this->normalizeSlidePageText($rawPageText);
        }

        $text = implode("\n\n", array_filter($pageTexts, fn ($t) => trim($t) !== ''));
        $text = $this->cleanShadowAndDuplicates($text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? '';
        $text = preg_replace('/\R{3,}/u', "\n\n", trim($text)) ?? '';

        if ($text === '') {
            throw new PdfDocumentRejected('PDF tidak berisi teks yang bisa diekstrak. PDF hasil scan memerlukan OCR yang belum tersedia.');
        }

        return ['text' => $text, 'pages' => $pages];
    }

    /**
     * Membersihkan duplikasi teks akibat efek bayangan (drop shadow) atau double-layer pada slide Canva/PPT.
     */
    public function cleanShadowAndDuplicates(string $text): string
    {
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
