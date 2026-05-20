<div class="space-y-4">

    {{-- ── Month navigation ──────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between">
        <button wire:click="prevMonth"
                class="p-2 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 text-gray-500 dark:text-gray-400 transition-colors"
                aria-label="Previous month">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
            </svg>
        </button>

        <div class="text-center">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                {{ $this->calendarLabel }}
            </h2>
            {{-- Loading indicator --}}
            <span wire:loading class="text-xs text-gray-400 dark:text-gray-500">Loading…</span>
        </div>

        <button wire:click="nextMonth"
                @class([
                    'p-2 rounded-lg border transition-colors',
                    'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 text-gray-500 dark:text-gray-400' => ! $this->isCurrentMonth,
                    'border-gray-100 dark:border-gray-800 text-gray-300 dark:text-gray-600 cursor-not-allowed' => $this->isCurrentMonth,
                ])
                @disabled($this->isCurrentMonth)
                aria-label="Next month">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
            </svg>
        </button>
    </div>

    {{-- ── Calendar grid ──────────────────────────────────────────────────── --}}
    <div wire:loading.class="opacity-50 pointer-events-none" wire:loading.class.remove="opacity-100">

        {{-- Day-of-week header --}}
        <div class="grid grid-cols-7 gap-1 mb-1">
            @foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $dow)
                <div class="text-center text-xs font-medium py-1
                    {{ in_array($dow, ['Sat','Sun']) ? 'text-red-400 dark:text-red-500' : 'text-gray-400 dark:text-gray-500' }}">
                    {{ $dow }}
                </div>
            @endforeach
        </div>

        {{-- Grid cells --}}
        <div class="grid grid-cols-7 gap-1">

            {{-- Empty offset --}}
            @for ($i = 0; $i < $this->firstDayOffset; $i++)
                <div class="min-h-[5rem] rounded-lg bg-gray-50 dark:bg-gray-800/30 border border-gray-100 dark:border-gray-800"></div>
            @endfor

            {{-- Day cells --}}
            @for ($day = 1; $day <= $this->daysInMonth; $day++)
                @php
                    $cellDate   = \Carbon\Carbon::create($year, $month, $day);
                    $isToday    = $cellDate->isToday();
                    $isFuture   = $cellDate->isFuture() && ! $isToday;
                    $isWeekend  = $cellDate->isWeekend();
                    $isSelected = $selectedDay === $day;
                    $entries    = $this->entriesByDay->get($day, collect());
                    $clickable  = ! $isFuture && ! $isWeekend;
                @endphp

                <div
                    @if ($clickable) wire:click="selectDay({{ $day }})" @endif
                    @class([
                        'min-h-[5rem] rounded-lg border p-1.5 text-left transition-all duration-150',
                        'cursor-pointer'                                                                     => $clickable,
                        'cursor-default opacity-40'                                                         => ! $clickable,
                        // Selected
                        'bg-indigo-50 dark:bg-indigo-950/40 border-indigo-400 dark:border-indigo-500'       => $isSelected,
                        // Today (not selected)
                        'bg-white dark:bg-gray-900 border-indigo-300 dark:border-indigo-600 hover:border-indigo-400' => $isToday && ! $isSelected,
                        // Normal past day
                        'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700 hover:border-gray-400 dark:hover:border-gray-500' => ! $isToday && ! $isSelected && $clickable,
                        // Weekend / future
                        'bg-gray-50 dark:bg-gray-800/30 border-gray-100 dark:border-gray-800'               => ! $clickable,
                    ])>

                    {{-- Day number --}}
                    <div @class([
                        'text-xs font-semibold mb-1',
                        'text-indigo-600 dark:text-indigo-400' => $isToday,
                        'text-gray-400 dark:text-gray-500'     => ! $isToday,
                    ])>{{ $day }}</div>

                    {{-- Name pills — show max 2 --}}
                    @foreach ($entries->take(2) as $entry)
                        @php $status = $entry->status(); @endphp
                        <span @class([
                            'block text-[10px] font-medium text-gray-600 dark:text-gray-400 ounded px-1 py-0.5 mb-0.5 truncate',
                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' => $status === 'in',
                            'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'         => $status === 'late',
                            'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'                => $status === 'out',
                        ])>
                            {{ explode(' ', $entry->user->name)[0] }}
                        </span>
                    @endforeach

                    {{-- +N more --}}
                    @if ($entries->count() > 2)
                        <span class="text-[10px] text-gray-400 dark:text-gray-500 px-0.5">
                            +{{ $entries->count() - 2 }} more
                        </span>
                    @endif

                </div>
            @endfor

        </div>
    </div>

    {{-- ── Legend ─────────────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-4 text-xs text-gray-400 dark:text-gray-500">
        <span class="flex items-center gap-1.5">
            <span class="inline-block w-2.5 h-2.5 rounded-sm bg-emerald-100 dark:bg-emerald-900/40 border border-emerald-300 dark:border-emerald-700"></span>
            Clocked in
        </span>
        <span class="flex items-center gap-1.5">
            <span class="inline-block w-2.5 h-2.5 rounded-sm bg-amber-100 dark:bg-amber-900/40 border border-amber-300 dark:border-amber-700"></span>
            Late
        </span>
        <span class="flex items-center gap-1.5">
            <span class="inline-block w-2.5 h-2.5 rounded-sm bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-600"></span>
            Clocked out
        </span>
    </div>

    {{-- ── Day detail panel ───────────────────────────────────────────────── --}}
    @if ($selectedDay)
        <div x-data
             x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'nearest' })"
             class="border border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-900 overflow-hidden">

            {{-- Panel header --}}
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-800">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ $this->selectedDateLabel }}
                        </h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                            {{ $this->selectedEntries->count() }} {{ Str::plural('employee', $this->selectedEntries->count()) }} recorded
                        </p>
                    </div>

                    {{-- Edit time entry --}}
                    <div class="text-right flex-shrink-0">
                        <!-- Manage Time Entry -->
                        @if ($this->canManageTeamMembers)
                            <button class="ms-2 text-sm text-gray-400 underline" wire:click="manageTimeEntry({{ $entry->id }})">
                                <x-lucide-calendar-cog class="w-7 h-7" />
                            </button>
                        @elseif (Laravel\Jetstream\Jetstream::hasRoles())
                            <div class="ms-2 text-sm text-gray-400">
                                {{-- {{ Laravel\Jetstream\Jetstream::findRole($entry->user->membership->role)->name }} --}}
                            </div>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('reports.attendance.export', ['date' => $this->selectedDateIso]) }}"
                       class="text-xs border border-gray-200 dark:border-gray-700 rounded-lg px-2.5 py-1.5 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                        Export day
                    </a>
                    <button wire:click="selectDay({{ $selectedDay }})"
                            class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition-colors p-1 rounded"
                            aria-label="Close panel">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Summary stats --}}
            <div class="grid grid-cols-3 divide-x divide-gray-100 dark:divide-gray-800 border-b border-gray-100 dark:border-gray-800">
                @php
                    $totalCount  = $this->selectedEntries->count();
                    $activeCount = $this->selectedEntries->filter(fn ($e) => in_array($e->status(), ['in','late']))->count();
                    $lateCount   = $this->selectedEntries->filter(fn ($e) => $e->status() === 'late')->count();
                @endphp
                <div class="px-4 py-3 text-center">
                    <div class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Total</div>
                    <div class="text-xl font-semibold text-gray-900 dark:text-white">{{ $totalCount }}</div>
                </div>
                <div class="px-4 py-3 text-center">
                    <div class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Still in</div>
                    <div class="text-xl font-semibold text-emerald-600 dark:text-emerald-400">{{ $activeCount }}</div>
                </div>
                <div class="px-4 py-3 text-center">
                    <div class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Late</div>
                    <div class="text-xl font-semibold text-amber-600 dark:text-amber-400">{{ $lateCount }}</div>
                </div>
            </div>

            {{-- Employee list --}}
            <div class="divide-y divide-gray-50 dark:divide-gray-800 max-h-80 overflow-y-auto">

                @forelse ($this->selectedEntries as $entry)
                    @php
                        $status    = $entry->status();
                        $initials  = collect(explode(' ', $entry->user->name))->map(fn($p) => strtoupper($p[0]))->take(2)->implode('');
                        $timeRange = $entry->clockOutFormatted()
                            ? $entry->clockInFormatted() . ' – ' . $entry->clockOutFormatted()
                            : $entry->clockInFormatted() . ' – now';
                    @endphp

                    <div class="flex items-center gap-3 px-4 py-2.5">

                        {{-- Avatar --}}
                        <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 flex items-center justify-center text-xs font-semibold flex-shrink-0 select-none">
                            {{ $initials }}
                        </div>

                        {{-- Name + team role --}}
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                {{ $entry->user->name }}
                            </div>
                            <div class="text-xs text-gray-400 dark:text-gray-500 truncate">
                                {{ $entry->user->email }}
                            </div>
                        </div>

                        {{-- Time range + hours --}}
                        <div class="text-right flex-shrink-0">
                            <div class="text-xs font-medium text-gray-700 dark:text-gray-300">
                                {{ $entry->durationForHumans() ?? '—' }}
                            </div>
                            <div class="text-xs text-gray-400 dark:text-gray-500">
                                {{ $timeRange }}
                            </div>
                        </div>

                        {{-- Status badge --}}
                        <span @class([
                            'text-[10px] font-semibold px-2 py-0.5 rounded-full flex-shrink-0',
                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => $status === 'in',
                            'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'         => $status === 'late',
                            'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'                => $status === 'out',
                        ])>
                            {{ match($status) { 'in' => 'Clocked in', 'late' => 'Late', default => 'Clocked out' } }}
                        </span>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">
                        No entries recorded for this day.
                    </div>
                @endforelse

            </div>
        </div>
    @endif

</div>
