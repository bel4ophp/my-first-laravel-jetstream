@props(['id' => null, 'maxWidth' => null])

<x-modal :id="$id" :maxWidth="$maxWidth" {{ $attributes }}>
    <div class="flex items-start justify-between px-6 py-4">
        <div class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ $title }}
        </div>

        <div class="ms-4 flex items-center">
            {{ $titleCloseModal ?? '' }}
        </div>
    </div>

    <div class="mt-4 px-6 text-sm text-gray-600 dark:text-gray-400">
        {{ $content }}
    </div>

    <div class="flex flex-row justify-end px-6 py-4 bg-base-300 text-end">
        {{ $footer }}
    </div>
</x-modal>
