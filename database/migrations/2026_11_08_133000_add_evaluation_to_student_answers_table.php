<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_answers', function (Blueprint $table) {
            $table->unsignedTinyInteger('score')->nullable()->after('answer_text');
            $table->string('understanding_level', 50)->nullable()->after('score');
            $table->json('evaluation')->nullable()->after('understanding_level');
            $table->timestamp('evaluated_at')->nullable()->after('evaluation');
        });
    }

    public function down(): void
    {
        Schema::table('student_answers', function (Blueprint $table) {
            $table->dropColumn(['score', 'understanding_level', 'evaluation', 'evaluated_at']);
        });
    }
};
