<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reading_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->restrictOnDelete();
            $table->string('event_type', 40);
            $table->unsignedSmallInteger('xp_awarded')->default(0);
            $table->string('summary', 180);
            $table->date('activity_date')->index();
            $table->timestamps();

            $table->unique(['reading_session_id', 'event_type']);
            $table->index(['user_id', 'activity_date']);
            $table->index(['user_id', 'document_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_activities');
    }
};
