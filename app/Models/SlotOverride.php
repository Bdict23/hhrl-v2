<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlotOverride extends Model
{
    use HasFactory;

    protected $table = "pickle_slot_overrides";
    protected $fillable = [
        'court_id',
        'date',
        'start_time',
        'end_time',
        'type', // 'open_play', 'maintenance', 'blocked', 'private_event', 'unavailable'
        'title',
        'reason',
        'fee_per_person',
        'max_participants',
    ];

    protected $casts = [
        'date'             => 'date:Y-m-d',
        'fee_per_person'   => 'decimal:2',
        'max_participants' => 'integer',
    ];

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function isOpenPlay(): bool
    {
        return $this->type === 'open_play';
    }

    public function isMaintenance(): bool
    {
        return in_array($this->type, ['maintenance', 'blocked', 'unavailable'], true);
    }

    /**
     * Check if this override intersects with a given slot on a specific court and date.
     */
    public function intersectsSlot(?int $courtId, string $date, string $slotStartTime, string $slotEndTime): bool
    {
        $overrideDate = $this->date instanceof Carbon ? $this->date->format('Y-m-d') : (string) $this->date;
        if ($overrideDate !== $date) {
            return false;
        }

        if ($this->court_id !== null && $courtId !== null && (int) $this->court_id !== (int) $courtId) {
            return false;
        }

        $oStart = substr($this->start_time, 0, 5);
        $oEnd = substr($this->end_time, 0, 5);
        $sStart = substr($slotStartTime, 0, 5);
        $sEnd = substr($slotEndTime, 0, 5);


        if ($sEnd === '00:00' || $sEnd === '24:00') {
            $sEnd = '24:00';
        }

        if ($oEnd === '00:00' || $oEnd === '24:00') {
            $oEnd = '24:00';
        }

        if ($oStart > $oEnd) {
            // Override crosses midnight (e.g. 22:00 to 02:00)
            $firstSegment = ($sStart < '24:00') && ($sEnd > $oStart);
            $secondSegment = ($sStart < $oEnd) && ($sEnd > '00:00');
            return $firstSegment || $secondSegment;
        }

        // slot starts before override ends, and slot ends after override starts
        return ($sStart < $oEnd) && ($sEnd > $oStart);
    }
}
