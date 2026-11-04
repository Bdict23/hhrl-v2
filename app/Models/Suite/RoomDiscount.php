<?php

namespace App\Models\Suite;

use Database\Factories\DiscountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'code',
    'description',
    'type',
    'value',
    'minimum_amount',
    'max_uses',
    'used_count',
    'valid_from',
    'valid_until',
    'applicable_days',
    'is_active',
])]
class RoomDiscount extends Model
{
    /** @use HasFactory<DiscountFactory> */
    use HasFactory;

    protected $table = 'room_discounts';

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'minimum_amount' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'applicable_days' => 'array',
            'is_active' => 'boolean',
            'max_uses' => 'integer',
            'used_count' => 'integer',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(RoomBooking::class);
    }

    public function isValidNow(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $today = Carbon::today();

        if ($this->valid_from && $today->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_until && $today->gt($this->valid_until)) {
            return false;
        }

        if ($this->max_uses && $this->used_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    public function calculateDiscount(float|int|string $amount): float
    {
        $amount = (float) $amount;
        $minAmount = (float) ($this->minimum_amount ?? 0);

        if ($minAmount > 0 && $amount < $minAmount) {
            return 0.0;
        }

        $val = (float) $this->value;
        $discount = $this->type === 'percentage'
            ? round($amount * ($val / 100), 2)
            : min($val, $amount);

        return max(0.0, min($discount, $amount));
    }
}
