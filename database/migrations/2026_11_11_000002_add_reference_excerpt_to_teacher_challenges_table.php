<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_challenges', function (Blueprint $table) {
            $table->text('reference_excerpt')->nullable()->after('rubric');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_challenges', function (Blueprint $table) {
            $table->dropColumn('reference_excerpt');
        });
    }
};
