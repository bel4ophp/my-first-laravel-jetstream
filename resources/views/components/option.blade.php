@props(['value' => null])

<option @isset($value) value="{{ $value }}" @endisset {{ $attributes->merge(['class' => 'bg-base-300 dark:text-gray-300 hover:bg-primary']) }}>
    {{ $slot }}
</option>
