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
        Schema::create('bm_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('bm_account_id')->constrained('bm_accounts')->onDelete('cascade');
            $table->enum('status', ['Pending', 'Paused','Processing', 'Completed', 'Failed'])->default('Pending');
            $table->integer('total_ad_accounts')->default(1);
            $table->integer('starting_ad_account_no')->default(1);
            $table->string('pattern')->nullable();
            $table->string('currency')->default('USD');
            $table->string('time_zone')->default('America/New_York');
            $table->integer('processed_ad_accounts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bm_jobs');
    }
};
