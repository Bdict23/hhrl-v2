<?php

use Livewire\Component;

new class extends Component
{
    
};
?>

<div>
    <x-slot:heading>Add New Room</x-slot:heading>
    <x-slot:subheading>Create a new room or accommodation unit</x-slot:subheading>

    <div class="max-w-3xl">
        <form method="POST" action="{{ route('admin.rooms.store') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf

            <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100">
                <h3 class="font-bold text-stone-900 mb-4">Basic Information</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-stone-700 mb-1.5">Room Number *</label>
                        <input type="text" name="room_number" value="{{ old('room_number') }}" required placeholder="e.g. DR-01"
                               class="w-full px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 @error('room_number') border-red-400 @enderror">
                        @error('room_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-stone-700 mb-1.5">Room Name *</label>
                        <input type="text" name="name" value="{{ old('name') }}" required
                               class="w-full px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 @error('name') border-red-400 @enderror">
                        @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-stone-700 mb-1.5">Category *</label>
                        <select name="category_id" required class="w-full px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            <option value="">— Select Category —</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-stone-700 mb-1.5">Booking Type *</label>
                        <select name="booking_type" required class="w-full px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            <option value="both" {{ old('booking_type') === 'both' ? 'selected' : '' }}>Overnight & Day Pass</option>
                            <option value="overnight" {{ old('booking_type') === 'overnight' ? 'selected' : '' }}>Overnight Only</option>
                            <option value="day_pass" {{ old('booking_type') === 'day_pass' ? 'selected' : '' }}>Day Pass Only</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-stone-700 mb-1.5">Description</label>
                        <textarea name="description" rows="3" class="w-full px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 resize-none">{{ old('description') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100">
                <h3 class="font-bold text-stone-900 mb-4">Capacity & Pricing</h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-stone-700 mb-1.5">Adults *</label>
                        <input type="number" name="capacity_adults" value="{{ old('capacity_adults', 2) }}" min="1" required
                               class="w-full px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-stone-700 mb-1.5">Children</label>
                        <input type="number" name="capacity_children" value="{{ old('capacity_children', 0) }}" min="0"
                               class="w-full px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-stone-700 mb-1.5">Base Price (₱) *</label>
                        <input type="number" name="base_price" value="{{ old('base_price', 2500) }}" min="0" step="0.01" required
                               class="w-full px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-stone-700 mb-1.5">Floor</label>
                        <input type="number" name="floor_number" value="{{ old('floor_number', 1) }}" min="0"
                               class="w-full px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>

                <div class="mt-4 p-4 bg-amber-50 rounded-xl border border-amber-100">
                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="allows_pets" value="1" {{ old('allows_pets') ? 'checked' : '' }}
                                   x-model="allowsPets" class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500">
                            <span class="font-medium text-stone-900 text-sm">🐾 Allow Pets</span>
                        </label>
                        <div x-show="allowsPets" x-data="{ allowsPets: {{ old('allows_pets') ? 'true' : 'false' }} }" class="flex-1">
                            <input type="number" name="pet_fee" value="{{ old('pet_fee', 300) }}" min="0" step="0.01"
                                   placeholder="Pet fee per night (₱)"
                                   class="w-full px-3 py-2 border border-amber-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-white">
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100">
                <h3 class="font-bold text-stone-900 mb-4">Amenities</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    @foreach($amenities as $amenity)
                        <label class="flex items-center gap-2 cursor-pointer p-2 rounded-lg hover:bg-stone-50 transition-colors">
                            <input type="checkbox" name="amenities[]" value="{{ $amenity->id }}"
                                   {{ in_array($amenity->id, old('amenities', [])) ? 'checked' : '' }}
                                   class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500">
                            <span class="text-sm text-stone-700">{{ $amenity->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100">
                <h3 class="font-bold text-stone-900 mb-4">Photos</h3>
                <input type="file" name="photos[]" multiple accept="image/*"
                       class="w-full text-sm text-stone-600 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                <p class="text-xs text-stone-400 mt-2">First photo will be the cover image. Max 5MB per photo.</p>
            </div>

            <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100">
                <h3 class="font-bold text-stone-900 mb-4">Status</h3>
                <div class="flex items-center gap-6">
                    <select name="status" class="px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        @foreach(['available', 'occupied', 'maintenance', 'housekeeping', 'blocked'] as $s)
                            <option value="{{ $s }}" {{ old('status', 'available') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500">
                        <span class="text-sm font-medium text-stone-700">Active (visible to guests)</span>
                    </label>
                </div>
            </div>

            @if($errors->any())
                <div class="p-4 bg-red-50 border border-red-200 rounded-2xl">
                    @foreach($errors->all() as $error)<p class="text-sm text-red-700">• {{ $error }}</p>@endforeach
                </div>
            @endif

            <div class="flex items-center gap-3">
                <button type="submit" class="px-8 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-sm transition-colors shadow-lg shadow-emerald-200">
                    Create Room
                </button>
                <a href="{{ route('admin.rooms.index') }}" class="px-6 py-3 border border-stone-200 text-stone-600 rounded-xl text-sm hover:bg-stone-50 transition-colors">Cancel</a>
            </div>
        </form>
    </div>
</div>