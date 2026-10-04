<div class="relative w-full"
     x-data="timerComponent(@js([
         'maxShiftSeconds' => \App\Services\TimeTrackingService::maxShiftHours() * 3600,
         'serverNowMs' => now()->getTimestampMs(),
     ]))">
    {{-- Clock In: only before today's shift has started — the server enforces the same rule --}}
    <template x-if="! $wire.clockInTime">
        <button class="h-full absolute inset-0 z-10 text-primary btn btn-soft btn-primary backdrop-blur-md bg-opacity-10 text-lg hover:text-base-300"
                @click="clockIn()" :disabled="busy">Clock In</button>
    </template>
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
            /**
             * Counts down the rest of today's shift and clocks out when it ends.
             *
             * The remaining time is recomputed from the clock-in time on every
             * tick rather than decremented: browsers throttle timers in
             * background tabs, so a decrementing counter falls behind. It is
             * measured against the server's clock, since the user's may be off.
             * The server caps the shift at the same length, so this clock-out is
             * a convenience, not the rule.
             */
            Alpine.data('timerComponent', ({ maxShiftSeconds, serverNowMs }) => ({
                remainingSeconds: 0,
                intervalId: null,
                busy: false,
                clockOutRequested: false,
                clockSkewMs: serverNowMs - Date.now(),

                init() {
                    this.sync();
                },

                destroy() {
                    this.stopTicking();
                },

                /**
                 * Re-read the shift from the component and start or stop ticking.
                 */
                sync() {
                    this.stopTicking();
                    this.remainingSeconds = this.secondsLeft();

                    if (! $wire.isRunning) {
                        return;
                    }

                    if (this.remainingSeconds > 0) {
                        this.intervalId = setInterval(() => this.tick(), 1000);
                    } else {
                        this.clockOut();
                    }
                },

                secondsLeft() {
                    if (! $wire.isRunning || ! $wire.clockInTime) {
                        return 0;
                    }

                    const now = Date.now() + this.clockSkewMs;
                    const elapsedSeconds = Math.floor((now - new Date($wire.clockInTime).getTime()) / 1000);

                    return Math.max(0, maxShiftSeconds - elapsedSeconds);
                },

                tick() {
                    this.remainingSeconds = this.secondsLeft();

                    if (this.remainingSeconds === 0) {
                        this.clockOut();
                    }
                },

                stopTicking() {
                    clearInterval(this.intervalId);
                    this.intervalId = null;
                },

                async clockIn() {
                    if (this.busy) {
                        return;
                    }

                    this.busy = true;

                    try {
                        await $wire.clockIn();
                    } catch {
                        // Livewire reports failed requests itself.
                    } finally {
                        this.busy = false;
                        this.sync();
                    }
                },

                /**
                 * Attempted once per page: if it fails, the scheduled command
                 * closes the shift, so retrying in a loop would only add load.
                 */
                async clockOut() {
                    this.stopTicking();

                    if (this.busy || this.clockOutRequested) {
                        return;
                    }

                    this.busy = true;
                    this.clockOutRequested = true;

                    try {
                        await $wire.clockOut();
                    } catch {
                        // Livewire reports failed requests itself; the scheduled
                        // command closes the shift if this never gets through.
                    } finally {
                        this.busy = false;
                        this.sync();
                    }
                },

                get hours() {
                    return Math.floor(this.remainingSeconds / 3600);
                },

                get minutes() {
                    return Math.floor((this.remainingSeconds % 3600) / 60);
                },

                get seconds() {
                    return this.remainingSeconds % 60;
                },
            }));
        </script>
    @endscript
</div>
