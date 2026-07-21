<?php

namespace App\Models\BanquetEvent;

use App\Models\Business\Employee;
use Illuminate\Database\Eloquent\Model;
use App\Models\Transaction\CashReturn;

class EventLiquidation extends Model
{
    protected $table = 'event_liquidations';
    protected $fillable = [
        'reference',
        'branch_id',
        'created_by',
        'event_id',
        'status',
        'updated_by',
        'note',
        'total_incurred',
        'created_at',
        'updated_at',
        'reviewed_by',
        'approved_by',
        'reviewed_date',
        'approved_date'
    ];

    public function preparedBy()
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }
    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }
    public function reviewedBy()
    {
        return $this->belongsTo(Employee::class, 'reviewed_by');
    }
    public function approvedBy()
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }
    public function cashReturn()
    {
        return $this->hasOne(CashReturn::class, 'event_liquidation_id');
    }
}
