@props(['disabled' => false])

<textarea {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'border-ghost bg-base-300/50 dark:text-gray-300 focus:border-primary focus:ring-primary rounded-md shadow-sm']) !!}>{{ $slot }}</textarea>
