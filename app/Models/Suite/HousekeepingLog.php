<?php

namespace App\Models\Suite;

use Database\Factories\HousekeepingLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

#[Fillable(['room_id', 'user_id', 'staff_name', 'status', 'notes', 'completed_at', 'cleaning_timestamp'])]
class HousekeepingLog extends Model
{
    /** @use HasFactory<HousekeepingLogFactory> */
    use HasFactory;

    protected $table = 'room_housekeeping_logs';

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'cleaning_timestamp' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
