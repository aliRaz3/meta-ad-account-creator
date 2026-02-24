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
        Schema::table('ad_accounts', function (Blueprint $table) {
            // Drop foreign key constraint
            $table->dropForeign(['bm_job_id']);

            // Modify column to be nullable
            $table->foreignId('bm_job_id')->nullable()->change();

            // Re-add foreign key constraint
            $table->foreign('bm_job_id')->references('id')->on('bm_jobs')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ad_accounts', function (Blueprint $table) {
            // Drop foreign key constraint
            $table->dropForeign(['bm_job_id']);

            // Modify column to be NOT nullable
            $table->foreignId('bm_job_id')->nullable(false)->change();

            // Re-add foreign key constraint
            $table->foreign('bm_job_id')->references('id')->on('bm_jobs')->onDelete('cascade');
        });
    }
};
