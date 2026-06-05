<div class="relative w-full" x-data="timerComponent()">
    {{-- Clock In: only show when no entry for today --}}
    @if (!($clockInTime && $clockOutTime))
        <template x-if="!running">
            <button class="h-full absolute inset-0 z-10 text-primary btn btn-soft btn-primary backdrop-blur-md bg-opacity-10 text-lg" @click="start()">Clock In</button>
        </template>
    @endif
    <div class="grid h-full w-full place-items-center">
        {{-- Timer Display --}}
        <div class="flex items-end gap-3 tabular-nums">

            {{-- Hours --}}
            <div class="flex flex-col items-center">
                <span x-text="String(hours).padStart(2, '0')"
                    class="
                text-5xl font-bold tracking-tight
                text-gray-800 dark:text-gray-100
            "></span>
                <span
                    class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 mt-1">h</span>
            </div>

            <span class="text-4xl font-light text-gray-300 dark:text-gray-600 mb-5">:</span>

            {{-- Minutes --}}
            <div class="flex flex-col items-center">
                <span x-text="String(minutes).padStart(2, '0')"
                    class="
                text-5xl font-bold tracking-tight
                text-gray-800 dark:text-gray-100
            "></span>
                <span
                    class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 mt-1">m</span>
            </div>

            <span class="text-4xl font-light text-gray-300 dark:text-gray-600 mb-5">:</span>

            {{-- Seconds --}}
            <div class="flex flex-col items-center">
                <span x-text="String(seconds).padStart(2, '0')"
                    class="
                text-5xl font-bold tracking-tight
                text-red-600 dark:text-red-500
            "></span>
                <span
                    class="text-xs font-semibold uppercase tracking-widest text-gray-300 dark:text-gray-600 mt-1">s</span>
            </div>

        </div>
    </div>


    @script
        <script>
            Alpine.data('timerComponent', () => ({

                totalSeconds: 0,
                intervalId: null,
                running: @entangle('isRunning'),

                init() {
                    if (!$wire.clockInTime) return;

                    if ($wire.clockOutTime) {
                        return;
                    };

                    const clockedInAt = new Date($wire.clockInTime);
                    const elapsedSinceClockIn = Math.floor((Date.now() - clockedInAt.getTime()) / 1000);
                    const totalWorked = ($wire.workedMinutes ?? 0) * 60 + elapsedSinceClockIn;

                    // countdown from 8h minus already worked time
                    this.totalSeconds = Math.max(0, (8 * 60 * 60) - totalWorked);

                    if (this.totalSeconds > 0) {
                        this.intervalId = setInterval(() => this.tick(), 1000);
                    } else {
                        // Timer has expired, automatically clock out
                        console.log('Timer expired, clocking out...');
                        this.stop();
                    }
                },

                tick() {
                    if (this.totalSeconds <= 0) {
                        this.stop();
                        return;
                    }

                    this.totalSeconds--;
                },

                start() {
                    if (this.running) return;

                    $wire.call('clockIn')
                        .then(() => {
                            // Set timer to 8 hours and start counting down
                            this.totalSeconds = 8 * 60 * 60;
                            this.intervalId = setInterval(() => this.tick(), 1000);
                        })
                        .catch((e) => {
                            // handle error
                            console.log(e, 'Error occurred while starting timer');
                        });
                },

                stop() {
                    clearInterval(this.intervalId);
                    this.totalSeconds = 0;

                    $wire.call('clockOut');
                },

                reset() {
                    this.stop();
                    this.totalSeconds = 0;
                },

                get hours() {
                    return Math.floor(this.totalSeconds / 3600);
                },

                get minutes() {
                    return Math.floor((this.totalSeconds % 3600) / 60);
                },

                get seconds() {
                    return this.totalSeconds % 60;
                },
            }));
        </script>
    @endscript
</div>
