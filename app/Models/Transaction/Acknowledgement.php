<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use App\Models\Business\Customer;
use App\Models\DataManagement\Bank;


class Acknowledgement extends Model
{
    protected $table = 'acknowledgement_receipts';
    protected $fillable = [
        'branch_id',
        'company_id',
        'event_id',
        'reference',
        'customer_id',
        'status',
        'check_number',
        'check_amount',
        'check_date',
        'bank_id',
        'account_name',
        'amount_in_words',
        'check_status',
        'created_by',
        'updated_by',
        'notes',
    ];

    protected $appends = ['additional_details'];
    public function getAdditionalDetailsAttribute(): string
    {
        return "({$this->check_number}) - ₱ {$this->check_amount}";
    }

    public function customer()
    {
               return $this->belongsTo(Customer::class, 'customer_id');
    }
    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }


}
