<?php

namespace App\Models\Transaction;

use App\Models\Business\Employee;
use App\Models\Business\Customer;
use App\Models\Inventory\PurchaseOrder;
use App\Models\BanquetEvent\Event;

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
        'advance_liquidation_id',
        'employee_advance_id',
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
        return $this->hasMany(PettyCashVoucherDetail::class, 'petty_cash_voucher_id');
    }
    public function liquidationData()
    {
        return $this->hasMany(PcvLiquidationSnapshot::class, 'pcv_id');
    }
    public function advanceLiquidation()
    {
        return $this->belongsTo(AdvancesForLiquidation::class, 'advance_liquidation_id');
    }
    public function reimbursements()
    {
        return $this->hasOne(Reimbursement::class, 'pcv_id');
    }
    public function cashReturns()
    {
        return $this->hasMany(CashReturn::class, 'pcv_id');
    }
    public function revolvingFundSnapshot()
    {
        return $this->hasOne(RevolvingFundSnapshot::class, 'pcv_id');
    }
    public function advancesForLiquidationSnapshot()
    {
        return $this->hasOne(AdvancesForLiquidationSnapshot::class, 'pcv_id');
    }
    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }
    public function employeeAdvance()
    {
        return $this->belongsTo(EmployeeAdvance::class, 'employee_advance_id');
    }

    // use in event liquidation
    public function cashReturn()
    {
        return $this->hasOne(CashReturn::class, 'pcv_id');
    }
}
