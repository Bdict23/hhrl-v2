<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use App\Models\Business\Company;

class AccountTitle extends Model
{
    protected $table = 'actng_chart_of_accounts';
    protected $fillable = [
        'company_id',
        'parent_id',
        'account_code',
        'transaction_type',
        'account_title',
        'is_active',
        'normal_balance',
        'description',
        'created_by',
        'created_at',
        'updated_at',
    ];

    public function businessUnit()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function parentTitle()
    {
        return $this->belongsTo(AccountTitle::class, 'parent_id');
    }

    public function accountType()
    {
        return $this->belongsTo(\App\Models\Accounting\AccountType::class, 'transaction_type');
    }

    public function children()
    {
        return $this->hasMany(AccountTitle::class, 'parent_id');
    }

    public function pcvDetails()
    {
        return $this->hasMany(\App\Models\Transaction\PettyCashVoucherDetail::class, 'transaction_title_id');
    }
}
