<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use App\Models\Business\Employee;
use App\Models\Transaction\PettyCashVoucher;
use App\Models\Transaction\CashReturnDetail;
use App\Models\BanquetEvent\Event;



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
        'advances_liquidation_id',
        'employee_advance_id',
        'event_liquidation_id',

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
        return $this->hasMany(CashReturnDetail::class, 'cash_return_id');
    }
    public function advancesLiquidationSnapshot()
    {
        return $this->hasOne(AdvancesForLiquidationSnapshot::class, 'cash_return_id');
    }
    public function advancesForLiquidation()
    {
        return $this->belongsTo(AdvancesForLiquidation::class, 'advances_liquidation_id');
    }
    public function revolvingFundSnapshot()
    {
        return $this->hasOne(RevolvingFundSnapshot::class, 'cash_return_id');
    }
    public function employeeCashAdvance()
    {
        return $this->belongsTo(EmployeeAdvance::class, 'employee_advance_id');
    }
    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }
}
