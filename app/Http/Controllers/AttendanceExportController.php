<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceExportPeriod;
use App\Http\Requests\ExportAttendanceRequest;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\AttendanceCalendarService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceExportController extends Controller
{
    public function __construct(private AttendanceCalendarService $calendarService) {}

    public function export(ExportAttendanceRequest $request): StreamedResponse
    {
        /** @var User $user The route is behind auth, so this is never null. */
        $user = Auth::user();

        $query = TimeEntry::with('user')
            ->whereIn('user_id', $this->allowedUserIds($user))
            ->orderBy('work_day')
            ->orderBy('clock_in')
            // chunk() pages with LIMIT/OFFSET, which needs a total order: two
            // entries clocked in at the same moment could otherwise swap places
            // between pages, so one is written twice and the other never.
            ->orderBy('id');

        [$query, $filename] = match ($request->period()) {
            AttendanceExportPeriod::Daily => $this->applyDailyFilter($request, $query),
            AttendanceExportPeriod::Weekly => $this->applyWeeklyFilter($request, $query),
            AttendanceExportPeriod::Monthly => $this->applyMonthlyFilter($request, $query),
            AttendanceExportPeriod::Yearly => $this->applyYearlyFilter($request, $query),
            AttendanceExportPeriod::Custom => $this->applyCustomFilter($request, $query),
        };

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Employee', 'Email', 'Date', 'Clock In', 'Clock Out', 'Worked (min)', 'Duration', 'Status']);

            $query->chunk(500, function ($chunk) use ($handle) {
                foreach ($chunk as $entry) {
                    fputcsv($handle, [
                        $entry->user->name,
                        $entry->user->email,
                        $entry->work_day->toDateString(),
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

    private function applyDailyFilter(Request $request, Builder $query): array
    {
        $date = $request->input('date', now()->format('Y-m-d'));

        return [
            $query->onDay($date),
            "attendance_daily_{$date}.csv",
        ];
    }

    private function applyWeeklyFilter(Request $request, Builder $query): array
    {
        $anchor = Carbon::parse($request->input('week_date', now()->format('Y-m-d')));
        $weekStart = (clone $anchor)->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
        $weekEnd = (clone $anchor)->endOfWeek(Carbon::SUNDAY)->format('Y-m-d');

        return [
            $query->whereBetween('work_day', [$weekStart, $weekEnd]),
            "attendance_week_{$weekStart}_{$weekEnd}.csv",
        ];
    }

    private function applyMonthlyFilter(Request $request, Builder $query): array
    {
        $year = $request->integer('year', now()->year);
        $month = $request->integer('month', now()->month);
        $label = Carbon::create($year, $month, 1)->format('Y-m');

        return [
            $query->forMonth($year, $month),
            "attendance_monthly_{$label}.csv",
        ];
    }

    private function applyYearlyFilter(Request $request, Builder $query): array
    {
        $year = $request->integer('year', now()->year);

        return [
            $query->forYear($year),
            "attendance_yearly_{$year}.csv",
        ];
    }

    private function applyCustomFilter(Request $request, Builder $query): array
    {
        $start = $request->input('start_date');
        $end = $request->input('end_date');

        return [
            $query->whereBetween('work_day', [$start, $end]),
            "attendance_custom_{$start}_{$end}.csv",
        ];
    }

    /**
     * Delegated so an export covers exactly the rows the calendar shows. The
     * previous local definition ignored is_admin, silently narrowing an admin's
     * export to their current team.
     */
    private function allowedUserIds(User $user): Collection
    {
        return $this->calendarService->scopedUserIds($user, null);
    }
}
