<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Court extends Model
{
    use HasFactory;
    protected $table = "pickle_courts";
    protected $fillable = [
        'name',
        'hourly_rate',
        'is_active',
        'display_order',
        'surface_type',
        'is_indoor',
        'description',
    ];

    protected $casts = [
        'hourly_rate'   => 'decimal:2',
        'is_active'     => 'boolean',
        'is_indoor'     => 'boolean',
        'display_order' => 'integer',
    ];

    public function bookingSlots(): HasMany
    {
        return $this->hasMany(BookingSlot::class);
    }

    public function slotOverrides(): HasMany
    {
        return $this->hasMany(SlotOverride::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('name');
    }
}
