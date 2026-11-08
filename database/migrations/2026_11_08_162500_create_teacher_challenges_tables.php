<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('type', 20)->default('essay'); // 'essay' or 'multiple_choice'
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->text('question');
            $table->json('options')->nullable(); // For multiple_choice: ['A' => '...', 'B' => '...']
            $table->string('correct_answer', 20)->nullable(); // For multiple_choice: 'A', 'B', etc.
            $table->text('rubric')->nullable(); // Kunci jawaban / panduan konsep esai
            $table->timestamps();

            $table->index(['document_id', 'sequence']);
        });

        Schema::create('student_teacher_challenge_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedSmallInteger('score')->default(0); // Skala 0 - 100
            $table->unsignedSmallInteger('xp_awarded')->default(0);
            $table->json('answers')->nullable(); // Detail jawaban, poin per soal, analisis esai
            $table->string('status', 24)->default('in_progress')->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_teacher_challenge_submissions');
        Schema::dropIfExists('teacher_challenges');
    }
};
