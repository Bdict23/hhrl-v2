<?php

use Livewire\Component;
use App\Models\Suite\RoomAddonService;
use App\Models\Suite\RoomBooking;
use App\Models\User;
use App\Services\Suite\RoomBookingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use TallStackUi\Traits\Interactions;

new class extends Component
{
    use Interactions;

    #[Locked]
    public int $bookingId;

    public RoomBooking $booking;

    public Collection $availableAddons;

    public Collection $housekeepers;

    public string $addonServiceId = '';

    public string $itemName = '';

    public string $unitPrice = '';

    public string $quantity = '1';

    public string $paymentAmount = '';

    public string $paymentMethod = 'cash';

    public bool $settlePaymentAtCheckout = true;

    public string $checkoutPaymentMethod = 'cash';

    public string $housekeeperId = '';

    public string $housekeepingNotes = '';

    public function mount(int $id): void
    {
        $this->bookingId = $id;
        $this->refreshBooking();
        $this->availableAddons = RoomAddonService::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $this->housekeepers = User::query()
            ->where('role', 'housekeeper')
            ->orderBy('name')
            ->get();
        $this->paymentAmount = number_format($this->booking->balance_due, 2, '.', '');

        if (in_array($this->booking->payment_method, ['cash', 'card', 'gcash', 'bank_transfer'], true)) {
            $this->paymentMethod = $this->booking->payment_method;
            $this->checkoutPaymentMethod = $this->booking->payment_method;
        }
    }

    public function checkInAction(): void
    {
        $this->dialog()
            ->question('Confirm check-in?', "Check in booking {$this->booking->booking_code} for room {$this->booking->room?->room_number}?")
            ->confirm('Check in guest', 'checkIn')
            ->cancel('Keep booking unchanged')
            ->send();
    }

    public function checkIn(RoomBookingService $service): void
    {
        $booking = $service->checkIn($this->currentBooking());
        $this->refreshBooking();
        $this->toast()->success('Guest checked in', "Booking {$booking->booking_code} is now checked in.")->send();
    }

    public function checkoutAction(): void
    {
        $this->validateCheckout();

        $message = "Check out booking {$this->booking->booking_code} and queue room {$this->booking->room?->room_number} for housekeeping?";

        if ($this->settlePaymentAtCheckout && $this->booking->balance_due > 0) {
            $message .= ' The remaining balance of ₱'.number_format($this->booking->balance_due, 2).' will be recorded via '.str_replace('_', ' ', $this->checkoutPaymentMethod).'.';
        }

        $this->dialog()
            ->question('Confirm guest checkout?', $message)
            ->confirm('Complete checkout', 'checkOut')
            ->cancel('Continue the stay')
            ->send();
    }

    public function checkOut(RoomBookingService $service): void
    {
        $this->validateCheckout();

        $booking = $service->checkout($this->currentBooking(), [
            'settle_payment' => $this->settlePaymentAtCheckout,
            'payment_method' => $this->checkoutPaymentMethod,
            'housekeeper_id' => $this->housekeeperId,
            'housekeeping_notes' => $this->housekeepingNotes,
        ]);

        $this->refreshBooking();
        $this->toast()->success(
            'Checkout complete',
            "Booking {$booking->booking_code} is checked out. Room {$booking->room?->room_number} is queued for housekeeping.",
        )->send();
    }

    public function addExtraServiceAction(): void
    {
        $this->validateExtraService();

        $selectedService = $this->addonServiceId !== ''
            ? $this->availableAddons->firstWhere('id', (int) $this->addonServiceId)
            : null;
        $name = $selectedService?->name ?? $this->itemName;
        $price = (float) ($selectedService?->price ?? $this->unitPrice);
        $subtotal = $price * (int) $this->quantity;

        $this->dialog()
            ->question('Add this charge?', "Add {$this->quantity} × {$name} to this booking for ₱".number_format($subtotal, 2).'?')
            ->confirm('Add charge', 'addExtraService')
            ->cancel('Do not add')
            ->send();
    }

    public function addExtraService(RoomBookingService $service): void
    {
        $this->validateExtraService();

        $booking = $service->addExtraCharge($this->currentBooking(), [
            'addon_service_id' => $this->addonServiceId,
            'item_name' => $this->itemName,
            'unit_price' => $this->unitPrice,
            'quantity' => $this->quantity,
        ]);

        $this->refreshBooking();
        $this->addonServiceId = '';
        $this->itemName = '';
        $this->unitPrice = '';
        $this->quantity = '1';
        $this->paymentAmount = number_format($booking->balance_due, 2, '.', '');
        $this->toast()->success(
            'Service added',
            "The charge was added to booking {$booking->booking_code}. Balance due: ₱".number_format($booking->balance_due, 2).'.',
        )->send();
    }

    public function settlePaymentAction(): void
    {
        $this->validatePayment();

        $this->dialog()
            ->question(
                'Record this payment?',
                'Record ₱'.number_format((float) $this->paymentAmount, 2).' via '.str_replace('_', ' ', $this->paymentMethod).' for booking '.$this->booking->booking_code.'?',
            )
            ->confirm('Record payment', 'recordPayment')
            ->cancel('Do not record')
            ->send();
    }

    public function verifyPaymentAction(): void
    {
        $this->paymentAmount = number_format($this->booking->balance_due, 2, '.', '');
        $this->settlePaymentAction();
    }

    public function recordPayment(RoomBookingService $service): void
    {
        $this->validatePayment();

        $booking = $service->settlePayment($this->currentBooking(), [
            'amount' => $this->paymentAmount,
            'payment_method' => $this->paymentMethod,
        ]);

        $this->refreshBooking();
        $this->paymentAmount = number_format($booking->balance_due, 2, '.', '');
        $this->toast()->success(
            'Payment recorded',
            'Payment saved. Remaining balance: ₱'.number_format($booking->balance_due, 2).'.',
        )->send();
    }

    public function completeHousekeepingAction(): void
    {
        $this->dialog()
            ->question(
                'Mark room as clean?',
                "Confirm that room {$this->booking->room?->room_number} has been inspected and cleaned. It will then become available for booking.",
            )
            ->confirm('Complete housekeeping', 'completeHousekeeping')
            ->cancel('Keep room in housekeeping')
            ->send();
    }

    public function completeHousekeeping(RoomBookingService $service): void
    {
        $staff = User::query()->find(auth()->id());
        $log = $service->completeHousekeeping($this->currentBooking(), $staff);
        $this->refreshBooking();
        $this->toast()->success(
            'Housekeeping complete',
            "Room {$this->booking->room?->room_number} is now available. Task completed at {$log->completed_at?->format('g:i A')}.",
        )->send();
    }

    public function cancelBookingAction(): void
    {
        $this->dialog()
            ->question('Cancel this booking?', "Cancel booking {$this->booking->booking_code}? This action cannot be undone.")
            ->confirm('Cancel booking', 'cancelBooking')
            ->cancel('Keep booking')
            ->send();
    }

    public function cancelBooking(RoomBookingService $service): void
    {
        $booking = $service->cancel($this->currentBooking());
        $this->refreshBooking();
        $this->toast()->success('Booking cancelled', "Booking {$booking->booking_code} was cancelled.")->send();
    }

    private function validateCheckout(): void
    {
        $this->validate([
            'settlePaymentAtCheckout' => ['boolean'],
            'checkoutPaymentMethod' => [
                Rule::requiredIf($this->settlePaymentAtCheckout && $this->booking->balance_due > 0),
                'nullable',
                'string',
                'in:cash,card,gcash,bank_transfer',
            ],
            'housekeeperId' => [
                'nullable',
                Rule::exists('users', 'id')->where('role', 'housekeeper'),
            ],
            'housekeepingNotes' => ['nullable', 'string', 'max:1000'],
        ], [
            'checkoutPaymentMethod.required' => 'Choose how the remaining balance will be collected.',
            'checkoutPaymentMethod.in' => 'Choose a supported payment method.',
            'housekeeperId.exists' => 'Choose a housekeeper from the list or leave the task unassigned.',
            'housekeepingNotes.max' => 'Housekeeping instructions cannot exceed 1,000 characters.',
        ]);
    }

    private function validateExtraService(): void
    {
        $isCustomItem = $this->addonServiceId === '';

        $this->validate([
            'addonServiceId' => [
                'nullable',
                Rule::exists('room_addon_services', 'id')->where('is_active', true),
            ],
            'itemName' => [$isCustomItem ? 'required' : 'nullable', 'string', 'max:255'],
            'unitPrice' => [$isCustomItem ? 'required' : 'nullable', 'numeric', 'min:0'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ], [
            'addonServiceId.exists' => 'Choose an available service from the list.',
            'itemName.required' => 'Enter a name for the custom item.',
            'itemName.max' => 'The item name cannot exceed 255 characters.',
            'unitPrice.required' => 'Enter a unit price for the custom item.',
            'unitPrice.numeric' => 'Enter a valid unit price.',
            'unitPrice.min' => 'The unit price cannot be negative.',
            'quantity.required' => 'Enter the quantity.',
            'quantity.integer' => 'Quantity must be a whole number.',
            'quantity.min' => 'Quantity must be at least one.',
            'quantity.max' => 'Quantity cannot exceed 100 items.',
        ]);
    }

    private function validatePayment(): void
    {
        $this->validate([
            'paymentAmount' => [
                'required',
                'numeric',
                'gt:0',
                'lte:'.$this->booking->balance_due,
            ],
            'paymentMethod' => ['required', 'string', 'in:cash,card,gcash,bank_transfer'],
        ], [
            'paymentAmount.required' => 'Enter the payment amount.',
            'paymentAmount.numeric' => 'Enter a valid payment amount.',
            'paymentAmount.gt' => 'Payment must be greater than zero.',
            'paymentAmount.lte' => 'Payment cannot exceed the current balance.',
            'paymentMethod.required' => 'Choose a payment method.',
            'paymentMethod.in' => 'Choose a supported payment method.',
        ]);
    }

    private function currentBooking(): RoomBooking
    {
        return RoomBooking::query()->findOrFail($this->bookingId);
    }

    private function refreshBooking(): void
    {
        $this->booking = RoomBooking::query()
            ->with([
                'room.category',
                'room.photos',
                'room.housekeepingLogs',
                'user',
                'addons.addonService',
                'discount',
            ])
            ->findOrFail($this->bookingId);
    }

};
?>

<div>
    <x-slot:heading>Booking Detail</x-slot:heading>
    <x-slot:subheading>{{ $booking->booking_code }}</x-slot:subheading>

    @if($errors->any())
        <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <p class="font-semibold">Please correct the following before continuing:</p>
            <ul class="mt-2 list-inside list-disc space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {{-- Main Info --}}
        <div class="xl:col-span-2 space-y-5">
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100">
                <div class="flex items-start justify-between mb-6">
                    <div>
                        <p class="font-mono text-2xl font-black text-stone-900">{{ $booking->booking_code }}</p>
                        <p class="text-stone-500 text-sm mt-1">Created {{ $booking->created_at->diffForHumans() }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        @php
                            $statusColors = [
                                'pending' => 'bg-amber-100 text-amber-800 border-amber-200',
                                'confirmed' => 'bg-blue-100 text-blue-800 border-blue-200',
                                'checked_in' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                'checked_out' => 'bg-stone-100 text-stone-800 border-stone-200',
                                'cancelled' => 'bg-red-100 text-red-800 border-red-200',
                            ];
                        @endphp
                        <span class="px-3 py-1.5 rounded-xl text-sm font-semibold border {{ $statusColors[$booking->status] ?? 'bg-stone-100 text-stone-700 border-stone-200' }}">
                            {{ ucfirst(str_replace('_', ' ', $booking->status)) }}
                        </span>
                        <span class="px-3 py-1.5 rounded-xl text-sm font-semibold border {{ $booking->payment_status === 'paid' ? 'bg-emerald-100 text-emerald-800 border-emerald-200' : ($booking->payment_status === 'partial' ? 'bg-amber-100 text-amber-800 border-amber-200' : 'bg-rose-100 text-rose-800 border-rose-200') }}">
                            {{ ucfirst($booking->payment_status) }}
                        </span>
                        @if($booking->is_walk_in)
                            <span class="px-3 py-1.5 rounded-xl text-sm font-semibold bg-blue-100 text-blue-800 border border-blue-200">Walk-In</span>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <p class="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-3">Guest Information</p>
                        <div class="space-y-2">
                            <div><span class="text-stone-500 text-sm">Name:</span> <span class="font-semibold text-stone-900 text-sm">{{ $booking->guest_name }}</span></div>
                            @if($booking->guest_email)
                                <div><span class="text-stone-500 text-sm">Email:</span> <span class="font-medium text-stone-700 text-sm">{{ $booking->guest_email }}</span></div>
                            @endif
                            @if($booking->guest_phone)
                                <div><span class="text-stone-500 text-sm">Phone:</span> <span class="font-medium text-stone-700 text-sm">{{ $booking->guest_phone }}</span></div>
                            @endif
                            @if($booking->user)
                                <div class="flex items-center gap-2 mt-2">
                                    @if($booking->user->avatar)
                                        <img src="{{ $booking->user->avatar }}" class="w-6 h-6 rounded-full">
                                    @endif
                                    <span class="text-xs text-emerald-600 font-medium">Registered · {{ $booking->user->visit_count }} visits</span>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-stone-400 uppercase tracking-wide mb-3">Stay Details</p>
                        <div class="space-y-2 text-sm">
                            <div><span class="text-stone-500">Check-In:</span> <span class="font-semibold">{{ $booking->check_in->format('D, M d Y') }}</span></div>
                            <div><span class="text-stone-500">Check-Out:</span> <span class="font-semibold">{{ $booking->check_out->format('D, M d Y') }}</span></div>
                            <div><span class="text-stone-500">Nights:</span> <span class="font-semibold">{{ $booking->nights }}</span></div>
                            <div><span class="text-stone-500">Type:</span> <span class="font-semibold capitalize">{{ str_replace('_', ' ', $booking->booking_type) }}</span></div>
                            <div><span class="text-stone-500">Guests:</span> <span class="font-semibold">{{ $booking->adults }} {{ Str::plural('Adult', $booking->adults) }}@if($booking->children > 0), {{ $booking->children }} {{ Str::plural('Child', $booking->children) }}@endif @if($booking->pets > 0), 🐾 {{ $booking->pets }} {{ Str::plural('Pet', $booking->pets) }}@endif</span></div>
                        </div>
                    </div>
                </div>

                @if($booking->special_requests)
                    <div class="mt-6 p-4 bg-amber-50 rounded-xl border border-amber-100">
                        <p class="text-xs font-semibold text-amber-700 uppercase tracking-wide mb-1">Special Requests</p>
                        <p class="text-sm text-amber-900">{{ $booking->special_requests }}</p>
                    </div>
                @endif

                @if($booking->payment_proof)
                    <div class="mt-6 p-4 bg-emerald-50/70 rounded-xl border border-emerald-200">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <p class="text-xs font-bold text-emerald-900 uppercase tracking-wide">🧾 Payment Proof Received</p>
                                <p class="text-xs text-emerald-700">Method: <strong class="capitalize">{{ $booking->payment_method ?? 'Online / Transfer' }}</strong></p>
                            </div>
                            @if($booking->balance_due > 0)
                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-300">
                                    Needs Verification
                                </span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                    Verified & Paid
                                </span>
                            @endif
                        </div>
                        <div class="flex items-center gap-4">
                            <a href="{{ asset('storage/'.$booking->payment_proof) }}" target="_blank" class="block w-28 h-28 rounded-xl overflow-hidden border-2 border-emerald-300 shadow-sm bg-white shrink-0 group">
                                <img src="{{ asset('storage/'.$booking->payment_proof) }}" alt="Payment Proof" class="w-full h-full object-cover group-hover:scale-105 transition-transform">
                            </a>
                            <div class="space-y-2">
                                <a href="{{ asset('storage/'.$booking->payment_proof) }}" target="_blank" class="text-xs text-emerald-700 font-bold hover:underline inline-flex items-center gap-1">
                                    🔍 Click to inspect receipt screenshot ↗
                                </a>
                                @if($booking->balance_due > 0 && $booking->status !== 'cancelled')
                                    <p class="text-[11px] text-amber-700 font-medium">
                                        Balance due: <strong>₱{{ number_format($booking->balance_due, 2) }}</strong>
                                    </p>
                                    <button type="button" wire:click="verifyPaymentAction" class="pt-0.5 px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-colors shadow-sm inline-flex items-center gap-1.5">
                                            ✓ Verify &amp; Mark as Fully Paid
                                    </button>
                                @else
                                    <p class="text-xs text-emerald-700 font-semibold flex items-center gap-1">
                                        ✅ Payment verified &amp; fully settled.
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Add-ons & Extra Charges --}}
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-stone-900">Add-ons & Extra Charges</h3>
                    <span class="text-xs font-medium text-stone-400">{{ $booking->addons->count() }} items</span>
                </div>

                @if($booking->addons->isNotEmpty())
                    <div class="space-y-3 mb-6">
                        @foreach($booking->addons as $addon)
                            <div class="flex items-center justify-between p-3 bg-stone-50 rounded-xl">
                                <div>
                                    <p class="font-medium text-stone-900 text-sm">{{ $addon->item_name ?? $addon->addonService?->name ?? 'Custom Service' }}</p>
                                    <p class="text-xs text-stone-400">Qty: {{ $addon->quantity }} × ₱{{ number_format($addon->unit_price, 2) }}</p>
                                </div>
                                <p class="font-semibold text-stone-900">₱{{ number_format($addon->subtotal, 2) }}</p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-stone-400 mb-6">No add-ons or extra charges currently attached.</p>
                @endif

                {{-- Form to Attach Additional Bills/Items --}}
                @if(!in_array($booking->status, ['cancelled', 'checked_out']))
                    <form wire:submit="addExtraServiceAction" class="pt-4 border-t border-stone-100 space-y-3">
                        <p class="text-xs font-bold text-stone-700 uppercase tracking-wide mb-3">+ Attach Extra Item / Service (Snacks, Extra Bed, etc.)</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-stone-500 mb-1">Select Service or Custom</label>
                                <select wire:model="addonServiceId"
                                        class="w-full px-3 py-2 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                    <option value="">Custom Item / Snack / Extra Bed</option>
                                    @foreach($availableAddons as $addonService)
                                        <option value="{{ $addonService->id }}">{{ $addonService->name }} (₱{{ number_format($addonService->price, 2) }})</option>
                                    @endforeach
                                </select>
                                @error('addonServiceId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-stone-500 mb-1">Item Description / Name</label>
                                <input type="text" wire:model="itemName" placeholder="e.g., Extra Bed, Snack Box, Mini-bar"
                                       class="w-full px-3 py-2 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                @error('itemName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-stone-500 mb-1">Unit Price (₱)</label>
                                <input type="number" step="0.01" min="0" wire:model="unitPrice" placeholder="Only for a custom item"
                                       class="w-full px-3 py-2 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                @error('unitPrice') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-stone-500 mb-1">Quantity</label>
                                <input type="number" wire:model="quantity" min="1" max="100" required
                                       class="w-full px-3 py-2 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                @error('quantity') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 bg-stone-800 hover:bg-stone-900 text-white rounded-xl text-xs font-semibold transition-colors disabled:opacity-50">
                            <span wire:loading.remove wire:target="addExtraServiceAction">+ Add Charge to Booking Billing</span>
                            <span wire:loading wire:target="addExtraServiceAction">Checking charge...</span>
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Right: Summary + Actions --}}
        <div class="space-y-5">
            {{-- Room Card --}}
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100">
                <h3 class="font-bold text-stone-900 mb-4">Room</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-stone-500">Room Number</span> <span class="font-semibold">{{ $booking->room?->room_number }}</span></div>
                    <div class="flex justify-between"><span class="text-stone-500">Name</span> <span class="font-semibold">{{ $booking->room?->name }}</span></div>
                    <div class="flex justify-between"><span class="text-stone-500">Category</span> <span class="font-semibold">{{ $booking->room?->category?->name }}</span></div>
                </div>
            </div>

            {{-- Pricing Breakdown --}}
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100">
                <h3 class="font-bold text-stone-900 mb-4">Billing & Pricing Breakdown</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-stone-500">Base Price ({{ $booking->nights }} nights)</span> <span>₱{{ number_format($booking->base_price, 2) }}</span></div>
                    @if($booking->addons_total > 0)
                        <div class="flex justify-between"><span class="text-stone-500">Add-ons & Extras</span> <span>₱{{ number_format($booking->addons_total, 2) }}</span></div>
                    @endif
                    @if($booking->pet_fee_total > 0)
                        <div class="flex justify-between"><span class="text-stone-500">Pet Fee</span> <span>₱{{ number_format($booking->pet_fee_total, 2) }}</span></div>
                    @endif
                    @if($booking->discount_amount > 0)
                        <div class="flex justify-between text-emerald-600"><span>Discount</span> <span>-₱{{ number_format($booking->discount_amount, 2) }}</span></div>
                    @endif
                    <div class="border-t border-stone-100 pt-2 mt-2 flex justify-between font-bold text-base">
                        <span>Total Charges</span>
                        <span class="text-stone-900">₱{{ number_format($booking->total_price, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-xs pt-1">
                        <span class="text-stone-500">Amount Paid</span>
                        <span class="font-bold text-emerald-600">₱{{ number_format($booking->amount_paid ?? 0, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-xs pt-1 border-t border-dashed border-stone-200">
                        <span class="font-bold text-stone-700">Remaining Balance Due</span>
                        <span class="font-extrabold text-sm {{ $booking->balance_due > 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                            ₱{{ number_format($booking->balance_due, 2) }}
                        </span>
                    </div>
                    <div class="flex justify-between pt-2 text-xs border-t border-stone-100">
                        <span class="text-stone-500">Payment Status</span>
                        <span class="font-bold uppercase px-2 py-0.5 rounded-lg {{ $booking->payment_status === 'paid' ? 'bg-emerald-100 text-emerald-800' : ($booking->payment_status === 'partial' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800') }}">
                            {{ $booking->payment_status }}
                        </span>
                    </div>
                    @if($booking->payment_method)
                        <div class="flex justify-between text-xs pt-0.5">
                            <span class="text-stone-500">Payment Method</span>
                            <span class="font-medium text-stone-700 capitalize">{{ $booking->payment_method }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Settlement & Actions --}}
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100">
                <h3 class="font-bold text-stone-900 mb-4">Settlement & Actions</h3>
                <div class="space-y-3">
                    {{-- Settlement form whenever there is a remaining balance --}}
                    @if($booking->balance_due > 0 && $booking->status !== 'cancelled')
                        <div class="p-4 bg-emerald-50 rounded-2xl border border-emerald-200 space-y-3">
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-bold text-emerald-900 uppercase tracking-wide">💳 Settle Balance</p>
                                <span class="text-xs font-extrabold text-rose-600">Due: ₱{{ number_format($booking->balance_due, 2) }}</span>
                            </div>
                            <form wire:submit="settlePaymentAction" class="space-y-2">
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-emerald-950 mb-0.5">Amount (₱)</label>
                                        <input type="number" step="0.01" wire:model="paymentAmount" max="{{ $booking->balance_due }}" min="0.01" required
                                               class="w-full px-2.5 py-1.5 bg-white border border-emerald-300 rounded-lg text-xs font-bold text-stone-800 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                        @error('paymentAmount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-emerald-950 mb-0.5">Method</label>
                                        <select wire:model="paymentMethod" class="w-full px-2.5 py-1.5 bg-white border border-emerald-300 rounded-lg text-xs font-medium focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                            <option value="cash">Cash</option>
                                            <option value="card">Card</option>
                                            <option value="gcash">GCash</option>
                                            <option value="bank_transfer">Bank Transfer</option>
                                        </select>
                                        @error('paymentMethod') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                                <button type="submit" wire:loading.attr="disabled" class="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs transition-colors shadow-sm disabled:opacity-50">
                                    <span wire:loading.remove wire:target="settlePaymentAction">✓ Record Payment</span>
                                    <span wire:loading wire:target="settlePaymentAction">Checking payment...</span>
                                </button>
                            </form>
                        </div>
                    @elseif($booking->status !== 'cancelled')
                        <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-200 text-xs font-semibold text-emerald-800 flex items-center gap-2">
                            <span>✅</span> All charges fully paid and settled.
                        </div>
                    @endif

                    @if($booking->status === 'pending' || $booking->status === 'confirmed')
                        <button wire:click="checkInAction" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-sm transition-colors">
                            ✅ Mark as Checked In
                        </button>
                    @endif

                    @if($booking->status === 'checked_in')
                        <div class="p-4 bg-amber-50 rounded-2xl border border-amber-200">
                            <h4 class="font-bold text-amber-950 text-sm mb-1 flex items-center gap-1.5">
                                <span>🏁</span> Guest Check-Out & Housekeeping
                            </h4>
                            <p class="text-xs text-amber-800 mb-3">
                                Checking out will immediately flag Room {{ $booking->room?->room_number }} as <strong>Housekeeping</strong>. A housekeeper must inspect and clear the room before it is available to occupy again.
                            </p>

                            <form wire:submit="checkoutAction" class="space-y-3">
                                @if($booking->balance_due > 0)
                                    <div class="p-3 bg-white/90 rounded-xl border border-amber-300 space-y-2">
                                        <label class="flex items-center gap-2 text-xs font-semibold text-amber-900 cursor-pointer">
                                            <input type="checkbox" wire:model="settlePaymentAtCheckout" class="rounded border-amber-300 text-amber-600 focus:ring-amber-500">
                                            <span>Collect remaining balance (₱{{ number_format($booking->balance_due, 2) }}) upon check-out</span>
                                        </label>
                                        @error('settlePaymentAtCheckout') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                        @if($settlePaymentAtCheckout)
                                            <label class="block text-[11px] font-medium text-stone-600 mb-1">Settlement Method</label>
                                            <select wire:model="checkoutPaymentMethod" class="w-full px-2.5 py-1.5 bg-white border border-stone-200 rounded-lg text-xs font-medium focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                                <option value="cash">Cash</option>
                                                <option value="card">Credit / Debit Card</option>
                                                <option value="gcash">GCash</option>
                                                <option value="bank_transfer">Bank Transfer</option>
                                            </select>
                                            @error('checkoutPaymentMethod') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                        @endif
                                    </div>
                                @endif

                                <div>
                                    <label class="block text-xs font-bold text-stone-700 uppercase tracking-wide mb-1">
                                        🧹 Assign Housekeeper
                                    </label>
                                    <select wire:model="housekeeperId" class="w-full px-3 py-2 bg-white border border-stone-200 rounded-xl text-xs font-medium focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                        <option value="">-- Unassigned (Any Available Housekeeper) --</option>
                                        @foreach($housekeepers as $housekeeper)
                                            <option value="{{ $housekeeper->id }}">{{ $housekeeper->name }} ({{ $housekeeper->email }})</option>
                                        @endforeach
                                    </select>
                                    @error('housekeeperId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-stone-600 mb-1">
                                        Inspection / Cleaning Instructions (Optional)
                                    </label>
                                    <textarea wire:model="housekeepingNotes" rows="2"
                                              placeholder="e.g., Check mini-bar, inspect for damages, replace linens and towels."
                                              class="w-full px-3 py-1.5 bg-white border border-stone-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500"></textarea>
                                    @error('housekeepingNotes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>

                                <button type="submit" wire:loading.attr="disabled" class="w-full py-2.5 bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-xl text-sm transition-colors shadow-sm disabled:opacity-50">
                                    <span wire:loading.remove wire:target="checkoutAction">Complete Check-Out</span>
                                    <span wire:loading wire:target="checkoutAction">Validating checkout...</span>
                                </button>
                            </form>
                        </div>
                    @endif

                    @if($booking->status === 'checked_out' && $booking->room?->status === 'housekeeping')
                        @php($housekeepingTask = $booking->room->housekeepingLogs->first())
                        @if($housekeepingTask)
                            <div class="p-4 bg-sky-50 rounded-2xl border border-sky-200 space-y-3">
                                <div>
                                    <h4 class="font-bold text-sky-950 text-sm">Room Housekeeping</h4>
                                    <p class="mt-1 text-xs text-sky-800">
                                        Status: <strong class="uppercase">{{ str_replace('_', ' ', $housekeepingTask->status) }}</strong>
                                        @if($housekeepingTask->staff_name)
                                            · Assigned to {{ $housekeepingTask->staff_name }}
                                        @endif
                                    </p>
                                    @if($housekeepingTask->notes)
                                        <p class="mt-2 text-xs text-sky-800">{{ $housekeepingTask->notes }}</p>
                                    @endif
                                </div>
                                @if($housekeepingTask->status === 'dirty')
                                    <button wire:click="completeHousekeepingAction" wire:loading.attr="disabled" class="w-full py-2.5 bg-sky-700 hover:bg-sky-800 text-white font-semibold rounded-xl text-sm transition-colors disabled:opacity-50">
                                        <span wire:loading.remove wire:target="completeHousekeepingAction">✓ Confirm Cleaning Complete</span>
                                        <span wire:loading wire:target="completeHousekeepingAction">Saving...</span>
                                    </button>
                                @else
                                    <p class="text-xs font-semibold text-emerald-700">
                                        Room marked clean {{ $housekeepingTask->completed_at?->diffForHumans() }}.
                                    </p>
                                @endif
                            </div>
                        @endif
                    @endif

                    @if(in_array($booking->status, ['pending', 'confirmed'], true))
                        <button wire:click="cancelBookingAction" class="w-full py-2.5 bg-red-50 hover:bg-red-100 text-red-700 font-semibold rounded-xl text-sm transition-colors border border-red-200">
                            ❌ Cancel Booking
                        </button>
                    @endif

                    <a href="{{ route('room-booking.list') }}" class="block w-full py-2.5 text-center text-stone-600 hover:text-stone-900 text-sm font-medium">
                        ← Back to Bookings
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>