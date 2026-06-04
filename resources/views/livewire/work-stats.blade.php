<div class="flex-1 flex flex-col justify-center gap-4 pr-1">

    <div class="text-center text-gray-900 dark:text-white">
        <p class="stat-value text-2xl font-semibold">{{ $monthlyHours }}</p>
        <p class="stat-label">{{ $isManager ? 'Team Hrs' : 'Work Hrs' }} ({{ now()->format('M') }})</p>
    </div>

    <div class="text-center text-gray-900 dark:text-white">
        <p class="stat-value text-2xl font-semibold">{{ $activeNow }}</p>
        <p class="stat-label">Active Now</p>
    </div>

    <div class="text-center text-gray-900 dark:text-white">
        <p class="stat-value text-2xl font-semibold">{{ $monthlyHours }}</p>
        <p class="stat-label">{{ $isManager ? 'Team Hrs' : 'Work Hrs' }} ({{ now()->format('M') }})</p>
    </div>

</div>
