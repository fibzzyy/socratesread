<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('teacher_classes', function (Blueprint $table) {
            $table->unsignedTinyInteger('target_grade')->nullable()->after('name');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->string('target_grade', 10)->default('all')->after('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teacher_classes', function (Blueprint $table) {
            $table->dropColumn('target_grade');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('target_grade');
        });
    }
};
