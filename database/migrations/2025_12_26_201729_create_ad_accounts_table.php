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
        Schema::create('ad_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('bm_account_id')->constrained('bm_accounts')->onDelete('cascade');
            $table->foreignId('bm_job_id')->constrained('bm_jobs')->onDelete('cascade');
            $table->string('ad_account_id')->nullable()->unique();
            $table->string('name');
            $table->string('currency')->default('USD');
            $table->string('time_zone')->nullable();
            $table->enum('status', ['Pending', 'Created', 'Failed'])->default('Pending');
            $table->text('api_response')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_accounts');
    }
};
