<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use App\Models\DataManagement\Item;


class PurchaseOrderItems extends Model
{
    protected $table = "requisition_details";
    protected $fillable = [
        'item_id',
        'qty',
        'price_level_id',
        'requisition_info_id',
        'created_at',
    ];

    public function item(){
        return $this->belongsTo(Item::class, 'item_id');
    }
}
