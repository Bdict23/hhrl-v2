<?php

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\SlotOverride;
use App\Services\PickleCourt\BookingService;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

new class extends Component {
    public ?string $previewProofUrl = null;

    public function approveBooking(int $bookingId, BookingService $bookingService): void
    {
        $booking = Booking::find($bookingId);
        if ($booking) {
            $bookingService->updateBookingStatus($booking, 'confirmed', 'Payment approved by staff.');
            $this->dispatch('booking-approved');
        }
    }

    public function cancelBooking(int $bookingId, BookingService $bookingService): void
    {
        $booking = Booking::find($bookingId);
        if ($booking) {
            $bookingService->updateBookingStatus($booking, 'cancelled', 'Cancelled by manager.');
            $this->dispatch('booking-cancelled');
        }
    }

    public function viewProof(string $url): void
    {
        $this->previewProofUrl = $url;
    }

    public function closeProof(): void
    {
        $this->previewProofUrl = null;
    }

    public function with(): array
    {
        $todayStr = Carbon::today()->format('Y-m-d');

        // Key metrics
        $todayBookingsCount = Booking::whereHas('slots', fn ($q) => $q->where('date', $todayStr))
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();

        $todayRevenue = (float) Booking::whereHas('slots', fn ($q) => $q->where('date', $todayStr))
            ->whereIn('status', ['confirmed', 'completed'])
            ->sum('total_amount');

        $activeCourtsCount = Court::active()->count();
        $totalCourtsCount = Court::count();

        $pendingApprovals = Booking::with('slots.court')
            ->where('status', 'pending')
            ->latest()
            ->get();

        $todayBookedSlots = BookingSlot::with(['booking', 'court'])
            ->where('date', $todayStr)
            ->whereHas('booking', fn ($q) => $q->whereIn('status', ['pending', 'confirmed']))
            ->orderBy('start_time')
            ->get();

        $todayOverrides = SlotOverride::with('court')
            ->where('date', $todayStr)
            ->get();

        return [
            'todayBookingsCount' => $todayBookingsCount,
            'todayRevenue'       => $todayRevenue,
            'activeCourtsCount'  => $activeCourtsCount,
            'totalCourtsCount'   => $totalCourtsCount,
            'pendingApprovals'   => $pendingApprovals,
            'todayBookedSlots'   => $todayBookedSlots,
            'todayOverrides'     => $todayOverrides,
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- PAGE HEADER -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                Venue Overview & Analytics
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Live court schedule status, revenue metrics, and pending reservations for today ({{ now()->format('l, F j, Y') }}).
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href=""
                target="_blank"
                class="px-4 py-2 rounded-xl bg-slate-900 dark:bg-slate-800 text-white hover:bg-slate-800 text-xs font-bold transition flex items-center gap-1.5"
            >
                <span>View Public Grid</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
            </a>
        </div>
    </div>

    <!-- STATS KPI CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Today's Bookings -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Today's Bookings</span>
                <div class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ $todayBookingsCount }}</div>
                <span class="text-[10px] text-slate-500">Active reservations</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-blue-500/10 text-blue-500 flex items-center justify-center text-xl font-bold">
                📅
            </div>
        </div>

        <!-- Today's Revenue -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Today's Revenue</span>
                <div class="text-2xl font-black text-emerald-500 mt-1">₱{{ number_format($todayRevenue, 2) }}</div>
                <span class="text-[10px] text-slate-500">Confirmed / Paid</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-xl font-bold">
                💰
            </div>
        </div>

        <!-- Active Courts -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Active Courts</span>
                <div class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ $activeCourtsCount }} / {{ $totalCourtsCount }}</div>
                <span class="text-[10px] text-slate-500">Ready for booking</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-purple-500/10 text-purple-500 flex items-center justify-center text-xl font-bold">
                🎾
            </div>
        </div>

        <!-- Pending Approvals -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Pending Approvals</span>
                <div class="text-2xl font-black text-amber-500 mt-1">{{ $pendingApprovals->count() }}</div>
                <span class="text-[10px] text-slate-500">Awaiting payment verification</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-500 flex items-center justify-center text-xl font-bold">
                ⏳
            </div>
        </div>
    </div>

    <!-- PENDING APPROVALS QUEUE (IF ANY) -->
    @if ($pendingApprovals->isNotEmpty())
        <div class="p-6 rounded-2xl bg-amber-500/5 border border-amber-500/30">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-ping"></span>
                    <h2 class="text-sm font-black uppercase tracking-wider text-amber-600 dark:text-amber-400">
                        Action Required: Pending Payment Approvals ({{ $pendingApprovals->count() }})
                    </h2>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach ($pendingApprovals as $pending)
                    <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-amber-500/20 shadow-sm flex flex-col justify-between gap-3">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-black font-mono text-slate-900 dark:text-white">{{ $pending->reference_code }}</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-500/10 text-amber-500">
                                        {{ $pending->payment_method }}
                                    </span>
                                </div>
                                <div class="text-xs font-bold text-slate-700 dark:text-slate-300 mt-1">
                                    {{ $pending->customer_name }} • {{ $pending->customer_phone }}
                                </div>
                                <div class="text-[11px] text-slate-500">
                                    {{ $pending->slots->count() }} Slots:
                                    @foreach ($pending->slots as $s)
                                        <span class="inline-block bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded text-[10px] mr-1">
                                            {{ $s->court->name }} ({{ \Carbon\Carbon::createFromTimeString($s->start_time)->format('g:i A') }})
                                        </span>
                                    @endforeach
                                </div>
                                @if ($pending->proof_of_payment_url)
                                    <div class="mt-2">
                                        <button
                                            type="button"
                                            wire:click="viewProof('{{ $pending->proof_of_payment_url }}')"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20 text-xs font-bold transition border border-emerald-500/20 cursor-pointer"
                                        >
                                            <span>📎 View Proof Image</span>
                                        </button>
                                    </div>
                                @endif
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-black text-emerald-500">₱{{ number_format((float)$pending->total_amount, 2) }}</div>
                                <span class="text-[10px] text-slate-400">{{ $pending->created_at->diffForHumans() }}</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                            <button
                                wire:click="cancelBooking({{ $pending->id }})"
                                wire:confirm="Are you sure you want to decline/cancel this reservation?"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold text-red-500 hover:bg-red-500/10 transition"
                            >
                                Decline
                            </button>
                            <button
                                wire:click="approveBooking({{ $pending->id }})"
                                class="px-4 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-950 text-xs font-black uppercase tracking-wider transition shadow-sm"
                            >
                                Approve & Mark Paid
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- PROOF OF PAYMENT PREVIEW MODAL -->
    @if ($previewProofUrl)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-700 rounded-3xl max-w-lg w-full p-6 shadow-2xl text-white relative animate-in zoom-in-95">
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-800">
                    <h3 class="text-sm font-black uppercase tracking-wider text-white">
                        Proof of Payment Image
                    </h3>
                    <button wire:click="closeProof" class="text-slate-400 hover:text-white p-1 rounded-lg">
                        ✕
                    </button>
                </div>
                <div class="rounded-xl overflow-hidden bg-slate-950 p-2 flex items-center justify-center">
                    <img src="{{ $previewProofUrl }}" alt="Proof of payment" class="max-h-[70vh] w-auto object-contain rounded-lg shadow" />
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <a href="{{ $previewProofUrl }}" target="_blank" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold transition">
                        Open Full Image ↗
                    </a>
                    <button wire:click="closeProof" class="px-4 py-2 rounded-xl bg-lime-500 hover:bg-lime-400 text-slate-950 text-xs font-black uppercase tracking-wider transition">
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- TODAY'S SCHEDULE & OVERRIDES TIMELINE -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Live Bookings Today -->
        <div class="lg:col-span-2 p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-4 flex items-center justify-between">
                <span>Today's Court Timeline</span>
                <span class="text-xs font-semibold text-slate-400">{{ $todayBookedSlots->count() }} booked hours</span>
            </h2>

            @if ($todayBookedSlots->isEmpty())
                <div class="text-center py-10 text-xs text-slate-400">
                    No reservations scheduled for today yet.
                </div>
            @else
                <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
                    @foreach ($todayBookedSlots as $slot)
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs">
                            <div class="flex items-center gap-3">
                                <span class="px-2 py-1 rounded bg-slate-200 dark:bg-slate-800 text-slate-800 dark:text-slate-200 font-mono font-bold text-[11px]">
                                    {{ \Carbon\Carbon::createFromTimeString($slot->start_time)->format('g:i A') }} - {{ \Carbon\Carbon::createFromTimeString($slot->end_time)->format('g:i A') }}
                                </span>
                                <div>
                                    <div class="font-bold text-slate-900 dark:text-white">{{ $slot->court->name }}</div>
                                    <div class="text-[11px] text-slate-500">
                                        {{ $slot->booking->customer_name }} ({{ $slot->booking->reference_code }})
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $slot->booking->status === 'confirmed' ? 'bg-emerald-500/10 text-emerald-500' : 'bg-amber-500/10 text-amber-500' }}">
                                    {{ $slot->booking->status }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Today's Overrides & Events -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-white mb-4">
                Special Events & Blocks Today
            </h2>

            @if ($todayOverrides->isEmpty())
                <div class="text-center py-10 text-xs text-slate-400">
                    No active maintenance blocks or special events today.
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($todayOverrides as $override)
                        <div class="p-3.5 rounded-xl border {{ $override->isOpenPlay() ? 'bg-amber-500/10 border-amber-500/30' : 'bg-red-500/10 border-red-500/30' }} text-xs">
                            <div class="flex items-center justify-between font-bold">
                                <span class="{{ $override->isOpenPlay() ? 'text-amber-500' : 'text-red-400' }}">
                                    {{ $override->isOpenPlay() ? '⚡ ' . $override->title : '🛠️ ' . $override->title }}
                                </span>
                                <span class="text-[10px] text-slate-400">
                                    {{ \Carbon\Carbon::createFromTimeString($override->start_time)->format('g:i A') }} - {{ \Carbon\Carbon::createFromTimeString($override->end_time)->format('g:i A') }}
                                </span>
                            </div>
                            <div class="text-[11px] text-slate-500 mt-1">
                                Court: {{ $override->court ? $override->court->name : 'All Courts' }}
                            </div>
                            @if ($override->reason)
                                <div class="text-[10px] text-slate-400 italic mt-1">{{ $override->reason }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
