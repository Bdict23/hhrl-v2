<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class GeneralNotification extends Notification implements ShouldBroadcastNow
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public string $url = '',
        public string $type = 'system_alert',
        public ?int $bookingId = null,
        public ?string $referenceCode = null
    ) {
        if (empty($this->url)) {
            $this->url = route('admin.dashboard');
        }
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title'          => $this->title,
            'message'        => $this->message,
            'url'            => $this->url,
            'type'           => $this->type,
            'booking_id'     => $this->bookingId,
            'reference_code' => $this->referenceCode,
            'created_at'     => now()->toIso8601String(),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id'             => $this->id,
            'title'          => $this->title,
            'message'        => $this->message,
            'url'            => $this->url,
            'type'           => $this->type,
            'booking_id'     => $this->bookingId,
            'reference_code' => $this->referenceCode,
            'created_at'     => now()->toIso8601String(),
        ]);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('hhrl-channel'),
        ];

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'new-notification';
    }
}
