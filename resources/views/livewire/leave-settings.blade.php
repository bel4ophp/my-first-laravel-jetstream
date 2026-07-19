@php
    $poolDefault = \App\Services\LeaveBalanceService::DEFAULT_POOL_DAYS;
@endphp

<div class="space-y-8">
    @if (session('leave-success'))
        <div
            class="rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-300">
            {{ session('leave-success') }}
        </div>
    @endif

    {{-- ── Holidays ─────────────────────────────────────────────────────── --}}
    <section>
        <div class="flex items-center justify-between mb-4 gap-4">
            <h3 class="font-semibold text-lg text-gray-800 dark:text-gray-200">Holidays</h3>

            @if (auth()->user()->is_admin)
                <div class="w-56">
                    <x-select wire:model.live="teamId" class="w-full">
                        @foreach ($this->teams as $team)
                            <x-option :value="$team->id">{{ $team->name }}</x-option>
                        @endforeach
                    </x-select>
                </div>
            @endif
        </div>

        <form wire:submit="saveHoliday" class="grid grid-cols-1 sm:grid-cols-[1fr_auto_auto] gap-3 items-start mb-5">
            <div>
                <x-label for="holidayName" value="Name" class="mb-1" />
                <x-input id="holidayName" wire:model="holidayName" class="w-full" />
                <x-input-error for="holidayName" class="mt-1" />
            </div>
            <div>
                <x-label for="holidayDate" value="Date" class="mb-1" />
                <x-input type="date" id="holidayDate" wire:model="holidayDate" class="w-full" />
                <x-input-error for="holidayDate" class="mt-1" />
            </div>
            <div class="flex gap-2 sm:mt-6">
                <button type="submit" class="btn btn-sm btn-soft btn-primary text-primary hover:text-base-300">
                    {{ $editingHolidayId ? 'Update' : 'Add' }}
                </button>
                @if ($editingHolidayId)
                    <button type="button" wire:click="resetHolidayForm" class="btn btn-sm btn-ghost">
                        Cancel
                    </button>
                @endif
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead>
                    <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-sm text-gray-700 dark:text-gray-300">
                    @forelse ($this->holidays as $holiday)
                        <tr>
                            <td class="px-4 py-3">{{ $holiday->date->toFormattedDateString() }}</td>
                            <td class="px-4 py-3">{{ $holiday->name }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <button wire:click="editHoliday({{ $holiday->id }})"
                                    class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                    Edit
                                </button>
                                <span class="text-gray-300 dark:text-gray-600 mx-1">|</span>
                                <button wire:click="deleteHoliday({{ $holiday->id }})"
                                    wire:confirm="Delete this holiday?"
                                    class="text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">
                                No holidays defined for this team yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- ── Members & pool reset ─────────────────────────────────────────── --}}
    <section class="border-t border-gray-200 dark:border-gray-700 pt-6">
        <h3 class="font-semibold text-lg text-gray-800 dark:text-gray-200 mb-1">Available Days</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
            @if (auth()->user()->is_admin)
                Resetting restores the full pool for <span class="font-medium">every manager and employee across all
                    teams</span>.
            @else
                Resetting restores the full pool for <span class="font-medium">you and your team's employees</span>.
            @endif
        </p>

        <div class="overflow-x-auto mb-5">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead>
                    <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Days off</th>
                        <th class="px-4 py-3">Used</th>
                        <th class="px-4 py-3">Remaining</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-sm text-gray-700 dark:text-gray-300">
                    @forelse ($this->members as $member)
                        @php
                            $balance = $member->leaveBalances->first();
                            $used = $balance?->used_days ?? 0;
                            $total = $balance?->total_days ?? $poolDefault;
                            $remaining = $total - $used;
                            $isEditing = $editingMemberId === $member->id;
                        @endphp
                        <tr>
                            <td class="px-4 py-3">{{ $member->name }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $member->email }}</td>
                            <td class="px-4 py-3">
                                @if ($isEditing)
                                    <div class="w-24">
                                        <x-input type="number" min="0" max="365" wire:model="memberTotalDays"
                                            wire:keydown.enter="saveMember" class="w-full" />
                                    </div>
                                    <x-input-error for="memberTotalDays" class="mt-1" />
                                @else
                                    {{ $total }}
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $used }}</td>
                            <td @class([
                                'px-4 py-3 font-medium',
                                'text-red-600 dark:text-red-400' => $remaining < 0,
                            ])>
                                {{ $remaining }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if ($isEditing)
                                    <button wire:click="saveMember"
                                        class="text-sm font-medium text-green-600 hover:text-green-800 dark:text-green-400">
                                        Save
                                    </button>
                                    <span class="text-gray-300 dark:text-gray-600 mx-1">|</span>
                                    <button wire:click="cancelMemberEdit"
                                        class="text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400">
                                        Cancel
                                    </button>
                                @else
                                    <button wire:click="editMember({{ $member->id }})"
                                        title="Edit available days"
                                        aria-label="Edit available days for {{ $member->name }}"
                                        class="inline-flex text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                                        </svg>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">
                                No team members yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <button wire:click="resetBalances"
            wire:confirm="This sets used days back to zero for everyone listed above. Continue?"
            class="btn btn-sm btn-soft btn-error text-error hover:text-base-300" wire:loading.attr="disabled"
            wire:target="resetBalances">
            <span wire:loading.remove wire:target="resetBalances">Reset available days</span>
            <span wire:loading wire:target="resetBalances">Resetting…</span>
        </button>
    </section>
</div>