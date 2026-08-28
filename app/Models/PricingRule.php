<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PricingRule extends Model
{
    use HasFactory;
    protected $table = "pickle_pricing_rules";
    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'type', // 'multiplier', 'flat'
        'rate_adjustment',
        'days_of_week',
        'is_active',
    ];

    protected $casts = [
        'days_of_week'    => 'array',
        'is_active'       => 'boolean',
        'rate_adjustment' => 'decimal:2',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if this pricing rule applies to a given date and time slot.
     */
    public function appliesTo(Carbon $date, string $slotStartTime, string $slotEndTime): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $dayName = strtolower($date->format('l'));
        if (! in_array($dayName, $this->days_of_week ?? [], true)) {
            return false;
        }

        // Compare slot times (e.g. 18:00 >= start_time and < end_time)
        $ruleStart = substr($this->start_time, 0, 5);
        $ruleEnd = substr($this->end_time, 0, 5);
        $slotStart = substr($slotStartTime, 0, 5);

        return $slotStart >= $ruleStart && $slotStart < $ruleEnd;
    }
}
