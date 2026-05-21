<?php

namespace App\Models\Transaction;
use App\Models\Business\Employee;
use App\Models\Business\Customer;
use App\Models\Inventory\PurchaseOrder;

use Illuminate\Database\Eloquent\Model;

class PettyCashVoucher extends Model
{
    protected $table = 'petty_cash_vouchers';
        protected $fillable = [
        'branch_id',
        'company_id',
        'event_id',
        'reference',
        'voucher_number',
        'paid_to_employee_id',
        'paid_to_customer_id',
        'total_amount',
        'purpose',
        'status',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'requisition_id',
        'account_types_id',
        'account_type',
        'template_id',
        'transaction_title',
        'type_id',
    ];

    public function paidToEmployee()
    {
        return $this->belongsTo(Employee::class, 'paid_to_employee_id');
    }

    public function paidToCustomer()
    {
        return $this->belongsTo(Customer::class, 'paid_to_customer_id');
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'requisition_id');
    }
    public function preparedBy()
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }
    public function pettyCashVoucherDetail()
    {
        return $this->hasMany(PettyCashVoucherDetail::class,'petty_cash_voucher_id');
    }

}
