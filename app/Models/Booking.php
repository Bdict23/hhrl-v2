<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Booking extends Model
{
    use HasFactory;

    protected $table = "pickle_bookings";
    protected $fillable = [
        'reference_code',
        'customer_name',
        'customer_email',
        'customer_phone',
        'total_amount',
        'subtotal_amount',
        'discount_or_surcharge',
        'payment_method',
        'proof_of_payment_path',
        'payment_status',
        'status',
        'notes',
        'admin_notes',
    ];

    protected $casts = [
        'total_amount'          => 'decimal:2',
        'subtotal_amount'       => 'decimal:2',
        'discount_or_surcharge' => 'decimal:2',
    ];

    public function slots(): HasMany
    {
        return $this->hasMany(BookingSlot::class);
    }

    public static function generateReferenceCode(): string
    {
        do {
            $code = 'LYR-' . strtoupper(Str::random(6));
        } while (static::where('reference_code', $code)->exists());

        return $code;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'confirmed']);
    }

    public function getProofOfPaymentUrlAttribute(): ?string
    {
        if (! $this->proof_of_payment_path) {
            return null;
        }

        return asset('storage/' . $this->proof_of_payment_path);
    }

    public function hasProofOfPayment(): bool
    {
        return ! empty($this->proof_of_payment_path);
    }
}
