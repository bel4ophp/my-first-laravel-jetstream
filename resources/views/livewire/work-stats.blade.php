<div class="flex-1 flex flex-col justify-center gap-4 pr-1">

    @if($label)
        <p class="text-center text-xs font-semibold uppercase tracking-widest text-card-title-color">{{ $label }}</p>
    @endif

    <div class="text-center text-gray-900 dark:text-white">
        <p class="stat-value text-2xl font-semibold">{{ $monthlyHours }}</p>
        <p class="stat-label">{{ ($isManager || $isOwner) ? 'Team Hrs' : 'Work Hrs' }} ({{ now()->format('M') }})</p>
    </div>

    @if($activeNow !== null)
        <div class="text-center text-gray-900 dark:text-white">
            <p class="stat-value text-2xl font-semibold">{{ $activeNow }}</p>
            <p class="stat-label">Active Now</p>
        </div>
    @endif

    <div class="text-center text-gray-900 dark:text-white">
        <p class="stat-value text-2xl font-semibold">{{ $freeDays }}</p>
        <p class="stat-label">Request day off</p>
    </div>

</div>