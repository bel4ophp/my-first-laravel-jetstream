<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Leave Request') }}
        </h2>
    </x-slot>

    @php
        $canApprove = auth()->user()->can('viewApprovals', App\Models\LeaveRequest::class);
        $tab = request('tab') === 'validation' && $canApprove ? 'validation' : 'request';
    @endphp

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Tabs -->
            <nav class="flex gap-1 border-b border-gray-200 dark:border-gray-700">
                <a href="{{ route('leave.index', ['tab' => 'request']) }}"
                    @class([
                        'px-4 py-2 -mb-px text-sm font-medium border-b-2 transition',
                        'border-indigo-500 text-indigo-600 dark:text-indigo-400' => $tab === 'request',
                        'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' => $tab !== 'request',
                    ])>
                    {{ __('Request') }}
                </a>

                @if ($canApprove)
                    <a href="{{ route('leave.index', ['tab' => 'validation']) }}"
                        @class([
                            'px-4 py-2 -mb-px text-sm font-medium border-b-2 transition',
                            'border-indigo-500 text-indigo-600 dark:text-indigo-400' => $tab === 'validation',
                            'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' => $tab !== 'validation',
                        ])>
                        {{ __('Validation') }}
                    </a>
                @endif
            </nav>

            @if ($tab === 'validation')
                <!-- Validation tab: managers + admin only -->
                <div class="bg-base-300 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                    <livewire:leave-approvals />
                </div>
            @else
                <!-- Request tab: visible to all roles -->
                <div class="bg-base-300 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                    <livewire:leave-request-form />
                </div>

                <div class="bg-base-300 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                    <livewire:leave-request-list />
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
