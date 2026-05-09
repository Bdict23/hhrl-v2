<?php

namespace App\Models\Inventory;
use App\Models\Business\Employee;
use App\Models\Inventory\PurchaseOrder;

use Illuminate\Database\Eloquent\Model;

class Receiving extends Model
{
    protected $table = "receivings";
    protected $fillable = [
        'REQUISITION_ID',
        'PACKING_NUMBER',
        'RECEIVING_TYPE',
        'RECEIVING_NUMBER',
        'WAYBILL_NUMBER',
        'DELIVERY_NUMBER',
        'INVOICE_NUMBER',
        'RECEIVED_DATE',
        'remarks',
        'CHECKED_BY',
        'ALLOCATED_BY',
        'DELIVERED_BY',
        'ATTACHMENT',
        'created_by',
        'branch_id',
        'company_id',
        'receiving_status',
        'stf_id',
        'receive_amount'
    ];




    public function preparedBy()
        {
            return $this->belongsTo(Employee::class, 'PREPARED_BY');
        }
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'REQUISITION_ID');
    }

}
