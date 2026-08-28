<?php

use App\Models\Booking;
use App\Models\Court;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new  class extends Component {
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public ?string $dateFilter = null;

    // View Details Modal
    public bool $showDetailsModal = false;
    public ?Booking $selectedBooking = null;

    // Manual / Walk-in Booking Modal
    public bool $showWalkInModal = false;
    public int $walkInCourtId = 1;
    public string $walkInDate;
    public string $walkInStartTime = '14:00:00';
    public string $walkInEndTime = '15:00:00';
    public string $walkInName = '';
    public string $walkInPhone = '';
    public string $walkInEmail = 'walkin@lyrpickle.com';
    public string $walkInPaymentMethod = 'cash';
    public string $walkInNotes = '';

    public function mount(): void
    {
        $this->walkInDate = Carbon::today()->format('Y-m-d');
        $firstCourt = Court::active()->ordered()->first();
        if ($firstCourt) {
            $this->walkInCourtId = $firstCourt->id;
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDateFilter(): void
    {
        $this->resetPage();
    }

    public function viewDetails(int $bookingId): void
    {
        $this->selectedBooking = Booking::with(['slots.court'])->find($bookingId);
        $this->showDetailsModal = true;
    }

    public function closeDetailsModal(): void
    {
        $this->showDetailsModal = false;
        $this->selectedBooking = null;
    }

    public function updateStatus(int $bookingId, string $status, BookingService $bookingService): void
    {
        $booking = Booking::findOrFail($bookingId);
        $bookingService->updateBookingStatus($booking, $status, "Status manually updated to {$status} by staff.");
        if ($this->selectedBooking && $this->selectedBooking->id === $bookingId) {
            $this->selectedBooking->refresh();
        }
    }

    public function openWalkInModal(): void
    {
        $this->walkInDate = Carbon::today()->format('Y-m-d');
        $this->walkInStartTime = '14:00';
        $this->walkInEndTime = '15:00';
        $this->walkInName = '';
        $this->walkInPhone = '';
        $this->walkInEmail = 'walkin@lyrpickle.com';
        $this->walkInPaymentMethod = 'cash';
        $this->walkInNotes = '';
        $this->showWalkInModal = true;
    }

    public function closeWalkInModal(): void
    {
        $this->showWalkInModal = false;
        $this->resetErrorBag();
    }

    public function saveWalkInBooking(BookingService $bookingService): void
    {
        $this->validate([
            'walkInCourtId'       => ['required', 'exists:pickle_courts,id'],
            'walkInDate'          => ['required', 'date'],
            'walkInStartTime'     => ['required'],
            'walkInEndTime'       => ['required'],
            'walkInName'          => ['required', 'string', 'min:2'],
            'walkInPhone'         => ['required', 'string'],
            'walkInPaymentMethod' => ['required', 'in:cash,gcash,maya,bank_transfer'],
        ]);

        $start = str_contains($this->walkInStartTime, ':') && strlen($this->walkInStartTime) === 5 ? $this->walkInStartTime . ':00' : $this->walkInStartTime;
        $end = str_contains($this->walkInEndTime, ':') && strlen($this->walkInEndTime) === 5 ? $this->walkInEndTime . ':00' : $this->walkInEndTime;

        try {
            $booking = $bookingService->createBooking(
                [
                    'customer_name'  => $this->walkInName,
                    'customer_email' => $this->walkInEmail,
                    'customer_phone' => $this->walkInPhone,
                    'payment_method' => $this->walkInPaymentMethod,
                    'notes'          => 'Walk-in booking created at front desk. ' . $this->walkInNotes,
                ],
                [
                    [
                        'court_id'   => $this->walkInCourtId,
                        'date'       => $this->walkInDate,
                        'start_time' => $start,
                        'end_time'   => $end,
                    ],
                ]
            );

            // Automatically mark walk-in as confirmed and paid
            $bookingService->updateBookingStatus($booking, 'confirmed', 'Walk-in paid on the spot.');

            $this->closeWalkInModal();
        } catch (\Exception $e) {
            $this->addError('walkin_error', $e->getMessage());
        }
    }

    public function with(): array
    {
        $query = Booking::with(['slots.court'])
            ->when($this->search !== '', function (Builder $q) {
                $term = mb_trim($this->search);
                $q->where(function ($sub) use ($term) {
                    $sub->where('reference_code', 'like', "%{$term}%")
                        ->orWhere('customer_name', 'like', "%{$term}%")
                        ->orWhere('customer_phone', 'like', "%{$term}%")
                        ->orWhere('customer_email', 'like', "%{$term}%");
                });
            })
            ->when($this->statusFilter !== 'all', function (Builder $q) {
                $q->where('status', $this->statusFilter);
            })
            ->when($this->dateFilter !== null && $this->dateFilter !== '', function (Builder $q) {
                $q->whereHas('slots', fn ($s) => $s->where('date', $this->dateFilter));
            })
            ->latest();

        /** @var LengthAwarePaginator $bookings */
        $bookings = $query->paginate(15);
        $courts = Court::active()->ordered()->get();

        return [
            'bookings' => $bookings,
            'courts'   => $courts,
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- PAGE HEADER -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                Booking Management
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                View, approve, cancel, and manage all customer reservations and create walk-in bookings.
            </p>
        </div>
        <div>
            <button
                wire:click="openWalkInModal"
                class="px-5 py-2.5 rounded-xl bg-lime-500 hover:bg-lime-400 text-slate-950 text-xs font-black uppercase tracking-wider transition shadow-lg shadow-lime-500/20 flex items-center gap-2 cursor-pointer"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>New Walk-in Booking</span>
            </button>
        </div>
    </div>

    <!-- FILTERS & SEARCH BAR -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm flex flex-col md:flex-row items-center justify-between gap-3 text-xs">
        <!-- Search Field -->
        <div class="relative w-full md:w-80">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search reference code, name, phone..."
                class="w-full pl-9 pr-4 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-lime-500 text-xs"
            />
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                🔍
            </div>
        </div>

        <!-- Filter Controls -->
        <div class="flex flex-wrap items-center gap-2 w-full md:w-auto justify-end">
            <!-- Status Filter -->
            <select
                wire:model.live="statusFilter"
                class="px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 font-semibold focus:outline-none focus:border-lime-500 text-xs"
            >
                <option value="all">All Statuses</option>
                <option value="pending">Pending Payment</option>
                <option value="confirmed">Confirmed</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>

            <!-- Date Filter -->
            <input
                type="date"
                wire:model.live="dateFilter"
                class="px-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 font-semibold focus:outline-none focus:border-lime-500 text-xs"
            />

            @if ($search || $statusFilter !== 'all' || $dateFilter)
                <button
                    wire:click="$set('search', ''); $set('statusFilter', 'all'); $set('dateFilter', null);"
                    class="px-3 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-slate-900 dark:hover:text-white transition font-medium text-xs"
                >
                    Reset
                </button>
            @endif
        </div>
    </div>

    <!-- BOOKINGS DATA TABLE -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 text-slate-500 uppercase font-black tracking-wider text-[10px]">
                    <tr>
                        <th class="p-4">Reference Code</th>
                        <th class="p-4">Customer</th>
                        <th class="p-4">Schedule / Slots</th>
                        <th class="p-4">Amount</th>
                        <th class="p-4">Payment</th>
                        <th class="p-4">Status</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse ($bookings as $b)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition">
                            <td class="p-4">
                                <div class="font-mono font-black text-sm text-slate-900 dark:text-white">
                                    {{ $b->reference_code }}
                                </div>
                                <div class="text-[10px] text-slate-400">
                                    {{ $b->created_at->format('M d, Y h:i A') }}
                                </div>
                            </td>

                            <td class="p-4">
                                <div class="font-bold text-slate-900 dark:text-white">{{ $b->customer_name }}</div>
                                <div class="text-[11px] text-slate-500 font-mono">{{ $b->customer_phone }}</div>
                            </td>

                            <td class="p-4">
                                <div class="space-y-0.5">
                                    @foreach ($b->slots as $s)
                                        <div class="text-[11px] text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-lime-500"></span>
                                            <span class="font-semibold">{{ $s->court->name }}</span>:
                                            <span class="text-slate-500">{{ $s->date->format('M d') }} ({{ \Carbon\Carbon::createFromTimeString($s->start_time)->format('g:i A') }})</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            <td class="p-4">
                                <div class="font-black text-sm text-slate-900 dark:text-white">
                                    ₱{{ number_format((float)$b->total_amount, 2) }}
                                </div>
                            </td>

                            <td class="p-4">
                                <span class="uppercase font-bold text-[10px] text-slate-700 dark:text-slate-300">
                                    {{ $b->payment_method }}
                                </span>
                                <div class="text-[10px] {{ $b->payment_status === 'paid' ? 'text-emerald-500 font-semibold' : 'text-amber-500' }}">
                                    {{ ucfirst($b->payment_status) }}
                                </div>
                            </td>

                            <td class="p-4">
                                @if ($b->status === 'confirmed')
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-500/10 text-emerald-500 border border-emerald-500/30">
                                        Confirmed
                                    </span>
                                @elseif ($b->status === 'pending')
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-500/10 text-amber-500 border border-amber-500/30">
                                        Pending
                                    </span>
                                @elseif ($b->status === 'completed')
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-blue-500/10 text-blue-500 border border-blue-500/30">
                                        Completed
                                    </span>
                                @else
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-red-500/10 text-red-400 border border-red-500/30">
                                        Cancelled
                                    </span>
                                @endif
                            </td>

                            <td class="p-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button
                                        wire:click="viewDetails({{ $b->id }})"
                                        class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold"
                                    >
                                        Details
                                    </button>
                                    <a
                                        href="{{ route('booking.receipt', $b->reference_code) }}"
                                        target="_blank"
                                        title="Print Receipt"
                                        class="p-1 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-500"
                                    >
                                        🖨️
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-400">
                                No bookings match your search filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($bookings->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $bookings->links() }}
            </div>
        @endif
    </div>

    <!-- BOOKING DETAILS MODAL -->
    @if ($showDetailsModal && $selectedBooking)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl text-slate-900 dark:text-white relative animate-in zoom-in-95 text-xs space-y-4">
                <button wire:click="closeDetailsModal" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 dark:hover:text-white p-2">✕</button>

                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-bold uppercase text-slate-400">Booking Reference</span>
                        <div class="text-2xl font-black font-mono text-lime-500">{{ $selectedBooking->reference_code }}</div>
                    </div>
                    <div class="text-right">
                        <span class="inline-block px-3 py-1 rounded-full text-xs font-bold uppercase {{ $selectedBooking->status === 'confirmed' ? 'bg-emerald-500/10 text-emerald-500' : ($selectedBooking->status === 'pending' ? 'bg-amber-500/10 text-amber-500' : 'bg-red-500/10 text-red-400') }}">
                            {{ $selectedBooking->status }}
                        </span>
                    </div>
                </div>

                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-2">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Customer:</span>
                        <span class="font-bold">{{ $selectedBooking->customer_name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Contact Phone:</span>
                        <span class="font-mono font-medium">{{ $selectedBooking->customer_phone }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Email:</span>
                        <span>{{ $selectedBooking->customer_email }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Payment:</span>
                        <span class="uppercase font-bold">{{ $selectedBooking->payment_method }} ({{ $selectedBooking->payment_status }})</span>
                    </div>
                    @if ($selectedBooking->proof_of_payment_url)
                        <div class="pt-2 border-t border-slate-200 dark:border-slate-800">
                            <span class="text-emerald-500 font-bold block mb-1">📎 Uploaded Proof of Payment:</span>
                            <div class="p-2 rounded-xl bg-slate-100 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 flex items-center gap-3">
                                <a href="{{ $selectedBooking->proof_of_payment_url }}" target="_blank" class="block group relative">
                                    <img src="{{ $selectedBooking->proof_of_payment_url }}" alt="Proof of Payment" class="w-16 h-16 object-cover rounded-lg border border-slate-300 dark:border-slate-700 group-hover:opacity-80 transition" />
                                </a>
                                <div class="text-xs">
                                    <div class="font-bold text-slate-900 dark:text-white">Customer Receipt Screenshot</div>
                                    <a href="{{ $selectedBooking->proof_of_payment_url }}" target="_blank" class="text-lime-500 hover:underline font-bold text-[11px]">
                                        Open Full Size Image ↗
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif
                    @if ($selectedBooking->notes)
                        <div class="pt-2 border-t border-slate-200 dark:border-slate-800">
                            <span class="text-slate-400 block mb-1">Customer Notes:</span>
                            <p class="text-slate-700 dark:text-slate-300 italic">{{ $selectedBooking->notes }}</p>
                        </div>
                    @endif
                </div>

                <!-- SLOTS -->
                <div>
                    <span class="font-black uppercase tracking-wider text-slate-400 block mb-2">Reserved Slots ({{ $selectedBooking->slots->count() }})</span>
                    <div class="space-y-1.5 max-h-40 overflow-y-auto">
                        @foreach ($selectedBooking->slots as $s)
                            <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                                <div>
                                    <span class="font-bold">{{ $s->court->name }}</span>
                                    <div class="text-[11px] text-slate-500">
                                        {{ $s->date->format('M d, Y') }} • {{ \Carbon\Carbon::createFromTimeString($s->start_time)->format('g:i A') }} - {{ \Carbon\Carbon::createFromTimeString($s->end_time)->format('g:i A') }}
                                    </div>
                                </div>
                                <span class="font-bold text-lime-500">₱{{ number_format((float)$s->price, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="pt-2 border-t border-slate-200 dark:border-slate-800 flex justify-between items-baseline text-sm">
                    <span class="font-bold">Total Amount:</span>
                    <span class="text-xl font-black text-lime-500">₱{{ number_format((float)$selectedBooking->total_amount, 2) }}</span>
                </div>

                <!-- ACTIONS -->
                <div class="pt-2 flex flex-wrap items-center justify-between gap-2">
                    <a
                        href="{{ route('booking.receipt', $selectedBooking->reference_code) }}"
                        target="_blank"
                        class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold hover:bg-slate-200"
                    >
                        View Receipt
                    </a>

                    <div class="flex items-center gap-2">
                        @if ($selectedBooking->status === 'pending')
                            <button
                                wire:click="updateStatus({{ $selectedBooking->id }}, 'confirmed')"
                                class="px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black uppercase tracking-wider"
                            >
                                Approve & Mark Paid
                            </button>
                        @elseif ($selectedBooking->status === 'confirmed')
                            <button
                                wire:click="updateStatus({{ $selectedBooking->id }}, 'completed')"
                                class="px-4 py-2 rounded-xl bg-blue-500 hover:bg-blue-400 text-white font-bold"
                            >
                                Mark Completed
                            </button>
                        @endif

                        @if ($selectedBooking->status !== 'cancelled')
                            <button
                                wire:click="updateStatus({{ $selectedBooking->id }}, 'cancelled')"
                                wire:confirm="Are you sure you want to cancel this booking?"
                                class="px-3 py-2 rounded-xl bg-red-500/10 text-red-500 hover:bg-red-500/20 font-bold"
                            >
                                Cancel
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- WALK-IN / MANUAL BOOKING MODAL -->
    @if ($showWalkInModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl text-slate-900 dark:text-white relative animate-in zoom-in-95 text-xs">
                <button wire:click="closeWalkInModal" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 dark:hover:text-white p-2">✕</button>

                <h3 class="text-xl font-black uppercase tracking-tight mb-4">
                    New Front-Desk Walk-In Booking
                </h3>

                @if ($errors->has('walkin_error'))
                    <div class="p-3 mb-3 rounded-xl bg-red-500/10 border border-red-500/30 text-red-500">
                        {{ $errors->first('walkin_error') }}
                    </div>
                @endif

                <form wire:submit="saveWalkInBooking" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">Select Court *</label>
                            <select
                                wire:model="walkInCourtId"
                                class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 font-semibold"
                            >
                                @foreach ($courts as $court)
                                    <option value="{{ $court->id }}">{{ $court->name }} (₱{{ $court->hourly_rate }}/hr)</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">Date *</label>
                            <input
                                type="date"
                                wire:model="walkInDate"
                                class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 font-semibold"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">Start Time *</label>
                            <input
                                type="time"
                                wire:model="walkInStartTime"
                                class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 font-semibold text-center"
                            />
                        </div>
                        <div>
                            <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">End Time *</label>
                            <input
                                type="time"
                                wire:model="walkInEndTime"
                                class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 font-semibold text-center"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">Player / Customer Name *</label>
                        <input
                            type="text"
                            wire:model="walkInName"
                            placeholder="e.g. Alex Rivera"
                            class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-sm font-semibold"
                        />
                        @error('walkInName') <span class="text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">Phone *</label>
                            <input
                                type="tel"
                                wire:model="walkInPhone"
                                placeholder="09171234567"
                                class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-sm"
                            />
                            @error('walkInPhone') <span class="text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">Payment Method</label>
                            <select
                                wire:model="walkInPaymentMethod"
                                class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800"
                            >
                                <option value="cash">Cash (Paid at Counter)</option>
                                <option value="gcash">GCash</option>
                                <option value="maya">Maya</option>
                                <option value="bank_transfer">Bank Transfer</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">Staff Notes (Optional)</label>
                        <input
                            type="text"
                            wire:model="walkInNotes"
                            placeholder="e.g. Paid in cash, issued 2 rental paddles"
                            class="w-full px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800"
                        />
                    </div>

                    <div class="pt-3 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            wire:click="closeWalkInModal"
                            class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="px-6 py-2.5 rounded-xl bg-lime-500 hover:bg-lime-400 text-slate-950 font-black uppercase tracking-wider shadow-md"
                        >
                            Confirm Walk-In
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
