<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;


class PcvLiquidationSnapshot extends Model
{
    protected $table = 'pcv_liquidation_snapshots';
    protected $fillable = [
        'pcv_id',
        'branch_id',
        'purchase_date',
        'vendor',
        'reference',
        'particular',
        'amount',
        'created_at',
        'updated_at',
    ];




}
