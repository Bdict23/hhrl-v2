<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

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
}
