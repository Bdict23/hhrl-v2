<?php

declare(strict_types=1);

use App\Models\Suite\Amenity;
use App\Models\Suite\Room;
use App\Models\Suite\RoomCategory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use TallStackUi\Traits\Interactions;

new class () extends Component {
    use Interactions;
    use WithFileUploads;

    #[Locked]
    public int $roomId;

    public ?int $category_id = null;
    public string $room_number = '';
    public string $name = '';
    public ?string $description = null;
    public string $capacity_adults = '2';
    public string $capacity_children = '0';
    public bool $allows_pets = false;
    public string $pet_fee = '0';
    public string $base_price = '0';
    public ?string $floor_number = null;
    public string $status = 'available';
    public string $booking_type = 'both';
    public bool $is_active = true;
    public array $amenities = [];
    public array $photos = [];
    public ?int $photoToDeleteId = null;

    public function mount(int $id): void
    {
        $this->roomId = $id;
        $room = Room::query()->with('amenities')->findOrFail($id);

        $this->category_id = $room->category_id;
        $this->room_number = $room->room_number;
        $this->name = $room->name;
        $this->description = $room->description;
        $this->capacity_adults = (string) $room->capacity_adults;
        $this->capacity_children = (string) $room->capacity_children;
        $this->allows_pets = $room->allows_pets;
        $this->pet_fee = (string) $room->pet_fee;
        $this->base_price = (string) $room->base_price;
        $this->floor_number = $room->floor_number === null ? null : (string) $room->floor_number;
        $this->status = $room->status;
        $this->booking_type = $room->booking_type;
        $this->is_active = $room->is_active;
        $this->amenities = $room->amenities->modelKeys();
    }

    public function getRoomProperty(): Room
    {
        return Room::query()
            ->with(['category', 'amenities', 'photos'])
            ->findOrFail($this->roomId);
    }

    public function getCategoriesProperty(): EloquentCollection
    {
        return RoomCategory::query()
            ->where(function ($query): void {
                $query->where('is_active', true)
                    ->orWhere('id', $this->room->category_id);
            })
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

    private function storedImagePath(?string $imagePath): ?string
    {
        if (!$imagePath || str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
            $path = parse_url($imagePath, PHP_URL_PATH) ?: $imagePath;
            $storageUrlPath = parse_url(Storage::disk('public')->url(''), PHP_URL_PATH);

            if ($storageUrlPath && str_starts_with($path, rtrim($storageUrlPath, '/') . '/')) {
                return ltrim(substr($path, strlen(rtrim($storageUrlPath, '/')) + 1), '/');
            }

            return null;
        }

        if (str_starts_with($imagePath, 'assets/') || str_starts_with($imagePath, '/assets/')) {
            return null;
        }

        return ltrim($imagePath, '/');
    }

    protected function rules(): array
    {
        $currentRoom = Room::query()->findOrFail($this->roomId);
        $maxNewPhotos = max(0, 10 - $currentRoom->photos()->count());

        return [
            'category_id' => [
                'required',
                'integer',
                Rule::exists('room_categories', 'id')->where(
                    fn ($query) => $query->where(
                        fn ($categoryQuery) => $categoryQuery->where('is_active', true)
                            ->orWhere('id', $currentRoom->category_id)
                    )
                ),
            ],
            'room_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('rooms', 'room_number')->ignore($currentRoom->getKey()),
            ],
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
            'photos' => ['array', "max:{$maxNewPhotos}"],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function updateRoom(): void
    {
        $validated = $this->validate();
        $room = Room::query()->findOrFail($this->roomId);
        $attributes = [
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
        $existingPhotoCount = $room->photos()->count();
        $nextPhotoSortOrder = ((int) ($room->photos()->max('sort_order') ?? -1)) + 1;

        try {
            DB::transaction(function () use ($room, $attributes, $amenityIds, $existingPhotoCount, $nextPhotoSortOrder, &$storedPaths): void {
                $room->update($attributes);
                $room->amenities()->sync($amenityIds);

                foreach ($this->photos as $index => $photo) {
                    $path = $photo->store("rooms/{$room->id}", 'public');

                    if ($path === false) {
                        throw new \RuntimeException('The uploaded room photo could not be stored.');
                    }

                    $storedPaths[] = $path;
                    $room->photos()->create([
                        'path' => url(Storage::disk('public')->url($path)),
                        'sort_order' => $nextPhotoSortOrder + $index,
                        'is_cover' => $existingPhotoCount === 0 && $index === 0,
                    ]);
                }

                $room->update([
                    'gallery' => $room->photos()->pluck('path')->all(),
                ]);
            });
        } catch (\Throwable $exception) {
            if ($storedPaths !== []) {
                Storage::disk('public')->delete($storedPaths);
            }

            throw $exception;
        }

        $this->toast()->success('Success!', "Room {$room->room_number} updated successfully.")->send();
        $this->redirectRoute('room.show', ['id' => $room->id]);
    }

    public function removePhotoAction(int $photoId): void
    {
        $this->room->photos()->findOrFail($photoId);
        $this->photoToDeleteId = $photoId;

        $this->dialog()
            ->question('Remove Photo?', 'Are you sure you want to remove this photo?')
            ->confirm('Delete', 'destroyPhoto')
            ->cancel('Cancel')
            ->send();
    }

    public function destroyPhoto(): void
    {
        $photoId = $this->photoToDeleteId;

        if ($photoId === null) {
            abort(404);
        }

        $room = Room::query()->findOrFail($this->roomId);
        $photo = $room->photos()->findOrFail($photoId);
        $storagePath = $this->storedImagePath($photo->path);

        DB::transaction(function () use ($room, $photo): void {
            $wasCover = $photo->is_cover;
            $photo->delete();

            if ($wasCover) {
                $room->photos()->oldest('sort_order')->first()?->update(['is_cover' => true]);
            }

            $room->update([
                'gallery' => $room->photos()->pluck('path')->all(),
            ]);
        });

        if ($storagePath && ! Storage::disk('public')->delete($storagePath)) {
            Log::warning('Room photo was removed from the database but could not be deleted from storage.', [
                'room_id' => $room->id,
                'photo_path' => $storagePath,
            ]);

            throw new \RuntimeException('The photo record was removed, but its stored file could not be deleted.');
        }

        $this->photoToDeleteId = null;
        unset($this->room);
        $this->toast()->success('Success', 'Photo removed successfully.')->send();
    }
};
?>

<div>
    <x-slot:heading>Edit Room</x-slot:heading>
    <x-slot:subheading>{{ $this->room->room_number }} — {{ $this->room->name }}</x-slot:subheading>

    <div class="max-w-3xl">
        <form wire:submit="updateRoom" class="space-y-5">

            <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100">
                <h3 class="font-bold text-stone-900 mb-4">Basic Information</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-stone-700 mb-1.5">Room Number *</label>
                        <input type="text" wire:model="room_number" required
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
                            @foreach($this->categories as $cat)
                                <option wire:key="room-category-{{ $cat->id }}" value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-stone-700 mb-1.5">Booking Type *</label>
                        <select wire:model="booking_type" required class="w-full px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            @foreach(['both' => 'Overnight & Day Pass', 'overnight' => 'Overnight Only', 'day_pass' => 'Day Pass Only'] as $val => $label)
                                <option wire:key="booking-type-{{ $val }}" value="{{ $val }}">{{ $label }}</option>
                            @endforeach
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
                        <label class="block text-sm font-medium text-stone-700 mb-1.5">Status *</label>
                        <select wire:model="status" required class="w-full px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            @foreach(['available', 'occupied', 'maintenance', 'housekeeping', 'blocked', 'out_of_service'] as $s)
                                <option wire:key="room-status-{{ $s }}" value="{{ $s }}">{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                            @endforeach
                        </select>
                        @error('status')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-stone-700 mb-1.5">Floor</label>
                        <input type="number" wire:model="floor_number" min="0"
                               class="w-full px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        @error('floor_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-4 p-4 bg-amber-50 rounded-xl border border-amber-100">
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
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="is_active"
                               class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500">
                        <span class="text-sm font-medium text-stone-700">Active</span>
                    </label>
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

            <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h3 class="font-bold text-stone-900">Room Photos</h3>
                        <p class="text-xs text-stone-500 mt-0.5">Manage existing photos or add new ones.</p>
                    </div>
                    @if($this->room->photos->isNotEmpty())
                        <span class="px-2.5 py-1 bg-stone-100 text-stone-700 rounded-lg text-xs font-bold">
                            {{ $this->room->photos->count() }} {{ $this->room->photos->count() === 1 ? 'photo' : 'photos' }}
                        </span>
                    @endif
                </div>

                {{-- Existing photos --}}
                @if($this->room->photos->isNotEmpty())
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3.5 mb-6">
                        @foreach($this->room->photos->unique('path') as $photo)
                            <div wire:key="room-photo-{{ $photo->id }}" class="relative bg-stone-50 rounded-2xl p-2 border border-stone-200 flex flex-col items-center shadow-xs hover:shadow-md transition-shadow">
                                <div class="relative w-full h-24 rounded-xl overflow-hidden bg-stone-200">
                                    <img src="{{ $photo->path }}" alt="Room photo"
                                         class="w-full h-full object-cover">

                                    @if($photo->is_cover)
                                        <span class="absolute bottom-1.5 left-1.5 px-2 py-0.5 bg-emerald-600 text-white text-[10px] font-bold rounded-full shadow">
                                            Cover
                                        </span>
                                    @endif
                                </div>

                                <button type="button"
                                        wire:click="removePhotoAction({{ $photo->id }})"
                                        wire:loading.attr="disabled"
                                        class="w-full mt-2 py-1 text-[11px] font-bold text-red-600 hover:text-white hover:bg-red-600 border border-red-200 hover:border-red-600 rounded-lg transition-colors flex items-center justify-center gap-1 cursor-pointer">
                                    <span>✕</span>
                                    <span>Remove</span>
                                </button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-6 mb-6 rounded-2xl bg-stone-50 border border-dashed border-stone-200 text-center text-xs text-stone-400">
                        📷 No photos uploaded for this room yet. Add your first photo below!
                    </div>
                @endif

                {{-- Upload new photos with dynamic live preview --}}
                <div class="p-4 bg-stone-50/70 border border-stone-200 rounded-2xl"
                     x-data="{
                         newPreviews: [],
                         handleFiles(event) {
                             this.newPreviews.forEach(preview => URL.revokeObjectURL(preview.url));
                             const files = Array.from(event.target.files);
                             this.newPreviews = files.map(file => ({
                                 name: file.name,
                                 url: URL.createObjectURL(file),
                                 size: (file.size / 1024).toFixed(1) + ' KB'
                             }));
                         },
                         clearPreviews() {
                             this.newPreviews.forEach(preview => URL.revokeObjectURL(preview.url));
                             this.newPreviews = [];
                             $refs.fileInput.value = '';
                             $wire.clearPhotos();
                         }
                     }">
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold text-stone-800 uppercase tracking-wider">
                            ➕ Upload New Photos
                        </label>
                        <template x-if="newPreviews.length > 0">
                            <span class="text-xs font-semibold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-lg"
                                  x-text="newPreviews.length + (newPreviews.length === 1 ? ' new photo selected' : ' new photos selected')"></span>
                        </template>
                    </div>
                    <input type="file" wire:model="photos" multiple accept="image/jpeg,image/png,image/webp" x-ref="fileInput" @change="handleFiles($event)"
                           class="w-full text-sm text-stone-600 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-600 file:text-white hover:file:bg-emerald-700 file:cursor-pointer cursor-pointer">
                    <p class="text-[11px] text-stone-400 mt-2">
                        Select JPG, PNG, or WebP photos, up to 10 photos total (max 5MB each).
                    </p>
                    @error('photos')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    @error('photos.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror

                    {{-- Live Image Preview Grid --}}
                    <div x-show="newPreviews.length > 0" class="mt-4 pt-4 border-t border-stone-200">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold text-stone-700 uppercase tracking-wide">New Photos to Upload:</span>
                            <button type="button" @click="clearPreviews()" class="text-xs text-red-500 hover:text-red-700 font-semibold cursor-pointer">Clear selection</button>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                            <template x-for="(preview, index) in newPreviews" :key="index">
                                <div class="relative bg-white rounded-xl overflow-hidden border border-stone-200 aspect-square group shadow-xs">
                                    <img :src="preview.url" :alt="preview.name" class="w-full h-full object-cover">
                                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col justify-end p-2 text-[10px] text-white">
                                        <span class="truncate font-medium" x-text="preview.name"></span>
                                        <span class="text-stone-300" x-text="preview.size"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            @if($errors->any())
                <div class="p-4 bg-red-50 border border-red-200 rounded-2xl">
                    @foreach($errors->all() as $error)
                        <p wire:key="room-edit-error-{{ $loop->index }}" class="text-sm text-red-700">• {{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="flex items-center gap-3">
                <button type="submit" wire:loading.attr="disabled" wire:target="updateRoom"
                        class="px-8 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-sm transition-colors disabled:opacity-60">
                    <span wire:loading.remove wire:target="updateRoom">Save Changes</span>
                    <span wire:loading wire:target="updateRoom">Saving...</span>
                </button>
                <a href="{{ route('room.show', $this->room->id) }}" class="px-6 py-3 border border-stone-200 text-stone-600 rounded-xl text-sm hover:bg-stone-50 transition-colors">Cancel</a>
            </div>
        </form>
    </div>
</div>