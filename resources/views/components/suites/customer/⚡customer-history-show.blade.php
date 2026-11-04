<?php

use Livewire\Component;
use App\Models\User;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;

new class extends Component
{
    use WithPagination;

    public User $user;

    public function mount(User $user)
    {
        $this->user = $user;
    }

    #[Computed]
    public function bookings()
    {
        return $this->user->roomBookings()
            ->with(['room.category', 'addons'])
            ->latest('check_in')
            ->paginate(15);
    }

    #[Computed]
    public function totalSpent(): float
    {
        return (float) $this->user->roomBookings()->where('payment_status', 'paid')->sum('total_price');
    }

    #[Computed]
    public function completedStays(): int
    {
        return $this->user->roomBookings()->where('status', 'checked_out')->count();
    }

    #[Computed]
    public function activeBookings(): int
    {
        return $this->user->roomBookings()->whereIn('status', ['confirmed', 'checked_in'])->count();
    }
};
?>

<div>
    <x-slot:heading>{{ $user->name }} — Customer History</x-slot:heading>
    <x-slot:subheading>Profile overview and detailed stay records</x-slot:subheading>

    <div class="mb-6">
        <a href="{{ route('suite.customer.history') }}" class="text-xs text-stone-500 hover:text-stone-900 font-medium flex items-center gap-1">
            ← Back to all customers
        </a>
    </div>

    {{-- Customer Profile & Lifetime Metrics --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100 md:col-span-1">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-800 font-bold text-lg flex items-center justify-center">
                    {{ substr($user->name, 0, 1) }}
                </div>
                <div>
                    <h3 class="font-bold text-stone-900">{{ $user->name }}</h3>
                    <p class="text-xs text-stone-400">Registered Guest</p>
                </div>
            </div>
            <div class="space-y-2 text-xs text-stone-600 border-t border-stone-100 pt-4">
                <div><span class="font-semibold text-stone-900">Email:</span> {{ $user->email }}</div>
                <div><span class="font-semibold text-stone-900">Phone:</span> {{ $user->phone_number ?? 'Not provided' }}</div>
                <div><span class="font-semibold text-stone-900">Joined:</span> {{ $user->created_at->format('M d, Y') }}</div>
                <div><span class="font-semibold text-stone-900">Total Visits:</span> {{ $user->visit_count ?? $this->completedStays }} times</div>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100 flex flex-col justify-between">
            <p class="text-xs font-semibold text-stone-500 uppercase tracking-wider">Total Revenue</p>
            <p class="text-2xl font-bold text-stone-900 mt-2">₱{{ number_format($this->totalSpent, 2) }}</p>
            <p class="text-[11px] text-stone-400 mt-1">Paid bookings lifetime</p>
        </div>

        <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100 flex flex-col justify-between">
            <p class="text-xs font-semibold text-stone-500 uppercase tracking-wider">Completed Stays</p>
            <p class="text-2xl font-bold text-emerald-600 mt-2">{{ $this->completedStays }}</p>
            <p class="text-[11px] text-stone-400 mt-1">Checked-out reservations</p>
        </div>

        <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100 flex flex-col justify-between">
            <p class="text-xs font-semibold text-stone-500 uppercase tracking-wider">Active / Upcoming</p>
            <p class="text-2xl font-bold text-blue-600 mt-2">{{ $this->activeBookings }}</p>
            <p class="text-[11px] text-stone-400 mt-1">In-house or confirmed</p>
        </div>
    </div>

    {{-- Stay History Table --}}
    <div class="bg-white rounded-2xl shadow-sm border border-stone-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-stone-100">
            <h3 class="font-bold text-stone-900 text-sm">Stay & Reservation History</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-stone-50 border-b border-stone-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-stone-500 uppercase tracking-wide">Booking Code</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-stone-500 uppercase tracking-wide">Room</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-stone-500 uppercase tracking-wide">Dates</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-stone-500 uppercase tracking-wide">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-stone-500 uppercase tracking-wide">Payment</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-stone-500 uppercase tracking-wide">Total</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-stone-500 uppercase tracking-wide">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-50">
                    @forelse($this->bookings as $booking)
                        <tr class="hover:bg-stone-50/50 transition-colors">
                            <td class="px-4 py-4 font-mono font-bold text-stone-900 text-xs">
                                {{ $booking->booking_code }}
                            </td>
                            <td class="px-4 py-4">
                                <div class="font-semibold text-stone-900">Room {{ $booking->room?->room_number }}</div>
                                <div class="text-xs text-stone-500">{{ $booking->room?->category?->name ?? 'Room' }}</div>
                            </td>
                            <td class="px-4 py-4 text-xs">
                                <div class="font-medium text-stone-900">{{ $booking->check_in->format('M d, Y') }} → {{ $booking->check_out->format('M d, Y') }}</div>
                                <div class="text-stone-400">{{ $booking->check_in->diffInDays($booking->check_out) }} nights · {{ ucfirst($booking->booking_type) }}</div>
                            </td>
                            <td class="px-4 py-4">
                                @php
                                    $statusBadges = [
                                        'pending' => 'bg-amber-100 text-amber-800',
                                        'confirmed' => 'bg-blue-100 text-blue-800',
                                        'checked_in' => 'bg-emerald-100 text-emerald-800',
                                        'checked_out' => 'bg-stone-100 text-stone-700',
                                        'cancelled' => 'bg-red-100 text-red-800',
                                    ];
                                @endphp
                                <span class="px-2.5 py-1 rounded-lg text-xs font-semibold {{ $statusBadges[$booking->status] ?? 'bg-stone-100 text-stone-700' }}">
                                    {{ ucfirst(str_replace('_', ' ', $booking->status)) }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-xs">
                                <span class="px-2 py-0.5 rounded font-semibold text-[11px] {{ $booking->payment_status === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ ucfirst($booking->payment_status) }}
                                </span>
                                @if($booking->payment_method)
                                    <span class="text-stone-400 capitalize block mt-0.5">via {{ $booking->payment_method }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-right font-bold text-stone-900">
                                ₱{{ number_format($booking->total_price, 2) }}
                            </td>
                            <td class="px-4 py-4 text-right">
                                <a href="{{ route('room-booking.show', $booking->id) }}"
                                   class="px-2.5 py-1.5 bg-stone-100 hover:bg-stone-200 text-stone-700 text-xs font-semibold rounded-lg transition-colors">
                                    View Booking
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-stone-400 text-sm">
                                No previous stays or bookings found for this customer.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($this->bookings->hasPages())
            <div class="px-4 py-3 border-t border-stone-100">{{ $this->bookings->links() }}</div>
        @endif
    </div>
</div>