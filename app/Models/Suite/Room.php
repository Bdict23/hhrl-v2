<?php

namespace App\Models\Suite;

use Illuminate\Database\Eloquent\Model;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

#[Fillable([
    'category_id',
    'room_number',
    'name',
    'description',
    'gallery',
    'capacity_adults',
    'capacity_children',
    'allows_pets',
    'pet_fee',
    'base_price',
    'floor_number',
    'status',
    'booking_type',
    'is_active',
])]
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'rooms';

    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'allows_pets' => 'boolean',
            'is_active' => 'boolean',
            'base_price' => 'decimal:2',
            'pet_fee' => 'decimal:2',
            'capacity_adults' => 'integer',
            'capacity_children' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RoomCategory::class, 'category_id');
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'room_room_amenities', 'room_id', 'amenity_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(RoomPhoto::class)->orderBy('sort_order');
    }

    public function coverPhoto(): ?RoomPhoto
    {
        return $this->photos()->where('is_cover', true)->first()
            ?? $this->photos()->first();
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(RoomBooking::class);
    }

    public function housekeepingLogs(): HasMany
    {
        return $this->hasMany(HousekeepingLog::class)->latest();
    }

    public function isAvailableFor(\DateTimeInterface $checkIn, \DateTimeInterface $checkOut): bool
    {
        if (! $this->is_active || in_array($this->status, ['maintenance', 'out_of_service', 'blocked'])) {
            return false;
        }

        $cIn = Carbon::parse($checkIn);
        $cOut = Carbon::parse($checkOut);

        $isSameDay = $cOut->lte($cIn) || $cIn->toDateString() === $cOut->toDateString();

        if ($isSameDay) {
            $effectiveIn = $cIn->copy()->startOfDay();
            $effectiveOut = $cIn->copy()->endOfDay();
        } else {
            $effectiveIn = $cIn;
            $effectiveOut = $cOut;
        }

        if ($cIn->isToday() && in_array($this->status, ['occupied', 'housekeeping'])) {
            return false;
        }

        // Check resort blackout dates
        if ($this->hasBlackoutOverlap($effectiveIn, $effectiveOut)) {
            return false;
        }

        return ! $this->bookings()
            ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
            ->where(function ($query) use ($effectiveIn, $effectiveOut, $isSameDay, $cIn) {
                if ($isSameDay) {
                    $dayStr = $cIn->toDateString();
                    // When checking same-day / Day Pass for $dayStr:
                    // Any active booking occupying this room on this date makes it unavailable:
                    // 1) An existing Day Pass on this calendar day (check_in date <= $dayStr and check_out date >= $dayStr)
                    // 2) An existing overnight booking covering this calendar day
                    $query->where(function ($sub) use ($dayStr) {
                        $sub->whereDate('check_in', '<=', $dayStr)
                            ->whereDate('check_out', '>=', $dayStr);
                    });
                } else {
                    $startDay = $effectiveIn->toDateString();
                    $endDay = $effectiveOut->toDateString();

                    // When checking overnight stay from $startDay to $endDay:
                    // 1) Another overnight booking overlaps nights:
                    //    check_in date < endDay AND check_out date > startDay
                    // 2) Any Day Pass booking touches any day of the stay:
                    //    check_in date >= startDay AND check_in date <= endDay
                    $query->where(function ($sub) use ($startDay, $endDay) {
                        $sub->where(function ($overnightSub) {
                            $overnightSub->where('booking_type', '!=', 'day_pass')
                                ->whereColumn('check_in', '!=', 'check_out');
                        })
                            ->whereDate('check_in', '<', $endDay)
                            ->whereDate('check_out', '>', $startDay);
                    })->orWhere(function ($sub) use ($startDay, $endDay) {
                        $sub->where(function ($dayPassSub) {
                            $dayPassSub->where('booking_type', 'day_pass')
                                ->orWhereColumn('check_in', 'check_out');
                        })
                            ->whereDate('check_in', '>=', $startDay)
                            ->whereDate('check_in', '<=', $endDay);
                    });
                }
            })
            ->exists();
    }

    public function hasBlackoutOverlap(Carbon $checkIn, Carbon $checkOut): bool
    {
        $raw = ResortSetting::get('blackout_dates');
        if (empty($raw)) {
            return false;
        }

        $blackoutDates = is_array($raw) ? $raw : array_filter(array_map('trim', preg_split('/[,\r\n]+/', (string) $raw)));
        $isSameDay = $checkOut->lte($checkIn) || $checkIn->toDateString() === $checkOut->toDateString();

        foreach ($blackoutDates as $blackout) {
            if (empty($blackout)) {
                continue;
            }

            if (str_contains($blackout, ' to ') || str_contains($blackout, ':')) {
                $parts = str_contains($blackout, ' to ') ? explode(' to ', $blackout) : explode(':', $blackout);
                if (count($parts) === 2) {
                    try {
                        $bStart = Carbon::parse(trim($parts[0]))->startOfDay();
                        $bEnd = Carbon::parse(trim($parts[1]))->endOfDay();
                        $stayStart = $checkIn->copy()->startOfDay();
                        $stayEnd = $isSameDay ? $checkIn->copy()->endOfDay() : $checkOut->copy()->endOfDay();
                        if ($stayStart->lte($bEnd) && $stayEnd->gte($bStart)) {
                            return true;
                        }
                    } catch (\Throwable) {
                    }
                }
            } else {
                try {
                    $bDate = Carbon::parse($blackout)->toDateString();
                    $startDate = $checkIn->toDateString();
                    $endDate = $checkOut->toDateString();

                    if ($isSameDay) {
                        if ($bDate === $startDate) {
                            return true;
                        }
                    } else {
                        if ($bDate >= $startDate && $bDate < $endDate) {
                            return true;
                        }
                    }
                } catch (\Throwable) {
                }
            }
        }

        return false;
    }

    /**
     * Find the next available check-in date starting from a given date.
     */
    public function findNextAvailableDate(?\DateTimeInterface $startDate = null, int $maxDaysAhead = 90): ?Carbon
    {
        $cursor = $startDate ? Carbon::parse($startDate) : Carbon::today();

        for ($i = 0; $i < $maxDaysAhead; $i++) {
            $testDate = $cursor->copy()->addDays($i);
            $testOut = $this->booking_type === 'day_pass' ? $testDate : $testDate->copy()->addDay();
            if ($this->isAvailableFor($testDate, $testOut)) {
                return $testDate;
            }
        }

        return null;
    }
}
