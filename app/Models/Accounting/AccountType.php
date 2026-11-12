<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use App\Models\Business\Company;

class AccountType extends Model
{
    protected $table = 'actng_account_types';
    protected $fillable = [
        'company_id',
        'type_name',
        'description',
        'created_by',
        'is_active',
        'created_at',
        'updated_at',
    ];

    public function businessUnit()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function pettyCashVouchers()
    {
        return $this->hasMany(\App\Models\Transaction\PettyCashVoucher::class, 'account_types_id');
    }

    public function templates()
    {
        return $this->hasMany(TransactionTemplate::class, 'transaction_type');
    }

    public function accountTitles()
    {
        return $this->hasMany(AccountTitle::class, 'transaction_type');
    }
}
