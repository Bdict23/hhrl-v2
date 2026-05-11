<?php

namespace App\Models\Inventory\FixedAsset;

use Illuminate\Database\Eloquent\Model;

class AssetBatchDetail extends Model
{
    protected $table = 'batch_property_details';
    protected $fillable = [
        'batch_id',
        'code',
        'item_id',
        'branch_id',
        'serial',
        'sidr_no',
        'cost',
        'lifespan',
        'span_ended',
        'condition',
        'created_at',
        'updated_at',
        'qty',
    ];
}
