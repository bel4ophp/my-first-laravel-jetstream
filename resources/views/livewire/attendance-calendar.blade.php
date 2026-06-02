<div class="space-y-2 sm:space-y-4 w-full px-2 sm:px-0">

    {{-- ── Month navigation + export trigger --}}
    <div class="flex items-center justify-between">
        <button wire:click="prevMonth"
                class="btn btn-square btn-ghost btn-xs sm:btn-sm text-base-content/60 hover:text-base-content"
                aria-label="Previous month">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
            </svg>
        </button>

        <div class="text-center">
            <h2 class="text-base sm:text-lg font-semibold text-base-content dark:text-white">
                {{ $this->calendarLabel }}
            </h2>
            <span wire:loading class="text-xs text-base-content/50 dark:text-gray-400">Loading…</span>
        </div>

        <button wire:click="nextMonth"
                @class([
                    'btn btn-square btn-ghost btn-xs sm:btn-sm transition-colors',
                    'text-base-content/60 hover:text-base-content' => ! $this->isCurrentMonth,
                    'btn-disabled text-base-content/30' => $this->isCurrentMonth,
                ])
                @disabled($this->isCurrentMonth)
                aria-label="Next month">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
            </svg>
        </button>
    </div>

    {{-- ── Calendar grid --}}
    <div wire:loading.class="opacity-50 pointer-events-none" wire:loading.class.remove="opacity-100">

        {{-- Day-of-week header --}}
        <div class="grid grid-cols-7 gap-1 sm:gap-2 mb-2">
            @foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $dow)
                <div class="text-center text-[8px] sm:text-[10px] font-semibold uppercase text-base-content/50">
                    {{ $dow }}
                </div>
            @endforeach
        </div>

        {{-- Grid cells --}}
        <div class="grid grid-cols-7 gap-1 sm:gap-2">

            {{-- Empty offset --}}
            @for ($i = 0; $i < $this->firstDayOffset; $i++)
                <div class="min-h-[3rem] sm:min-h-[5rem] rounded-box bg-base-200/70 border border-base-200"></div>
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
                        'min-h-[3rem] sm:min-h-[5rem] rounded-box border p-1.5 sm:p-2 text-left transition-all duration-150',
                        'cursor-pointer hover:border-primary/80 hover:bg-base-100' => $clickable,
                        'cursor-default opacity-50'                             => ! $clickable,
                        'bg-primary text-white border-primary'                  => $isSelected,
                        'bg-base-100 border-primary'                            => $isToday && ! $isSelected,
                        'bg-base-100 border-base-200'                           => ! $isToday && ! $isSelected && $clickable,
                        'bg-base-200 border-base-200'                           => ! $clickable,
                    ])>

                    {{-- Day number --}}
                    <div @class([
                        'text-[9px] sm:text-[11px] font-semibold mb-0.5 sm:mb-1',
                        'text-white' => $isSelected,
                        'text-primary' => $isToday && ! $isSelected,
                        'text-base-content/50' => ! $isToday && ! $isSelected,
                    ])>{{ $day }}</div>

                    {{-- Name pills — show max 2 (hidden on mobile, skeleton instead) --}}
                    <div class="hidden sm:flex sm:flex-col sm:gap-0.5">
                        @foreach ($entries->take(2) as $entry)
                            @php $status = $entry->status(); @endphp
                            <span @class([
                                'badge badge-xs truncate text-[7px] sm:text-xs',
                                'badge-success' => $status === 'in',
                                'badge-warning' => $status === 'late',
                                'badge-outline' => $status === 'out',
                            ])>
                                {{ explode(' ', $entry->user->name)[0] }}
                            </span>
                        @endforeach
                    </div>

                    {{-- Mobile skeleton loaders --}}
                    <div class="sm:hidden flex flex-col gap-0.5">
                        @foreach ($entries->take(2) as $entry)
                            <div class="skeleton h-4 w-100 rounded"></div>
                        @endforeach
                    </div>

                    {{-- +N more --}}
                    @if ($entries->count() > 2)
                        <span class="text-[8px] sm:text-[10px] text-base-content/50 px-0.5">
                            +{{ $entries->count() - 2 }}
                        </span>
                    @endif

                </div>
            @endfor

        </div>
    </div>

    {{-- ── Legend + export trigger --}}
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-1 sm:gap-2 text-[10px] sm:text-xs text-base-content/60">
            <span class="badge badge-sm badge-success badge-outline">Clocked in</span>
            <span class="badge badge-sm badge-warning badge-outline">Late</span>
            <span class="badge badge-sm badge-outline">Clocked out</span>
        </div>

        @if ($this->canExportAttendance)
            <button type="button"
                    onclick="document.getElementById('attendance-export-modal').showModal()"
                    class="btn btn-xs btn-outline gap-1.5">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                </svg>
                Export time entries
            </button>
        @endif
    </div>

    {{-- ── Day detail panel --}}
    {{-- ── Export modal (admin + manager only) --}}
    @if ($this->canExportAttendance)
        <dialog id="attendance-export-modal" class="modal">
            <div class="modal-box w-full max-w-md"
                 x-data="{
                     type: 'monthly',
                     date: '{{ now()->format('Y-m-d') }}',
                     weekDate: '{{ now()->startOfWeek(\Carbon\Carbon::MONDAY)->format('Y-m-d') }}',
                     year: {{ now()->year }},
                     month: {{ now()->month }},
                     startDate: '{{ now()->startOfMonth()->format('Y-m-d') }}',
                     endDate: '{{ now()->format('Y-m-d') }}'
                 }">

                <form method="dialog">
                    <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2" aria-label="Close">✕</button>
                </form>

                <h3 class="font-semibold text-base mb-4">Export Attendance</h3>

                <form method="GET" action="{{ route('reports.attendance.export') }}" class="space-y-4">

                    {{-- Period type --}}
                    <div role="tablist" class="tabs tabs-boxed">
                        @foreach ([
                            'daily'   => 'Daily',
                            'weekly'  => 'Weekly',
                            'monthly' => 'Monthly',
                            'yearly'  => 'Yearly',
                            'custom'  => 'Custom',
                        ] as $value => $label)
                            <button type="button"
                                    role="tab"
                                    class="tab text-xs"
                                    :class="{ 'tab-active': type === '{{ $value }}' }"
                                    @click="type = '{{ $value }}'">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                    <input type="hidden" name="type" :value="type" />

                    {{-- Daily --}}
                    <template x-if="type === 'daily'">
                        <div class="form-control">
                            <label class="label pb-1"><span class="label-text text-xs">Date</span></label>
                            <input type="date" name="date" x-model="date"
                                   class="input input-sm input-bordered w-full" />
                        </div>
                    </template>

                    {{-- Weekly --}}
                    <template x-if="type === 'weekly'">
                        <div class="form-control">
                            <label class="label pb-1"><span class="label-text text-xs">Any date within the week</span></label>
                            <input type="date" name="week_date" x-model="weekDate"
                                   class="input input-sm input-bordered w-full" />
                        </div>
                    </template>

                    {{-- Monthly --}}
                    <template x-if="type === 'monthly'">
                        <div class="flex gap-2">
                            <div class="form-control flex-1">
                                <label class="label pb-1"><span class="label-text text-xs">Year</span></label>
                                <select name="year" x-model.number="year" class="select select-sm text-sm select-bordered w-full">
                                    @foreach (range(now()->year, 2020) as $y)
                                        <option value="{{ $y }}">{{ $y }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-control flex-1">
                                <label class="label pb-1"><span class="label-text text-xs">Month</span></label>
                                <select name="month" x-model.number="month" class="select select-sm text-sm select-bordered w-full">
                                    @foreach (range(1, 12) as $m)
                                        <option value="{{ $m }}">{{ \Carbon\Carbon::create(null, $m)->format('F') }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </template>

                    {{-- Yearly --}}
                    <template x-if="type === 'yearly'">
                        <div class="form-control">
                            <label class="label pb-1"><span class="label-text text-xs">Year</span></label>
                            <select name="year" x-model.number="year" class="select select-sm text-sm select-bordered w-full">
                                @foreach (range(now()->year, 2020) as $y)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>
                    </template>

                    {{-- Custom range --}}
                    <template x-if="type === 'custom'">
                        <div class="flex gap-2">
                            <div class="form-control flex-1">
                                <label class="label pb-1"><span class="label-text text-xs">Start date</span></label>
                                <input type="date" name="start_date" x-model="startDate"
                                       class="input input-sm input-bordered w-full" />
                            </div>
                            <div class="form-control flex-1">
                                <label class="label pb-1"><span class="label-text text-xs">End date</span></label>
                                <input type="date" name="end_date" x-model="endDate"
                                       class="input input-sm input-bordered w-full" />
                            </div>
                        </div>
                    </template>

                    <div class="modal-action mt-2">
                        <button type="submit" class="btn btn-primary btn-sm gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                            </svg>
                            Download CSV
                        </button>
                    </div>
                </form>
            </div>
            <form method="dialog" class="modal-backdrop"><button>close</button></form>
        </dialog>
    @endif

    @if ($selectedDay)
        <div x-data
             x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'nearest' })"
             class="card bg-base-100 shadow-sm overflow-hidden text-sm">

            {{-- Panel header --}}
            <div class="flex flex-col gap-3 p-3 sm:gap-4 sm:p-4 border-b border-base-200 md:flex-row md:items-center md:justify-between">
                <div class="space-y-1">
                    <h3 class="text-sm sm:text-base font-semibold text-base-content dark:text-white">
                        {{ $this->selectedDateLabel }}
                    </h3>
                    <p class="text-xs text-base-content/60">
                        {{ $this->selectedEntries->count() }} {{ Str::plural('employee', $this->selectedEntries->count()) }} recorded
                    </p>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-2">
                    @if ($this->canCreateTimeEntries && ! $this->editingEntries)
                        <button type="button"
                                wire:click="{{ $creatingEntry ? 'cancelCreatingEntry' : 'startCreatingEntry' }}"
                                class="btn btn-sm gap-2 text-xs {{ $creatingEntry ? 'btn-error' : 'btn-outline' }}">
                            <x-lucide-plus class="w-4 h-4" />
                            <span>{{ $creatingEntry ? __('Cancel') : __('Add entry') }}</span>
                        </button>
                    @endif

                    @if ($this->canUpdateTimeEntries)
                        <button type="button"
                                wire:click="toggleEditingEntries"
                                class="btn btn-sm gap-2 text-xs {{ $this->editingEntries ? 'btn-error' : 'btn-outline' }}">
                            <x-lucide-edit-2 class="w-4 h-4" />
                            <span>{{ $this->editingEntries ? __('Stop editing') : __('Manage entries') }}</span>
                        </button>
                    @endif

                    <div class="flex items-center gap-2">
                        @if ($this->canExportAttendance)
                            <a href="{{ route('reports.attendance.export', ['type' => 'daily', 'date' => $this->selectedDateIso]) }}"
                               class="btn btn-sm btn-outline gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                                </svg>
                                Export day
                            </a>
                        @endif
                        <button wire:click="selectDay({{ $selectedDay }})"
                                class="btn btn-square btn-ghost btn-sm"
                                aria-label="Close panel">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Summary stats --}}
            <div class="stats stats-3 shadow-none bg-base-100 border-b border-base-200">
                @php
                    $totalCount  = $this->selectedEntries->count();
                    $activeCount = $this->selectedEntries->filter(fn ($e) => in_array($e->status(), ['in','late']))->count();
                    $lateCount   = $this->selectedEntries->filter(fn ($e) => $e->status() === 'late')->count();
                @endphp
                <div class="stat">
                    <div class="stat-title">Total</div>
                    <div class="stat-value">{{ $totalCount }}</div>
                </div>
                <div class="stat">
                    <div class="stat-title">Still in</div>
                    <div class="stat-value text-emerald-600 dark:text-emerald-400">{{ $activeCount }}</div>
                </div>
                <div class="stat">
                    <div class="stat-title">Late</div>
                    <div class="stat-value text-amber-600 dark:text-amber-400">{{ $lateCount }}</div>
                </div>
            </div>

            {{-- Employee list --}}
            <div class="divide-y divide-base-200 max-h-80 overflow-y-auto">

                {{-- New entry form — shown in standalone create mode OR inside manage-entries mode --}}
                @if ($creatingEntry || ($this->editingEntries && $this->canCreateTimeEntries))
                    <div class="flex flex-col gap-3 p-3 sm:p-4 bg-base-200/40">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:gap-4">

                            {{-- Employee select --}}
                            <div class="form-control flex-1">
                                <x-label value="{{ __('Employee') }}" class="text-[10px] sm:text-xs mb-1" />
                                <select wire:model.defer="createForm.user_id"
                                        class="select select-sm select-bordered w-full py-0 text-sm">
                                    <option value="">{{ __('Select employee…') }}</option>
                                    @foreach ($this->selectableUsers as $selectableUser)
                                        <option value="{{ $selectableUser->id }}">{{ $selectableUser->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error for="createForm.user_id" class="mt-1 text-xs" />
                            </div>

                            {{-- Clock in --}}
                            <div class="form-control">
                                <x-label value="{{ __('Clock in') }}" class="text-[10px] sm:text-xs mb-1" />
                                <x-input type="time"
                                         class="input input-sm input-bordered"
                                         wire:model.defer="createForm.clock_in" />
                                <x-input-error for="createForm.clock_in" class="mt-1 text-xs" />
                            </div>

                            {{-- Clock out --}}
                            <div class="form-control">
                                <x-label value="{{ __('Clock out') }}" class="text-[10px] sm:text-xs mb-1" />
                                <x-input type="time"
                                         class="input input-sm input-bordered"
                                         wire:model.defer="createForm.clock_out" />
                                <x-input-error for="createForm.clock_out" class="mt-1 text-xs" />
                            </div>

                            {{-- Save --}}
                            <button type="button"
                                    wire:click="saveNewEntry"
                                    class="btn btn-sm btn-success gap-2 self-end">
                                <x-lucide-save class="w-4 h-4" />
                                {{ __('Save') }}
                            </button>
                        </div>
                    </div>
                @endif

                @forelse ($this->selectedEntries as $entry)
                    @php
                        $status    = $entry->status();
                        $initials  = collect(explode(' ', $entry->user->name))->map(fn($p) => strtoupper($p[0]))->take(2)->implode('');
                        $timeRange = $entry->clockOutFormatted()
                            ? $entry->clockInFormatted() . ' – ' . $entry->clockOutFormatted()
                            : $entry->clockInFormatted() . ' – now';
                    @endphp

                    <div class="flex flex-col gap-3 p-3 sm:p-4 sm:flex-row sm:items-center">

                        {{-- Avatar --}}
                        <div class="avatar placeholder">
                            <div class="w-8 sm:w-10 h-8 sm:h-10 rounded-full bg-primary/70 text-primary-content grid place-items-center text-[9px] sm:text-[11px] leading-none font-semibold ring ring-primary/20">
                                {{ $initials }}
                            </div>
                        </div>

                        {{-- Name + team role --}}
                        <div class="min-w-0 flex-1">
                            <div class="text-xs sm:text-sm font-medium text-base-content dark:text-white truncate">
                                {{ $entry->user->name }}
                            </div>
                            <div class="text-[10px] sm:text-xs text-base-content/60 truncate">
                                {{ $entry->user->email }}
                            </div>
                        </div>

                        {{-- Time range + hours --}}
                        <div class="min-w-0 flex-1 space-y-1 sm:space-y-2">
                            <div class="text-xs font-medium text-base-content/70 dark:text-base-content/50">
                                {{ $entry->durationForHumans() ?? '—' }}
                            </div>
                            <div class="text-[10px] sm:text-xs text-base-content/60 dark:text-base-content/50">
                                {{ $timeRange }}
                            </div>

                            @if ($this->editingEntries)
                                <div class="grid gap-2 mt-2 sm:mt-3 sm:grid-cols-2">
                                    <div class="form-control">
                                        <x-label for="clock_in_{{ $entry->id }}" value="{{ __('Clock in') }}" class="text-[10px] sm:text-xs text-error" />
                                        <x-input id="clock_in_{{ $entry->id }}" type="time" class="input input-sm input-bordered mt-1" wire:model.defer="entryEdits.{{ $entry->id }}.clock_in" />
                                        <x-input-error for="entryEdits.{{ $entry->id }}.clock_in" class="mt-2 text-xs" />
                                    </div>
                                    <div class="form-control">
                                        <x-label for="clock_out_{{ $entry->id }}" value="{{ __('Clock out') }}" class="text-[10px] sm:text-xs text-error" />
                                        <x-input id="clock_out_{{ $entry->id }}" type="time" class="input input-sm input-bordered mt-1" wire:model.defer="entryEdits.{{ $entry->id }}.clock_out" />
                                        <x-input-error for="entryEdits.{{ $entry->id }}.clock_out" class="mt-2 text-xs" />
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Status badge + actions --}}
                        <div class="flex flex-row-reverse sm:flex-col items-end gap-2">
                            @if ($this->editingEntries)
                                <button type="button"
                                        wire:click="saveEntryEdits({{ $entry->id }})"
                                        class="btn btn-square btn-success btn-xs sm:btn-sm">
                                    <x-lucide-save class="w-4 h-4" />
                                </button>
                            @endif

                            @if ($this->canDeleteTimeEntries)
                                <button type="button"
                                        wire:click="deleteEntry({{ $entry->id }})"
                                        wire:confirm="Delete this time entry? This cannot be undone."
                                        class="btn btn-square btn-error btn-xs sm:btn-sm btn-outline">
                                    <x-lucide-trash-2 class="w-4 h-4" />
                                </button>
                            @endif

                            <span @class([
                                'badge badge-xs font-semibold p-2 text-[9px] sm:text-xs',
                                'badge-success' => $status === 'in',
                                'badge-warning' => $status === 'late',
                                'badge-outline' => $status === 'out',
                            ])>
                                {{ match($status) { 'in' => 'In', 'late' => 'Late', default => 'Out' } }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-base-content/60">
                        No entries recorded for this day.
                    </div>
                @endforelse

            </div>
        </div>
    @endif
</div>
