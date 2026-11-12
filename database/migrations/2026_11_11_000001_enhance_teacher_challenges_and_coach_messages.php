<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_challenges', function (Blueprint $table) {
            $table->text('context')->nullable()->after('question');
            $table->string('difficulty', 20)->default('medium')->after('rubric');
            $table->unsignedTinyInteger('difficulty_weight')->default(2)->after('difficulty');
        });

        Schema::create('reading_coach_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reading_session_id')->constrained('reading_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('role', 20);
            $table->text('content');
            $table->boolean('is_meaningful')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'document_id', 'role']);
            $table->index(['reading_session_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_coach_messages');

        Schema::table('teacher_challenges', function (Blueprint $table) {
            $table->dropColumn(['context', 'difficulty', 'difficulty_weight']);
        });
    }
};
