<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use App\Models\Business\Employee;
use App\Models\BanquetEvent\Event;

class PurchaseOrder extends Model
{
    protected $table = "requisition_infos";
    protected $fillable = [
        'requisition_number',
        'from_branch_id',
        'to_branch_id',
        'trans_date',
        'merchandise_po_number',
        'category',
        'supplier_id',
        'prepared_by',
        'reviewed_by',
        'approved_by',
        'approved_date',
        'rejected_date',
        'reviewed_date',
        'requisition_status',
        'remarks',
        'event_id',
        'total_amount',
        'production_id',
        'type_id',
        'term_type_id',
    ];

    public function preparedBy()
    {
        return $this->belongsTo(Employee::class, 'prepared_by');
    }
    public function event(){
        return $this->belongsTo(Event::class,'event_id');
    }
    public function purchaseOrderItems(){
        return $this->hasMany(PurchaseOrderItems::class,'requisition_info_id');
    }
}
