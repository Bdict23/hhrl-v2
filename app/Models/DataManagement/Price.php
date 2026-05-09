<?php

namespace App\Models\DataManagement;

use Illuminate\Database\Eloquent\Model;
use App\Models\Business\Supplier;

class Price extends Model
{
    protected $table = "price_levels";
    protected $fillable = [
    'item_id',
    'menu_id',
    'venue_id',
    'price_type',
    'markup',
    'amount',
    'start_date',
    'end_date',
    'created_by',
    'company_id',
    'supplier_id',
    'branch_id',
    'created_at',
    'updated_at',
    'service_id',
    ];

    public function supplier(){
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
