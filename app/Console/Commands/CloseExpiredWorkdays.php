<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('app:close-expired-workdays')]
#[Description('Command description')]
class CloseExpiredWorkdays extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $affected = DB::table('time_entries')
                ->whereNull('clock_out')
                ->where('clock_in', '<=', DB::raw('DATE_SUB(NOW(), INTERVAL 8 HOUR)'))
                ->update([
                    'clock_out'       => DB::raw('DATE_ADD(clock_in, INTERVAL 8 HOUR)'),
                    'worked_minutes'  => DB::raw('TIMESTAMPDIFF(MINUTE, clock_in, DATE_ADD(clock_in, INTERVAL 8 HOUR))'),
                    'updated_at'      => now(),
                ]);

            $this->info("✅ Closed {$affected} expired workday(s).");
            Log::info("CloseExpiredWorkdays: closed {$affected} entries.");

            return self::SUCCESS;

        } catch (Throwable $e) {
            $this->error("❌ Failed: {$e->getMessage()}");
            Log::error('CloseExpiredWorkdays failed', ['exception' => $e]);

            return self::FAILURE;
        }
    }
}
