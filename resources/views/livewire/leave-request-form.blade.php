<div>
    @if (session('leave-success'))
        <div
            class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-300">
            {{ session('leave-success') }}
        </div>
    @endif

    <form wire:submit="submit" class="space-y-5">
        <div class="flex items-center justify-between">
            <h3 class="font-semibold text-lg text-gray-800 dark:text-gray-200">Request Leave</h3>
            <span class="text-sm text-gray-500 dark:text-gray-400">
                Pool remaining: <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $this->remainingDays }}</span> day(s)
            </span>
        </div>

        {{-- Type --}}
        <div>
            <x-label for="type" value="Leave Type" class="mb-1" />
            <x-select id="type" wire:model="type" class="w-full">
                <x-option value="">— Select a type —</x-option>
                @foreach ($this->availableTypes as $value => $label)
                    <x-option :value="$value">{{ $label }}</x-option>
                @endforeach
            </x-select>
            <x-input-error for="type" class="mt-1" />
        </div>

        {{-- Dates --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <x-label for="startDate" value="Start Date" class="mb-1" />
                <x-input type="date" id="startDate" wire:model.live="startDate" class="w-full" />
                <x-input-error for="startDate" class="mt-1" />
            </div>
            <div>
                <x-label for="endDate" value="End Date" class="mb-1" />
                <x-input type="date" id="endDate" wire:model.live="endDate" class="w-full" />
                <x-input-error for="endDate" class="mt-1" />
            </div>
        </div>

        {{-- Live preview --}}
        @if ($this->previewDays !== null)
            <div
                class="rounded-lg bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-800 px-4 py-3 text-sm text-indigo-700 dark:text-indigo-300">
                This request covers <span class="font-semibold">{{ $this->previewDays }}</span> working day(s),
                excluding weekends and team holidays.
            </div>
        @endif

        {{-- Notes --}}
        <div>
            <x-label for="notes" class="mb-1">Notes <span class="text-gray-400">(optional)</span></x-label>
            <x-textarea id="notes" wire:model="notes" rows="3" class="w-full" />
            <x-input-error for="notes" class="mt-1" />
        </div>

        <div class="flex justify-end">
            <button type="submit"
                class="
                    inline-flex 
                    items-center 
                    rounded-lg 
                    btn btn-sm btn-soft
                    btn-primary 
                    backdrop-blur-md 
                    bg-opacity-10 
                    text-sm 
                    text-primary
                    hover:text-base-300
                    disabled:opacity-50"
                wire:loading.attr="disabled" wire:target="submit">
                <span wire:loading.remove wire:target="submit">Submit Request</span>
                <span wire:loading wire:target="submit">Submitting…</span>
            </button>
        </div>
    </form>
</div>
