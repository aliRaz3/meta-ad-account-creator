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
            // Index for name searches and sorting
            $table->index('name');

            // Index for status filtering and sorting
            $table->index('status');

            // Index for currency sorting
            $table->index('currency');

            // Index for created_at sorting (default sort)
            $table->index('created_at');

            // Composite index for common query patterns (user + bm_account + status)
            $table->index(['user_id', 'bm_account_id', 'status'], 'idx_user_bm_status');

            // Composite index for user + created_at (for pagination)
            $table->index(['user_id', 'created_at'], 'idx_user_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ad_accounts', function (Blueprint $table) {
            // Drop indexes in reverse order
            $table->dropIndex('idx_user_created');
            $table->dropIndex('idx_user_bm_status');
            $table->dropIndex(['created_at']);
            $table->dropIndex(['currency']);
            $table->dropIndex(['status']);
            $table->dropIndex(['name']);
        });
    }
};
