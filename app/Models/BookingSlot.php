<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingSlot extends Model
{
    use HasFactory;

    protected $table = "pickle_booking_slots";
    protected $fillable = [
        'booking_id',
        'court_id',
        'date',
        'start_time',
        'end_time',
        'slot_type', // 'regular', 'open_play', 'blocked'
        'price',
    ];

    protected $casts = [
        'date'  => 'date:Y-m-d',
        'price' => 'decimal:2',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function getFormattedTimeAttribute(): string
    {
        $start = substr($this->start_time, 0, 5);
        $end = substr($this->end_time, 0, 5);
        return "{$start} - {$end}";
    }
}
