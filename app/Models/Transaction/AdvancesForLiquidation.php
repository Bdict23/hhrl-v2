<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use App\Models\Business\Employee;

class AdvancesForLiquidation extends Model
{
    protected $table = 'advance_liquidations';
    protected $fillable = [
        'branch_id',
        'company_id',
        'reference',
        'status',
        'prepared_by',
        'received_by',
        'date_received',
        'date_returned',
        'approved_by',
        'updated_by',
        'amount_received',
        'amount_returned',
        'created_at',
        'updated_at',
        'notes',
    ];

    public function preparedBy(){
        return $this->belongsTo(Employee::class, 'prepared_by');
    }
}
