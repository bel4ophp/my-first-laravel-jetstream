@php $isOwner = auth()->user()->ownedTeams()->exists(); @endphp

<h1 class="card-title mb-4">{{ auth()->user()->name }}. Welcome to your Jetstream application!</h1>

<div class="flex w-full flex-col lg:flex-row mb-4">
    <div class="card shadow-md shadow-primary/40 rounded-box bg-base-300 grid grow place-items-center">

        @if($isOwner)
            {{-- Admin: All Teams + Current Team stats side by side, no avatar --}}
            <div class="w-full rounded-2xl p-6 flex gap-4">
                <livewire:work-stats key="all-teams" :all-teams="true" />
                <div class="divider divider-horizontal"></div>
                <livewire:work-stats key="current-team" />
            </div>
        @else
            {{-- Manager / Employee: avatar + stats --}}
            <div class="profile-card w-full rounded-2xl p-6 flex gap-4">

                <!-- LEFT: Avatar + Name -->
                <div class="flex-1 flex flex-col justify-center items-center gap-3 flex-1">

                    <!-- Avatar -->
                    <div class="avatar-ring">
                        <div
                            class="w-20 h-20 p-2 rounded-full overflow-hidden bg-gradient-to-br from-amber-300 to-orange-400">
                            <div class="w-full h-full rounded-full flex items-center justify-center">
                                <span
                                    class="text-gray-900 dark:text-white text-2xl font-semibold">{{ auth()->user()->initials }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Name & Title -->
                    <div class="text-center">
                        <p class="card-name text-lg font-semibold text-gray-900 dark:text-white leading-tight">
                            {{ auth()->user()->name }}</p>
                        <p class="text-xs text-gray-900 dark:text-white mt-0.5 tracking-wide">{{ auth()->user()->roleName }}
                        </p>
                    </div>
                </div>

                <!-- RIGHT: Stats -->
                <livewire:work-stats key="user-stats" />

            </div>
        @endif

    </div>

    <div class="divider lg:divider-horizontal before:bg-transparent after:bg-transparent color-transparent"></div>

    <div class="card shadow-md shadow-primary/40 rounded-box bg-base-300 grid grow place-items-center">
        <livewire:time-tracker />
    </div>
</div>

<div class="card rounded-box bg-base-300 grid grow place-items-center p-6 lg:p-8">
    <livewire:attendance-calendar />
</div>