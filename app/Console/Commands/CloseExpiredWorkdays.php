<?php

namespace App\Console\Commands;

use App\Models\TimeEntry;
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
     * A shift left open longer than this is assumed to be a forgotten clock-out
     * and is capped rather than left running.
     */
    private const MAX_SHIFT_HOURS = 8;

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
     * Expressed in PHP rather than DATE_ADD/TIMESTAMPDIFF so the command runs
     * on any driver — the raw form only worked on MySQL, which left it
     * untestable against the SQLite database the suite uses.
     */
    private function closeExpiredEntries(): int
    {
        $closed = 0;

        TimeEntry::query()
            ->whereNull('clock_out')
            ->where('clock_in', '<=', now()->subHours(self::MAX_SHIFT_HOURS))
            ->chunkById(500, function ($entries) use (&$closed) {
                foreach ($entries as $entry) {
                    $entry->update([
                        'clock_out' => $entry->clock_in->copy()->addHours(self::MAX_SHIFT_HOURS),
                        'worked_minutes' => self::MAX_SHIFT_HOURS * 60,
                    ]);

                    $closed++;
                }
            });

        return $closed;
    }
}
