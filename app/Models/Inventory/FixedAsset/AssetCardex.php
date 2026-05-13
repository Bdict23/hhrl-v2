<?php

namespace App\Models\Inventory\FixedAsset;

use Illuminate\Database\Eloquent\Model;

class AssetCardex extends Model
{
   protected $table = 'asset_cardex';
    protected $fillable = [
         'reference',
         'batch_id',
         'batch_dtl_id',
         'item_id',
         'branch_id',
         'qr_code',
         'qty',
         'is_serialized',
         'status',
         'type',
         'transaction',
    ];
}
