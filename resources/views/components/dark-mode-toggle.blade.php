<button
    x-data="{ dark: localStorage.theme === 'dark' || (!localStorage.theme && window.matchMedia('(prefers-color-scheme: dark)').matches) }"
    x-init="$watch('dark', val => {
        localStorage.theme = val ? 'dark' : 'light';
        document.documentElement.classList.toggle('dark', val);
    })"
    @click="dark = !dark"
    class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition"
>
    <!-- Sun icon (light mode) -->
    <svg x-show="!dark" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M12 3v1m0 16v1m8.66-9h-1M4.34 12h-1m15.07-6.07-.71.71M6.34 17.66l-.71.71M17.66 17.66l-.71-.71M6.34 6.34l-.71-.71M12 8a4 4 0 100 8 4 4 0 000-8z"/>
    </svg>
    <!-- Moon icon (dark mode) -->
    <svg x-show="dark" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
    </svg>
</button>