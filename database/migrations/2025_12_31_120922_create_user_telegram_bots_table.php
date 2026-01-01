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
        Schema::create('user_telegram_bots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name')->nullable()->comment('User-friendly bot name');
            $table->text('bot_token')->comment('Telegram Bot Token');
            $table->string('chat_id')->comment('Telegram Chat ID');
            $table->json('notification_preferences')->nullable()->comment('Events to notify: job_started, job_completed, job_failed, job_paused, job_resumed, progress_25, progress_50, progress_75, system_errors');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_notification_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_telegram_bots');
    }
};
