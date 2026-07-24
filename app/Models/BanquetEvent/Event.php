<?php

namespace App\Models\BanquetEvent;

use App\Models\Transaction\Acknowledgement;
use App\Models\Business\Customer;
use App\Models\Business\Employee;

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


    public function budgetAllocation()
    {
        return $this->hasOne(BanquetProcurement::class, 'event_id');
    }
    // public function banquetEventLiquidation()
    // {
    //     return $this->hasOne(EventLiquidation::class, 'event_id');
    // }
    public function acknowledgment()
    {
        return $this->hasOne(Acknowledgement::class, 'event_id');
    }
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
    public function venues()
    {
        return $this->hasMany(EventVenue::class, 'event_id');
    }
    public function services()
    {
        return $this->hasMany(EventService::class, 'event_id');
    }
    public function menus()
    {
        return $this->hasMany(EventMenu::class, 'event_id');
    }
    public function reviewedBy()
    {
        return $this->belongsTo(Employee::class, 'reviewer_id');
    }
    public function approvedBy()
    {
        return $this->belongsTo(Employee::class, 'approver_id');
    }
    public function preparedBy()
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }
}
