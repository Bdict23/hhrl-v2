<x-guest-layout>
    <div class="my-6 flex items-center justify-center">
        <img src="{{ asset('/assets/images/tsui.png') }}" />
    </div>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="space-y-4">
            <x-ts-input label="Email *" type="email" name="email" :value="old('email', '')" required autofocus autocomplete="username" />

            <x-ts-password label="Password *" type="password" name="password" required autocomplete="current-password" />
        </div>

        <div class="block mt-4">
            <x-ts-checkbox label="Remember me" id="remember_me" type="checkbox" name="remember" />
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('register'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md" href="{{ route('register') }}">
                    {{ __('Sign up') }}
                </a>
            @endif

            <x-ts-button type="submit" class="ms-3">
                {{ __('Log in') }}
            </x-ts-button>
        </div>
    </form>
</x-guest-layout>
