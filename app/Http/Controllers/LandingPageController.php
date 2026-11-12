<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LandingPageController extends Controller
{
    /**
     * Tampilkan landing page interaktif SocratesRead.
     */
    public function __invoke(Request $request): View
    {
        $user = auth()->user();

        // Ambil data bacaan kurasi nyata untuk preview jika ada di database
        $sampleDocuments = Document::query()
            ->where('is_published', true)
            ->latest('created_at')
            ->limit(3)
            ->get();

        $publishedCount = Document::query()
            ->where('is_published', true)
            ->count();

        // Kumpulan pemikiran dan pertanyaan Sokratik untuk maskot Athena
        $socraticThoughts = [
            [
                'quote' => 'Pikiran yang terlatih mampu menimbang suatu gagasan tanpa harus langsung menerimanya.',
                'author' => 'Aristoteles',
                'context' => 'Etika Nikomakea'
            ],
            [
                'quote' => 'Hidup yang tidak pernah diuji dan dipertanyakan adalah hidup yang tidak layak dijalani.',
                'author' => 'Socrates',
                'context' => 'Apologia'
            ],
            [
                'quote' => 'Membaca kritis bukan mencari apa yang ingin kita dengar, melainkan menguji apa yang tertulis.',
                'author' => 'Refleksi Sokratik',
                'context' => 'Metodologi SocratesRead'
            ],
            [
                'quote' => 'Pertanyaan yang tepat membuka gerbang pemahaman baru yang tidak pernah diduga sebelumnya.',
                'author' => 'Athena sang Burung Hantu',
                'context' => 'Pendamping Membaca'
            ],
        ];

        return view('landing', compact(
            'user',
            'sampleDocuments',
            'publishedCount',
            'socraticThoughts'
        ));
    }
}
