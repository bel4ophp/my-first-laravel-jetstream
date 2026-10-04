<?php

namespace App\Console\Commands;

use App\Models\TimeEntry;
use App\Services\TimeTrackingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('app:close-expired-workdays')]
#[Description('Closes shifts left open beyond the maximum shift length, capping them at that length.')]
class CloseExpiredWorkdays extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $closed = $this->closeExpiredEntries();

            $this->info("Closed {$closed} expired workday(s).");
            Log::info("CloseExpiredWorkdays: closed {$closed} entries.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Failed: {$e->getMessage()}");
            Log::error('CloseExpiredWorkdays failed', ['exception' => $e]);

            return self::FAILURE;
        }
    }

    /**
     * Expressed in PHP rather than DATE_ADD/TIMESTAMPDIFF so the command doesn't
     * depend on MySQL-only SQL and goes through the model like every other write.
     */
    private function closeExpiredEntries(): int
    {
        $closed = 0;

        // A shift left open longer than this is assumed to be a forgotten
        // clock-out and is capped rather than left running.
        $maxShiftHours = TimeTrackingService::maxShiftHours();

        TimeEntry::query()
            ->whereNull('clock_out')
            ->where('clock_in', '<=', now()->subHours($maxShiftHours))
            ->chunkById(500, function ($entries) use (&$closed, $maxShiftHours) {
                foreach ($entries as $entry) {
                    $entry->update([
                        'clock_out' => $entry->clock_in->copy()->addHours($maxShiftHours),
                        'worked_minutes' => $maxShiftHours * 60,
                    ]);

                    $closed++;
                }
            });

        return $closed;
    }
}
