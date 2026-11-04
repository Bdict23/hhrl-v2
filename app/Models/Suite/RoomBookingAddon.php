<?php

namespace App\Models\Suite;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Database\Factories\BookingAddonFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['booking_id', 'addon_service_id', 'item_name', 'quantity', 'unit_price', 'subtotal'])]
class RoomBookingAddon extends Model
{
    /** @use HasFactory<BookingAddonFactory> */
    use HasFactory;

    protected $table = 'room_booking_addons';

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(RoomBooking::class, 'booking_id');
    }

    public function addonService(): BelongsTo
    {
        return $this->belongsTo(RoomAddonService::class, 'addon_service_id');
    }
}
