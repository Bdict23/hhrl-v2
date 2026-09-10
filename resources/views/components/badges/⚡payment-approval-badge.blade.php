<?php

use Livewire\Component;
use App\Models\Booking;
use App\Events\PickleBooking;
use Livewire\Attributes\On;

new class extends Component
{
    #[On('echo-private:hhrl-channel,.update-booking')]
    #[On('echo-private:hhrl-channel,PickleBooking')]
    #[On('echo-private:hhrl-channel,.new-notification')]
    public function handleBookingUpdate($event = null): void
    {
        // Re-evaluates pendingBookingCount and re-renders sidebar badge
    }
    
    public function with(): array
    {
        $pendingBookingCount = Booking::query()
            ->where('status', 'pending')
            ->count();

        return [
            'pendingBookingCount' => $pendingBookingCount,
        ];
    }
};
?>

<div class="contents" wire:poll.15s wire:key="payment-approval-badge">
    <x-ts-side-bar.item text="Overview"
                        :route="route('admin.dashboard')"
                        :badge="$pendingBookingCount > 0 ? $pendingBookingCount : null"
                        badge-color="amber">
        <x-slot:icon>
            <x-icon-dot class="w-5 h-5" />
        </x-slot:icon>
    </x-ts-side-bar.item>
</div>