<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use App\Models\Business\Employee;
use App\Models\BanquetEvent\Event;

class AdvancesForLiquidation extends Model
{
    protected $table = 'advance_liquidations';
    protected $fillable = [
        'branch_id',
        'company_id',
        'reference',
        'status',
        'prepared_by',
        'received_by',
        'date_received',
        'date_returned',
        'approved_by',
        'updated_by',
        'amount_received',
        'amount_returned',
        'created_at',
        'updated_at',
        'notes',
        'event_id',
    ];

    protected $appends = ['description_one'];
    public function getDescriptionOneAttribute(): string
    {
        if ($this->event) {
            return "Event : {$this->event?->event_name}";
        } else {
            return  "Accountable : {$this->receivedBy->full_name}";
        }
    }
    public function preparedBy()
    {
        return $this->belongsTo(Employee::class, 'prepared_by');
    }
    public function advanceLiquidationSnapshot()
    {
        return $this->hasMany(AdvancesForLiquidationSnapshot::class, 'advance_liquidation_id');
    }
    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }
    public function receivedBy()
    {
        return $this->belongsTo(Employee::class, 'received_by');
    }
    public function approvedBy()
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }
}
