<div class="relative" x-data="{ open: false }">

    <!-- Bell -->
    <button @click="open = !open" class="relative focus:outline-none">
        <svg class="w-6 h-6 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300" fill="none"
            stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M15 17h5l-1.4-1.4A2 2 0 0118 14.17V11a6 6 0 10-12 0v3.17a2 2 0 01-.6 1.43L4 17h5m6 0a3 3 0 11-6 0m6 0z" />
        </svg>

        @if ($this->unreadCount > 0)
            <span class="absolute top-0 right-0 bg-red-500 text-white text-xs px-1 rounded-full">
                {{ $this->unreadCount }}
            </span>
        @endif
    </button>

    <!-- Dropdown -->
    <div x-show="open" @click.away="open = false" x-transition
        class="absolute left-[-8rem] sm:right-0 z-50 mt-3 w-80 max-w-sm
            bg-white dark:bg-gray-800
            border border-gray-200 dark:border-gray-700 shadow-lg"
    >

        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                Notifications
            </h3>

            @if ($this->unreadCount > 0)
                <button wire:click="markAllAsRead"
                    class="text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                    Mark all read
                </button>
            @endif
        </div>

        <!-- List -->
        <div class="max-h-96 overflow-y-auto">

            @forelse($this->notifications as $notification)
                <div
                    class="px-4 py-3 flex gap-3 hover:bg-gray-50 dark:hover:bg-gray-700
                            transition border-b border-gray-100 dark:border-gray-700
                            {{ is_null($notification->read_at) ? 'bg-gray-50/50 dark:bg-gray-700/30' : '' }}">

                    <!-- Avatar / Icon -->
                    <div class="flex-shrink-0">
                        <div
                            class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-500/20
                                    flex items-center justify-center text-indigo-600 dark:text-indigo-300">
                            👤
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="flex-1 min-w-0">

                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                            {{ $notification->data['employee_name'] ?? 'System' }}
                        </p>

                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Clocked <span class="font-medium text-gray-700 dark:text-gray-300">
                                {{ $notification->data['action'] ?? '' }}
                            </span>
                        </p>

                    </div>

                    <!-- Action -->
                    <div class="flex items-start gap-2">

                        @if (is_null($notification->read_at))
                            <button wire:click="markAsRead('{{ $notification->id }}')"
                                class="text-xs text-green-600 hover:text-green-700">
                                Mark
                            </button>
                        @else
                            <span class="text-[10px] text-gray-400">read</span>
                        @endif

                    </div>

                </div>

            @empty
                <div class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                    No notifications yet
                </div>
            @endforelse

        </div>
    </div>
</div>
