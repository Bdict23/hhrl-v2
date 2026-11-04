<?php

namespace App\Services\Suite;

use App\Models\Suite\HousekeepingLog;

class HousekeepingService
{
    protected $hk;

    public function __construct(HousekeepingLog $hk)
    {
        $this->hk = $hk;
    }


    public function startCleaning(array $data): String
    {
        $housekeepingLog = $this->hk->with('room')->findOrFail($data['id']);
        $housekeepingLog->update([
            'status' => 'in_progress',
            'user_id' => $housekeepingLog->user_id ?: $data['user_id'],
            'staff_name' => $housekeepingLog->user_id ? $housekeepingLog->staff_name : $data['user_name'],
        ]);

        return "Started cleaning Room {$housekeepingLog->room?->room_number}. Status updated to In Progress.";
    }
}
