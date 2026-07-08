<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use App\Models\Business\Employee;

class Reimbursement extends Model
{
    protected $table = 'reimbursements';
    protected $fillable = [
        'reference',
        'status',
        'branch_id',
        'pcv_id',
        'event_id',
        'amount',
        'prepared_by',
        'approved_by',
        'approved_date',
        'rejected_date',
        'note',
    ];

    public function preparedBy()
    {
        return $this->belongsTo(Employee::class, 'prepared_by');
    }
    public function pettyCashVoucher()
    {
        return $this->belongsTo(PettyCashVoucher::class, 'pcv_id');
    }
    public function approvedBy()
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }
}
