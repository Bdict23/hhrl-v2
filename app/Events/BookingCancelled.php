<?php

namespace App\Events;

use App\Models\Suite\RoomBooking as Booking;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingCancelled implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Booking $booking)
    {
    }

    /**
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
        return 'BookingCancelled';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->booking->id,
            'booking_code' => $this->booking->booking_code,
            'room_id' => $this->booking->room_id,
            'status' => $this->booking->status,
        ];
    }
}
