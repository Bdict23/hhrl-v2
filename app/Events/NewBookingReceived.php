<?php

namespace App\Events;

use App\Models\Suite\RoomBooking as Booking;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewBookingReceived implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Booking $booking)
    {
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('bookings'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'NewBookingReceived';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->booking->id,
            'booking_code' => $this->booking->booking_code,
            'guest_name' => $this->booking->guest_name,
            'room_id' => $this->booking->room_id,
            'room_number' => $this->booking->room?->room_number,
            'check_in' => $this->booking->check_in->toIso8601String(),
            'check_out' => $this->booking->check_out->toIso8601String(),
            'status' => $this->booking->status,
            'total_price' => $this->booking->total_price,
            'is_walk_in' => $this->booking->is_walk_in,
        ];
    }
}
