<div>
    @if (session('leave-success'))
        <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-300">
            {{ session('leave-success') }}
        </div>
    @endif

    @if (session('leave-error'))
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-300">
            {{ session('leave-error') }}
        </div>
    @endif

    <h3 class="font-semibold text-lg text-gray-800 dark:text-gray-200 mb-4">Pending Approvals</h3>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead>
                <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    <th class="px-4 py-3">Employee</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Dates</th>
                    <th class="px-4 py-3">Days</th>
                    <th class="px-4 py-3">Notes</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-sm text-gray-700 dark:text-gray-300">
                @forelse ($this->pendingRequests as $request)
                    <tr>
                        <td class="px-4 py-3">{{ $request->user->name }}</td>
                        <td class="px-4 py-3">{{ $request->type->label() }}</td>
                        <td class="px-4 py-3">
                            {{ $request->start_date->toFormattedDateString() }}
                            &ndash; {{ $request->end_date->toFormattedDateString() }}
                        </td>
                        <td class="px-4 py-3">{{ $request->calculated_days }}</td>
                        <td class="px-4 py-3 max-w-xs truncate" title="{{ $request->notes }}">
                            {{ $request->notes ?: '—' }}
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button wire:click="approve({{ $request->id }})"
                                wire:confirm="Approve this leave request?"
                                class="text-sm font-medium text-green-600 hover:text-green-800 dark:text-green-400">
                                Approve
                            </button>
                            <span class="text-gray-300 dark:text-gray-600 mx-1">|</span>
                            <button wire:click="deny({{ $request->id }})"
                                wire:confirm="Deny this leave request?"
                                class="text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400">
                                Deny
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">
                            No pending requests awaiting your approval.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>