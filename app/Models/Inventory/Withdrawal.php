<?php

namespace App\Models\Inventory;

use App\Models\Business\Employee;
use App\Models\Business\Department;
use App\Models\Settings\SystemParameter;


use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    protected $table = 'withdrawals';

    protected $fillable = [
        'event_id',
        'source_branch_id',
        'reference_number',
        'usage_date',
        'useful_date',
        'prepared_by',
        'reviewed_by',
        'approved_by',
        'department_id',
        'remarks',
        'withdrawal_status',
        'approved_date',
        'reviewed_date',
        'rejected_date',
        'withdrawal_type',
        'production_order_id',
        'type_id',
    ];


    protected $appends = ['cost_amount'];

    public function getCostAmountAttribute(): string
    {
        $total = $this->cardex()->get()->map(function ($item) {
            return ['cost' => $item->cost->amount * ($item->qty == 0 ? $item->qty_out : $item->qty)];
        })->sum('cost');
        return $total;
    }

    public function preparedBy()
    {
        return $this->belongsTo(Employee::class, 'prepared_by');
    }
    public function approvedBy()
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }
    public function reviewedBy()
    {
        return $this->belongsTo(Employee::class, 'reviewed_by');
    }
    public  function cardex()
    {
        return $this->hasMany(Cardex::class, 'withdrawal_id');
    }
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
    public function type()
    {
        return $this->belongsTo(SystemParameter::class, 'type_id');
    }

    // purpose : display ang cost dretso when called
    public function totalCost()
    {
        $total = $this->cardex()->get()->map(function ($item) {
            return ['cost' => $item->cost->amount];
        })->sum('cost');
        return $total;
    }
}
