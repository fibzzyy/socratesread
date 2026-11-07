<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('school_origin', 160)->nullable()->after('avatar_path');
            $table->string('nip_nuptk', 50)->nullable()->after('school_origin');
            $table->string('nisn', 50)->nullable()->after('nip_nuptk');
            $table->string('otp_code', 6)->nullable()->after('nisn');
            $table->timestamp('otp_expires_at')->nullable()->after('otp_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['school_origin', 'nip_nuptk', 'nisn', 'otp_code', 'otp_expires_at']);
        });
    }
};
