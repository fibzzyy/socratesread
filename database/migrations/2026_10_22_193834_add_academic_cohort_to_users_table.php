<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('entry_grade')->nullable()->after('nisn');
            $table->unsignedSmallInteger('enrollment_year')->nullable()->after('entry_grade');
            $table->unsignedSmallInteger('graduation_year')->nullable()->after('enrollment_year');
        });

        // Backfill existing student users so they have valid cohort values
        $currentYear = (int) date('Y');
        DB::table('users')
            ->where('role', 'student')
            ->whereNull('entry_grade')
            ->update([
                'entry_grade' => 10,
                'enrollment_year' => $currentYear,
                'graduation_year' => $currentYear + (12 - 10), // 3-year span: e.g. 2026 -> 2028
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['entry_grade', 'enrollment_year', 'graduation_year']);
        });
    }
};
