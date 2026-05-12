<?php

namespace App\Models\Inventory\FixedAsset;
use App\Models\DataManagement\Item;
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

    public function item(){
        return $this->belongsTo(Item::class, 'item_id');
    }
    public function isSerialized(){
        return $this->serial ? true : false;
    }
}
