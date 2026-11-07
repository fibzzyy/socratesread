<?php

namespace Database\Seeders;

use App\Models\Document;
use Illuminate\Database\Seeder;

class ReadingContentSeeder extends Seeder
{
    public function run(): void
    {
        $document = Document::query()->updateOrCreate(
            ['title' => 'Rahasia Hutan Mangrove'],
            [
                'category' => 'Sains & Alam',
                'excerpt' => 'Di antara daratan dan lautan, ada hutan yang tumbuh dengan cara yang tidak biasa.',
                'body' => implode("\n\n", [
                    'Hutan mangrove hidup di wilayah pesisir yang dipengaruhi pasang surut air laut. Akar-akarnya yang kuat dan menjulang membantu pohon ini bertahan di lumpur yang lunak. Akar tersebut juga menjadi tempat berlindung dan mencari makan bagi banyak hewan kecil.',
                    'Bagi masyarakat pesisir, mangrove punya peran yang lebih luas. Jalinan akar dapat memperlambat gelombang dan menahan sebagian sedimen. Hutan ini juga menjadi habitat bagi ikan muda, kepiting, dan burung. Ketika mangrove tumbuh sehat, lingkungan pesisir memiliki penyangga alami yang penting.',
                    'Namun, mangrove dapat rusak ketika wilayah pesisir dibuka tanpa perencanaan yang baik. Menjaga hutan ini memerlukan pemahaman tentang hubungan antara alam dan kegiatan manusia. Perubahan kecil pada satu bagian ekosistem dapat memengaruhi bagian lainnya.',
                ]),
                'estimated_minutes' => 8,
                'is_published' => true,
            ],
        );

        $processor = app(\App\Services\AI\DocumentAiProcessor::class);
        $aiData = $processor->processWithHeuristics($document->body, $document->title, $document->category);

        foreach ($aiData['questions'] as $question) {
            $document->questions()->updateOrCreate(
                ['sequence' => $question['sequence']],
                $question,
            );
        }

        foreach ($aiData['quiz_questions'] as $quiz) {
            $document->quizQuestions()->updateOrCreate(
                ['sequence' => $quiz['sequence']],
                [
                    'question' => $quiz['question'],
                    'options' => $quiz['options'],
                    'correct_answer' => $quiz['correct_answer'],
                    'explanation' => $quiz['explanation'],
                ],
            );
        }
    }
}
