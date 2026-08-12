<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use App\Models\Business\Employee;


class AdditionalFund extends Model
{
    protected $table = 'afl_adtl_funds';
    protected $fillable = [
        'reference',
        'advances_liquidation_id',
        'amount',
        'prepared_by',
        'status',
        'remarks',
    ];


    public function advanceLiquidation()
    {
        return $this->belongsTo(AdvancesForLiquidation::class, 'advances_liquidation_id');
    }
    public function preparedBy()
    {
        return $this->belongsTo(Employee::class, 'prepared_by');
    }
}
