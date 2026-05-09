<div @updated="$dispatch('name-updated', { name: $event.detail.name })">
    <x-ts-card :header="__('Edit Your Profile')">
        <form id="update-profile" wire:submit="save">
            <div class="space-y-6">
                <div>
                    <div class="mb-4">
                        @if ($user->photo_url)
                            <img
                            {{-- src="{{ $user->photo_url }}"  --}}
                            src="{{ asset($user->photo_url) }}"
                            alt="Avatar" class="w-20 h-20 rounded-full object-cover" />
                        @else
                            <div class="w-20 h-20 rounded-full bg-gray-300 flex items-center justify-center">
                                <span class="text-gray-600 text-sm">No Avatar</span>
                            </div>
                        @endif
                    </div>
                    <label class="block">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Avatar') }}</span>
                        <input type="file" wire:model="photo" accept="image/*" class="mt-2 block w-full text-sm text-gray-500
                            file:mr-4 file:py-2 file:px-4
                            file:rounded file:border-0
                            file:text-sm file:font-semibold
                            file:bg-blue-50 file:text-blue-700
                            hover:file:bg-blue-100" />
                        @error('photo')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </label>
                </div>
                <div>
                    <x-ts-input label="{{ __('Name') }} *" wire:model="user.name" required />
                </div>
                <div>
                    <x-ts-input label="{{ __('Email') }} *" value="{{ $user->email }}" disabled />
                </div>
                <div>
                    <x-ts-password :label="__('Password')"
                                :hint="__('The password will only be updated if you set the value of this field')"
                                wire:model="password"
                                rules
                                generator
                                x-on:generate="$wire.set('password_confirmation', $event.detail.password)" />
                </div>
                <div>
                    <x-ts-password :label="__('Confirm password')" wire:model="password_confirmation" rules />
                </div>
            </div>
            <x-slot:footer>
                <x-ts-button type="submit">
                    @lang('Save')
                </x-ts-button>
            </x-slot:footer>
        </form>
        <x-slot:footer>
            <div class="flex justify-end">
                <x-ts-button type="submit" form="update-profile">
                    @lang('Save')
                </x-ts-button>
            </div>
        </x-slot:footer>
    </x-ts-card>
</div>
