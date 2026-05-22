<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;

class CashReturnDetail extends Model
{
    protected $table = 'cash_return_details';
    protected $fillable = [
        'cash_return_id',
        'branch_id',
        'purchase_date',
        'vendor',
        'reference',
        'particular',
        'amount',
    ];
}
