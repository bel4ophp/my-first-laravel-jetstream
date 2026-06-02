<?php

namespace App\Http\Controllers;

use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceExportController extends Controller
{
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('export', TimeEntry::class);

        $request->validate([
            'type'       => ['required', 'in:daily,weekly,monthly,yearly,custom'],
            'date'       => ['nullable', 'date_format:Y-m-d'],
            'week_date'  => ['nullable', 'date_format:Y-m-d'],
            'start_date' => ['nullable', 'date_format:Y-m-d', 'required_if:type,custom'],
            'end_date'   => ['nullable', 'date_format:Y-m-d', 'required_if:type,custom', 'after_or_equal:start_date'],
            'year'       => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month'      => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $user = Auth::user();
        $type = $request->input('type');

        $query = TimeEntry::with('user')
            ->whereIn('user_id', $this->allowedUserIds($user))
            ->orderBy('work_day')
            ->orderBy('clock_in');

        [$query, $filename] = match ($type) {
            'daily'   => $this->applyDailyFilter($request, $query),
            'weekly'  => $this->applyWeeklyFilter($request, $query),
            'monthly' => $this->applyMonthlyFilter($request, $query),
            'yearly'  => $this->applyYearlyFilter($request, $query),
            'custom'  => $this->applyCustomFilter($request, $query),
        };

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

    private function applyDailyFilter(Request $request, $query): array
    {
        $date = $request->input('date', now()->format('Y-m-d'));

        return [
            $query->whereDate('work_day', $date),
            "attendance_daily_{$date}.csv",
        ];
    }

    private function applyWeeklyFilter(Request $request, $query): array
    {
        $anchor    = Carbon::parse($request->input('week_date', now()->format('Y-m-d')));
        $weekStart = (clone $anchor)->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
        $weekEnd   = (clone $anchor)->endOfWeek(Carbon::SUNDAY)->format('Y-m-d');

        return [
            $query->whereBetween('work_day', [$weekStart, $weekEnd]),
            "attendance_week_{$weekStart}_{$weekEnd}.csv",
        ];
    }

    private function applyMonthlyFilter(Request $request, $query): array
    {
        $year  = $request->integer('year',  now()->year);
        $month = $request->integer('month', now()->month);
        $label = Carbon::create($year, $month, 1)->format('Y-m');

        return [
            $query->whereYear('work_day', $year)->whereMonth('work_day', $month),
            "attendance_monthly_{$label}.csv",
        ];
    }

    private function applyYearlyFilter(Request $request, $query): array
    {
        $year = $request->integer('year', now()->year);

        return [
            $query->whereYear('work_day', $year),
            "attendance_yearly_{$year}.csv",
        ];
    }

    private function applyCustomFilter(Request $request, $query): array
    {
        $start = $request->input('start_date');
        $end   = $request->input('end_date');

        return [
            $query->whereBetween('work_day', [$start, $end]),
            "attendance_custom_{$start}_{$end}.csv",
        ];
    }

    private function allowedUserIds(User $user): \Illuminate\Support\Collection
    {
        return $user->currentTeam->allUsers()->pluck('id')->push($user->id)->unique();
    }
}