<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;

class EmployeeAdvanceSnapshot extends Model
{
    protected $table = 'employee_advances_snapshots';
    protected $fillable = [
        'advance_id',
        'type',
        'status',
        'description',
        'amount',
        'balance',
        'pcv_id',
        'cash_return_id',
        'revolving_fund_id',
    ];


    public function pettyCashVoucher()
    {
        return $this->belongsTo(PettyCashVoucher::class, 'pcv_id');
    }
    public function cashReturn()
    {
        return $this->belongsTo(CashReturn::class, 'cash_return_id');
    }
}
