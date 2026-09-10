<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PickleBooking implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ?int $bookingId;
    public ?string $status;
    public int $pendingCount;

    public function __construct(?Booking $booking = null)
    {
        $this->bookingId = $booking?->id;
        $this->status = $booking?->status;
        $this->pendingCount = Booking::where('status', 'pending')->count();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('hhrl-channel'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'update-booking';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'bookingId' => $this->bookingId,
            'status' => $this->status,
            'pendingCount' => $this->pendingCount,
        ];
    }
}
