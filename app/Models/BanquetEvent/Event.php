<?php

namespace App\Models\BanquetEvent;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $table = "banquet_events";
    protected $fillable = [
        'event_name',
        'reference',
        'event_address',
        'start_date',
        'end_date',
        'arrival_time',
        'departure_time',
        'guest_count',
        'status',
        'reviewer_id',
        'approver_id',
        'reviewed_at',
        'approved_at',
        'created_by',
        'notes',
        'customer_id',
        'total_amount',
    ];


    public function banquetEventBudget()
    {
        return $this->hasOne(BanquetProcurement::class, 'event_id');
    }
}
