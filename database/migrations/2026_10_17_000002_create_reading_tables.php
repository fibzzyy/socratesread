<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category', 80)->default('Literasi');
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->unsignedSmallInteger('estimated_minutes')->default(5);
            $table->boolean('is_published')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('reading_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('in_progress')->index();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'document_id', 'status']);
        });

        Schema::create('socratic_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('skill', 80);
            $table->text('prompt');
            $table->text('context')->nullable();
            $table->text('hint')->nullable();
            $table->timestamps();
            $table->unique(['document_id', 'sequence']);
        });

        Schema::create('student_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reading_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('socratic_question_id')->constrained()->cascadeOnDelete();
            $table->text('answer_text');
            $table->timestamp('answered_at')->useCurrent();
            $table->timestamps();
            $table->unique(['reading_session_id', 'socratic_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_answers');
        Schema::dropIfExists('socratic_questions');
        Schema::dropIfExists('reading_sessions');
        Schema::dropIfExists('documents');
    }
};
