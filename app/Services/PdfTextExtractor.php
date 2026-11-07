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
        $pages = count($pdf->getPages());

        if ($pages === 0) {
            throw new PdfDocumentRejected('PDF tidak memiliki halaman.');
        }

        $text = $pdf->getText();
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? '';
        $text = preg_replace('/\R{3,}/u', "\n\n", trim($text)) ?? '';

        if ($text === '') {
            throw new PdfDocumentRejected('PDF tidak berisi teks yang bisa diekstrak. PDF hasil scan memerlukan OCR yang belum tersedia.');
        }

        return ['text' => $text, 'pages' => $pages];
    }
}
