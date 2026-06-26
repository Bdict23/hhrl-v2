<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use App\Models\DataManagement\Item;
use App\Models\Business\Branch;
use App\Models\Business\Company;

class Backorder extends Model
{
    protected $table = 'backorders';
    protected $fillable = [
        'requisition_id',
        'item_id',
        'status',
        'cancelled_date',
        'bo_type',
        'remarks',
        'branch_id',
        'company_id',
        'receiving_attempt',

    ];

    public function requisition()
    {
        return $this->belongsTo(PurchaseOrder::class, 'requisition_id');
    }
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    public function cardex()
    {
        return $this->hasMany(Cardex::class, 'requisition_id');
    }
}
