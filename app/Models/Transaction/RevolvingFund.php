<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use App\Models\Transaction\Acknowledgement;
use App\Models\Business\Employee;


class RevolvingFund extends Model
{
    protected $table = 'revolving_funds';
    protected $fillable = [
        'reference',
        'branch_id',
        'status',
        'prepared_by',
        'approved_by',
        'replenished_amount',
        'ceiling_amount',
        'starting_balance',
        'ending_balance',
        'opened_at',
        'closed_at',
        'created_at',
        'updated_at',
        'acknowledgement_id',
    ];

    public function revolvingFundSnapshot()
    {
        return $this->hasMany(RevolvingFundSnapshot::class, 'revolving_fund_id');
    }
    public function acknowledgement()
    {
        return $this->belongsTo(Acknowledgement::class, 'acknowledgement_id');
    }
    public function preparedBy()
    {
        return $this->belongsTo(Employee::class, 'prepared_by');
    }
    public function approvedBy()
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }
}
