<?php

use App\Models\Booking;
use App\Models\SlotOverride;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public string $selectedDate;
    public array $selectedSlots = [];

    // Checkout modal form fields
    public bool $showCheckoutModal = false;
    public string $customerName = '';
    public string $customerEmail = '';
    public string $customerPhone = '';
    public string $paymentMethod = 'gcash';
    public $paymentProof = null;
    public string $notes = '';

    // Confirmation modal
    public bool $showConfirmationModal = false;
    public ?array $confirmedBooking = null;

    // Open play details modal
    public bool $showOpenPlayModal = false;
    public ?array $activeOpenPlay = null;

    // Maintenance / Blocked details modal
    public bool $showBlockedModal = false;
    public ?array $activeBlocked = null;

    public function mount(?string $date = null): void
    {
        $this->selectedDate = $date ?? Carbon::today()->format('Y-m-d');
    }

    public function setDate(string $date): void
    {
        $this->selectedDate = Carbon::parse($date)->format('Y-m-d');
        $this->selectedSlots = [];
    }

    public function previousDay(): void
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->subDay()->format('Y-m-d');
        $this->selectedSlots = [];
    }

    public function nextDay(): void
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->addDay()->format('Y-m-d');
        $this->selectedSlots = [];
    }

    public function jumpToToday(): void
    {
        $this->selectedDate = Carbon::today()->format('Y-m-d');
        $this->selectedSlots = [];
    }

    public function toggleSlot(
        int $courtId,
        string $date,
        string $startTime,
        string $endTime,
        float $price,
        float $baseRate,
        string $courtName,
        string $timeLabel,
        bool $isPeak = false,
        ?string $ruleName = null
    ): void {
        $slotKey = "{$courtId}_{$date}_{$startTime}";

        if (isset($this->selectedSlots[$slotKey])) {
            unset($this->selectedSlots[$slotKey]);
        } else {
            $this->selectedSlots[$slotKey] = [
                'key'         => $slotKey,
                'court_id'    => $courtId,
                'court_name'  => $courtName,
                'date'        => $date,
                'start_time'  => $startTime,
                'end_time'    => $endTime,
                'time_label'  => $timeLabel,
                'base_rate'   => $baseRate,
                'price'       => $price,
                'is_peak'     => $isPeak,
                'rule_name'   => $ruleName,
            ];
        }
    }

    public function clearSelection(): void
    {
        $this->selectedSlots = [];
    }

    public function openCheckout(): void
    {
        if (empty($this->selectedSlots)) {
            return;
        }
        $this->showCheckoutModal = true;
    }

    public function closeCheckout(): void
    {
        $this->showCheckoutModal = false;
        $this->resetErrorBag();
    }

    public function viewOpenPlayDetails(int $overrideId): void
    {
        $override = SlotOverride::with('court')->find($overrideId);
        if (! $override) {
            return;
        }

        $this->activeOpenPlay = [
            'id'               => $override->id,
            'title'            => $override->title ?? 'Open Play Rally',
            'reason'           => $override->reason ?? 'All skill levels welcome. Paddle queue system in effect.',
            'date'             => $override->date->format('l, F j, Y'),
            'start_time'       => substr($override->start_time, 0, 5),
            'end_time'         => substr($override->end_time, 0, 5),
            'time_label'       => Carbon::createFromTimeString($override->start_time)->format('g:i A') . ' - ' . Carbon::createFromTimeString($override->end_time)->format('g:i A'),
            'fee_per_person'   => (float) ($override->fee_per_person ?? 150.00),
            'max_participants' => $override->max_participants ?? 24,
            'court_name'       => $override->court ? $override->court->name : 'All Active Courts',
        ];

        $this->showOpenPlayModal = true;
    }

    public function viewBlockedDetails(int $overrideId): void
    {
        $override = SlotOverride::with('court')->find($overrideId);
        if (! $override) {
            return;
        }

        $this->activeBlocked = [
            'id'         => $override->id,
            'type'       => $override->type,
            'title'      => $override->title ?? match ($override->type) {
                'maintenance'   => 'Court Maintenance',
                'private_event' => 'Private Tournament / Event',
                default         => 'Unavailable / Blocked',
            },
            'reason'     => $override->reason ?? 'This slot is reserved or unavailable for public booking.',
            'date'       => $override->date->format('l, F j, Y'),
            'time_label' => Carbon::createFromTimeString($override->start_time)->format('g:i A') . ' - ' . Carbon::createFromTimeString($override->end_time)->format('g:i A'),
            'court_name' => $override->court ? $override->court->name : 'All Courts',
        ];

        $this->showBlockedModal = true;
    }

    public function submitBooking(BookingService $bookingService): void
    {
        $this->validate([
            'customerName'  => ['required', 'string', 'min:2', 'max:100'],
            'customerEmail' => ['required', 'email', 'max:150'],
            'customerPhone' => ['required', 'string', 'min:7', 'max:20'],
            'paymentMethod' => ['required', 'in:gcash,maya,bank_transfer,cash'],
            'paymentProof'  => ['nullable', 'image', 'max:5120'],
            'notes'         => ['nullable', 'string', 'max:500'],
        ], [
            'customerName.required'  => 'Please enter your full name.',
            'customerEmail.required' => 'Please enter your email address for the booking receipt.',
            'customerPhone.required' => 'Please enter a contact number.',
            'paymentProof.image'     => 'Proof of payment must be a valid image file (PNG, JPG, JPEG, WEBP).',
            'paymentProof.max'       => 'Proof of payment image must not exceed 5MB in size.',
        ]);

        try {
            $proofPath = null;
            if ($this->paymentProof) {
                $proofPath = $this->paymentProof->store('proofs', 'public');
            }

            $booking = $bookingService->createBooking(
                [
                    'customer_name'         => $this->customerName,
                    'customer_email'        => $this->customerEmail,
                    'customer_phone'        => $this->customerPhone,
                    'payment_method'        => $this->paymentMethod,
                    'proof_of_payment_path' => $proofPath,
                    'notes'                 => $this->notes,
                ],
                array_values($this->selectedSlots)
            );

            $this->confirmedBooking = [
                'id'                    => $booking->id,
                'reference_code'        => $booking->reference_code,
                'customer_name'         => $booking->customer_name,
                'customer_email'        => $booking->customer_email,
                'customer_phone'        => $booking->customer_phone,
                'total_amount'          => (float) $booking->total_amount,
                'subtotal_amount'       => (float) $booking->subtotal_amount,
                'discount_or_surcharge' => (float) $booking->discount_or_surcharge,
                'payment_method'        => $booking->payment_method,
                'proof_of_payment_path' => $booking->proof_of_payment_path,
                'proof_of_payment_url'  => $booking->proof_of_payment_url,
                'status'                => $booking->status,
                'created_at'            => $booking->created_at->format('M d, Y h:i A'),
                'slots_count'           => $booking->slots->count(),
                'slots'                 => $booking->slots->map(function ($s) {
                    return [
                        'court_name'  => $s->court->name,
                        'date'        => $s->date->format('M d, Y'),
                        'time_label'  => Carbon::createFromTimeString($s->start_time)->format('g:i A') . ' - ' . Carbon::createFromTimeString($s->end_time)->format('g:i A'),
                        'price'       => (float) $s->price,
                    ];
                })->toArray(),
            ];

            // Reset state
            $this->selectedSlots = [];
            $this->paymentProof = null;
            $this->showCheckoutModal = false;
            $this->showConfirmationModal = true;
            $this->notes = '';

        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->getMessageBag());
        } catch (\Exception $e) {
            $this->addError('checkout_error', $e->getMessage());
        }
    }

    public function with(BookingService $bookingService): array
    {
        $currentDate = Carbon::parse($this->selectedDate);
        $schedule = $bookingService->getMatrixSchedule($currentDate);

        // Generate 1-Month (31 to 35 days) scrollable date pills
        $quickDates = [];
        $startWindow = Carbon::today();
        $startDate = $currentDate->isBefore($startWindow) ? (clone $currentDate) : (clone $startWindow);
        
        $daysCount = max(31, $startDate->diffInDays($currentDate) + 7);
        $daysCount = min($daysCount, 60);

        for ($i = 0; $i < $daysCount; $i++) {
            $d = (clone $startDate)->addDays($i);
            $dStr = $d->format('Y-m-d');
            $quickDates[] = [
                'date'        => $dStr,
                'day_name'    => $d->isToday() ? 'Today' : ($d->isTomorrow() ? 'Tomorrow' : $d->format('D')),
                'day_number'  => $d->format('d'),
                'month_name'  => $d->format('M'),
                'is_selected' => $dStr === $this->selectedDate,
                'is_today'    => $d->isToday(),
                'is_weekend'  => $d->isWeekend(),
            ];
        }

        // Totals for selection
        $selectedCount = count($this->selectedSlots);
        $selectedSubtotal = array_sum(array_column($this->selectedSlots, 'base_rate'));
        $selectedTotal = array_sum(array_column($this->selectedSlots, 'price'));
        $selectedAdjustment = $selectedTotal - $selectedSubtotal;

        return [
            'schedule'           => $schedule,
            'quickDates'         => $quickDates,
            'currentDate'        => $currentDate,
            'selectedCount'      => $selectedCount,
            'selectedSubtotal'   => $selectedSubtotal,
            'selectedTotal'      => $selectedTotal,
            'selectedAdjustment' => $selectedAdjustment,
        ];
    }
}; ?>

<div
    class="relative w-full"
    x-data="{
        scrollLeft() {
            this.$refs.dateScrollContainer?.scrollBy({ left: -260, behavior: 'smooth' });
        },
        scrollRight() {
            this.$refs.dateScrollContainer?.scrollBy({ left: 260, behavior: 'smooth' });
        },
        scrollToSelected(smooth = true) {
            this.$nextTick(() => {
                const selected = this.$refs.dateScrollContainer?.querySelector('[data-selected=true]');
                if (selected) {
                    selected.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto', inline: 'center', block: 'nearest' });
                }
            });
        }
    }"
    x-init="
        scrollToSelected(false);
        $watch('$wire.selectedDate', () => scrollToSelected(true));
    "
    wire:poll.15s
>
    <!-- TOP TOOLBAR & DATE NAVIGATION -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-3.5 sm:p-6 shadow-xl mb-4 sm:mb-6 transition-colors duration-200">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 sm:gap-4 pb-3 sm:pb-4 border-b border-slate-200 dark:border-slate-800/80">
            <!-- Title & Status -->
            <div class="flex items-center gap-2.5 sm:gap-3">
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-lime-500/15 border border-lime-500/30 flex items-center justify-center text-lime-700 dark:text-lime-400 font-bold text-lg sm:text-xl shadow-xs flex-shrink-0">
                    🎾
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-lg sm:text-2xl font-black tracking-tight text-slate-900 dark:text-white uppercase">
                            Court Schedule Grid
                        </h2>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] sm:text-xs font-semibold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400 animate-pulse"></span>
                            Live
                        </span>
                    </div>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                        Select available slots to reserve your court with instant zero-friction checkout.
                    </p>
                </div>
            </div>

            <!-- Action Controls: Prev/Next, Date Picker & Quick Tools -->
            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                <button
                    type="button"
                    wire:click="jumpToToday"
                    class="px-2.5 sm:px-3 py-1.5 rounded-xl text-xs font-bold uppercase tracking-wider bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white border border-slate-200 dark:border-slate-700 transition cursor-pointer"
                >
                    Today
                </button>

                <div class="flex items-center bg-slate-100 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl p-1 shadow-xs">
                    <button
                        type="button"
                        wire:click="previousDay"
                        title="Previous Day"
                        class="p-1.5 sm:p-2 text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white hover:bg-slate-200 dark:hover:bg-slate-800 rounded-lg transition cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>

                    <div class="relative px-1 sm:px-2">
                        <input
                            type="date"
                            wire:model.live="selectedDate"
                            min="{{ Carbon::today()->format('Y-m-d') }}"
                            class="bg-transparent text-xs sm:text-sm font-bold text-slate-900 dark:text-white focus:outline-none cursor-pointer border-none p-0 text-center dark:[color-scheme:dark]"
                        />
                    </div>

                    <button
                        type="button"
                        wire:click="nextDay"
                        title="Next Day"
                        class="p-1.5 sm:p-2 text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white hover:bg-slate-200 dark:hover:bg-slate-800 rounded-lg transition cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>

                <button
                    type="button"
                    wire:click="$refresh"
                    title="Refresh Grid"
                    class="p-2 sm:p-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 hover:text-lime-600 dark:hover:text-lime-400 border border-slate-200 dark:border-slate-700 transition flex items-center gap-1.5 text-xs font-semibold cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span class="hidden sm:inline">Refresh</span>
                </button>
            </div>
        </div>

        <!-- 1-MONTH SCROLLABLE DATE NAVIGATION STRIP -->
        <div class="mt-3 sm:mt-4 relative group">
            <!-- Left Scroll Button -->
            <button
                type="button"
                x-on:click="scrollLeft()"
                class="absolute left-0 top-1/2 -translate-y-1/2 z-10 w-7 sm:w-8 h-10 sm:h-12 rounded-r-xl bg-white dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 border border-l-0 border-slate-200 dark:border-slate-800 shadow-md flex items-center justify-center transition cursor-pointer backdrop-blur-xs"
                title="Scroll Left"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
            </button>

            <!-- Scrollable Date Pills List -->
            <div
                x-ref="dateScrollContainer"
                class="flex items-center gap-1.5 sm:gap-2 overflow-x-auto px-7 sm:px-8 py-1.5 scroll-smooth scrollbar-thin scrollbar-thumb-slate-300 dark:scrollbar-thumb-slate-700"
                style="scrollbar-width: thin; -webkit-overflow-scrolling: touch;"
            >
                @foreach ($quickDates as $qd)
                    <button
                        type="button"
                        wire:click="setDate('{{ $qd['date'] }}')"
                        data-selected="{{ $qd['is_selected'] ? 'true' : 'false' }}"
                        class="flex-shrink-0 flex flex-col items-center justify-center min-w-[62px] sm:min-w-[76px] py-2 sm:py-2.5 px-2 sm:px-3 rounded-xl border text-center transition duration-150 cursor-pointer {{ $qd['is_selected'] ? 'bg-lime-500 text-slate-950 font-bold border-lime-400 shadow-md shadow-lime-500/25 scale-[1.02]' : ($qd['is_today'] ? 'bg-lime-500/10 hover:bg-lime-500/20 text-lime-700 dark:text-lime-400 border-lime-500/30 font-semibold' : 'bg-slate-50 hover:bg-slate-100 dark:bg-slate-950 dark:hover:bg-slate-850 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700') }}"
                    >
                        <span class="text-[9px] sm:text-[10px] font-black uppercase tracking-wider {{ $qd['is_selected'] ? 'text-slate-950' : ($qd['is_today'] ? 'text-lime-700 dark:text-lime-400' : 'text-slate-500 dark:text-slate-400') }}">
                            {{ $qd['day_name'] }}
                        </span>
                        <span class="text-base sm:text-lg font-black leading-tight {{ $qd['is_selected'] ? 'text-slate-950' : 'text-slate-900 dark:text-white' }}">
                            {{ $qd['day_number'] }}
                        </span>
                        <span class="text-[9px] sm:text-[10px] font-medium {{ $qd['is_selected'] ? 'text-slate-900' : 'text-slate-500 dark:text-slate-500' }}">
                            {{ $qd['month_name'] }}
                        </span>
                    </button>
                @endforeach
            </div>

            <!-- Right Scroll Button -->
            <button
                type="button"
                x-on:click="scrollRight()"
                class="absolute right-0 top-1/2 -translate-y-1/2 z-10 w-7 sm:w-8 h-10 sm:h-12 rounded-l-xl bg-white dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 border border-r-0 border-slate-200 dark:border-slate-800 shadow-md flex items-center justify-center transition cursor-pointer backdrop-blur-xs"
                title="Scroll Right"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>

        <!-- LEGEND BAR -->
        <div class="mt-3 sm:mt-4 pt-3 border-t border-slate-200 dark:border-slate-800/60 flex flex-wrap items-center justify-between gap-2.5 sm:gap-3 text-xs">
            <div class="flex flex-wrap items-center gap-2 sm:gap-4">
                <div class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300 text-[11px] sm:text-xs">
                    <span class="w-3 h-3 rounded bg-slate-100 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 flex items-center justify-center text-[9px] text-lime-600 dark:text-lime-400 font-bold">●</span>
                    <span>Available</span>
                </div>
                <div class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300 text-[11px] sm:text-xs">
                    <span class="w-3 h-3 rounded bg-lime-500 text-slate-950 flex items-center justify-center text-[9px] font-bold">✓</span>
                    <span class="text-lime-700 dark:text-lime-400 font-semibold">Selected</span>
                </div>
                <div class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300 text-[11px] sm:text-xs">
                    <span class="w-3 h-3 rounded bg-amber-500/20 border border-amber-500/40 text-amber-600 dark:text-amber-400 flex items-center justify-center text-[9px]">⚡</span>
                    <span class="text-amber-700 dark:text-amber-300 font-medium">Open Play</span>
                </div>
                <div class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300 text-[11px] sm:text-xs">
                    <span class="w-3 h-3 rounded bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400 flex items-center justify-center text-[9px]">⏳</span>
                    <span>Awaiting</span>
                </div>
                <div class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300 text-[11px] sm:text-xs">
                    <span class="w-3 h-3 rounded bg-slate-200 dark:bg-slate-800/60 border border-slate-300 dark:border-slate-700 text-slate-500 flex items-center justify-center text-[9px]">🔒</span>
                    <span class="text-slate-500 dark:text-slate-400">Reserved</span>
                </div>
                <div class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300 text-[11px] sm:text-xs">
                    <span class="w-3 h-3 rounded bg-red-100 dark:bg-red-950/40 border border-red-200 dark:border-red-900/40 text-red-600 dark:text-red-400 flex items-center justify-center text-[9px]">🛠️</span>
                    <span class="text-red-600 dark:text-red-400/80">Maintenance</span>
                </div>
            </div>

            <div class="text-slate-500 dark:text-slate-400 flex items-center gap-1.5 text-[11px] sm:text-xs">
                <span class="inline-block w-2 h-2 rounded-full bg-lime-500 dark:bg-lime-400"></span>
                <span>Peak Evening: <strong class="text-slate-700 dark:text-slate-300">6:00 PM – 10:00 PM (+20%)</strong></span>
            </div>
        </div>
    </div>

    <!-- CLOSED FACILITY STATE -->
    @if ($schedule['is_closed'])
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-8 sm:p-12 text-center shadow-xl transition-colors duration-200">
            <div class="w-14 h-14 sm:w-16 sm:h-16 bg-red-500/10 border border-red-500/20 rounded-2xl flex items-center justify-center text-2xl sm:text-3xl mx-auto mb-4">
                ⛔
            </div>
            <h3 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white">Venue Closed on {{ $schedule['date_formatted'] }}</h3>
            <p class="text-slate-500 dark:text-slate-400 mt-2 max-w-md mx-auto text-xs sm:text-sm">
                LYR Pickleball Club is scheduled as closed for this day. Please pick another date above to view availability.
            </p>
        </div>
    @else
        <!-- MAIN INTERACTIVE MATRIX SCHEDULE GRID -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl sm:rounded-2xl overflow-hidden shadow-xl transition-colors duration-200">
            <div class="overflow-x-auto scrollbar-thin scrollbar-thumb-slate-300 dark:scrollbar-thumb-slate-700" style="-webkit-overflow-scrolling: touch;">
                <table class="w-full border-collapse text-left">
                    <!-- COURT MATRIX HEADER (X-AXIS) -->
                    <thead>
                        <tr class="bg-slate-100 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
                            <!-- Time column header -->
                            <th class="sticky left-0 z-20 bg-slate-100 dark:bg-slate-950 p-2.5 sm:p-4 min-w-[90px] w-[95px] sm:min-w-[120px] sm:w-[130px] border-r border-slate-200 dark:border-slate-800">
                                <div class="text-[10px] sm:text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Time Slot
                                </div>
                                <div class="text-[11px] sm:text-xs font-bold text-lime-700 dark:text-lime-400 mt-0.5 truncate">
                                    {{ $schedule['date_formatted'] }}
                                </div>
                            </th>

                            <!-- Dynamic Court Columns -->
                            @foreach ($schedule['courts'] as $court)
                                <th class="p-2.5 sm:p-4 min-w-[135px] sm:min-w-[190px] border-r border-slate-200 dark:border-slate-800/80 last:border-r-0">
                                    <div class="flex items-center justify-between gap-1.5">
                                        <span class="text-sm sm:text-base font-black text-slate-900 dark:text-white tracking-wide truncate">
                                            {{ $court->name }}
                                        </span>
                                        @if ($court->is_indoor)
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 border border-cyan-500/20 flex-shrink-0">
                                                A/C
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex items-center justify-between mt-1 text-[11px] sm:text-xs">
                                        <span class="text-slate-500 dark:text-slate-400 font-medium truncate">
                                            {{ $court->surface_type }}
                                        </span>
                                        <span class="px-1.5 sm:px-2 py-0.5 rounded-full bg-slate-200 dark:bg-slate-800 text-slate-900 dark:text-lime-400 font-bold text-[10px] sm:text-[11px] border border-slate-300 dark:border-slate-700 flex-shrink-0">
                                            ₱{{ number_format((float)$court->hourly_rate, 0) }}/hr
                                        </span>
                                    </div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    <!-- TIME SLOTS MATRIX BODY (Y-AXIS) -->
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60">
                        @foreach ($schedule['time_slots'] as $slot)
                            @php
                                $slotKey = $slot['key'];
                                $isEveningPeak = ($slot['hour'] >= 18 && $slot['hour'] < 22);
                            @endphp
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-850/40 transition">
                                <!-- TIME LABEL CELL (Sticky Left) -->
                                <td class="sticky left-0 z-10 bg-slate-50/95 dark:bg-slate-950/95 p-2 sm:p-3.5 border-r border-slate-200 dark:border-slate-800 align-middle">
                                    <div class="flex flex-col">
                                        <span class="text-[11px] sm:text-xs font-bold text-slate-900 dark:text-white whitespace-nowrap">
                                            {{ $slot['start_label'] }}
                                        </span>
                                        <span class="text-[9px] sm:text-[10px] text-slate-500 dark:text-slate-500 whitespace-nowrap">
                                            to {{ $slot['end_label'] }}
                                        </span>
                                        @if ($isEveningPeak)
                                            <span class="mt-0.5 inline-flex items-center gap-0.5 text-[8px] sm:text-[9px] font-bold text-amber-700 dark:text-amber-400 bg-amber-500/10 px-1 py-0.5 rounded border border-amber-500/20 w-fit">
                                                ⚡ Peak
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                <!-- COURT SLOTS -->
                                @foreach ($schedule['courts'] as $court)
                                    @php
                                        $courtId = $court->id;
                                        $cell = $schedule['cells'][$slotKey][$courtId] ?? null;
                                        $slotSelectionKey = "{$courtId}_{$schedule['date']}_{$slot['start_time']}";
                                        $isSelected = isset($selectedSlots[$slotSelectionKey]);
                                    @endphp

                                    <td class="p-1.5 sm:p-2.5 border-r border-slate-200 dark:border-slate-800/60 last:border-r-0 align-middle">
                                        @if (! $cell)
                                            <div class="h-14 sm:h-16 rounded-xl bg-slate-100/50 dark:bg-slate-950/40 border border-slate-200 dark:border-slate-800/40 flex items-center justify-center text-xs text-slate-400 dark:text-slate-600">
                                                —
                                            </div>

                                        {{-- 1. SELECTED BY USER --}}
                                        @elseif ($isSelected)
                                            <button
                                                type="button"
                                                wire:click="toggleSlot({{ $courtId }}, '{{ $schedule['date'] }}', '{{ $slot['start_time'] }}', '{{ $slot['end_time'] }}', {{ $cell['price'] }}, {{ $cell['base_rate'] }}, '{{ addslashes($court->name) }}', '{{ $slot['label'] }}', {{ $cell['is_peak'] ? 'true' : 'false' }}, '{{ addslashes($cell['rule_name'] ?? '') }}')"
                                                class="w-full h-14 sm:h-16 rounded-xl bg-lime-500 text-slate-950 p-2 sm:p-2.5 flex flex-col justify-between font-bold border-2 border-lime-400 dark:border-lime-300 shadow-md shadow-lime-500/30 transition transform scale-[0.98] cursor-pointer"
                                            >
                                                <div class="flex items-center justify-between text-xs leading-none">
                                                    <span class="uppercase tracking-wider text-[9px] sm:text-[10px] font-black">SELECTED</span>
                                                    <span class="w-3.5 h-3.5 sm:w-4 sm:h-4 rounded-full bg-slate-950 text-lime-400 flex items-center justify-center text-[9px] sm:text-[10px]">✓</span>
                                                </div>
                                                <div class="flex items-end justify-between leading-none">
                                                    <span class="text-[10px] sm:text-[11px] font-semibold text-slate-900">{{ $slot['start_label'] }}</span>
                                                    <span class="text-xs sm:text-sm font-black">₱{{ number_format((float)$cell['price'], 0) }}</span>
                                                </div>
                                            </button>

                                        {{-- 2. AVAILABLE SLOT --}}
                                        @elseif ($cell['state'] === 'available')
                                            <button
                                                type="button"
                                                wire:click="toggleSlot({{ $courtId }}, '{{ $schedule['date'] }}', '{{ $slot['start_time'] }}', '{{ $slot['end_time'] }}', {{ $cell['price'] }}, {{ $cell['base_rate'] }}, '{{ addslashes($court->name) }}', '{{ $slot['label'] }}', {{ $cell['is_peak'] ? 'true' : 'false' }}, '{{ addslashes($cell['rule_name'] ?? '') }}')"
                                                class="w-full h-14 sm:h-16 rounded-xl bg-slate-50/90 hover:bg-lime-50/90 border border-slate-200 hover:border-lime-500/60 dark:bg-slate-950/80 dark:hover:bg-slate-800 dark:border-slate-800 dark:hover:border-lime-500/60 p-2 sm:p-2.5 flex flex-col justify-between text-left transition group cursor-pointer shadow-2xs"
                                            >
                                                <div class="flex items-center justify-between text-xs">
                                                    <span class="text-[10px] sm:text-[11px] font-semibold text-slate-500 dark:text-slate-400 group-hover:text-lime-700 dark:group-hover:text-lime-400 transition">
                                                        Available
                                                    </span>
                                                    @if ($cell['is_peak'])
                                                        <span class="text-[8px] sm:text-[9px] font-bold text-amber-700 dark:text-amber-400 bg-amber-500/10 px-1 py-0.5 rounded">
                                                            Peak
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="flex items-end justify-between">
                                                    <span class="text-[9px] sm:text-[10px] text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300 transition">
                                                        Select
                                                    </span>
                                                    <span class="text-xs sm:text-sm font-extrabold text-slate-900 dark:text-white group-hover:text-lime-600 dark:group-hover:text-lime-400 transition">
                                                        ₱{{ number_format((float)$cell['price'], 0) }}
                                                    </span>
                                                </div>
                                            </button>

                                        {{-- 3. OPEN PLAY EVENT --}}
                                        @elseif ($cell['state'] === 'open_play')
                                            <button
                                                type="button"
                                                wire:click="viewOpenPlayDetails({{ $cell['override_id'] }})"
                                                class="w-full h-14 sm:h-16 rounded-xl bg-amber-50 hover:bg-amber-100/90 border border-amber-300 hover:border-amber-500 dark:bg-amber-950/30 dark:hover:bg-amber-900/40 dark:border-amber-500/30 dark:hover:border-amber-400 p-2 sm:p-2.5 flex flex-col justify-between text-left transition group shadow-2xs cursor-pointer"
                                            >
                                                <div class="flex items-center justify-between">
                                                    <span class="inline-flex items-center gap-1 text-[9px] sm:text-[10px] font-black uppercase tracking-wider text-amber-800 dark:text-amber-300 bg-amber-500/20 px-1.5 py-0.5 rounded">
                                                        ⚡ OPEN PLAY
                                                    </span>
                                                    <span class="text-[9px] sm:text-[10px] font-bold text-emerald-700 dark:text-lime-400">
                                                        ₱{{ number_format((float)$cell['fee_per_person'], 0) }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center justify-between text-[10px] sm:text-[11px] text-amber-900 dark:text-amber-200/90 truncate">
                                                    <span class="truncate font-semibold">{{ $cell['title'] }}</span>
                                                    <span class="text-[9px] text-amber-700 dark:text-amber-400 underline font-medium ml-1 flex-shrink-0">Info</span>
                                                </div>
                                            </button>

                                        {{-- 4. AWAITING / PENDING PAYMENT --}}
                                        @elseif ($cell['state'] === 'pending')
                                            <div class="w-full h-14 sm:h-16 rounded-xl bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-500/20 p-2 sm:p-2.5 flex flex-col justify-between text-left">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-[9px] sm:text-[10px] font-bold text-amber-700 dark:text-amber-400 flex items-center gap-1">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 dark:bg-amber-400 animate-ping"></span>
                                                        Awaiting
                                                    </span>
                                                    <span class="text-[9px] sm:text-[10px] text-amber-800 dark:text-amber-300 font-mono font-bold">{{ $cell['reference_code'] }}</span>
                                                </div>
                                                <div class="text-[9px] sm:text-[10px] text-slate-500 dark:text-slate-400 truncate">
                                                    {{ $cell['customer_name'] }}
                                                </div>
                                            </div>

                                        {{-- 5. CONFIRMED / BOOKED --}}
                                        @elseif ($cell['state'] === 'booked')
                                            <div class="w-full h-14 sm:h-16 rounded-xl bg-slate-100/90 dark:bg-slate-950/90 border border-slate-200 dark:border-slate-800 p-2 sm:p-2.5 flex flex-col justify-between text-left opacity-80">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider text-slate-500 flex items-center gap-1">
                                                        🔒 Reserved
                                                    </span>
                                                </div>
                                                <div class="text-[9px] sm:text-[10px] text-slate-500 truncate">
                                                    {{ $cell['reference_code'] }}
                                                </div>
                                            </div>

                                        {{-- 6. MAINTENANCE / BLOCKED --}}
                                        @elseif ($cell['state'] === 'blocked')
                                            <button
                                                type="button"
                                                wire:click="viewBlockedDetails({{ $cell['override_id'] }})"
                                                class="w-full h-14 sm:h-16 rounded-xl bg-red-50 hover:bg-red-100/80 border border-red-200 hover:border-red-300 dark:bg-red-950/20 dark:border-red-900/30 dark:hover:border-red-800/60 p-2 sm:p-2.5 flex flex-col justify-between text-left transition group cursor-pointer"
                                            >
                                                <div class="flex items-center justify-between">
                                                    <span class="text-[9px] sm:text-[10px] font-bold text-red-700 dark:text-red-400/90 flex items-center gap-1">
                                                        🛠️ {{ $cell['state_label'] }}
                                                    </span>
                                                    <span class="text-[9px] text-red-700 dark:text-red-400 underline font-medium">Details</span>
                                                </div>
                                                <div class="text-[9px] sm:text-[10px] text-red-600 dark:text-red-300/60 truncate">
                                                    {{ $cell['reason'] }}
                                                </div>
                                            </button>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- FLOATING BOOKING SUMMARY DOCK (WHEN SLOTS ARE SELECTED) -->
    @if ($selectedCount > 0)
        <div class="fixed bottom-3 inset-x-2 sm:bottom-6 sm:inset-x-auto sm:right-8 sm:max-w-xl z-40 animate-bounce-short">
            <div class="bg-white/95 dark:bg-slate-900/95 backdrop-blur-xl border-2 border-lime-500 rounded-xl sm:rounded-2xl p-3 sm:p-5 shadow-2xl shadow-lime-500/20 text-slate-900 dark:text-white flex flex-col sm:flex-row items-center justify-between gap-3 sm:gap-4 transition-colors duration-200">
                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl bg-lime-500 text-slate-950 flex items-center justify-center font-black text-lg sm:text-xl flex-shrink-0 shadow-md shadow-lime-500/30">
                        {{ $selectedCount }}
                    </div>
                    <div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                            {{ $selectedCount === 1 ? '1 slot selected' : "{$selectedCount} slots selected" }}
                        </div>
                        <div class="text-lg sm:text-2xl font-black text-slate-900 dark:text-white flex items-baseline gap-2">
                            <span>₱{{ number_format($selectedTotal, 2) }}</span>
                            @if ($selectedAdjustment > 0)
                                <span class="text-[11px] sm:text-xs text-amber-700 dark:text-amber-400 font-semibold">(incl. peak rate)</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                    <button
                        type="button"
                        wire:click="clearSelection"
                        class="px-3 py-2 sm:py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold transition cursor-pointer"
                    >
                        Clear
                    </button>
                    <button
                        type="button"
                        wire:click="openCheckout"
                        class="flex-1 sm:flex-initial px-5 sm:px-6 py-2 sm:py-2.5 rounded-xl bg-lime-500 hover:bg-lime-400 text-slate-950 font-black text-xs sm:text-sm uppercase tracking-wider transition shadow-md shadow-lime-500/30 flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <span>Book Now</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- ZERO-FRICTION CHECKOUT MODAL -->
    @if ($showCheckoutModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/75 backdrop-blur-sm flex items-center justify-center p-2 sm:p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl sm:rounded-3xl max-w-xl w-full p-4 sm:p-8 shadow-2xl text-slate-900 dark:text-white relative animate-in fade-in zoom-in-95 transition-colors duration-200 max-h-[92vh] overflow-y-auto">
                <!-- Close Button -->
                <button
                    type="button"
                    wire:click="closeCheckout"
                    class="absolute top-4 right-4 sm:top-6 sm:right-6 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white p-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800/60 dark:hover:bg-slate-800 transition cursor-pointer"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>

                <div class="flex items-center gap-2.5 sm:gap-3 mb-4 sm:mb-6 pr-10">
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-lime-500/20 text-lime-700 dark:text-lime-400 flex items-center justify-center text-lg sm:text-xl font-bold flex-shrink-0">
                        ⚡
                    </div>
                    <div>
                        <h3 class="text-lg sm:text-xl font-black uppercase tracking-tight text-slate-900 dark:text-white">
                            Complete Court Reservation
                        </h3>
                        <p class="text-[11px] sm:text-xs text-slate-500 dark:text-slate-400">
                            Zero friction, instant booking confirmation. No account registration needed.
                        </p>
                    </div>
                </div>

                @if ($errors->has('checkout_error') || $errors->has('slots'))
                    <div class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/30 text-red-600 dark:text-red-400 text-xs">
                        {{ $errors->first('checkout_error') ?: $errors->first('slots') }}
                    </div>
                @endif

                <!-- SELECTED SLOTS BREAKDOWN -->
                <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl sm:rounded-2xl p-3.5 sm:p-4 mb-4 sm:mb-6 space-y-2">
                    <div class="text-[10px] sm:text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5 sm:mb-2">
                        Reservation Summary ({{ count($selectedSlots) }} Slots)
                    </div>
                    <div class="max-h-32 sm:max-h-36 overflow-y-auto space-y-1.5 sm:space-y-2 pr-1 scrollbar-thin scrollbar-thumb-slate-300 dark:scrollbar-thumb-slate-800">
                        @foreach ($selectedSlots as $s)
                            <div class="flex items-center justify-between text-xs py-1.5 border-b border-slate-200 dark:border-slate-900 last:border-b-0">
                                <div>
                                    <div class="font-bold text-slate-900 dark:text-white">{{ $s['court_name'] }}</div>
                                    <div class="text-slate-500 dark:text-slate-400 text-[10px] sm:text-[11px]">{{ $s['date'] }} • {{ $s['time_label'] }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-lime-600 dark:text-lime-400">₱{{ number_format((float)$s['price'], 2) }}</div>
                                    @if ($s['is_peak'])
                                        <span class="text-[9px] text-amber-700 dark:text-amber-400 bg-amber-500/10 px-1 py-0.5 rounded">Peak Rate</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="pt-2 sm:pt-3 mt-1.5 sm:mt-2 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs sm:text-sm">
                        <span class="font-bold text-slate-600 dark:text-slate-300">Total Amount Due:</span>
                        <span class="text-lg sm:text-xl font-black text-lime-600 dark:text-lime-400">₱{{ number_format($selectedTotal, 2) }}</span>
                    </div>
                </div>

                <!-- CUSTOMER DETAILS FORM -->
                <form wire:submit="submitBooking" class="space-y-3 sm:space-y-4">
                    <div>
                        <label class="block text-[11px] sm:text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Full Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            wire:model="customerName"
                            placeholder="e.g. Juan Dela Cruz"
                            class="w-full px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 text-xs sm:text-sm focus:outline-none focus:border-lime-500 dark:focus:border-lime-400 focus:ring-1 focus:ring-lime-500 transition"
                        />
                        @error('customerName') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] sm:text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                Email Address <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="email"
                                wire:model="customerEmail"
                                placeholder="juan@example.com"
                                class="w-full px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 text-xs sm:text-sm focus:outline-none focus:border-lime-500 dark:focus:border-lime-400 focus:ring-1 focus:ring-lime-500 transition"
                            />
                            @error('customerEmail') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-[11px] sm:text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                Mobile Number <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="tel"
                                wire:model="customerPhone"
                                placeholder="09171234567"
                                class="w-full px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 text-xs sm:text-sm focus:outline-none focus:border-lime-500 dark:focus:border-lime-400 focus:ring-1 focus:ring-lime-500 transition"
                            />
                            @error('customerPhone') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- PAYMENT METHOD -->
                    <div>
                        <label class="block text-[11px] sm:text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5 sm:mb-2">
                            Payment Method
                        </label>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            <label class="cursor-pointer">
                                <input type="radio" wire:model.live="paymentMethod" value="gcash" class="peer sr-only">
                                <div class="p-2.5 sm:p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 peer-checked:border-blue-500 peer-checked:bg-blue-500/10 peer-checked:text-blue-600 dark:peer-checked:text-blue-400 text-center text-xs font-bold transition">
                                    GCash
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" wire:model.live="paymentMethod" value="maya" class="peer sr-only">
                                <div class="p-2.5 sm:p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 peer-checked:border-emerald-500 peer-checked:bg-emerald-500/10 peer-checked:text-emerald-600 dark:peer-checked:text-emerald-400 text-center text-xs font-bold transition">
                                    Maya
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" wire:model.live="paymentMethod" value="bank_transfer" class="peer sr-only">
                                <div class="p-2.5 sm:p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 peer-checked:border-purple-500 peer-checked:bg-purple-500/10 peer-checked:text-purple-600 dark:peer-checked:text-purple-400 text-center text-xs font-bold transition">
                                    Bank Transfer
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" wire:model.live="paymentMethod" value="cash" class="peer sr-only">
                                <div class="p-2.5 sm:p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 peer-checked:border-lime-500 peer-checked:bg-lime-500/10 peer-checked:text-lime-700 dark:peer-checked:text-lime-400 text-center text-xs font-bold transition">
                                    Cash at Desk
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- PAYMENT INFO PREVIEW -->
                    <div class="p-2.5 sm:p-3 rounded-xl bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800/80 text-xs text-slate-600 dark:text-slate-400">
                        @if ($paymentMethod === 'gcash')
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-blue-600 dark:text-blue-400">GCash Account:</span> 0917-888-9999 (LYR Pickleball Club)
                            </div>
                        @elseif ($paymentMethod === 'maya')
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-emerald-600 dark:text-emerald-400">Maya Merchant:</span> @LYRPickleClub / 0917-888-9999
                            </div>
                        @elseif ($paymentMethod === 'bank_transfer')
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-purple-600 dark:text-purple-400">BDO Account:</span> 001234567890 (LYR Sports Inc.)
                            </div>
                        @else
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-lime-700 dark:text-lime-400">Cash Payment:</span> Settle fee at the front desk 15 mins before game time.
                            </div>
                        @endif
                    </div>

                    <!-- PROOF OF PAYMENT UPLOAD -->
                    <div>
                        <label class="block text-[11px] sm:text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Proof of Payment Image
                            <span class="text-slate-400 font-normal normal-case ml-1">(Optional / Recommended)</span>
                        </label>
                        <div class="bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl p-2.5 sm:p-3">
                            <x-upload
                                wire:model="paymentProof"
                                placeholder="Drop screenshot/receipt image or click to browse"
                                hint="Upload GCash / Maya confirmation screenshot (PNG, JPG, WEBP max 5MB)"
                                accept="image/png,image/jpeg,image/jpg,image/webp"
                                delete
                            />
                        </div>
                        @error('paymentProof') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-[11px] sm:text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Special Requests / Notes (Optional)
                        </label>
                        <textarea
                            wire:model="notes"
                            rows="2"
                            placeholder="Need paddle rentals, extra balls, or special setup..."
                            class="w-full px-3 sm:px-4 py-2 sm:py-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 text-xs sm:text-sm focus:outline-none focus:border-lime-500 dark:focus:border-lime-400 focus:ring-1 focus:ring-lime-500 transition"
                        ></textarea>
                    </div>

                    <div class="pt-2">
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="w-full py-3.5 sm:py-4 rounded-xl sm:rounded-2xl bg-lime-500 hover:bg-lime-400 disabled:opacity-50 text-slate-950 font-black text-sm sm:text-base uppercase tracking-wider shadow-lg shadow-lime-500/30 transition flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <span wire:loading.remove>Confirm & Reserve Court</span>
                            <span wire:loading class="inline-flex items-center gap-2">
                                <svg class="animate-spin h-5 w-5 text-slate-950" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                Securing your slots...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- BOOKING CONFIRMATION SUCCESS MODAL -->
    @if ($showConfirmationModal && $confirmedBooking)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-2 sm:p-4">
            <div class="bg-white dark:bg-slate-900 border border-lime-500/40 rounded-2xl sm:rounded-3xl max-w-lg w-full p-4 sm:p-8 shadow-2xl text-slate-900 dark:text-white relative text-center animate-in zoom-in-95 transition-colors duration-200 max-h-[92vh] overflow-y-auto">
                <div class="w-12 h-12 sm:w-16 sm:h-16 rounded-full bg-lime-500/20 text-lime-700 dark:text-lime-400 border border-lime-500/40 flex items-center justify-center text-2xl sm:text-3xl mx-auto mb-3 sm:mb-4 shadow-md shadow-lime-500/20">
                    ✓
                </div>

                <h3 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                    Reservation Confirmed!
                </h3>
                <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm mt-1">
                    Your court reservation has been recorded. Please save your Reference Code.
                </p>

                <!-- REFERENCE CODE BADGE -->
                <div class="my-4 sm:my-6 p-3.5 sm:p-4 rounded-xl sm:rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 flex flex-col items-center justify-center gap-1">
                    <span class="text-[10px] sm:text-[11px] font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400">Reference Code</span>
                    <span class="text-2xl sm:text-3xl font-black text-lime-600 dark:text-lime-400 font-mono tracking-wider">{{ $confirmedBooking['reference_code'] }}</span>
                    <span class="text-[10px] text-slate-500">Show this code at the reception desk</span>
                </div>

                <!-- SUMMARY DETAILS -->
                <div class="bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80 rounded-xl p-3.5 sm:p-4 text-left text-xs space-y-2 mb-4 sm:mb-6">
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400">Customer:</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ $confirmedBooking['customer_name'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400">Phone:</span>
                        <span class="font-medium text-slate-900 dark:text-white">{{ $confirmedBooking['customer_phone'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400">Total Amount:</span>
                        <span class="font-black text-lime-600 dark:text-lime-400">₱{{ number_format($confirmedBooking['total_amount'], 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400">Payment:</span>
                        <span class="uppercase font-bold text-slate-900 dark:text-white">{{ $confirmedBooking['payment_method'] }} ({{ $confirmedBooking['status'] }})</span>
                    </div>
                    @if (!empty($confirmedBooking['proof_of_payment_url']))
                        <div class="pt-2 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
                            <span class="text-emerald-700 dark:text-emerald-400 font-semibold flex items-center gap-1">
                                <span>📎</span> Proof of Payment:
                            </span>
                            <a href="{{ $confirmedBooking['proof_of_payment_url'] }}" target="_blank" class="text-lime-600 dark:text-lime-400 hover:underline font-bold">
                                View Receipt ↗
                            </a>
                        </div>
                    @endif
                </div>

                <div class="flex flex-col sm:flex-row items-center gap-2 sm:gap-3">
                    <a
                        href="{{ route('booking.receipt', $confirmedBooking['reference_code']) }}"
                        target="_blank"
                        class="w-full py-2.5 sm:py-3 rounded-xl bg-slate-900 hover:bg-slate-800 text-white dark:bg-slate-800 dark:hover:bg-slate-700 font-bold text-xs uppercase tracking-wider transition border border-slate-700 flex items-center justify-center gap-2"
                    >
                        <span>View / Print Receipt</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                    <button
                        type="button"
                        wire:click="$set('showConfirmationModal', false)"
                        class="w-full py-2.5 sm:py-3 rounded-xl bg-lime-500 hover:bg-lime-400 text-slate-950 font-black text-xs uppercase tracking-wider transition shadow-md shadow-lime-500/20 cursor-pointer"
                    >
                        Done
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- OPEN PLAY DETAILS MODAL -->
    @if ($showOpenPlayModal && $activeOpenPlay)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/75 backdrop-blur-sm flex items-center justify-center p-2 sm:p-4">
            <div class="bg-white dark:bg-slate-900 border border-amber-500/40 rounded-2xl sm:rounded-3xl max-w-md w-full p-4 sm:p-8 shadow-2xl text-slate-900 dark:text-white relative animate-in zoom-in-95 transition-colors duration-200 max-h-[92vh] overflow-y-auto">
                <button
                    type="button"
                    wire:click="$set('showOpenPlayModal', false)"
                    class="absolute top-4 right-4 sm:top-6 sm:right-6 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white p-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800/60 transition cursor-pointer"
                >
                    ✕
                </button>

                <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/40 flex items-center justify-center text-xl sm:text-2xl mb-3 sm:mb-4">
                    ⚡
                </div>

                <div class="inline-block px-2.5 py-0.5 rounded-full text-[9px] sm:text-[10px] font-black uppercase tracking-wider bg-amber-500/20 text-amber-800 dark:text-amber-300 border border-amber-500/30 mb-2">
                    Open Play Rally Event
                </div>
                <h3 class="text-lg sm:text-xl font-black text-slate-900 dark:text-white">
                    {{ $activeOpenPlay['title'] }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    {{ $activeOpenPlay['date'] }} • {{ $activeOpenPlay['time_label'] }}
                </p>

                <div class="my-4 sm:my-5 space-y-2.5 sm:space-y-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl sm:rounded-2xl p-3.5 sm:p-4 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400">Courts:</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ $activeOpenPlay['court_name'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400">Admission Fee:</span>
                        <span class="font-black text-lime-600 dark:text-lime-400">₱{{ number_format($activeOpenPlay['fee_per_person'], 2) }} / player</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400">Player Capacity:</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ $activeOpenPlay['max_participants'] }} players max</span>
                    </div>
                    <div class="pt-2 border-t border-slate-200 dark:border-slate-800">
                        <span class="text-slate-500 dark:text-slate-400 block mb-1">Event Details & Format:</span>
                        <p class="text-slate-700 dark:text-slate-300 italic">{{ $activeOpenPlay['reason'] }}</p>
                    </div>
                </div>

                <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-800 dark:text-amber-300 text-xs mb-4">
                    🎾 <strong>How to Join:</strong> Walk-in registration available at the reception desk. Paddle stack system in effect.
                </div>

                <button
                    type="button"
                    wire:click="$set('showOpenPlayModal', false)"
                    class="w-full py-2.5 sm:py-3 rounded-xl bg-slate-900 hover:bg-slate-800 text-white dark:bg-slate-800 dark:hover:bg-slate-700 font-bold text-xs uppercase tracking-wider transition border border-slate-700 cursor-pointer"
                >
                    Close
                </button>
            </div>
        </div>
    @endif

    <!-- MAINTENANCE / BLOCKED DETAILS MODAL -->
    @if ($showBlockedModal && $activeBlocked)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/75 backdrop-blur-sm flex items-center justify-center p-2 sm:p-4">
            <div class="bg-white dark:bg-slate-900 border border-red-500/40 rounded-2xl sm:rounded-3xl max-w-md w-full p-4 sm:p-8 shadow-2xl text-slate-900 dark:text-white relative animate-in zoom-in-95 transition-colors duration-200 max-h-[92vh] overflow-y-auto">
                <button
                    type="button"
                    wire:click="$set('showBlockedModal', false)"
                    class="absolute top-4 right-4 sm:top-6 sm:right-6 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white p-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800/60 transition cursor-pointer"
                >
                    ✕
                </button>

                <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl sm:rounded-2xl bg-red-500/20 text-red-600 dark:text-red-400 border border-red-500/40 flex items-center justify-center text-xl sm:text-2xl mb-3 sm:mb-4">
                    🛠️
                </div>

                <h3 class="text-lg sm:text-xl font-black text-slate-900 dark:text-white">
                    {{ $activeBlocked['title'] }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    {{ $activeBlocked['date'] }} • {{ $activeBlocked['time_label'] }}
                </p>

                <div class="my-4 sm:my-5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl sm:rounded-2xl p-3.5 sm:p-4 text-xs space-y-2">
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400">Affected Court:</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ $activeBlocked['court_name'] }}</span>
                    </div>
                    <div>
                        <span class="text-slate-500 dark:text-slate-400 block mb-1">Reason:</span>
                        <p class="text-slate-700 dark:text-slate-300">{{ $activeBlocked['reason'] }}</p>
                    </div>
                </div>

                <button
                    type="button"
                    wire:click="$set('showBlockedModal', false)"
                    class="w-full py-2.5 sm:py-3 rounded-xl bg-slate-900 hover:bg-slate-800 text-white dark:bg-slate-800 dark:hover:bg-slate-700 font-bold text-xs uppercase tracking-wider transition border border-slate-700 cursor-pointer"
                >
                    Close
                </button>
            </div>
        </div>
    @endif

</div>
