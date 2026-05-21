<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class AccountType extends Model
{
     protected $table = 'actng_account_types';
    protected $fillable = [
        'company_id',
        'type_name',
        'acct_code',
        'description',
        'created_by',
        'is_active',
        'created_at',
        'updated_at',
    ];
}
