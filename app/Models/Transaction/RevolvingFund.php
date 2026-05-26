<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;

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
    ];

    public function revolvingFundDetail()
    {
        return $this->hasMany(RevolvingFundDetail::class,'revolving_fund_id');
    }
}
