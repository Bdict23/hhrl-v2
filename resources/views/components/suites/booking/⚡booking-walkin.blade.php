<?php

use Livewire\Component;
use App\Events\CheckInUpdated;
use App\Events\NewBookingReceived;
use App\Models\Suite\RoomAddonService as AddonService;
use App\Models\Suite\RoomBooking as Booking;
use App\Models\Suite\RoomBookingAddon as BookingAddon;
use App\Models\Suite\Room;
use App\Models\Suite\RoomCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

new class extends Component
{
     public string $bookingType = 'overnight';
    public string $checkIn = '';
    public string $checkOut = '';
    public ?int $selectedRoomId = null;
    public ?int $selectedCategoryId = null;

    public string $guestName = '';
    public string $guestPhone = '';
    public string $guestEmail = '';
    public ?int $userId = null;

    public int $adults = 1;
    public int $children = 0;
    public int $pets = 0;
    public string $specialRequests = '';

    public array $addonQuantities = [];
    public string $paymentOption = 'pay_now'; // 'pay_now', 'partial', 'pay_later'
    public string $paymentMethod = 'cash'; // 'cash', 'card', 'gcash', 'bank_transfer'
    public ?float $partialAmount = null;
    public bool $checkInNow = true;

    public function mount(): void
    {
        $this->checkIn = now()->format('Y-m-d\TH:i');
        $this->checkOut = now()->addDay()->format('Y-m-d\TH:i');
    }

    public function updatedBookingType(): void
    {
        $cIn = Carbon::parse($this->checkIn);
        if ($this->bookingType === 'day_pass') {
            $this->checkOut = $cIn->copy()->setTime(17, 0)->format('Y-m-d\TH:i');
        } else {
            $this->checkOut = $cIn->copy()->addDay()->setTime(12, 0)->format('Y-m-d\TH:i');
        }
    }

    public function updatedCheckIn(): void
    {
        $this->validateDates();
        $cIn = Carbon::parse($this->checkIn);
        if (! $cIn->isToday()) {
            $this->checkInNow = false;
        }
        // Reset room selection when dates change
        $this->selectedRoomId = null;
    }

    public function updatedCheckOut(): void
    {
        $this->validateDates();
        // Reset room selection when dates change
        $this->selectedRoomId = null;
    }

    protected function validateDates(): void
    {
        try {
            $cIn = Carbon::parse($this->checkIn);
            if ($this->bookingType === 'day_pass') {
                $this->checkOut = $cIn->copy()->setTime(17, 0)->format('Y-m-d\TH:i');

                return;
            }

            $cOut = Carbon::parse($this->checkOut);

            if ($cOut->lte($cIn)) {
                $this->checkOut = $cIn->copy()->addDay()->setTime(12, 0)->format('Y-m-d\TH:i');
            }
        } catch (\Throwable) {
            $this->checkIn = now()->format('Y-m-d\TH:i');
            $this->checkOut = now()->addDay()->format('Y-m-d\TH:i');
        }
    }

    public function selectRoom(int $roomId): void
    {
        $this->selectedRoomId = $roomId;
    }

    public function setAddonQty(int $addonId, int $qty): void
    {
        if ($qty <= 0) {
            unset($this->addonQuantities[$addonId]);
        } else {
            $this->addonQuantities[$addonId] = min(20, $qty);
        }
    }

    public function getNightsProperty(): int
    {
        if ($this->bookingType === 'day_pass') {
            return 1;
        }

        try {
            $cIn = Carbon::parse($this->checkIn);
            $cOut = Carbon::parse($this->checkOut);

            return max(1, (int) $cIn->diffInDays($cOut));
        } catch (\Throwable) {
            return 1;
        }
    }

    public function getCategoriesProperty(): EloquentCollection
    {
        return RoomCategory::where('is_active', true)->orderBy('name')->get();
    }

    public function getCustomersProperty(): EloquentCollection
    {
        return User::where('role', 'customer')->orderBy('name')->get();
    }

    public function getAvailableAddonsProperty(): EloquentCollection
    {
        return AddonService::where('is_active', true)->orderBy('sort_order')->get();
    }

    public function getAvailableRoomsProperty(): Collection
    {
        try {
            $cIn = Carbon::parse($this->checkIn);
            $cOut = Carbon::parse($this->checkOut);
        } catch (\Throwable) {
            $cIn = now();
            $cOut = now()->addDay();
        }

        return Room::with(['category', 'photos'])
            ->where('is_active', true)
            ->whereNotIn('status', ['maintenance', 'out_of_service', 'blocked'])
            ->when($this->selectedCategoryId, fn ($q) => $q->where('category_id', $this->selectedCategoryId))
            ->when($this->bookingType === 'day_pass', fn ($q) => $q->whereIn('booking_type', ['day_pass', 'both']))
            ->when($this->bookingType === 'overnight', fn ($q) => $q->whereIn('booking_type', ['overnight', 'both']))
            ->orderBy('category_id')
            ->orderBy('room_number')
            ->get()
            ->map(function (Room $room) use ($cIn, $cOut) {
                $isAvailable = $room->isAvailableFor($cIn, $cOut);
                $capacityOk = $room->capacity_adults >= max(1, $this->adults);
                $petOk = $this->pets === 0 || $room->allows_pets;
                $room->can_be_booked = $isAvailable && $capacityOk && $petOk;
                $room->availability_issue = ! $isAvailable
                    ? 'Booked for these dates'
                    : (! $capacityOk ? 'Exceeds adult capacity' : (! $petOk ? 'Pets not allowed' : null));
                return $room;
            });
    }

    public function getSelectedRoomProperty(): ?Room
    {
        if (! $this->selectedRoomId) {
            return null;
        }

        return Room::with(['category', 'photos'])->find($this->selectedRoomId);
    }

    public function getBaseTotalProperty(): float
    {
        if (! $this->selectedRoom) {
            return 0.0;
        }

        return round((float) $this->selectedRoom->base_price * $this->nights, 2);
    }

    public function getPetTotalProperty(): float
    {
        if (! $this->selectedRoom || $this->pets <= 0 || ! $this->selectedRoom->allows_pets) {
            return 0.0;
        }

        return round((float) $this->selectedRoom->pet_fee * $this->nights * $this->pets, 2);
    }

    public function getAddonsTotalProperty(): float
    {
        $total = 0.0;
        $addons = $this->availableAddons->keyBy('id');

        foreach ($this->addonQuantities as $addonId => $qty) {
            $qty = (int) $qty;
            if ($qty <= 0 || ! isset($addons[$addonId])) {
                continue;
            }
            $addon = $addons[$addonId];
            $multiplier = match ($addon->pricing_type) {
                'per_night' => $this->nights,
                'per_person' => $this->adults + $this->children,
                default => 1,
            };
            $total += (float) $addon->price * $qty * $multiplier;
        }

        return round($total, 2);
    }

    public function getTotalPriceProperty(): float
    {
        return round($this->baseTotal + $this->petTotal + $this->addonsTotal, 2);
    }

    public function submitWalkin(): void
    {
        $isDayPass = $this->bookingType === 'day_pass';
        if ($isDayPass) {
            try {
                $cIn = Carbon::parse($this->checkIn);
                $this->checkOut = $cIn->copy()->setTime(17, 0)->format('Y-m-d\TH:i');
            } catch (\Throwable) {
            }
        }

        $this->validate([
            'selectedRoomId' => ['required', 'exists:rooms,id'],
            'guestName' => ['required', 'string', 'max:255'],
            'guestPhone' => ['nullable', 'string', 'max:50'],
            'guestEmail' => ['nullable', 'email', 'max:255'],
            'checkIn' => ['required', 'date', 'after_or_equal:today'],
            'checkOut' => [
                'required',
                'date',
                $isDayPass
                    ? function ($attribute, $value, $fail) {
                        try {
                            if (Carbon::parse($value)->toDateString() !== Carbon::parse($this->checkIn)->toDateString()) {
                                $fail('For Day Pass, the Check-Out date must match the Check-In date.');
                            }
                        } catch (\Throwable) {
                            $fail('Invalid date format.');
                        }
                    }
                    : 'after:checkIn',
            ],
            'adults' => ['required', 'integer', 'min:1'],
            'children' => ['nullable', 'integer', 'min:0'],
            'pets' => ['nullable', 'integer', 'min:0'],
            'bookingType' => ['required', 'in:overnight,day_pass'],
            'paymentOption' => ['required', 'in:pay_now,partial,pay_later'],
            'paymentMethod' => ['required', 'in:cash,card,gcash,bank_transfer'],
            'partialAmount' => ['nullable', 'numeric', 'min:0', 'max:'.$this->totalPrice],
        ], [
            'checkOut.after' => 'Check-out date must be after check-in date for overnight stays.',
        ]);

        $room = Room::findOrFail($this->selectedRoomId);
        $cIn = Carbon::parse($this->checkIn);
        $cOut = Carbon::parse($this->checkOut);

        if (! $room->isAvailableFor($cIn, $cOut)) {
            $this->addError('selectedRoomId', 'This room is not available for the selected dates.');
            return;
        }

        if ($this->adults > $room->capacity_adults) {
            $this->addError('adults', "This room's max adult capacity is {$room->capacity_adults}.");
            return;
        }

        if ($this->pets > 0 && ! $room->allows_pets) {
            $this->addError('pets', 'This room does not allow pets.');
            return;
        }

        $total = $this->totalPrice;
        $amountPaid = 0.0;
        $paymentStatus = 'unpaid';

        if ($this->paymentOption === 'pay_now') {
            $amountPaid = $total;
            $paymentStatus = 'paid';
        } elseif ($this->paymentOption === 'partial') {
            $amountPaid = min($total, (float) ($this->partialAmount ?? 0));
            $paymentStatus = ($amountPaid >= $total && $total > 0) ? 'paid' : ($amountPaid > 0 ? 'partial' : 'unpaid');
        } else {
            $amountPaid = 0.0;
            $paymentStatus = 'unpaid';
        }

        // Guard: if check-in date is not today, never immediately check in
        $cInDate = Carbon::parse($this->checkIn);
        if (! $cInDate->isToday()) {
            $this->checkInNow = false;
        }

        $bookingStatus = $this->checkInNow ? 'checked_in' : 'confirmed';

        $booking = Booking::create([
            'booking_code' => 'AGRO-'.strtoupper(Str::random(8)),
            'room_id' => $room->id,
            'user_id' => $this->userId ?: null,
            'guest_name' => $this->guestName,
            'guest_email' => $this->guestEmail ?: null,
            'guest_phone' => $this->guestPhone ?: null,
            'check_in' => $this->checkIn,
            'check_out' => $this->checkOut,
            'booking_type' => $this->bookingType,
            'adults' => $this->adults,
            'children' => $this->children,
            'pets' => $this->pets,
            'base_price' => $this->baseTotal,
            'addons_total' => $this->addonsTotal,
            'pet_fee_total' => $this->petTotal,
            'discount_amount' => 0.0,
            'total_price' => $total,
            'amount_paid' => $amountPaid,
            'status' => $bookingStatus,
            'checked_in_at' => $this->checkInNow ? now() : null,
            'payment_status' => $paymentStatus,
            'payment_method' => $this->paymentMethod,
            'is_walk_in' => true,
            'special_requests' => $this->specialRequests ?: null,
        ]);

        // Save addons
        $addons = $this->availableAddons->keyBy('id');
        foreach ($this->addonQuantities as $addonId => $qty) {
            $qty = (int) $qty;
            if ($qty > 0 && isset($addons[$addonId])) {
                $addon = $addons[$addonId];
                $multiplier = match ($addon->pricing_type) {
                    'per_night' => $this->nights,
                    'per_person' => $this->adults + $this->children,
                    default => 1,
                };
                BookingAddon::create([
                    'booking_id' => $booking->id,
                    'addon_service_id' => $addon->id,
                    'item_name' => $addon->name,
                    'quantity' => $qty,
                    'unit_price' => (float) $addon->price,
                    'subtotal' => round((float) $addon->price * $qty * $multiplier, 2),
                ]);
            }
        }

        if ($this->checkInNow) {
            $room->update(['status' => 'occupied']);
            try {
                broadcast(new CheckInUpdated($booking));
            } catch (\Throwable) {}
        }

        try {
            broadcast(new NewBookingReceived($booking))->toOthers();
        } catch (\Throwable) {}

        session()->flash('success', "Walk-in booking {$booking->booking_code} registered successfully!");
        $this->redirect(route('room-booking.show', $booking->id));
    }
};
?>


<div class="space-y-6">
    <form wire:submit="submitWalkin" class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {{-- Left 2 Cols: Stay Dates, Room Selection, Guest Info --}}
        <div class="xl:col-span-2 space-y-6">

            {{-- 1. Stay Dates & Times --}}
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100 space-y-4">
                <div class="flex items-center justify-between border-b border-stone-100 pb-3">
                    <div>
                        <h3 class="font-bold text-stone-900 text-base">1. Stay Schedule & Type</h3>
                        <p class="text-xs text-stone-500">Pick check-in and check-out to instantly see available rooms.</p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-bold {{ $bookingType === 'overnight' ? 'bg-indigo-100 text-indigo-800' : 'bg-amber-100 text-amber-800' }}">
                        {{ $bookingType === 'overnight' ? $this->nights . ' Night(s)' : 'Day Pass' }}
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-stone-700 mb-1">Booking Type *</label>
                        <select wire:model.live="bookingType"
                                class="w-full px-3 py-2 bg-stone-50 border border-stone-200 rounded-xl text-xs font-medium focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            <option value="overnight">Overnight Stay</option>
                            <option value="day_pass">Day Pass (Same Day)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-stone-700 mb-1">Check-In Date & Time *</label>
                        <input type="datetime-local" wire:model.live="checkIn" required
                               class="w-full px-3 py-2 bg-white border border-stone-200 rounded-xl text-xs font-medium focus:outline-none focus:ring-2 focus:ring-emerald-500 @error('checkIn') border-red-400 @enderror">
                        @error('checkIn') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-semibold text-stone-700">Check-Out Date & Time *</label>
                            @if($bookingType === 'day_pass')
                                <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded">
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    Locked (Day Pass)
                                </span>
                            @endif
                        </div>
                        <input type="datetime-local" wire:model.live="checkOut" required
                               @disabled($bookingType === 'day_pass')
                               @readonly($bookingType === 'day_pass')
                               class="w-full px-3 py-2 bg-white border border-stone-200 rounded-xl text-xs font-medium focus:outline-none focus:ring-2 focus:ring-emerald-500 @error('checkOut') border-red-400 @enderror {{ $bookingType === 'day_pass' ? 'bg-stone-100 text-stone-500 cursor-not-allowed border-dashed' : '' }}">
                        @if($bookingType === 'day_pass')
                            <p class="text-[11px] text-amber-600 mt-1">Same-day departure (standard 5:00 PM check-out).</p>
                        @endif
                        @error('checkOut') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            {{-- 2. Room Selection with Dynamic Availability --}}
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-stone-100 pb-3">
                    <div>
                        <h3 class="font-bold text-stone-900 text-base">2. Room Selection</h3>
                        <p class="text-xs text-stone-500">Live availability based on selected dates above.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-semibold text-stone-600">Category:</label>
                        <select wire:model.live="selectedCategoryId"
                                class="px-2.5 py-1.5 bg-stone-50 border border-stone-200 rounded-lg text-xs font-medium focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            <option value="">All Categories</option>
                            @foreach($this->categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @error('selectedRoomId')
                    <div class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-xs font-medium">
                        ⚠️ {{ $message }}
                    </div>
                @enderror

                {{-- Rooms Grid --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 max-h-96 overflow-y-auto p-1">
                    @forelse($this->availableRooms as $room)
                        @php
                            $isSelected = $selectedRoomId === $room->id;
                            $canBook = $room->can_be_booked;
                        @endphp
                        <div wire:click="{{ $canBook ? 'selectRoom(' . $room->id . ')' : '' }}"
                             class="relative p-3.5 rounded-2xl border-2 transition-all cursor-pointer {{ $isSelected ? 'border-emerald-600 bg-emerald-50/50 shadow-md ring-2 ring-emerald-500/30' : ($canBook ? 'border-stone-200 hover:border-emerald-400 bg-white hover:bg-stone-50/50' : 'border-stone-200 bg-stone-50/70 opacity-60 cursor-not-allowed') }}">
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <div>
                                    <span class="inline-block px-2 py-0.5 rounded text-[11px] font-extrabold font-mono {{ $isSelected ? 'bg-emerald-600 text-white' : 'bg-stone-800 text-white' }}">
                                        Room {{ $room->room_number }}
                                    </span>
                                    <p class="font-bold text-stone-900 text-sm mt-1 truncate">{{ $room->name }}</p>
                                    <p class="text-[11px] text-stone-500">{{ $room->category?->name }}</p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-xs font-black text-emerald-700">₱{{ number_format($room->base_price, 0) }}</p>
                                    <p class="text-[10px] text-stone-400">/night</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 text-[11px] text-stone-500 mt-2 pt-2 border-t border-stone-100">
                                <span>👥 Up to {{ $room->capacity_adults }}</span>
                                @if($room->allows_pets)
                                    <span class="text-amber-700 font-medium">🐾 Pet friendly</span>
                                @endif
                            </div>

                            @if(! $canBook)
                                <div class="mt-2 text-[11px] font-bold text-rose-600 flex items-center gap-1">
                                    <span>✕</span> {{ $room->availability_issue }}
                                </div>
                            @elseif($isSelected)
                                <div class="mt-2 text-[11px] font-bold text-emerald-700 flex items-center gap-1">
                                    <span>✓</span> Selected for this booking
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="col-span-full py-8 text-center text-stone-400 text-xs">
                            No rooms found matching the current criteria.
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- 3. Guest Information & Headcount --}}
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100 space-y-4">
                <div class="border-b border-stone-100 pb-3">
                    <h3 class="font-bold text-stone-900 text-base">3. Guest Details & Party Size</h3>
                    <p class="text-xs text-stone-500">Enter walk-in guest details and guest count.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-stone-700 mb-1">Guest Full Name *</label>
                        <input type="text" wire:model="guestName" required placeholder="e.g., Juan Dela Cruz"
                               class="w-full px-3 py-2 bg-white border border-stone-200 rounded-xl text-xs font-medium focus:outline-none focus:ring-2 focus:ring-emerald-500 @error('guestName') border-red-400 @enderror">
                        @error('guestName') <span class="text-[11px] text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-stone-700 mb-1">Phone Number</label>
                        <input type="tel" wire:model="guestPhone" placeholder="0912-345-6789"
                               class="w-full px-3 py-2 bg-white border border-stone-200 rounded-xl text-xs font-medium focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-stone-700 mb-1">Email (Optional)</label>
                        <input type="email" wire:model="guestEmail" placeholder="juan@example.com"
                               class="w-full px-3 py-2 bg-white border border-stone-200 rounded-xl text-xs font-medium focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-stone-700 mb-1">Link to Customer Account (Optional)</label>
                        <select wire:model="userId"
                                class="w-full px-3 py-2 bg-white border border-stone-200 rounded-xl text-xs font-medium focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            <option value="">— Walk-in Guest (No Linked Account) —</option>
                            @foreach($this->customers as $cust)
                                <option value="{{ $cust->id }}">{{ $cust->name }} ({{ $cust->email }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Guest Breakdown --}}
                <div class="grid grid-cols-3 gap-3 pt-2">
                    <div>
                        <label class="block text-xs font-semibold text-stone-700 mb-1">Adults *</label>
                        <input type="number" wire:model.live="adults" min="1" max="20" required
                               class="w-full px-3 py-2 bg-white border border-stone-200 rounded-xl text-xs font-bold focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-stone-700 mb-1">Children</label>
                        <input type="number" wire:model.live="children" min="0" max="20"
                               class="w-full px-3 py-2 bg-white border border-stone-200 rounded-xl text-xs font-bold focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-stone-700 mb-1">Pets 🐾</label>
                        <input type="number" wire:model.live="pets" min="0" max="10"
                               class="w-full px-3 py-2 bg-white border border-stone-200 rounded-xl text-xs font-bold focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 mb-1">Special Notes / Requests</label>
                    <textarea wire:model="specialRequests" rows="2" placeholder="e.g., Requested upper floor, late checkout, extra key"
                              class="w-full px-3 py-2 bg-white border border-stone-200 rounded-xl text-xs font-medium focus:outline-none focus:ring-2 focus:ring-emerald-500"></textarea>
                </div>
            </div>

            {{-- 4. Front-Desk Extras / Add-on Services --}}
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100 space-y-4">
                <div class="border-b border-stone-100 pb-3">
                    <h3 class="font-bold text-stone-900 text-base">4. Add-on Services & Amenities (Optional)</h3>
                    <p class="text-xs text-stone-500">Select any extra services the guest wants upon booking.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach($this->availableAddons as $addon)
                        @php
                            $qty = $addonQuantities[$addon->id] ?? 0;
                        @endphp
                        <div class="p-3 rounded-xl border {{ $qty > 0 ? 'border-emerald-300 bg-emerald-50/40' : 'border-stone-200 bg-white' }} flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-stone-900">{{ $addon->name }}</p>
                                <p class="text-[11px] text-stone-500">
                                    ₱{{ number_format($addon->price, 0) }}
                                    <span class="text-stone-400">· {{ str_replace('_', ' ', $addon->pricing_type) }}</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <button type="button" wire:click="setAddonQty({{ $addon->id }}, {{ $qty - 1 }})"
                                        class="w-7 h-7 rounded-lg bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold text-xs flex items-center justify-center">
                                    -
                                </button>
                                <span class="w-6 text-center text-xs font-bold">{{ $qty }}</span>
                                <button type="button" wire:click="setAddonQty({{ $addon->id }}, {{ $qty + 1 }})"
                                        class="w-7 h-7 rounded-lg bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold text-xs flex items-center justify-center">
                                    +
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>

        {{-- Right Col: Price Breakdown & Payment Settlement --}}
        <div class="space-y-6">

            {{-- Selected Room Summary --}}
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100">
                <h3 class="font-bold text-stone-900 text-sm mb-3">Selected Room</h3>
                @if($this->selectedRoom)
                    <div class="p-3 bg-stone-50 rounded-xl space-y-2">
                        <div class="flex justify-between items-start">
                            <div>
                                <span class="px-2 py-0.5 rounded text-[11px] font-extrabold bg-stone-900 text-white font-mono">
                                    Room {{ $this->selectedRoom->room_number }}
                                </span>
                                <p class="font-bold text-stone-900 text-sm mt-1">{{ $this->selectedRoom->name }}</p>
                                <p class="text-xs text-stone-500">{{ $this->selectedRoom->category?->name }}</p>
                            </div>
                            <span class="text-xs font-black text-emerald-700">₱{{ number_format($this->selectedRoom->base_price, 2) }}/night</span>
                        </div>
                    </div>
                @else
                    <div class="p-4 border-2 border-dashed border-stone-200 rounded-xl text-center text-stone-400 text-xs">
                        👉 Please click a room from the list on the left.
                    </div>
                @endif
            </div>

            {{-- Pricing Breakdown --}}
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100 space-y-3">
                <h3 class="font-bold text-stone-900 text-sm border-b border-stone-100 pb-2">Price Breakdown</h3>
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between text-stone-600">
                        <span>Room Rate ({{ $this->nights }} night{{ $this->nights > 1 ? 's' : '' }})</span>
                        <span class="font-semibold text-stone-900">₱{{ number_format($this->baseTotal, 2) }}</span>
                    </div>

                    @if($this->petTotal > 0)
                        <div class="flex justify-between text-stone-600">
                            <span>Pet Fee ({{ $this->pets }} pet × {{ $this->nights }} nights)</span>
                            <span class="font-semibold text-stone-900">₱{{ number_format($this->petTotal, 2) }}</span>
                        </div>
                    @endif

                    @if($this->addonsTotal > 0)
                        <div class="flex justify-between text-stone-600">
                            <span>Add-ons & Extras</span>
                            <span class="font-semibold text-stone-900">₱{{ number_format($this->addonsTotal, 2) }}</span>
                        </div>
                    @endif

                    <div class="border-t border-stone-100 pt-2 flex justify-between text-sm font-extrabold text-stone-900">
                        <span>Total Amount Due</span>
                        <span class="text-emerald-700 text-base">₱{{ number_format($this->totalPrice, 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- Front-Desk Payment Settlement --}}
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100 space-y-4">
                <div class="border-b border-stone-100 pb-2">
                    <h3 class="font-bold text-stone-900 text-sm">💳 Front-Desk Payment</h3>
                    <p class="text-[11px] text-stone-500">Record cash/card payment right now or collect later.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-stone-700 mb-1">Payment Status</label>
                    <div class="grid grid-cols-3 gap-2">
                        <button type="button" wire:click="$set('paymentOption', 'pay_now')"
                                class="py-2 px-1 text-center rounded-xl text-xs font-bold border transition-colors {{ $paymentOption === 'pay_now' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-stone-700 border-stone-200 hover:bg-stone-50' }}">
                            Pay Full
                        </button>
                        <button type="button" wire:click="$set('paymentOption', 'partial')"
                                class="py-2 px-1 text-center rounded-xl text-xs font-bold border transition-colors {{ $paymentOption === 'partial' ? 'bg-amber-500 text-white border-amber-500' : 'bg-white text-stone-700 border-stone-200 hover:bg-stone-50' }}">
                            Partial
                        </button>
                        <button type="button" wire:click="$set('paymentOption', 'pay_later')"
                                class="py-2 px-1 text-center rounded-xl text-xs font-bold border transition-colors {{ $paymentOption === 'pay_later' ? 'bg-stone-800 text-white border-stone-800' : 'bg-white text-stone-700 border-stone-200 hover:bg-stone-50' }}">
                            Unpaid
                        </button>
                    </div>
                </div>

                @if($paymentOption === 'partial')
                    <div>
                        <label class="block text-xs font-semibold text-stone-700 mb-1">Deposit Amount (₱) *</label>
                        <input type="number" step="0.01" wire:model="partialAmount" placeholder="Amount collected now"
                               class="w-full px-3 py-2 bg-white border border-stone-200 rounded-xl text-xs font-bold text-stone-900 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                @endif

                @if($paymentOption !== 'pay_later')
                    <div>
                        <label class="block text-xs font-semibold text-stone-700 mb-1">Payment Method</label>
                        <select wire:model="paymentMethod"
                                class="w-full px-3 py-2 bg-white border border-stone-200 rounded-xl text-xs font-medium focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            <option value="cash">Cash Settlement</option>
                            <option value="card">Credit / Debit Card</option>
                            <option value="gcash">GCash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                @endif

                {{-- Immediate Check-In Toggle --}}
                <div class="p-3.5 bg-stone-50 rounded-xl border border-stone-200 space-y-1">
                    <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-stone-900">
                        <input type="checkbox" wire:model="checkInNow" class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500">
                        <span>Check In Guest Immediately</span>
                    </label>
                    <p class="text-[11px] text-stone-500 pl-6">
                        If checked, booking is marked <strong>Checked In</strong> and Room is marked <strong>Occupied</strong> right away.
                    </p>
                </div>

                {{-- Submit Button --}}
                <button type="submit"
                        class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-sm transition-colors shadow-lg shadow-emerald-200 flex items-center justify-center gap-2">
                    <span>✅</span> Confirm & Create Booking
                </button>
            </div>

        </div>

    </form>
</div>