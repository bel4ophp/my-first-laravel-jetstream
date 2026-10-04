{{-- Polls so "who is in now" and the hours stay current while the dashboard is open --}}
<div class="flex-1 flex flex-col justify-center gap-4 pr-1" wire:poll.60s>

    @if($this->label)
        <p class="text-center text-xs font-semibold uppercase tracking-widest text-card-title-color">{{ $this->label }}</p>
    @endif

    <div class="text-center text-gray-900 dark:text-white">
        <p class="stat-value text-2xl font-semibold">{{ $this->monthlyHours }}</p>
        <p class="stat-label">{{ ($this->isManager || $this->isOwner) ? 'Team Hrs' : 'Work Hrs' }} ({{ now()->format('M') }})</p>
    </div>

    @if($this->activeNow !== null)
        <div class="text-center text-gray-900 dark:text-white">
            <p class="stat-value text-2xl font-semibold">{{ $this->activeNow }}</p>
            <p class="stat-label">Active Now</p>
        </div>
    @endif

    <div class="text-center text-gray-900 dark:text-white">
        <p class="stat-value text-2xl font-semibold">{{ $this->freeDays }}</p>
        <p class="stat-label">Request day off</p>
    </div>

</div>