<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Create User') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <form method="POST" action="{{ route('users.store') }}" class="p-6">
                    @csrf

                    <div class="mb-6">
                        <label for="name" class="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">
                            {{ __('Name') }}
                        </label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" 
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500"
                               required>
                        @error('name')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-6">
                        <label for="email" class="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">
                            {{ __('Email') }}
                        </label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" 
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500"
                               required>
                        @error('email')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-6">
                        <label for="team_id" class="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">
                            {{ __('Assign to Team (Optional)') }}
                        </label>
                        <select name="team_id" id="team_id" 
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">{{ __('Select a team...') }}</option>
                            @foreach ($teams as $team)
                                <option value="{{ $team->id }}" {{ old('team_id') == $team->id ? 'selected' : '' }}>
                                    {{ $team->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('team_id')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-6">
                        <label for="role" class="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">
                            {{ __('Select Role (Optional)') }}
                        </label>
                        <select name="role" id="role" 
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">{{ __('Select a role...') }}</option>
                            @foreach ($roles as $role)
                                @if($role->key === 'admin')
                                    @continue
                                @elseif (auth()->user()->isTeamManager() && $role->key === 'admin')
                                    @continue
                                @endif
                                <option value="{{ $role->key }}" {{ old('role') == $role->key ? 'selected' : '' }}>
                                    {{ $role->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('role')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <p class="mb-6 text-sm text-gray-600 dark:text-gray-400">
                        {{ __('A password reset link will be sent to the user email.') }}
                    </p>

                    <div class="flex gap-4">
                        <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition">
                            {{ __('Create User') }}
                        </button>
                        <a href="{{ route('users.index') }}" class="px-6 py-2 bg-gray-300 hover:bg-gray-400 dark:bg-gray-600 dark:hover:bg-gray-700 text-gray-900 dark:text-gray-100 font-medium rounded-lg transition">
                            {{ __('Cancel') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
