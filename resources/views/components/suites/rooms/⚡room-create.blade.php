<?php

declare(strict_types=1);

use App\Models\Suite\Amenity;
use App\Models\Suite\Room;
use App\Models\Suite\RoomCategory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use TallStackUi\Traits\Interactions;

new class () extends Component {
    use Interactions;
    use WithFileUploads;

    public ?int $category_id = null;
    public string $room_number = '';
    public string $name = '';
    public ?string $description = null;
    public string $capacity_adults = '2';
    public string $capacity_children = '0';
    public bool $allows_pets = false;
    public string $pet_fee = '0';
    public string $base_price = '2500';
    public ?string $floor_number = '1';
    public string $status = 'available';
    public string $booking_type = 'both';
    public bool $is_active = true;
    public array $amenities = [];
    public array $photos = [];

    public function getCategoriesProperty(): EloquentCollection
    {
        return RoomCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function getAmenityOptionsProperty(): EloquentCollection
    {
        return Amenity::query()->orderBy('name')->get();
    }

    public function clearPhotos(): void
    {
        $this->reset('photos');
    }

    protected function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('room_categories', 'id')->where('is_active', true)],
            'room_number' => ['required', 'string', 'max:255', Rule::unique('rooms', 'room_number')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'capacity_adults' => ['required', 'integer', 'min:1', 'max:65535'],
            'capacity_children' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'allows_pets' => ['boolean'],
            'pet_fee' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'base_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'floor_number' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'status' => ['required', Rule::in(['available', 'occupied', 'maintenance', 'housekeeping', 'blocked', 'out_of_service'])],
            'booking_type' => ['required', Rule::in(['overnight', 'day_pass', 'both'])],
            'is_active' => ['boolean'],
            'amenities' => ['array'],
            'amenities.*' => ['integer', 'distinct', 'exists:room_amenities,id'],
            'photos' => ['array', 'max:10'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function createRoom(): void
    {
        $validated = $this->validate();
        $roomAttributes = [
            'category_id' => $validated['category_id'],
            'room_number' => $validated['room_number'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'capacity_adults' => (int) $validated['capacity_adults'],
            'capacity_children' => filled($validated['capacity_children'] ?? null)
                ? (int) $validated['capacity_children']
                : 0,
            'allows_pets' => $validated['allows_pets'] ?? false,
            'pet_fee' => (float) $validated['pet_fee'],
            'base_price' => (float) $validated['base_price'],
            'floor_number' => filled($validated['floor_number'] ?? null)
                ? (int) $validated['floor_number']
                : null,
            'status' => $validated['status'],
            'booking_type' => $validated['booking_type'],
            'is_active' => $validated['is_active'] ?? false,
        ];
        $amenityIds = $validated['amenities'] ?? [];
        $storedPaths = [];

        try {
            $room = DB::transaction(function () use ($roomAttributes, $amenityIds, &$storedPaths): Room {
                $room = Room::create($roomAttributes);
                $room->amenities()->sync($amenityIds);

                foreach ($this->photos as $index => $photo) {
                    $path = $photo->store("rooms/{$room->id}", 'public');

                    if ($path === false) {
                        throw new \RuntimeException('The uploaded room photo could not be stored.');
                    }

                    $storedPaths[] = $path;
                    $room->photos()->create([
                        'path' => url(Storage::disk('public')->url($path)),
                        'sort_order' => $index,
                        'is_cover' => $index === 0,
                    ]);
                }

                $room->update([
                    'gallery' => $room->photos()->pluck('path')->all(),
                ]);

                return $room;
            });
        } catch (\Throwable $exception) {
            if ($storedPaths !== []) {
                Storage::disk('public')->delete($storedPaths);
            }

            throw $exception;
        }

        $this->toast()->success('Success!', "Room {$room->room_number} created successfully.")->send();
        $this->redirectRoute('rooms.list');
    }
};
?>

<div>
    <x-slot:heading>Add New Room</x-slot:heading>
    <x-slot:subheading>Create a new room or accommodation unit</x-slot:subheading>

    <div class="max-w-3xl">
        <form wire:submit="createRoom" class="space-y-5">

            <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100">
                <h3 class="font-bold text-stone-900 mb-4">Basic Information</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-stone-700 mb-1.5">Room Number *</label>
                        <input type="text" wire:model="room_number" required placeholder="e.g. DR-01"
                               class="w-full px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 @error('room_number') border-red-400 @enderror">
                        @error('room_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-stone-700 mb-1.5">Room Name *</label>
                        <input type="text" wire:model="name" required
                               class="w-full px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 @error('name') border-red-400 @enderror">
                        @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-stone-700 mb-1.5">Category *</label>
                        <select wire:model="category_id" required class="w-full px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            <option value="">— Select Category —</option>
                            @foreach($this->categories as $cat)
                                <option wire:key="room-category-{{ $cat->id }}" value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-stone-700 mb-1.5">Booking Type *</label>
                        <select wire:model="booking_type" required class="w-full px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            <option value="both">Overnight & Day Pass</option>
                            <option value="overnight">Overnight Only</option>
                            <option value="day_pass">Day Pass Only</option>
                        </select>
                        @error('booking_type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-stone-700 mb-1.5">Description</label>
                        <textarea wire:model="description" rows="3" class="w-full px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 resize-none"></textarea>
                        @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100">
                <h3 class="font-bold text-stone-900 mb-4">Capacity & Pricing</h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-stone-700 mb-1.5">Adults *</label>
                        <input type="number" wire:model="capacity_adults" min="1" required
                               class="w-full px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        @error('capacity_adults')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-stone-700 mb-1.5">Children</label>
                        <input type="number" wire:model="capacity_children" min="0"
                               class="w-full px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        @error('capacity_children')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-stone-700 mb-1.5">Base Price (₱) *</label>
                        <input type="number" wire:model="base_price" min="0" step="0.01" required
                               class="w-full px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        @error('base_price')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-stone-700 mb-1.5">Floor</label>
                        <input type="number" wire:model="floor_number" min="0"
                               class="w-full px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        @error('floor_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="mt-4 p-4 bg-amber-50 rounded-xl border border-amber-100">
                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model.live="allows_pets"
                                   class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500">
                            <span class="font-medium text-stone-900 text-sm">🐾 Allow Pets</span>
                        </label>
                        @if($allows_pets)
                            <div class="flex-1">
                                <input type="number" wire:model="pet_fee" min="0" step="0.01"
                                       placeholder="Pet fee per night (₱)"
                                       class="w-full px-3 py-2 border border-amber-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-white">
                                @error('pet_fee')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100">
                <h3 class="font-bold text-stone-900 mb-4">Amenities</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    @foreach($this->amenityOptions as $amenity)
                        <label wire:key="amenity-{{ $amenity->id }}" class="flex items-center gap-2 cursor-pointer p-2 rounded-lg hover:bg-stone-50 transition-colors">
                            <input type="checkbox" wire:model="amenities" value="{{ $amenity->id }}"
                                   class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500">
                            <span class="text-sm text-stone-700">{{ $amenity->name }}</span>
                        </label>
                    @endforeach
                </div>
                @error('amenities')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                @error('amenities.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100"
                 x-data="{
                     previews: [],
                     handleFiles(event) {
                         this.previews.forEach(preview => URL.revokeObjectURL(preview.url));
                         const files = Array.from(event.target.files);
                         this.previews = files.map(file => ({
                             name: file.name,
                             url: URL.createObjectURL(file),
                             size: (file.size / 1024).toFixed(1) + ' KB'
                         }));
                     },
                     clearPreviews() {
                         this.previews.forEach(preview => URL.revokeObjectURL(preview.url));
                         this.previews = [];
                         $refs.fileInput.value = '';
                         $wire.clearPhotos();
                     }
                 }">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-stone-900">Photos</h3>
                    <template x-if="previews.length > 0">
                        <span class="text-xs font-semibold text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-lg"
                              x-text="previews.length + (previews.length === 1 ? ' photo selected' : ' photos selected')"></span>
                    </template>
                </div>
                <input type="file" wire:model="photos" multiple accept="image/jpeg,image/png,image/webp" x-ref="fileInput" @change="handleFiles($event)"
                       class="w-full text-sm text-stone-600 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                <p class="text-xs text-stone-400 mt-2">First photo will be the cover image. Select up to 10 JPG, PNG, or WebP photos (max 5MB each).</p>
                @error('photos')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                @error('photos.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror

                {{-- Live Image Preview Grid --}}
                <div x-show="previews.length > 0" class="mt-4 pt-4 border-t border-stone-100">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-stone-700 uppercase tracking-wide">Selected Image Previews:</span>
                        <button type="button" @click="clearPreviews()" class="text-xs text-red-500 hover:text-red-700 font-semibold cursor-pointer">Clear selection</button>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                        <template x-for="(preview, index) in previews" :key="index">
                            <div class="relative bg-stone-50 rounded-xl overflow-hidden border border-stone-200 aspect-square group shadow-xs">
                                <img :src="preview.url" :alt="preview.name" class="w-full h-full object-cover">
                                <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col justify-end p-2 text-[10px] text-white">
                                    <span class="truncate font-medium" x-text="preview.name"></span>
                                    <span class="text-stone-300" x-text="preview.size"></span>
                                </div>
                                <template x-if="index === 0">
                                    <span class="absolute top-1.5 left-1.5 bg-emerald-600 text-white text-[9px] font-bold px-1.5 py-0.5 rounded shadow">Cover</span>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100">
                <h3 class="font-bold text-stone-900 mb-4">Status</h3>
                <div class="flex items-center gap-6">
                    <select wire:model="status" class="px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        @foreach(['available', 'occupied', 'maintenance', 'housekeeping', 'blocked', 'out_of_service'] as $s)
                            <option wire:key="room-status-{{ $s }}" value="{{ $s }}">{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                        @endforeach
                    </select>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="is_active" class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500">
                        <span class="text-sm font-medium text-stone-700">Active (visible to guests)</span>
                    </label>
                </div>
                @error('status')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            @if($errors->any())
                <div class="p-4 bg-red-50 border border-red-200 rounded-2xl">
                    @foreach($errors->all() as $error)
                        <p wire:key="room-error-{{ $loop->index }}" class="text-sm text-red-700">• {{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="flex items-center gap-3">
                <button type="submit" wire:loading.attr="disabled" wire:target="createRoom"
                        class="px-8 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-sm transition-colors shadow-lg shadow-emerald-200 disabled:opacity-60">
                    <span wire:loading.remove wire:target="createRoom">Create Room</span>
                    <span wire:loading wire:target="createRoom">Creating...</span>
                </button>
                <a href="{{ route('rooms.list') }}" class="px-6 py-3 border border-stone-200 text-stone-600 rounded-xl text-sm hover:bg-stone-50 transition-colors">Cancel</a>
            </div>
        </form>
    </div>

</div>