<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Suite\RoomBooking;

new class extends Component
{
    use WithPagination;
    public string $statusFilter = '';
    public $date = null;
    public $search = '';


    public function with() :array
    {
        return [
            'bookingListHeader' => [
                ['index' => 'booking_code', 'label' => 'Booking Code', 'sortable' => false], 
                ['index' => 'guest', 'label' => 'Guest', 'sortable' => false], 
                ['index' => 'room', 'label' => 'Room', 'sortable' => false], 
                ['index' => 'check_in', 'label' => 'Check In', 'sortable' => false], 
                ['index' => 'check_out', 'label' => 'Check Out', 'sortable' => false], 
                ['index' => 'guests', 'label' => 'Guests', 'sortable' => false], 
                ['index' => 'total_price', 'label' => 'Total', 'sortable' => false], 
                ['index' => 'status', 'label' => 'Status'], 
                ['index' => 'payment', 'label' => 'Payment', 'sortable' => false], 
                ['index' => 'action', 'label' => 'action']],
            'bookingRows' => RoomBooking::with(['room.category', 'user'])
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search, fn ($q) => $q->where(function ($query) use ($request) {
                $query->where('booking_code', 'like', "%{$request->search}%")
                    ->orWhere('guest_name', 'like', "%{$request->search}%")
                    ->orWhere('guest_email', 'like', "%{$request->search}%");
            }))
            ->when($this->date, fn ($q) => $q->whereDate('check_in', $this->date))
            ->latest()
            ->paginate(10),
    

        ];
    }
};
?>

<div>
    <x-slot:heading>Bookings</x-slot:heading>
    <x-slot:subheading>Manage all reservations</x-slot:subheading>

    {{-- Filters --}}
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by code, guest name or email..."
               class="flex-1 min-w-48 px-4 py-2 border border-stone-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
        <select name="status" class="px-4 py-2 border border-stone-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
            <option value="">All Statuses</option>
            @foreach(['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'no_show'] as $s)
                <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
            @endforeach
        </select>
        <input type="date" name="date" value="{{ request('date') }}"
               class="px-4 py-2 border border-stone-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
        <button type="submit" class="px-4 py-2 bg-stone-800 text-white text-sm font-medium rounded-lg hover:bg-stone-700">Filter</button>
        <a href="" class="px-4 py-2 border border-stone-200 text-stone-600 text-sm rounded-lg hover:bg-stone-50">Clear</a>
        <a href="{{ route('room-booking.create')}}" class="ml-auto px-4 py-2 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 transition-colors">
            + Walk-In Booking
        </a>
    
    {{-- Table --}}
    
    <div class="mt-3">
        <x-ts-table :headers="$bookingListHeader" :rows="$bookingRows" loading paginate>
            @interact('column_booking_code', $row)
                <a 
                {{-- href="{{ route('admin.bookings.show', $booking) }}" --}}
                    class="font-mono text-xs text-emerald-600 font-semibold hover:underline">
                    {{ $row->booking_code }}
                </a>
                @if($row->is_walk_in)
                    <span class="ml-1 px-1.5 py-0.5 bg-blue-100 text-blue-700 text-xs rounded font-medium">Walk-in</span>
                @endif
            @endinteract
            @interact('column_guest', $row)
                 <div class="font-medium text-stone-900">{{ $row->guest_name }}</div>
                        @if($row->guest_email)
                            <div class="text-xs text-stone-400">{{ $row->guest_email }}</div>
                        @endif
            @endinteract
            @interact('column_room',$row)
                <div class="font-medium text-stone-800">{{ $row->room?->room_number }}</div>
                <div class="text-xs text-stone-400">{{ $row->room?->category?->name }}</div>
            @endinteract
            @interact('column_check_in', $row)
                {{ $row->check_in->format('M d, Y') }}
            @endinteract
            @interact('column_check_out', $row)
                {{ $row->check_in->format('M d, Y') }}
            @endinteract
            @interact('column_guests', $row)
                {{ $row->adults }}A
                @if($row->children > 0) {{ $row->children }}C @endif
                @if($row->pets > 0) 🐾{{ $row->pets }} @endif
            @endinteract
            @interact('column_total_price', $row)
                <span class="font-semibold text-stone-900">
                    ₱{{ number_format($row->total_price, 2) }}
                </span>
            @endinteract
            @interact('column_status', $row)
                @php
                    $statusClasses = [
                        'pending' => 'bg-amber-100 text-amber-700',
                        'confirmed' => 'bg-blue-100 text-blue-700',
                        'checked_in' => 'bg-emerald-100 text-emerald-700',
                        'checked_out' => 'bg-stone-100 text-stone-600',
                        'cancelled' => 'bg-red-100 text-red-700',
                        'no_show' => 'bg-stone-200 text-stone-500',
                    ];
                @endphp
                <span class="px-2.5 py-1 rounded-lg text-xs font-semibold {{ $statusClasses[$row->status] ?? 'bg-stone-100 text-stone-600' }}">
                    {{ ucfirst(str_replace('_', ' ', $row->status)) }}
                </span>
            @endinteract
            @interact('column_payment', $row)
                <span class="px-2.5 py-1 rounded-lg text-xs font-semibold {{ $row->payment_status === 'paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                    {{ ucfirst($row->payment_status) }}
                </span>
            @endinteract
            @interact('column_action', $row)
                     <x-ts-dropdown icon="ellipsis-vertical" static lg>
               
                <a href="{{ route('room-booking.show', ['id' => $row->id]) }}">
                    <x-ts-dropdown.items text="View" separator icon="eye" />
                </a>
                @if($row->status === 'confirmed')
                    <x-ts-dropdown.items text="Check In" separator icon="arrow-right-end-on-rectangle" />
                @endif
                @if($row->status === 'checked_in')
                    <x-ts-dropdown.items text="Check out" separator icon="arrow-right-start-on-rectangle" />
                @endif
               
            </x-ts-dropdown>
                @endinteract
        </x-ts-table>
    </div>

</div>