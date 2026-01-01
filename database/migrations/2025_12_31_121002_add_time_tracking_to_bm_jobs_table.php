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
        Schema::table('bm_jobs', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('processed_ad_accounts');
            $table->timestamp('completed_at')->nullable()->after('started_at');
            $table->timestamp('paused_at')->nullable()->after('completed_at');
            $table->timestamp('resumed_at')->nullable()->after('paused_at');
            $table->integer('total_running_seconds')->default(0)->after('resumed_at')->comment('Total time in seconds while status was Running');
            $table->decimal('accounts_per_minute', 8, 2)->nullable()->after('total_running_seconds')->comment('Average account creation rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bm_jobs', function (Blueprint $table) {
            $table->dropColumn([
                'started_at',
                'completed_at',
                'paused_at',
                'resumed_at',
                'total_running_seconds',
                'accounts_per_minute',
            ]);
        });
    }
};
