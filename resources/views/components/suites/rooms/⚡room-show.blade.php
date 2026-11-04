<?php

use Livewire\Component;
use App\Models\Suite\Room;
use Illuminate\Support\Facades\Storage;


new class extends Component

{
    public $room;


    public function mount(int $id)
    {
       $room = Room::find($id);
        $room->load(['category', 'amenities', 'photos', 'bookings' => fn ($q) => $q->latest()->take(10)]);
        $this->room = $room;
        
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

    public function destroyPhoto(Room $room, RoomPhoto $photo): RedirectResponse
    {
        if ($photo->room_id !== $room->id) {
            abort(404);
        }

        // Remove from storage
        $storagePath = $this->storedImagePath($photo->path);
        if ($storagePath) {
            Storage::disk('public')->delete($storagePath);
        }

        $photo->delete();

        // Keep gallery array strictly in sync with actual photos
        $room->update(['gallery' => $room->photos()->pluck('path')->toArray()]);

        return back()->with('success', 'Photo removed successfully.');
    }
    
};
?>

<div>
    <x-slot:heading>{{ $room->name }}</x-slot:heading>
    <x-slot:subheading>Room {{ $room->room_number }}</x-slot:subheading>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-5">
            {{-- Photos --}}
            @php
                $uniquePhotos = $room->photos->unique('path')->values();
            @endphp
            @if($uniquePhotos->isNotEmpty())
                <div class="bg-white rounded-2xl overflow-hidden shadow-sm border border-stone-100">
                    <div x-data="{ active: 0 }" class="relative aspect-video bg-stone-100">
                        @foreach($uniquePhotos as $i => $photo)
                            <img x-show="active === {{ $i }}" src="{{ $photo->path }}"
                                 alt="{{ $room->name }}" class="absolute inset-0 w-full h-full object-cover">
                        @endforeach
                        @if($uniquePhotos->count() > 1)
                            <div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-2">
                                @foreach($uniquePhotos as $i => $photo)
                                    <button @click="active = {{ $i }}"
                                            :class="active === {{ $i }} ? 'bg-white w-6' : 'bg-white/50 w-2'"
                                            class="h-2 rounded-full transition-all"></button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Details --}}
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100">
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <p class="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-3">Room Info</p>
                        <div class="space-y-2 text-sm">
                            <div><span class="text-stone-500">Category:</span> <span class="font-medium">{{ $room->category?->name }}</span></div>
                            <div><span class="text-stone-500">Booking Type:</span> <span class="font-medium capitalize">{{ str_replace('_', ' ', $room->booking_type) }}</span></div>
                            <div><span class="text-stone-500">Status:</span>
                                <span class="ml-1 px-2 py-0.5 rounded text-xs font-medium
                                    {{ $room->status === 'available' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                    {{ ucfirst($room->status) }}
                                </span>
                            </div>
                            <div><span class="text-stone-500">Active:</span> <span class="font-medium">{{ $room->is_active ? 'Yes' : 'No' }}</span></div>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-3">Capacity & Pricing</p>
                        <div class="space-y-2 text-sm">
                            <div><span class="text-stone-500">Adults:</span> <span class="font-medium">{{ $room->capacity_adults }}</span></div>
                            <div><span class="text-stone-500">Children:</span> <span class="font-medium">{{ $room->capacity_children }}</span></div>
                            <div><span class="text-stone-500">Base Price:</span> <span class="font-bold text-emerald-600">₱{{ number_format($room->base_price, 2) }}/night</span></div>
                            @if($room->allows_pets)
                                <div><span class="text-stone-500">Pet Fee:</span> <span class="font-medium">₱{{ number_format($room->pet_fee, 2) }}/night</span></div>
                            @endif
                        </div>
                    </div>
                </div>
                @if($room->description)
                    <div class="mt-4 p-4 bg-stone-50 rounded-xl">
                        <p class="text-sm text-stone-700">{{ $room->description }}</p>
                    </div>
                @endif
            </div>

            {{-- Amenities --}}
            @if($room->amenities->isNotEmpty())
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100">
                    <h3 class="font-bold text-stone-900 mb-3">Amenities</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach($room->amenities as $amenity)
                            <span class="px-3 py-1.5 bg-emerald-50 text-emerald-700 text-sm rounded-xl font-medium">✓ {{ $amenity->name }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Recent Bookings --}}
            @if($room->bookings->isNotEmpty())
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100">
                    <h3 class="font-bold text-stone-900 mb-4">Recent Bookings</h3>
                    <div class="space-y-2">
                        @foreach($room->bookings as $booking)
                            {{-- <a href="{{ route('admin.bookings.show', $booking) }}" class="flex items-center justify-between p-3 rounded-xl border border-stone-100 hover:border-emerald-200 transition-colors"> --}}
                                <div>
                                    <p class="font-mono text-xs text-emerald-600 font-semibold">{{ $booking->booking_code }}</p>
                                    <p class="text-sm font-medium text-stone-900">{{ $booking->guest_name }}</p>
                                    <p class="text-xs text-stone-400">{{ $booking->check_in->format('M d') }} – {{ $booking->check_out->format('M d, Y') }}</p>
                                </div>
                                <span class="text-sm font-bold text-stone-600">₱{{ number_format($booking->total_price, 0) }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Actions --}}
        <div>
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100">
                <h3 class="font-bold text-stone-900 mb-4">Actions</h3>
                <div class="space-y-2">
                    <a href="{{ route('room.edit', $room->id) }}"
                       class="block w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-sm text-center transition-colors">
                        ✏️ Edit Room
                    </a>
                    {{-- <a href="{{ route('admin.bookings.create', ['room_id' => $room->id]) }}"
                       class="block w-full py-2.5 bg-blue-100 hover:bg-blue-200 text-blue-800 font-semibold rounded-xl text-sm text-center transition-colors">
                        + New Booking
                    </a> --}}
                    {{-- <form method="POST" action="{{ route('admin.rooms.destroy', $room) }}"
                          onsubmit="return confirm('Delete this room?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="w-full py-2.5 bg-red-50 hover:bg-red-100 text-red-700 font-semibold rounded-xl text-sm transition-colors border border-red-200">
                            🗑️ Delete Room
                        </button>
                    </form> --}}
                    {{-- <a href="{{ route('admin.rooms.index') }}"
                       class="block w-full py-2.5 text-center text-stone-500 hover:text-stone-700 text-sm font-medium">
                        ← Back to Rooms
                    </a> --}}
                </div>
            </div>
        </div>
    </div>
</div>