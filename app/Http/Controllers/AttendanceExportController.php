<?php

namespace App\Http\Controllers;

use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceExportController extends Controller
{
    public function export(Request $request): StreamedResponse
    {
        $request->validate([
            'date'  => ['nullable', 'date_format:Y-m-d'],
            'year'  => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $user     = Auth::user();
        $date     = $request->get('date');
        $year     = $request->integer('year',  now()->year);
        $month    = $request->integer('month', now()->month);

        // Scope to team (or all, for admins)
        $allowedIds = $this->allowedUserIds($user);

        $query = TimeEntry::with('user')
            ->whereIn('user_id', $allowedIds)
            ->orderBy('work_day')
            ->orderBy('clock_in');

        if ($date) {
            $query->whereDate('work_day', $date);
            $filename = "attendance_{$date}.csv";
        } else {
            $query->whereYear('work_day', $year)->whereMonth('work_day', $month);
            $label    = Carbon::create($year, $month, 1)->format('Y-m');
            $filename = "attendance_{$label}.csv";
        }

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Employee', 'Email', 'Date', 'Clock In', 'Clock Out', 'Worked (min)', 'Duration', 'Status']);

            $query->chunk(500, function ($chunk) use ($handle) {
                foreach ($chunk as $entry) {
                    fputcsv($handle, [
                        $entry->user->name,
                        $entry->user->email,
                        Carbon::parse($entry->work_day)->format('Y-m-d'),
                        $entry->clockInFormatted(),
                        $entry->clockOutFormatted() ?? '—',
                        $entry->worked_minutes ?? '—',
                        $entry->durationForHumans() ?? '—',
                        $entry->status(),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function allowedUserIds($user): \Illuminate\Support\Collection
    {
        if ($user->ownsTeam($user->currentTeam)) {
            // Team owner (admin) — all users on the team
            return $user->currentTeam->allUsers()->pluck('id')->push($user->id);
        }

        // Manager — same team scope
        return $user->currentTeam->allUsers()->pluck('id')->push($user->id);
    }
}
