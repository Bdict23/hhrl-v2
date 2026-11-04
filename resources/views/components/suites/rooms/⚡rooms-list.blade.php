<?php

use App\Models\Suite\Room;
use Livewire\WithPagination;
use TallStackUi\Traits\Interactions;
use App\Models\Suite\RoomCategory;
use Livewire\Component;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;

new class extends Component {
    use WithPagination;
    use Interactions;


    public string $search = '';
    public ?int $categoryId = null;
    public string $statusFilter = '';
    public ?string $feedbackMessage = null;

    public function updateRoomStatus(int $roomId, string $newStatus): void
    {
        $room = Room::find($roomId);
        if ($room && in_array($newStatus, ['available', 'occupied', 'maintenance', 'housekeeping', 'out_of_service', 'blocked'])) {
            $room->update(['status' => $newStatus]);
            $this->feedbackMessage = "Room {$room->room_number} status updated to ".ucfirst(str_replace('_', ' ', $newStatus)).".";
        }
    }

    public function getCategoriesProperty(): EloquentCollection
    {
        return RoomCategory::where('is_active', true)->orderBy('name')->get();
    }

    // public function getRoomsProperty(): EloquentCollection
    // {
    //     return Room::query()
    //         ->with(['category'])
    //         ->when(filled($this->search), function ($q) {
    //             $term = '%'.trim($this->search).'%';
    //             $q->where(function ($sub) use ($term) {
    //                 $sub->where('name', 'like', $term)
    //                     ->orWhere('room_number', 'like', $term);
    //             });
    //         })
    //         ->when($this->categoryId, fn ($q) => $q->where('category_id', $this->categoryId))
    //         ->when(filled($this->statusFilter), fn ($q) => $q->where('status', $this->statusFilter))
    //         ->orderBy('room_number')
    //         ->paginate(10);
    // }

    public function with() :array
    {
        return [
            'roomListHeader' => [
                ['index' => 'status', 'label' => 'status', 'sortable' => false], 
                ['index' => 'room_number', 'label' => 'room #', 'sortable' => false], 
                ['index' => 'name', 'label' => 'Name', 'sortable' => false], 
                ['index' => 'category_name', 'label' => 'category', 'sortable' => false], 
                ['index' => 'capacity', 'label' => 'capacity', 'sortable' => false], 
                ['index' => 'base_price', 'label' => 'price', 'sortable' => false], 
                ['index' => 'allows_pets', 'label' => 'pets'], 
                ['index' => 'action', 'label' => 'action']],
            'roomsRows' => Room::query()
            ->with(['category'])
            ->when(filled($this->search), function ($q) {
                $term = '%'.trim($this->search).'%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('name', 'like', $term)
                        ->orWhere('room_number', 'like', $term);
                });
            })
            ->when($this->categoryId, fn ($q) => $q->where('category_id', $this->categoryId))
            ->when(filled($this->statusFilter), fn ($q) => $q->where('status', $this->statusFilter))
            ->orderBy('room_number')
            ->paginate(10),
    

        ];

    }
}; ?>

<div>
    {{-- Search and Filter Controls --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div class="flex flex-wrap items-center gap-2">
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   placeholder="Search room # or name..."
                   class="px-3 py-2 bg-white border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 shadow-sm">

            <select wire:model.live="categoryId"
                    class="px-3 py-2 bg-white border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 shadow-sm">
                <option value="">All Categories</option>
                @foreach($this->categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="statusFilter"
                    class="px-3 py-2 bg-white border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 shadow-sm">
                <option value="">All Statuses</option>
                <option value="available">Available</option>
                <option value="occupied">Occupied</option>
                <option value="housekeeping">Housekeeping</option>
                <option value="maintenance">Maintenance</option>
                <option value="out_of_service">Out of Service</option>
                <option value="blocked">Blocked</option>
            </select>
        </div>

        <a href="{{ route('room.create') }}"
           class="px-5 py-2.5 bg-emerald-600 text-white text-sm font-semibold rounded-xl hover:bg-emerald-700 transition-colors shadow-sm self-start sm:self-auto">
            + Add Room
        </a>
    </div>

    @if($feedbackMessage)
        <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-semibold flex items-center justify-between">
            <span>✅ {{ $feedbackMessage }}</span>
            <button wire:click="$set('feedbackMessage', null)" class="text-emerald-600 hover:text-emerald-900 font-bold">✕</button>
        </div>
    @endif

    <div class="bg-white rounded-2xl shadow-sm border border-stone-100 overflow-hidden">
        <div class="overflow-x-auto">
            {{-- <table class="w-full text-sm">
                <thead class="bg-stone-50 border-b border-stone-200">
                    <tr>
                        @foreach(['Room #', 'Name', 'Category', 'Capacity', 'Price', 'Status', 'Pets', 'Actions'] as $h)
                            <th class="px-4 py-3 text-left text-xs font-semibold text-stone-500 uppercase tracking-wide">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-50">
                    @forelse($this->rooms as $room)
                        <tr class="hover:bg-stone-50/50 transition-colors">
                            <td class="px-4 py-4 font-mono font-bold text-stone-800">{{ $room->room_number }}</td>
                            <td class="px-4 py-4">
                                <div class="font-medium text-stone-900">{{ $room->name }}</div>
                                @if(!$room->is_active)
                                    <span class="text-xs text-stone-400">Inactive</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-stone-600">{{ $room->category?->name }}</td>
                            <td class="px-4 py-4 text-stone-600">
                                {{ $room->capacity_adults }}A @if($room->capacity_children > 0){{ $room->capacity_children }}C @endif
                            </td>
                            <td class="px-4 py-4 font-semibold text-stone-900">₱{{ number_format($room->base_price, 0) }}/night</td>
                            <td class="px-4 py-4">
                                @php
                                    $statusColors = [
                                        'available' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                                        'occupied' => 'bg-red-100 text-red-800 border-red-300',
                                        'maintenance' => 'bg-amber-100 text-amber-800 border-amber-300',
                                        'housekeeping' => 'bg-purple-100 text-purple-800 border-purple-300',
                                        'out_of_service' => 'bg-stone-200 text-stone-800 border-stone-400',
                                        'blocked' => 'bg-stone-300 text-stone-700 border-stone-400',
                                    ];
                                @endphp
                                <select wire:change="updateRoomStatus({{ $room->id }}, $event.target.value)"
                                        class="text-xs font-semibold rounded-lg border py-1 pl-2.5 pr-7 cursor-pointer transition-colors focus:ring-2 focus:ring-emerald-500 {{ $statusColors[$room->status] ?? 'bg-stone-100 text-stone-700 border-stone-200' }}">
                                    <option value="available" @selected($room->status === 'available')>Available</option>
                                    <option value="occupied" @selected($room->status === 'occupied')>Occupied</option>
                                    <option value="maintenance" @selected($room->status === 'maintenance')>Maintenance</option>
                                    <option value="housekeeping" @selected($room->status === 'housekeeping')>Housekeeping</option>
                                    <option value="out_of_service" @selected($room->status === 'out_of_service')>Out of Service</option>
                                    <option value="blocked" @selected($room->status === 'blocked')>Blocked</option>
                                </select>
                                <form method="POST" action="{{ route('admin.rooms.quick-status', $room) }}" class="hidden">
                                    @csrf
                                </form>
                            </td>
                            <td class="px-4 py-4">
                                @if($room->allows_pets)
                                    <span class="text-lg" title="Pet-friendly — ₱{{ number_format($room->pet_fee, 0) }}/night">🐾</span>
                                @else
                                    <span class="text-stone-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-1">
                                    <a href="{{ route('admin.rooms.show', $room) }}"
                                       class="px-2.5 py-1.5 bg-stone-100 hover:bg-stone-200 text-stone-700 text-xs font-medium rounded-lg transition-colors">View</a>
                                     <a href="{{ route('admin.rooms.edit', $room) }}"
                                       class="px-2.5 py-1.5 bg-emerald-100 hover:bg-emerald-200 text-emerald-700 text-xs font-medium rounded-lg transition-colors">Edit</a> 
                                    <form method="POST" action="{{ route('admin.rooms.destroy', $room) }}"
                                          onsubmit="return confirm('Delete room {{ $room->room_number }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="px-2.5 py-1.5 bg-red-100 hover:bg-red-200 text-red-700 text-xs font-medium rounded-lg transition-colors">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-16 text-center text-stone-400">
                                <div class="text-4xl mb-3">🏨</div>
                                <p class="font-medium">No matching rooms found</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table> --}}
            <x-ts-table :headers="$roomListHeader" :rows="$roomsRows" loading paginate >
                @interact('column_status', $row)
                    @if ($row->status == 'available')
                        <x-ts-badge text="Available" color="green" />
                    @elseif($row->status == 'occupied')
                        <x-ts-badge text="Occupied" color="amber" />
                    @elseif($row->status == 'maintenance')
                        <x-ts-badge text="Maintenance" color="yellow" />
                    @elseif($row->status == 'housekeeping')
                        <x-ts-badge text="housekeeping" color="cyan" />
                    @elseif($row->status == 'out_of_service')
                        <x-ts-badge text="Out of service" color="gray" />
                    @elseif($row->status == 'blocked')
                        <x-ts-badge text="Blocked" color="rose" />
                    @endif
                @endinteract

                @interact('column_room_number', $row)
                    <span class="px-4 py-4 font-mono font-bold text-stone-800">{{ $row->room_number }}</span>
                @endinteract
                @interact('column_name', $row)
                    <div>
                        <div class="font-medium text-stone-900">{{ $row->name }}</div>
                        @if(!$row->is_active)
                            <span class="text-xs text-stone-400">Inactive</span>
                        @endif
                    </div>
                @endinteract
                @interact('column_category_name', $row)
                            <span class=" text-stone-600">{{ $row->category?->name }}</span>
                @endinteract
                @interact('column_capacity', $row)
                        {{ $row->capacity_adults }}A @if($row->capacity_children > 0){{ $row->capacity_children }}C @endif
                @endinteract
                @interact('column_base_price', $row)
                    <span class="px-4 py-4 font-semibold text-stone-900">₱{{ number_format($row->base_price, 0) }}/night</span>
                @endinteract
                @interact('column_allows_pets', $row)
                        @if($row->allows_pets)
                            <span class="text-lg" title="Pet-friendly — ₱{{ number_format($row->pet_fee, 0) }}/night">🐾</span>
                        @else
                            <span class="text-stone-300">—</span>
                        @endif
                @endinteract
                @interact('column_action', $row)
                     <x-ts-dropdown icon="ellipsis-vertical" static lg>
                <a href="{{ route('room.edit', ['id' => $row->id]) }}">
                    <x-ts-dropdown.items text="Edit" icon="pencil-square" />
                </a>
                <a href="{{ route('room.show', ['id' => $row->id]) }}">
                    <x-ts-dropdown.items text="View" separator icon="eye" />
                </a>
                <a>
                    <x-ts-dropdown.items text="Cancel" color="rose" separator icon="x-mark" />
                </a>
            </x-ts-dropdown>
                @endinteract
            </x-ts-table>
        </div>
    </div>
</div>
