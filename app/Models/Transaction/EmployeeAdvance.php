<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use App\Models\Business\Employee;

class EmployeeAdvance extends Model
{
    protected $table = 'employee_advances';
    protected $fillable = [
        'reference',
        'branch_id',
        'prepared_by',
        'received_by',
        'approved_by',
        'status',
        'amount',
        'opened_at',
        'closed_at',
        'approved_at',
        'rejected_at',
        'remarks',
    ];

    public function preparedBy()
    {
        return $this->belongsTo(Employee::class, 'prepared_by');
    }
    public function approvedBy()
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }
    public function receivedBy()
    {
        return $this->belongsTo(Employee::class, 'received_by');
    }
    public function employeeAdvanceSnapshot()
    {
        return $this->hasMany(EmployeeAdvanceSnapshot::class, 'advance_id');
    }
    public function cashReturn()
    {
        return $this->hasMany(CashReturn::class, 'employee_advance_id');
    }
}
