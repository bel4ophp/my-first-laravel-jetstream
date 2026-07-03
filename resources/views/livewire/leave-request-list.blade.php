@php
    use App\Enums\LeaveStatus;

    $badgeClasses = [
        LeaveStatus::Pending->value   => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
        LeaveStatus::Approved->value  => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        LeaveStatus::Denied->value    => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        LeaveStatus::Cancelled->value => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    ];
@endphp

<div>
    <h3 class="font-semibold text-lg text-gray-800 dark:text-gray-200 mb-4">My Leave Requests</h3>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead>
                <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Dates</th>
                    <th class="px-4 py-3">Days</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-sm text-gray-700 dark:text-gray-300">
                @forelse ($this->requests as $request)
                    <tr>
                        <td class="px-4 py-3">{{ $request->type->label() }}</td>
                        <td class="px-4 py-3">
                            {{ $request->start_date->toFormattedDateString() }}
                            &ndash; {{ $request->end_date->toFormattedDateString() }}
                        </td>
                        <td class="px-4 py-3">{{ $request->calculated_days }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badgeClasses[$request->status->value] }}">
                                {{ $request->status->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            @can('cancel', $request)
                                <button wire:click="cancel({{ $request->id }})"
                                    wire:confirm="Cancel this leave request?"
                                    class="text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400">
                                    Cancel
                                </button>
                            @else
                                <span class="text-gray-300 dark:text-gray-600">&mdash;</span>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">
                            You haven't submitted any leave requests yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>