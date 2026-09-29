<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The attendance calendar, the work-stats tiles and the CSV export all
        // filter on a set of users plus a work_day range. The foreign key only
        // indexes user_id, leaving work_day to be scanned.
        Schema::table('time_entries', function (Blueprint $table) {
            $table->index(['user_id', 'work_day']);
        });

        // The approvals queue and the holiday recalculation both narrow by
        // owner and status before touching anything else.
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'work_day']);
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
        });
    }
};
