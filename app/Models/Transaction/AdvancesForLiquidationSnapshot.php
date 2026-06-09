<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;

class AdvancesForLiquidationSnapshot extends Model
{
    protected $table = 'advance_liquidation_snaptshots';
    protected $fillable = [
        'advance_liquidation_id',
        'type',
        'status',
        'description',
        'pcv_id',
        'cash_return_id',
        'amount',
        'branch_id',
        'balance',
        'created_at',
        'updated_at',
    ];

    public function advanceLiquidation()
    {
        return $this->belongsTo(AdvancesForLiquidation::class, 'advance_liquidation_id');
    }
    public function pettyCashVoucher()
    {
        return $this->belongsTo(PettyCashVoucher::class, 'pcv_id');
    }
    public function cashReturn()
    {
        return $this->belongsTo(CashReturn::class, 'cash_return_id');
    }
}
