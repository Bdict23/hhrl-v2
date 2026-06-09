<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use App\Models\Transaction\Acknowledgement;

class RevolvingFundSnapshot extends Model
{
    protected $table = 'revolving_fund_snapshots';
    protected $fillable = [
        'revolving_fund_id',
        'type',
        'description',
        'amount',
        'balance',
        'pcv_id',
        'cash_return_id',
        'forwarded_revolving_fund_id',
        'acknowledgement_id',
        'status',
    ];

    public function acknowledgement()
    {
         return $this->belongsTo(Acknowledgement::class, 'acknowledgement_id');
    }
    public function pettyCashVoucher()
    {
         return $this->belongsTo(PettyCashVoucher::class, 'pcv_id');
    }
    public function cashReturn()
    {
         return $this->belongsTo(CashReturn::class, 'cash_return_id');
    }
    public function forwardedRevolvingFund()
    {
         return $this->belongsTo(RevolvingFund::class, 'forwarded_revolving_fund_id');
    }
    public function revolvingFund()
    {
        return $this->belongsTo(RevolvingFund::class, 'revolving_fund_id');
    }

}
