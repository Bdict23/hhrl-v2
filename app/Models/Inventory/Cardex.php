<?php

namespace App\Models\Inventory;

use App\Models\DataManagement\Item;
use App\Models\DataManagement\Price;

use Illuminate\Database\Eloquent\Model;

class Cardex extends Model
{
    protected $table = 'cardex';

    protected $fillable = [
        'source_branch_id',
        'qty_in',
        'qty_out',
        'expiration_date',
        'manufactured_date',
        'item_id',
        'status',
        'transaction_type',
        'price_level_id',
        'invoice_id',
        'stf_id',
        'withdrawal_id',
        'receiving_id',
        'requisition_id',
        'final_date',
        'batch_id',
        'reference',
        'type',
        'qty',
    ];


    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
    public function cost()
    {
        return $this->belongsTo(Price::class, 'price_level_id');
    }
}
