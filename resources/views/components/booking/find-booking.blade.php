<?php

use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new class extends Component {
    public string $query = '';
    public ?Booking $foundBooking = null;
    /** @var Collection<int, Booking> */
    public Collection $phoneBookings;
    public bool $hasSearched = false;
    public ?string $cancelMessage = null;

    public function mount(?string $code = null): void
    {
        $this->phoneBookings = new Collection();
        if ($code) {
            $this->query = $code;
            $this->search(app(BookingService::class));
        }
    }

    public function search(BookingService $bookingService): void
    {
        $this->cancelMessage = null;
        $this->hasSearched = true;
        $this->foundBooking = null;
        $this->phoneBookings = new Collection();

        $trimmed = mb_trim($this->query);
        if (empty($trimmed)) {
            return;
        }

        // Try single reference code match first
        $booking = $bookingService->findBooking($trimmed);
        if ($booking) {
            $this->foundBooking = $booking;
            return;
        }

        // Try phone query
        $this->phoneBookings = $bookingService->findBookingsByPhone($trimmed);
    }

    public function selectBooking(int $bookingId): void
    {
        $this->foundBooking = Booking::with('slots.court')->find($bookingId);
    }

    public function cancelBooking(BookingService $bookingService): void
    {
        if (! $this->foundBooking) {
            return;
        }

        if ($this->foundBooking->isCancelled()) {
            $this->cancelMessage = 'This booking is already cancelled.';
            return;
        }

        $bookingService->updateBookingStatus($this->foundBooking, 'cancelled', 'Cancelled by customer via Find Booking portal.');
        $this->foundBooking->refresh();
        $this->cancelMessage = 'Your reservation has been successfully cancelled.';
    }
}; ?>

<div class="max-w-3xl mx-auto py-4 sm:py-8 px-2 sm:px-4">
    <!-- BACK LINK -->
    <div class="mb-3 sm:mb-4">
        <a href="{{ route('welcome') }}" class="text-xs font-bold text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white flex items-center gap-1.5 transition">
            ← Back to Court Schedule
        </a>
    </div>

    <!-- SEARCH HEADER CARD -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl sm:rounded-3xl p-4 sm:p-8 shadow-xl text-slate-900 dark:text-white mb-6 sm:mb-8 transition-colors duration-200">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl bg-lime-500/15 text-lime-700 dark:text-lime-400 border border-lime-500/30 flex items-center justify-center text-xl sm:text-2xl font-bold flex-shrink-0">
                🔍
            </div>
            <div>
                <h1 class="text-xl sm:text-3xl font-black uppercase tracking-tight text-slate-900 dark:text-white">
                    Find My Booking
                </h1>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                    Lookup your court reservation details, status, or cancel an upcoming booking.
                </p>
            </div>
        </div>

        <!-- SEARCH INPUT FORM -->
        <form wire:submit="search" class="mt-4 sm:mt-6 flex flex-col sm:flex-row gap-2.5 sm:gap-3">
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3.5 sm:pl-4 flex items-center pointer-events-none text-slate-400 dark:text-slate-500 font-bold text-sm">
                    #
                </div>
                <input
                    type="text"
                    wire:model="query"
                    placeholder="Enter Reference Code (e.g. LYR-DINK01) or Mobile Number"
                    class="w-full pl-9 sm:pl-10 pr-3 sm:pr-4 py-3 sm:py-3.5 rounded-xl sm:rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 text-xs sm:text-sm focus:outline-none focus:border-lime-500 dark:focus:border-lime-400 focus:ring-1 focus:ring-lime-500 uppercase font-mono tracking-wider transition"
                />
            </div>
            <button
                type="submit"
                class="px-6 sm:px-8 py-3 sm:py-3.5 rounded-xl sm:rounded-2xl bg-lime-500 hover:bg-lime-400 text-slate-950 font-black text-xs sm:text-sm uppercase tracking-wider transition shadow-md shadow-lime-500/20 flex items-center justify-center gap-2 cursor-pointer flex-shrink-0"
            >
                <span>Search</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </button>
        </form>

        @if ($cancelMessage)
            <div class="mt-4 p-3 sm:p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-700 dark:text-amber-300 text-xs font-semibold">
                {{ $cancelMessage }}
            </div>
        @endif
    </div>

    <!-- SEARCH RESULTS SECTION -->
    @if ($hasSearched)
        @if ($foundBooking)
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl sm:rounded-3xl p-4 sm:p-8 shadow-xl text-slate-900 dark:text-white animate-in zoom-in-95 space-y-4 sm:space-y-6 transition-colors duration-200">
                <!-- TOP HEADER -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 pb-4 sm:pb-6 border-b border-slate-200 dark:border-slate-800">
                    <div>
                        <div class="text-[11px] sm:text-xs font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400">Reference Code</div>
                        <div class="text-2xl sm:text-3xl font-black text-lime-600 dark:text-lime-400 font-mono tracking-wider mt-0.5">
                            {{ $foundBooking->reference_code }}
                        </div>
                        <div class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            Booked on {{ $foundBooking->created_at->format('M d, Y h:i A') }}
                        </div>
                    </div>

                    <!-- STATUS BADGE -->
                    <div class="flex items-center gap-2">
                        @if ($foundBooking->status === 'confirmed')
                            <span class="px-3 py-1 sm:px-3.5 sm:py-1.5 rounded-full text-[11px] sm:text-xs font-black uppercase tracking-wider bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 dark:bg-emerald-400"></span>
                                Confirmed & Paid
                            </span>
                        @elseif ($foundBooking->status === 'pending')
                            <span class="px-3 py-1 sm:px-3.5 sm:py-1.5 rounded-full text-[11px] sm:text-xs font-black uppercase tracking-wider bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/30 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-amber-500 dark:bg-amber-400 animate-ping"></span>
                                Awaiting Payment
                            </span>
                        @elseif ($foundBooking->status === 'cancelled')
                            <span class="px-3 py-1 sm:px-3.5 sm:py-1.5 rounded-full text-[11px] sm:text-xs font-black uppercase tracking-wider bg-red-500/10 text-red-700 dark:text-red-400 border border-red-500/30">
                                Cancelled
                            </span>
                        @else
                            <span class="px-3 py-1 sm:px-3.5 sm:py-1.5 rounded-full text-[11px] sm:text-xs font-black uppercase tracking-wider bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                {{ ucfirst($foundBooking->status) }}
                            </span>
                        @endif
                    </div>
                </div>

                <!-- CUSTOMER DETAILS -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800/80 rounded-xl sm:rounded-2xl p-3.5 sm:p-4 text-xs">
                    <div>
                        <span class="text-slate-500 dark:text-slate-400 block font-medium">Customer Name</span>
                        <span class="text-slate-900 dark:text-white font-bold text-sm">{{ $foundBooking->customer_name }}</span>
                    </div>
                    <div>
                        <span class="text-slate-500 dark:text-slate-400 block font-medium">Phone Number</span>
                        <span class="text-slate-900 dark:text-white font-mono font-bold text-sm">{{ $foundBooking->customer_phone }}</span>
                    </div>
                    <div>
                        <span class="text-slate-500 dark:text-slate-400 block font-medium">Email Address</span>
                        <span class="text-slate-900 dark:text-white font-bold text-sm truncate block">{{ $foundBooking->customer_email }}</span>
                    </div>
                </div>

                <!-- RESERVED SLOTS LIST -->
                <div>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2 sm:mb-3">
                        Reserved Court Slots ({{ $foundBooking->slots->count() }})
                    </h3>
                    <div class="space-y-2">
                        @foreach ($foundBooking->slots as $s)
                            <div class="flex items-center justify-between p-3 sm:p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                                <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
                                    <span class="w-8 h-8 rounded-lg bg-lime-500/15 border border-lime-500/30 text-lime-700 dark:text-lime-400 flex items-center justify-center font-bold text-sm flex-shrink-0">
                                        🎾
                                    </span>
                                    <div class="min-w-0">
                                        <div class="font-bold text-slate-900 dark:text-white text-sm truncate">{{ $s->court->name }}</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400 truncate">
                                            {{ $s->date->format('M d, Y') }} • {{ \Carbon\Carbon::createFromTimeString($s->start_time)->format('g:i A') }} - {{ \Carbon\Carbon::createFromTimeString($s->end_time)->format('g:i A') }}
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0 ml-2">
                                    <div class="font-black text-lime-600 dark:text-lime-400 text-sm">₱{{ number_format((float)$s->price, 2) }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- FINANCIAL SUMMARY -->
                <div class="p-3.5 sm:p-4 rounded-xl sm:rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-2 text-xs">
                    <div class="flex justify-between text-slate-600 dark:text-slate-400">
                        <span>Subtotal (Base Hours):</span>
                        <span>₱{{ number_format((float)$foundBooking->subtotal_amount, 2) }}</span>
                    </div>
                    @if ((float)$foundBooking->discount_or_surcharge != 0)
                        <div class="flex justify-between text-amber-700 dark:text-amber-400">
                            <span>Peak Surcharge / Adjustments:</span>
                            <span>₱{{ number_format((float)$foundBooking->discount_or_surcharge, 2) }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between text-slate-600 dark:text-slate-400">
                        <span>Payment Method:</span>
                        <span class="uppercase font-bold text-slate-900 dark:text-white">{{ $foundBooking->payment_method }}</span>
                    </div>
                    @if ($foundBooking->proof_of_payment_url)
                        <div class="flex justify-between items-center text-slate-600 dark:text-slate-400">
                            <span class="text-emerald-700 dark:text-emerald-400 font-semibold flex items-center gap-1">
                                <span>📎</span> Proof of Payment:
                            </span>
                            <a href="{{ $foundBooking->proof_of_payment_url }}" target="_blank" class="text-lime-600 dark:text-lime-400 hover:underline font-bold">
                                View Receipt Image ↗
                            </a>
                        </div>
                    @endif
                    <div class="pt-2 border-t border-slate-200 dark:border-slate-800 flex justify-between text-sm sm:text-base font-black">
                        <span class="text-slate-900 dark:text-white">Total Amount:</span>
                        <span class="text-lime-600 dark:text-lime-400 text-lg sm:text-xl">₱{{ number_format((float)$foundBooking->total_amount, 2) }}</span>
                    </div>
                </div>

                <!-- ACTIONS -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2">
                    <a
                        href="{{ route('booking.receipt', $foundBooking->reference_code) }}"
                        target="_blank"
                        class="w-full sm:w-auto px-5 sm:px-6 py-2.5 sm:py-3 rounded-xl bg-slate-900 hover:bg-slate-800 text-white dark:bg-slate-800 dark:hover:bg-slate-700 font-bold text-xs uppercase tracking-wider transition border border-slate-700 flex items-center justify-center gap-2"
                    >
                        <span>Official Receipt</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>

                    @if (! $foundBooking->isCancelled())
                        <button
                            wire:click="cancelBooking"
                            wire:confirm="Are you sure you want to cancel this reservation?"
                            class="w-full sm:w-auto px-5 sm:px-6 py-2.5 sm:py-3 rounded-xl bg-red-500/10 hover:bg-red-500/20 text-red-600 dark:text-red-400 border border-red-500/30 font-bold text-xs uppercase tracking-wider transition cursor-pointer"
                        >
                            Cancel Reservation
                        </button>
                    @endif
                </div>
            </div>

        @elseif ($phoneBookings->isNotEmpty())
            <!-- MULTIPLE BOOKINGS FOUND FOR PHONE -->
            <div class="space-y-4">
                <h3 class="text-base font-bold text-slate-700 dark:text-slate-300">
                    Found {{ $phoneBookings->count() }} reservations for this phone number:
                </h3>

                @foreach ($phoneBookings as $pb)
                    <div
                        wire:click="selectBooking({{ $pb->id }})"
                        class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-lime-500/50 transition cursor-pointer flex items-center justify-between shadow-xs"
                    >
                        <div>
                            <div class="text-sm font-mono font-black text-lime-600 dark:text-lime-400">{{ $pb->reference_code }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $pb->created_at->format('M d, Y') }} • {{ $pb->slots->count() }} Slots</div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-black text-slate-900 dark:text-white">₱{{ number_format((float)$pb->total_amount, 2) }}</div>
                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full {{ $pb->status === 'confirmed' ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' : ($pb->status === 'pending' ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400' : 'bg-red-500/10 text-red-700 dark:text-red-400') }}">
                                {{ $pb->status }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>

        @else
            <!-- NO BOOKINGS FOUND -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-12 text-center text-slate-900 dark:text-white shadow-xl">
                <div class="w-14 h-14 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-2xl mx-auto mb-3 text-slate-500">
                    🔎
                </div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">No Bookings Found</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
                    We could not find any active reservations matching "<span class="font-mono text-lime-600 dark:text-lime-400 font-bold">{{ $query }}</span>". Please double-check your reference code or mobile number.
                </p>
            </div>
        @endif
    @endif
</div>
