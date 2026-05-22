<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use App\Models\Business\Employee;
use App\Models\Transaction\PettyCashVoucher;


class CashReturn extends Model
{
    protected $table = 'cash_returns';
    protected $fillable = [
        'branch_id',
        'reference',
        'status',
        'pcv_id',
        'event_id',
        'prepared_by',
        'updated_by',
        'amount_returned',
        'notes',
        'created_at',
        'approved_by',
        'approved_date',
    ];

    public function preparedBy()
    {
        return $this->belongsTo(Employee::class, 'prepared_by');
    }
    public function pettyCashVoucher()
    {
        return $this->belongsTo(PettyCashVoucher::class, 'pcv_id');
    }
    public function cashReturnDetail()
    {
        return $this->hasMany(CashReturnDetail::class,'cash_return_id');
    }


}
