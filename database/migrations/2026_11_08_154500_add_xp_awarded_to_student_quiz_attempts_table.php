<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_quiz_attempts', function (Blueprint $table) {
            if (! Schema::hasColumn('student_quiz_attempts', 'xp_awarded')) {
                $table->unsignedSmallInteger('xp_awarded')->default(0)->after('score');
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_quiz_attempts', function (Blueprint $table) {
            if (Schema::hasColumn('student_quiz_attempts', 'xp_awarded')) {
                $table->dropColumn('xp_awarded');
            }
        });
    }
};
