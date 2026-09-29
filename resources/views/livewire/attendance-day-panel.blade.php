<div x-data
     x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'nearest' })"
     class="card bg-base-100 shadow-sm overflow-hidden text-sm">

    {{-- Panel header --}}
    <div class="flex flex-col gap-3 p-3 sm:gap-4 sm:p-4 border-b border-base-200 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1">
            <h3 class="text-sm sm:text-base font-semibold text-base-content dark:text-white">
                {{ $this->dateLabel }}
            </h3>
            <p class="text-xs text-base-content/60">
                {{ $this->entries->count() }} {{ Str::plural('employee', $this->entries->count()) }} recorded
            </p>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-2">
            @if ($this->canCreateTimeEntries && ! $editingEntries)
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
                        class="btn btn-sm gap-2 text-xs {{ $editingEntries ? 'btn-error' : 'btn-outline' }}">
                    <x-lucide-edit-2 class="w-4 h-4" />
                    <span>{{ $editingEntries ? __('Stop editing') : __('Manage entries') }}</span>
                </button>
            @endif

            <div class="flex items-center gap-2">
                @if ($this->canExportAttendance)
                    <a href="{{ route('reports.attendance.export', ['type' => 'daily', 'date' => $date]) }}"
                       class="btn btn-sm btn-outline gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                        </svg>
                        Export day
                    </a>
                @endif
                <button wire:click="close"
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
            $totalCount  = $this->entries->count();
            $activeCount = $this->entries->filter(fn ($e) => in_array($e->status(), ['in','late']))->count();
            $lateCount   = $this->entries->filter(fn ($e) => $e->status() === 'late')->count();
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

        @if ($this->canViewTimeEntries)
            {{-- New entry form — shown in standalone create mode OR inside manage-entries mode --}}
            @if ($creatingEntry || ($editingEntries && $this->canCreateTimeEntries))
                <div class="flex flex-col gap-3 p-3 sm:p-4 bg-base-200/40">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:gap-4">

                        {{-- Employee select --}}
                        <div class="form-control flex-1">
                            <x-label value="{{ __('Employee') }}" class="text-[10px] sm:text-xs mb-1" />
                            <select wire:model="createForm.user_id"
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
                                     wire:model="createForm.clock_in" />
                            <x-input-error for="createForm.clock_in" class="mt-1 text-xs" />
                        </div>

                        {{-- Clock out --}}
                        <div class="form-control">
                            <x-label value="{{ __('Clock out') }}" class="text-[10px] sm:text-xs mb-1" />
                            <x-input type="time"
                                     class="input input-sm input-bordered"
                                     wire:model="createForm.clock_out" />
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

            @forelse ($this->entries as $entry)
                @php
                    $status    = $entry->status();
                    $initials  = collect(explode(' ', $entry->user->name))->map(fn($p) => strtoupper($p[0]))->take(2)->implode('');
                    $timeRange = $entry->clockOutFormatted()
                        ? $entry->clockInFormatted() . ' – ' . $entry->clockOutFormatted()
                        : $entry->clockInFormatted() . ' – now';
                @endphp

                <div wire:key="entry-{{ $entry->id }}"
                     class="flex flex-col gap-3 p-3 sm:p-4 sm:flex-row sm:items-center">

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

                        @if ($editingEntries)
                            <div class="grid gap-2 mt-2 sm:mt-3 sm:grid-cols-2">
                                <div class="form-control">
                                    <x-label for="clock_in_{{ $entry->id }}" value="{{ __('Clock in') }}" class="text-[10px] sm:text-xs text-error" />
                                    <x-input id="clock_in_{{ $entry->id }}" type="time" class="input input-sm input-bordered mt-1" wire:model="entryEdits.{{ $entry->id }}.clock_in" />
                                    <x-input-error for="entryEdits.{{ $entry->id }}.clock_in" class="mt-2 text-xs" />
                                </div>
                                <div class="form-control">
                                    <x-label for="clock_out_{{ $entry->id }}" value="{{ __('Clock out') }}" class="text-[10px] sm:text-xs text-error" />
                                    <x-input id="clock_out_{{ $entry->id }}" type="time" class="input input-sm input-bordered mt-1" wire:model="entryEdits.{{ $entry->id }}.clock_out" />
                                    <x-input-error for="entryEdits.{{ $entry->id }}.clock_out" class="mt-2 text-xs" />
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Status badge + actions --}}
                    <div class="flex flex-row-reverse sm:flex-col items-end gap-2">
                        @if ($editingEntries)
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
        @else
            <div class="px-4 py-8 text-center text-sm text-base-content/60">
                You do not have permission to view time entries.
            </div>
        @endif

    </div>
</div>
