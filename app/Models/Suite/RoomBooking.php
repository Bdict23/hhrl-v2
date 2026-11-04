<?php

namespace App\Models\Suite;

use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'booking_code',
    'room_id',
    'user_id',
    'discount_id',
    'guest_name',
    'guest_email',
    'guest_phone',
    'check_in',
    'check_out',
    'booking_type',
    'adults',
    'children',
    'pets',
    'base_price',
    'addons_total',
    'pet_fee_total',
    'discount_amount',
    'total_price',
    'amount_paid',
    'status',
    'payment_status',
    'payment_method',
    'payment_proof',
    'is_walk_in',
    'special_requests',
    'qr_code_path',
    'checked_in_at',
    'checked_out_at',
])]
class RoomBooking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'room_bookings';

    protected function casts(): array
    {
        return [
            'check_in' => 'datetime',
            'check_out' => 'datetime',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'is_walk_in' => 'boolean',
            'base_price' => 'decimal:2',
            'addons_total' => 'decimal:2',
            'pet_fee_total' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_price' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'adults' => 'integer',
            'children' => 'integer',
            'pets' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (RoomBooking $booking) {
            if (empty($booking->booking_code)) {
                $booking->booking_code = strtoupper('AGRO-' . Str::random(8));
            }
        });
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(RoomDiscount::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(RoomBookingAddon::class, 'booking_id');
    }

    public function getNightsAttribute(): int
    {
        return (int) $this->check_in->diffInDays($this->check_out);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isCheckedIn(): bool
    {
        return $this->status === 'checked_in';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function getBalanceDueAttribute(): float
    {
        return max(0.0, round((float) $this->total_price - (float) ($this->amount_paid ?? 0), 2));
    }

    public function isFullyPaid(): bool
    {
        return $this->payment_status === 'paid' && $this->balance_due <= 0;
    }

    public function isPartial(): bool
    {
        return $this->payment_status === 'partial' || ($this->amount_paid > 0 && $this->balance_due > 0);
    }
}
