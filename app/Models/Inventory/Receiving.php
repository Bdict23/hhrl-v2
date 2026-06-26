<?php

namespace App\Models\Inventory;

use App\Models\Business\Employee;
use App\Models\Inventory\PurchaseOrder;

use Illuminate\Database\Eloquent\Model;

class Receiving extends Model
{
    protected $table = "receivings";
    protected $fillable = [
        'requisition_id',
        'PACKING_NUMBER',
        'receiving_type',
        'receiving_number',
        'waybill_number',
        'delivery_number',
        'invoice_number',
        'remarks',
        'delivered_by',
        'branch_id',
        'company_id',
        'receiving_status',
        'stf_id',
        'receive_amount',
        'reference',
        'prepared_by',
    ];




    public function preparedBy()
    {
        return $this->belongsTo(Employee::class, 'PREPARED_BY');
    }
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'REQUISITION_ID');
    }
    public function attachments()
    {
        return $this->hasMany(ReceivingAttachment::class, 'receiving_id');
    }
    public function cardex()
    {
        return $this->hasMany(Cardex::class, 'receiving_id');
    }
}
