<div
    class="p-6 lg:p-8 bg-white dark:bg-gray-800 dark:bg-gradient-to-bl dark:from-gray-700/50 dark:via-transparent border-b border-gray-200 dark:border-gray-700">

    <h1 class="text-2xl font-medium text-gray-900 dark:text-white">
        {{ auth()->user()->name }}. Welcome to your Jetstream application!
    </h1>

    <div class="flex items-center justify-between gap-2">
        <div class="flex-1">
            <div class="profile-card rounded-2xl p-6 flex gap-4">

                <!-- LEFT: Avatar + Name + Buttons -->
                <div class="flex-1 flex flex-col justify-center items-center gap-3 flex-1">

                    <!-- Avatar -->
                    <div class="avatar-ring">
                        <div class="w-20 h-20 p-2 rounded-full overflow-hidden bg-amber-200">
                            <!-- Placeholder avatar using initials -->
                            <div
                                class="w-full h-full flex items-center justify-center bg-gradient-to-br from-amber-300 to-orange-400">
                                <span class="text-primary text-2xl font-semibold">{{ auth()->user()->initials }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Name & Title -->
                    <div class="text-center">
                        <p class="card-name text-lg font-semibold text-gray-900 dark:text-white leading-tight">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-gray-900 dark:text-white mt-0.5 tracking-wide">{{ auth()->user()->roleName }}</p>
                    </div>
                </div>

                <!-- RIGHT: Stats -->
                <div class="flex-1 flex flex-col justify-center gap-4 pr-1">

                    <div class="text-center text-gray-900 dark:text-white">
                        <p class="stat-value text-2xl font-semibold">523</p>
                        <p class="stat-label">Posts</p>
                    </div>

                    <div class="text-center text-gray-900 dark:text-white">
                        <p class="stat-value text-2xl font-semibold">1387</p>
                        <p class="stat-label">Likes</p>
                    </div>

                    <div class="text-center text-gray-900 dark:text-white">
                        <p class="stat-value text-2xl font-semibold">146</p>
                        <p class="stat-label">Followers</p>
                    </div>

                </div>

            </div>
        </div>

        <livewire:time-tracker />
    </div>
</div>


<div class="bg-gray-200 dark:bg-gray-800 bg-opacity-25 p-6 lg:p-8">
    <livewire:attendance-calendar />
</div>
